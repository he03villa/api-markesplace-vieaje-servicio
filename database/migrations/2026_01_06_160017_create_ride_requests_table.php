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
        Schema::create('ride_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained('users')->onDelete('cascade');
            
            // Origen
            $table->string('origin_address');
            $table->decimal('origin_lat', 10, 8);
            $table->decimal('origin_lng', 11, 8);
            $table->string('origin_city')->nullable();
            $table->string('origin_state')->nullable();
            
            // Destino
            $table->string('destination_address');
            $table->decimal('destination_lat', 10, 8);
            $table->decimal('destination_lng', 11, 8);
            $table->string('destination_city')->nullable();
            $table->string('destination_state')->nullable();
            
            // Detalles del viaje
            $table->timestamp('departure_time');
            $table->timestamp('estimated_arrival_time')->nullable();
            $table->integer('available_seats');
            $table->integer('total_seats')->default(4);
            $table->decimal('price_per_seat', 10, 2);
            $table->decimal('total_price', 10, 2)->nullable();
            
            // Información del vehículo
            $table->string('vehicle_make')->nullable();
            $table->string('vehicle_model')->nullable();
            $table->integer('vehicle_year')->nullable();
            $table->string('vehicle_color')->nullable();
            $table->string('vehicle_plate')->nullable();
            
            // Estado y preferencias
            $table->enum('status', ['available', 'full', 'in_progress', 'completed', 'cancelled'])->default('available');
            $table->enum('smoking_policy', ['allowed', 'not_allowed', 'outside_only'])->default('not_allowed');
            $table->enum('music_policy', ['allowed', 'not_allowed', 'driver_choice'])->default('driver_choice');
            $table->boolean('pets_allowed')->default(false);
            $table->boolean('luggage_space')->default(true);
            
            // Rutas y paradas
            $table->json('route_waypoints')->nullable();
            $table->json('allowed_stops')->nullable();
            $table->integer('estimated_distance_km')->nullable();
            $table->integer('estimated_duration_minutes')->nullable();
            
            // Información adicional
            $table->text('notes')->nullable();
            $table->json('amenities')->nullable(); // WiFi, cargador, etc.
            $table->json('preferences')->nullable(); // Género, edad, etc.
            
            // Estadísticas
            $table->integer('views_count')->default(0);
            $table->integer('passenger_requests_count')->default(0);
            
            // Tiempos
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Índices optimizados
            $table->index('driver_id');
            $table->index('status');
            $table->index('departure_time');
            $table->index(['origin_lat', 'origin_lng']);
            $table->index(['destination_lat', 'destination_lng']);
            $table->index('price_per_seat');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ride_requests');
    }
};
