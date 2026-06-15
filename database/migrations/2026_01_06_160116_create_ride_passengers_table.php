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
        Schema::create('ride_passengers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ride_request_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Información del pasajero
            $table->integer('seats_reserved');
            $table->enum('status', ['pending', 'confirmed', 'cancelled', 'rejected', 'completed'])->default('pending');
            
            // Detalles del viaje
            $table->decimal('price_paid', 10, 2)->nullable();
            $table->decimal('price_per_seat', 10, 2)->nullable();
            $table->string('pickup_location')->nullable();
            $table->string('dropoff_location')->nullable();
            
            // Calificación y comentarios
            $table->integer('driver_rating')->nullable();
            $table->text('driver_comment')->nullable();
            $table->integer('passenger_rating')->nullable();
            $table->text('passenger_comment')->nullable();
            
            // Pago
            $table->enum('payment_status', ['pending', 'paid', 'refunded', 'cancelled'])->default('pending');
            $table->string('payment_method')->nullable();
            $table->string('payment_id')->nullable();
            $table->timestamp('paid_at')->nullable();
            
            // Confirmación y tiempos
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            
            // Información adicional
            $table->text('special_requests')->nullable();
            $table->boolean('no_show')->default(false);
            $table->text('cancellation_reason')->nullable();
            
            $table->timestamps();
            
            // Índices optimizados
            $table->unique(['ride_request_id', 'user_id']);
            $table->index('ride_request_id');
            $table->index('user_id');
            $table->index('status');
            $table->index('payment_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ride_passengers');
    }
};
