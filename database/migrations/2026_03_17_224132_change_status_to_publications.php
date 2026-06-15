<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('publications', function (Blueprint $table) {
            $table->enum('status', [
                'active',           // Disponible para ofertas/reservas
                'in_progress',      // En ejecución (con worker/conductor asignado)
                'delivered',        // Worker entregó / Conductor completó viaje
                'completed',        // Solicitante aprobó / Pasajeros confirmaron llegada
                'disputed',         // Conflicto en resolución
                'cancelled',        // Cancelado por alguna parte
                'expired',          // Venció sin actividad
                'full',             // SIN ASIENTOS (solo para rides)
            ])->default('active')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('publications', function (Blueprint $table) {
            $table->enum('status', [
                'active',           // Disponible para ofertas/reservas
                'in_progress',      // En ejecución (con worker/conductor asignado)
                'delivered',        // Worker entregó / Conductor completó viaje
                'completed',        // Solicitante aprobó / Pasajeros confirmaron llegada
                'disputed',         // Conflicto en resolución
                'cancelled',        // Cancelado por alguna parte
                'expired',          // Venció sin actividad
            ])->default('active')->change();
        });
    }
};
