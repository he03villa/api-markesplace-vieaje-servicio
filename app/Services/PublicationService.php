<?php

namespace App\Services;

use App\Http\Requests\ExplorePublicationsRequest;
use App\Http\Requests\MyRidesRequest;
use App\Http\Requests\MyServicesRequest;
use App\Http\Resources\MyRideResource;
use App\Http\Resources\MyRidesStatsResource;
use App\Http\Resources\MyServiceResource;
use App\Http\Resources\MyServicesStatsResource;
use App\Http\Resources\PublicationExploreResource;
use App\Models\Publication;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class PublicationService
{
    /**
     * Obtener publicaciones del usuario (unificadas)
     */
    public function getUserPublications(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = Publication::forUser($user->id)
            ->with([
                'publishable' => function ($morph) {
                    $morph->morphWith([
                        \App\Models\ServiceRequest::class => ['city', 'state', 'country'],
                        \App\Models\RideRequest::class => [
                            'originCity',
                            'originState',
                            'originCountry',
                            'destinationCity',
                            'destinationState',
                            'destinationCountry'
                        ],
                    ]);
                }
            ]);

        // ── Filtro por categoría principal (service | ride) ──────────────────
        // FIX: filtra por 'category', no 'sub_category'
        if (!empty($filters['category'])) {
            $query->byCategory($filters['category']);   // usa el scope existente
        }

        // ── Filtro por sub-categoría (Fontanería, Electricidad, etc.) ────────
        // Separado del anterior para no confundirlos
        if (!empty($filters['sub_category'])) {
            $query->where('sub_category', $filters['sub_category']);
        }

        // ── Filtro por estado (tab) ───────────────────────────────────────────
        // FIX: usa null-coalescing para evitar error si la key no existe
        if (!empty($filters['status'])) {
            $statusMap = [
                'active'    => ['active'],
                'completed' => ['completed'],
                'paused'    => ['paused'],
                'pending'   => ['pending', 'in_review'],
            ];

            $dbStatuses = $statusMap[$filters['status']] ?? [$filters['status']];
            $query->whereIn('status', $dbStatuses);
        }

        // ── Filtro de búsqueda ────────────────────────────────────────────────
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('sub_category', 'like', "%{$search}%");
            });
        }

        // ── Ordenamiento ──────────────────────────────────────────────────────
        // FIX: ordenamiento antes de paginar, y con latest como fallback explícito
        match ($filters['sort'] ?? 'recent') {
            'views'      => $query->orderByDesc('views_count'),
            'offers'     => $query->orderByDesc('offers_count'),
            'price_high' => $query->orderByRaw(
                "CAST(JSON_UNQUOTE(JSON_EXTRACT(ui_metadata, '$.budget_max')) AS UNSIGNED) DESC"
            ),
            'price_low'  => $query->orderByRaw(
                "CAST(JSON_UNQUOTE(JSON_EXTRACT(ui_metadata, '$.budget_min')) AS UNSIGNED) ASC"
            ),
            default      => $query->orderByDesc('published_at'),
        };

        return $query->paginate($filters['per_page'] ?? 10);
    }

    /**
     * Obtener resumen de publicaciones para dashboard
     */
    public function getUserStats(User $user): array
    {
        $stats = Publication::forUser($user->id)
            ->selectRaw('category, status, count(*) as count')
            ->groupBy('category', 'status')
            ->get();

        return [
            'services' => [
                'active' => $stats->where('category', 'service')->where('status', 'active')->sum('count'),
                'in_progress' => $stats->where('category', 'service')->where('status', 'in_progress')->sum('count'),
                'completed' => $stats->where('category', 'service')->where('status', 'completed')->sum('count'),
                'total' => $stats->where('category', 'service')->sum('count'),
            ],
            'rides' => [
                'active' => $stats->where('category', 'ride')->where('status', 'active')->sum('count'),
                'in_progress' => $stats->where('category', 'ride')->where('status', 'in_progress')->sum('count'),
                'completed' => $stats->where('category', 'ride')->where('status', 'completed')->sum('count'),
                'total' => $stats->where('category', 'ride')->sum('count'),
            ],
        ];
    }

    /**
     * Obtener publicaciones para mostrar en home/explorar
     */
    public function getPublicFeed(array $filters = []): LengthAwarePaginator
    {
        $query = Publication::with('publishable')
            ->where('status', 'active')
            ->latest('published_at');

        // Filtro por tipo
        if (!empty($filters['type'])) {
            $query->byCategory($filters['type']);
        }

        // Filtro por ubicación (para servicios)
        if (!empty($filters['lat']) && !empty($filters['lng'])) {
            // Esto requeriría un join más complejo o PostGIS
            // Por ahora lo dejamos como placeholder
        }

        return $query->paginate(20);
    }

    public function getSummary(Request $request)
    {
        $userId = $request->user()->id;

        $publications = Publication::forUser($userId)
            ->selectRaw('category, status, COUNT(*) as count')
            ->groupBy('category', 'status')
            ->get();

        // Totales por categoría
        $services = $publications->where('category', 'service');
        $rides    = $publications->where('category', 'ride');

        // Desglose por estado (todas las categorías)
        $statusBreakdown = $publications
            ->groupBy('status')
            ->map(fn($group) => $group->sum('count'))
            ->sortDesc();

        return [
            'summary' => [
                'active_services' => $services
                    ->whereIn('status', ['open', 'in_progress', 'delivered'])
                    ->sum('count'),
                'scheduled_rides' => $rides
                    ->whereIn('status', ['available', 'full'])
                    ->sum('count'),
            ],
            'by_status' => $statusBreakdown,
            'by_category' => [
                'service' => $services->pluck('count', 'status'),
                'ride'    => $rides->pluck('count', 'status'),
            ],
        ];
    }

    public function explore(ExplorePublicationsRequest $request)
    {
        $results = Publication::with(['user', 'publishable'])
            ->active()
            ->when($request->q, function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($request->category, fn($q, $v) => $q->byCategory($v))
            ->when($request->sub_category, fn($q, $v) => $q->where('sub_category', $v))
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->when($request->sort, function ($q, $sort) use ($request) {
                match ($sort) {
                    'recent'     => $q->orderByDesc('published_at'),
                    'price_asc'  => $q->orderByRaw("JSON_EXTRACT(ui_metadata, '$.budget_range') ASC"),
                    'price_desc' => $q->orderByRaw("JSON_EXTRACT(ui_metadata, '$.budget_range') DESC"),
                    'rating'     => $q->orderByDesc(
                        \App\Models\Review::selectRaw('AVG(rating)')
                            ->whereColumn('reviewed_user_id', 'publications.user_id')
                            ->limit(1)
                    ),
                    'distance'   => $q->orderByRaw(
                        '(6371 * ACOS(
                                        COS(RADIANS(?)) * COS(RADIANS(JSON_EXTRACT(ui_metadata, "$.latitude")))
                                        * COS(RADIANS(JSON_EXTRACT(ui_metadata, "$.longitude")) - RADIANS(?))
                                        + SIN(RADIANS(?)) * SIN(RADIANS(JSON_EXTRACT(ui_metadata, "$.latitude")))
                                    ))',
                        [$request->lat, $request->lng, $request->lat]
                    ),
                    default => $q->orderByDesc('published_at'),
                };
            }, fn($q) => $q->orderByDesc('published_at')) // default si no viene sort
            ->paginate($request->per_page ?? 12);

        return $results;
    }

    /**
     * Stats para el header: total, activos, vistas totales, earnings.
     * (Consulta optimizada: un solo query agrupado + uno para vistas/earnings)
     */
    public function getUserServiceStats(User $user): array
    {
        // Conteos por estado
        $counts = Publication::forUser($user->id)
            ->where('category', 'service')
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        // Suma de vistas
        $totals = Publication::forUser($user->id)
            ->where('category', 'service')
            ->selectRaw('SUM(views_count) as total_views')
            ->first();

        // Earnings: suma del budget_max de los completados (ajusta a tu lógica real)
        $earnings = Publication::forUser($user->id)
            ->where('category', 'service')
            ->where('status', 'completed')
            ->with('publishable')
            ->get()
            ->sum(fn($pub) => optional($pub->publishable)->budget_max ?? 0);

        return [
            'total'          => $counts->sum(),
            'active'         => (int) ($counts['active']    ?? 0),
            'completed'      => (int) ($counts['completed'] ?? 0),
            'paused'         => (int) ($counts['paused']    ?? 0),
            'pending'        => (int) ($counts['pending']   ?? 0) + (int) ($counts['in_review'] ?? 0),
            'total_views'    => (int) ($totals->total_views ?? 0),
            'total_earnings' => (float) $earnings,
        ];
    }

     public function getUserRideStats(User $user): array
    {
        $counts = Publication::forUser($user->id)
            ->where('category', 'ride')
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');
 
        $totals = Publication::forUser($user->id)
            ->where('publications.category', 'ride')
            ->where('publications.status', 'completed')
            ->join('ride_requests', function ($join) {
                $join->on('ride_requests.id', '=', 'publications.publishable_id')
                     ->where('publications.publishable_type', \App\Models\RideRequest::class);
            })
            ->selectRaw('
                SUM(ride_requests.total_seats - ride_requests.available_seats) as total_passengers,
                SUM(
                    ride_requests.price_per_seat *
                    (ride_requests.total_seats - ride_requests.available_seats)
                ) as total_earnings
            ')
            ->first();
 
        return [
            'total'            => $counts->sum(),
            // 'available' y 'full' son los estados de BD que equivalen a 'upcoming' en la UI
            'upcoming'         => (int) (($counts['available'] ?? 0) + ($counts['full'] ?? 0)),
            'in_progress'      => (int) ($counts['in_progress'] ?? 0),
            'completed'        => (int) ($counts['completed']   ?? 0),
            'cancelled'        => (int) ($counts['cancelled']   ?? 0),
            'total_passengers' => (int)   ($totals->total_passengers ?? 0),
            'total_earnings'   => (float) ($totals->total_earnings   ?? 0),
        ];
    }

    public function getMyServices(User $user, MyServicesRequest $request): array
    {
        $filters = [
            'category'     => 'service',                      // filtra la tabla publications.category
            'sub_category' => $request->input('category'),    // el filtro del modal (Fontanería, etc.)
            'status'       => $request->status(),
            'search'       => $request->input('search'),
            'sort'         => $request->sort(),
            'per_page'     => $request->perPage(),
        ];

        $paginated = $this->getUserPublications($user, $filters);

        return [
            'publications' => MyServiceResource::collection($paginated),
            'stats'        => new MyServicesStatsResource($this->getUserServiceStats($user)),
        ];
    }

    public function getMyRides(User $user, MyRidesRequest $request): array
    {
        $filters = [
            'category' => 'ride',
            'status'   => $request->status(),
            'search'   => $request->input('search'),
            'sort'     => $request->sort(),
            'per_page' => $request->perPage(),
        ];
 
        $paginated = $this->getUserPublications($user, $filters);
 
        return [
            'rides' => MyRideResource::collection($paginated),
            'stats' => new MyRidesStatsResource($this->getUserRideStats($user)),
        ];
    }
}
