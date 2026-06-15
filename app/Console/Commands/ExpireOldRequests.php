<?php

namespace App\Console\Commands;

use App\Models\RideRequest;
use App\Models\ServiceRequest;
use Illuminate\Console\Command;

class ExpireOldRequests extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'requests:expire';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expira solicitudes vencidas';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Expirar ServiceRequests pasados deadline
        $expiredServices = ServiceRequest::where('status', 'open')
            ->where('deadline', '<', now())
            ->get();

        foreach ($expiredServices as $request) {
            $request->transitionTo('expired');
            $this->info("ServiceRequest {$request->id} expirado");
        }

        // Expirar RideRequests pasados departure_time
        $expiredRides = RideRequest::whereIn('status', ['available', 'full'])
            ->where('departure_time', '<', now()->subHour()) // 1 hora de tolerancia
            ->get();

        foreach ($expiredRides as $ride) {
            $ride->transitionTo('cancelled'); // O 'expired' si agregas ese estado
            $this->info("RideRequest {$ride->id} cancelado por no iniciarse");
        }

        $this->info("Proceso completado");
    }
}
