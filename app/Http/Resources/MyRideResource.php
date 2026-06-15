<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MyRideResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var \App\Models\Publication $pub */
        $pub = $this->resource;

        /** @var \App\Models\RideRequest|null $ride */
        $ride = $pub->publishable;

        $meta = $pub->ui_metadata ?? [];

        return [
            // ── Identificadores ──────────────────────────────────────────────
            'id'             => $pub->id,
            'publishable_id' => $pub->publishable_id,

            // ── Ruta ─────────────────────────────────────────────────────────
            // Usamos los accessors del modelo: origin_full_location / destination_full_location
            'origin'      => $ride?->origin_full_location,
            'destination' => $ride?->destination_full_location,

            // ── Coordenadas ───────────────────────────────────────────────────
            // Campos reales: origin_lat / origin_lng / destination_lat / destination_lng
            'originLat' => $ride?->origin_lat,
            'originLng' => $ride?->origin_lng,
            'destLat'   => $ride?->destination_lat,
            'destLng'   => $ride?->destination_lng,

            // ── Fecha y hora ──────────────────────────────────────────────────
            // Campo real: departure_time (no departure_date ni time separado)
            'date'          => $ride?->departure_time?->toDateString(),
            'time'          => $ride?->departure_time?->format('H:i'),
            'relativeTime' => $meta['subtitle'] ?? $ride?->departure_time?->diffForHumans(),

            // ── Estado ────────────────────────────────────────────────────────
            'status'       => $pub->status,
            'statusLabel' => $pub->status_label,
            'badge'        => $pub->badge,

            // ── Asientos ─────────────────────────────────────────────────────
            // Campos reales: total_seats / available_seats
            'seatsTotal'     => $ride?->total_seats,
            'seatsAvailable' => $ride?->available_seats,
            'passengers'      => ($ride?->total_seats ?? 0) - ($ride?->available_seats ?? 0),
            'occupancyRate'  => $this->occupancyRate($ride),
            'isFull'         => $ride?->is_full ?? false,

            // ── Precio ────────────────────────────────────────────────────────
            'pricePerSeat'  => $ride?->price_per_seat,
            'priceFormatted' => $meta['price']
                ?? ($ride?->price_per_seat
                    ? '$' . number_format($ride->price_per_seat, 0) . '/asiento'
                    : null),

            // ── Ganancias (pasajeros × precio) ────────────────────────────────
            'earnings' => $this->calculateEarnings($ride),

            // ── Vehículo ──────────────────────────────────────────────────────
            // Campos reales: vehicle_make, vehicle_model, vehicle_year, vehicle_color
            'vehicle' => $this->buildVehicleLabel($ride),

            // ── Distancia ─────────────────────────────────────────────────────
            // Viene del accessor estimated_distance del modelo
            'distance' => $meta['distance']
                ?? ($ride?->estimated_distance
                    ? $ride->estimated_distance . ' km'
                    : null),

            // ── Métricas ──────────────────────────────────────────────────────
            'views'  => $pub->views_count,
            'offers' => $pub->offers_count,  // = confirmedPassengers según getOffersCount()
        ];
    }

    private function occupancyRate(?\App\Models\RideRequest $ride): float
    {
        if (!$ride || !$ride->total_seats) {
            return 0;
        }
        $taken = $ride->total_seats - ($ride->available_seats ?? 0);
        return round(($taken / $ride->total_seats) * 100, 1);
    }

    private function calculateEarnings(?\App\Models\RideRequest $ride): float
    {
        if (!$ride || !$ride->price_per_seat) {
            return 0;
        }
        $passengers = $ride->total_seats - ($ride->available_seats ?? 0);
        return round($ride->price_per_seat * $passengers, 2);
    }

    private function buildVehicleLabel(?\App\Models\RideRequest $ride): ?string
    {
        if (!$ride) {
            return null;
        }
        $parts = array_filter([
            $ride->vehicle_color,
            $ride->vehicle_make,
            $ride->vehicle_model,
            $ride->vehicle_year,
        ]);
        // Resultado ejemplo: "Blanco Toyota Corolla 2020"
        return !empty($parts) ? implode(' ', $parts) : null;
    }
}
