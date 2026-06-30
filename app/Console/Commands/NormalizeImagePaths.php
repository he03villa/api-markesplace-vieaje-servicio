<?php

namespace App\Console\Commands;

use App\Models\ServiceRequest;
use App\Models\ServiceRequestDelivery;
use Illuminate\Console\Command;

class NormalizeImagePaths extends Command
{
    protected $signature = 'images:normalize-paths';
    protected $description = 'Normaliza rutas de imágenes existentes a paths relativos';

    public function handle(): int
    {
        $this->info('Normalizando rutas de imágenes...');

        $stats = [
            'service_requests' => $this->normalizeServiceRequests(),
            'service_request_deliveries_images' => $this->normalizeDeliveryImages(),
            'service_request_deliveries_docs' => $this->normalizeDeliveryDocs(),
        ];

        $this->newLine();
        $this->table(
            ['Tabla / Columna', 'Registros actualizados'],
            collect($stats)->map(fn($count, $key) => [$key, $count])->toArray()
        );

        return Command::SUCCESS;
    }

    private function extractRelativePath(string $url): string
    {
        if (preg_match('#/storage/(.+)$#', $url, $matches)) {
            return $matches[1];
        }
        return $url;
    }

    private function normalizeServiceRequests(): int
    {
        $count = 0;
        ServiceRequest::whereNotNull('images')->chunk(100, function ($requests) use (&$count) {
            foreach ($requests as $request) {
                $images = $request->images;
                if (!is_array($images)) {
                    continue;
                }

                $normalized = array_map(fn($img) => $this->extractRelativePath($img), $images);

                if ($normalized !== $images) {
                    ServiceRequest::withoutTimestamps(fn() => $request->updateQuietly(['images' => $normalized]));
                    $count++;
                }
            }
        });
        return $count;
    }

    private function normalizeDeliveryImages(): int
    {
        $count = 0;
        ServiceRequestDelivery::whereNotNull('evidence_images')->chunk(100, function ($deliveries) use (&$count) {
            foreach ($deliveries as $delivery) {
                $images = $delivery->evidence_images;
                if (!is_array($images)) {
                    continue;
                }

                $normalized = array_map(fn($img) => $this->extractRelativePath($img), $images);

                if ($normalized !== $images) {
                    ServiceRequestDelivery::withoutTimestamps(fn() => $delivery->updateQuietly(['evidence_images' => $normalized]));
                    $count++;
                }
            }
        });
        return $count;
    }

    private function normalizeDeliveryDocs(): int
    {
        $count = 0;
        ServiceRequestDelivery::whereNotNull('evidence_docs')->chunk(100, function ($deliveries) use (&$count) {
            foreach ($deliveries as $delivery) {
                $docs = $delivery->evidence_docs;
                if (!is_array($docs)) {
                    continue;
                }

                $normalized = array_map(function ($doc) {
                    if (is_array($doc) && isset($doc['path'])) {
                        $doc['path'] = $this->extractRelativePath($doc['path']);
                    }
                    return $doc;
                }, $docs);

                if ($normalized !== $docs) {
                    ServiceRequestDelivery::withoutTimestamps(fn() => $delivery->updateQuietly(['evidence_docs' => $normalized]));
                    $count++;
                }
            }
        });
        return $count;
    }
}
