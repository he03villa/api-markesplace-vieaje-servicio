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
        Schema::create('service_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Información básica
            $table->string('title');
            $table->text('description');
            $table->string('category');
            
            // Ubicación
            $table->string('address');
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();
            
            // Presupuesto y tiempo
            $table->decimal('budget_min', 10, 2)->nullable();
            $table->decimal('budget_max', 10, 2)->nullable();
            $table->decimal('budget_fixed', 10, 2)->nullable();
            $table->timestamp('deadline')->nullable();
            $table->integer('estimated_hours')->nullable();
            
            // Estado y control
            $table->enum('status', ['open', 'in_progress', 'completed', 'cancelled', 'expired', 'delivered'])->default('open');
            $table->enum('budget_type', ['range', 'fixed', 'negotiable'])->default('negotiable');
            $table->enum('urgency', ['low', 'medium', 'high', 'urgent'])->default('medium');
            
            // Multimedia
            $table->json('images')->nullable();
            $table->json('documents')->nullable();
            
            // Estadísticas
            $table->integer('views_count')->default(0);
            $table->integer('offers_count')->default(0);
            
            // Tiempos
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Índices optimizados
            $table->index('user_id');
            $table->index('status');
            $table->index('category');
            $table->index(['latitude', 'longitude']);
            $table->index('created_at');
            $table->index('deadline');
            $table->index('urgency');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_requests');
    }
};
