<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DriverStatsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'period'       => $this->resource['period'],
 
            // ── KPIs ──────────────────────────────────────────────────────────
            'kpis' => [
                [
                    'label'  => 'Ganancias',
                    'value'  => '$' . number_format($this->resource['kpis']['earnings']['current'], 0),
                    'raw'    => $this->resource['kpis']['earnings']['current'],
                    'change' => $this->formatChange($this->resource['kpis']['earnings']['change']),
                    'icon'   => 'cash-outline',
                    'color'  => '#10b981',
                    'bg'     => 'rgba(16,185,129,0.1)',
                ],
                [
                    'label'  => 'Viajes',
                    'value'  => (string) $this->resource['kpis']['rides']['current'],
                    'raw'    => $this->resource['kpis']['rides']['current'],
                    'change' => $this->formatChange($this->resource['kpis']['rides']['change']),
                    'icon'   => 'flash-outline',
                    'color'  => '#3b82f6',
                    'bg'     => 'rgba(59,130,246,0.1)',
                ],
                [
                    'label'  => 'Pasajeros',
                    'value'  => (string) $this->resource['kpis']['passengers']['current'],
                    'raw'    => $this->resource['kpis']['passengers']['current'],
                    'change' => $this->formatChange($this->resource['kpis']['passengers']['change']),
                    'icon'   => 'people-outline',
                    'color'  => '#8b5cf6',
                    'bg'     => 'rgba(139,92,246,0.1)',
                ],
                [
                    'label'  => 'Rating',
                    'value'  => number_format($this->resource['kpis']['rating']['current'], 1),
                    'raw'    => $this->resource['kpis']['rating']['current'],
                    'change' => $this->formatChange($this->resource['kpis']['rating']['change'], decimals: 1),
                    'icon'   => 'star-outline',
                    'color'  => '#f59e0b',
                    'bg'     => 'rgba(245,158,11,0.1)',
                ],
            ],
 
            // ── Gráfica semanal ───────────────────────────────────────────────
            'weekly_chart' => collect($this->resource['weekly_chart'])
                ->map(fn($bar) => [
                    'label' => $bar['label'],
                    'value' => $bar['value'],
                    'raw'   => $bar['raw'],
                    'color' => $bar['value'] >= 80 ? '#10b981' : '#3b82f6',
                ])
                ->values(),
 
            // ── Mapa de calor ─────────────────────────────────────────────────
            'heatmap' => collect($this->resource['heatmap'])
                ->map(fn($day) => [
                    'day'       => $day['day'],
                    'date'      => $day['date'],
                    'intensity' => $day['intensity'],
                    'rides'     => $day['rides'],
                ])
                ->values(),
 
            // ── Insights ──────────────────────────────────────────────────────
            'insights' => collect($this->resource['insights'])
                ->map(fn($insight) => [
                    'icon'        => $insight['icon'],
                    'color'       => $insight['color'],
                    'bg'          => $insight['bg'],
                    'title'       => $insight['title'],
                    'description' => $insight['description'],
                    'trend'       => $insight['trend'],
                    'percent'     => $insight['percent'],
                ])
                ->values(),
 
            // ── Meta ──────────────────────────────────────────────────────────
            'generated_at' => now()->toIso8601String(),
        ];
    }

    private function formatChange(float $change, int $decimals = 1): string
    {
        $formatted = number_format(abs($change), $decimals);
        return $change >= 0 ? "+{$formatted}%" : "-{$formatted}%";
    }
}
