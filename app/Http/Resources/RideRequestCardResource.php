<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RideRequestCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $available = (int) ($this->available_seats ?? 0);
        $total = (int) ($this->total_seats ?? max($available, 1));
        $occupied = max(0, $total - $available);

        $fullName = $this->driver?->name ?? 'Conductor';
        $nameParts = explode(' ', $fullName);
        $shortName = $nameParts[0] . (isset($nameParts[1]) ? ' ' . mb_substr($nameParts[1], 0, 1) . '.' : '');

        return [
            'id' => $this->id,
            'route' => [
                'origin_short' => $this->shortenAddress($this->origin_address) ?? $this->originCity?->name ?? 'Origen',
                'destination_short' => $this->shortenAddress($this->destination_address) ?? $this->destinationCity?->name ?? 'Destino',
            ],
            'departure' => [
                'time' => $this->departure_time,
                'human' => $this->formatDepartureTime(),
                'is_today' => $this->departure_time?->isToday(),
                'is_tomorrow' => $this->departure_time?->isTomorrow(),
            ],
            'driver' => [
                'name' => $shortName,
                'full_name' => $fullName,
                'avatar' => $this->driver?->avatar_url,
                'rating' => (float) ($this->driver?->rating ?? 5.0),
                'completed_jobs' => (int) ($this->driver?->completed_jobs ?? 0),
            ],
            'vehicle' => [
                'make' => $this->vehicle_make,
                'model' => $this->vehicle_model,
                'year' => $this->vehicle_year ? (int) $this->vehicle_year : null,
                'color' => $this->vehicle_color,
                'display' => $this->buildVehicleDisplay(), // ← "Toyota Corolla 2020"
            ],
            'seats' => [
                'available' => $available,
                'total' => $total,
                'occupied' => $occupied,
                'visual' => $this->buildSeatsVisual($available, $total),
                'text' => $available . ' asiento' . ($available !== 1 ? 's' : '') . ' libre' . ($available !== 1 ? 's' : ''),
            ],
            'price' => [
                'amount' => (float) $this->price_per_seat,
                'formatted' => '$' . number_format($this->price_per_seat, 0),
                'unit' => 'c/u',
            ],
            'status' => $this->status,
        ];
    }

    private function buildVehicleDisplay(): ?string
    {
        $parts = array_filter([
            $this->vehicle_make,
            $this->vehicle_model,
            $this->vehicle_year,
        ]);

        return !empty($parts) ? implode(' ', $parts) : null;
    }

    private function formatDepartureTime(): string
    {
        if (!$this->departure_time) {
            return 'Fecha no disponible';
        }

        $time = $this->departure_time->format('h:i A');

        if ($this->departure_time->isToday()) {
            return "Hoy, {$time}";
        }

        if ($this->departure_time->isTomorrow()) {
            return "Mañana, {$time}";
        }

        return $this->departure_time->translatedFormat('d M, h:i A');
    }

    private function buildSeatsVisual(int $available, int $total): array
    {
        $visual = [];
        $occupied = max(0, $total - $available);

        for ($i = 0; $i < $occupied; $i++) {
            $visual[] = 'occupied';
        }
        for ($i = 0; $i < $available; $i++) {
            $visual[] = 'available';
        }

        return $visual;
    }

    private function shortenAddress(?string $address): ?string
    {
        if (!$address) {
            return null;
        }

        $parts = explode(',', $address);
        $short = trim($parts[0]);

        if (mb_strlen($short) > 25) {
            $short = mb_substr($short, 0, 22) . '...';
        }

        return $short;
    }
}