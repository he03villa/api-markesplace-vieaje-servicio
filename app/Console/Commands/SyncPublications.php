<?php

namespace App\Console\Commands;

use App\Models\RideRequest;
use App\Models\ServiceRequest;
use Illuminate\Console\Command;

class SyncPublications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'publications:sync 
                            {--type=all : Tipo a sincronizar (service, ride, all)}
                            {--fix-missing : Solo crear las faltantes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincroniza publicaciones existentes con la tabla publications ';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $type = $this->option('type');
        $fixOnly = $this->option('fix-missing');

        if (in_array($type, ['service', 'all'])) {
            $this->syncServices($fixOnly);
        }

        if (in_array($type, ['ride', 'all'])) {
            $this->syncRides($fixOnly);
        }

        $this->info('Sincronización completada!');
        return 0;
    }

    private function syncServices(bool $fixOnly): void
    {
        $query = ServiceRequest::query();
        
        if ($fixOnly) {
            $query->whereDoesntHave('publication');
        }

        $count = $query->count();
        $this->info("Sincronizando {$count} servicios...");

        $bar = $this->output->createProgressBar($count);
        
        $query->chunk(100, function ($services) use ($bar) {
            foreach ($services as $service) {
                $service->syncPublication();
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
    }

    private function syncRides(bool $fixOnly): void
    {
        $query = RideRequest::query();
        
        if ($fixOnly) {
            $query->whereDoesntHave('publication');
        }

        $count = $query->count();
        $this->info("Sincronizando {$count} viajes...");

        $bar = $this->output->createProgressBar($count);
        
        $query->chunk(100, function ($rides) use ($bar) {
            foreach ($rides as $ride) {
                $ride->syncPublication();
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
    }
}
