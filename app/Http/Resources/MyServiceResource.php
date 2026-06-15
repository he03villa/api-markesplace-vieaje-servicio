<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MyServiceResource extends JsonResource
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

        /** @var \App\Models\ServiceRequest|null $sr */
        $sr  = $pub->publishable;

        $meta = $pub->ui_metadata ?? [];

        return [
            // ── Identificadores ──────────────────────────────────────────────
            'id'              => $pub->id,
            'publishable_id'  => $pub->publishable_id,

            // ── Contenido ────────────────────────────────────────────────────
            'title'           => $pub->title,
            'description'     => $pub->description,
            'category'        => $pub->sub_category ?? $pub->category,
            'category_label'  => $meta['category_label'] ?? $pub->sub_category,

            // ── Estado ───────────────────────────────────────────────────────
            'status'          => $pub->status,
            'status_label'    => $pub->status_label,          // accessor del modelo
            'badge'           => $pub->badge,                 // ['text' => ..., 'color' => ...]

            // ── Precios ──────────────────────────────────────────────────────
            'price_range'     => $meta['budget_range'] ?? null,
            'budget_min'      => $sr?->budget_min,
            'budget_max'      => $sr?->budget_max,

            // ── Imágenes ─────────────────────────────────────────────────────
            'image'           => $this->firstImage($sr),
            'has_images'      => $meta['has_images'] ?? !empty($sr?->images),

            // ── Métricas (las que muestra la tarjeta) ─────────────────────────
            'views'           => $pub->views_count,
            'offers'          => $pub->offers_count,
            'rating'          => $sr?->reviews()->avg('rating')
                ? round($sr->reviews()->avg('rating'), 1)
                : 0,

            // ── Localización ─────────────────────────────────────────────────
            'location'        => $meta['location'] ?? $sr?->address,

            // ── Fechas ───────────────────────────────────────────────────────
            'deadline'        => $meta['deadline'] ?? $sr?->deadline?->format('d/m/Y'),
            'relative_time'   => $meta['subtitle']  ?? $pub->published_at?->diffForHumans(),
            'created_at'      => $pub->published_at?->toDateString(),
        ];
    }

    private function firstImage(?\App\Models\ServiceRequest $sr): ?string
    {
        if (!$sr || empty($sr->images)) {
            return null;
        }

        $first = is_array($sr->images) ? $sr->images[0] : null;

        if (!$first) {
            return null;
        }

        // Si el uploader guarda un array con 'url', devolvemos eso;
        // si guarda directamente el path, lo usamos directo.
        return is_array($first) ? ($first['url'] ?? null) : $first;
    }
}
