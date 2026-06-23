<?php

namespace App\Console\Commands;

use App\Models\ServiceRequest;
use Illuminate\Console\Command;

class SyncWorkers extends Command
{
    protected $signature = 'requests:sync-workers
                            {--dry-run : Solo mostrar cuántos se actualizarían sin ejecutar}';

    protected $description = 'Backfill worker_id en ServiceRequests con offer aceptada';

    public function handle()
    {
        $query = ServiceRequest::whereNull('worker_id')
            ->where('status', '!=', 'open')
            ->whereHas('offers', fn($q) => $q->where('status', 'accepted'));

        $total = $query->count();

        if ($total === 0) {
            $this->info('No hay solicitudes pendientes de sincronizar.');
            return 0;
        }

        if ($this->option('dry-run')) {
            $this->info("Se actualizarían {$total} solicitudes.");
            return 0;
        }

        $this->info("Sincronizando {$total} solicitudes...");
        $bar = $this->output->createProgressBar($total);

        $query->chunk(100, function ($services) use ($bar) {
            foreach ($services as $service) {
                $offer = $service->offers()->where('status', 'accepted')->first();
                if ($offer) {
                    $service->updateQuietly(['worker_id' => $offer->user_id]);
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info('Sincronización completada!');

        return 0;
    }
}
