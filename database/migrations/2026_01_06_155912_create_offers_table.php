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
        Schema::create('offers', function (Blueprint $table) {
             $table->id();
            $table->foreignId('service_request_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Detalles de la oferta
            $table->decimal('price', 10, 2);
            $table->text('description');
            $table->string('estimated_time')->nullable();
            $table->json('deliverables')->nullable();
            $table->json('timeline')->nullable();
            
            // Estado
            $table->enum('status', ['pending', 'accepted', 'rejected', 'withdrawn', 'expired'])->default('pending');
            
            // Negociación
            $table->boolean('negotiable')->default(true);
            $table->decimal('counter_price', 10, 2)->nullable();
            $table->text('counter_message')->nullable();
            
            // Calificación del trabajo
            $table->decimal('final_price', 10, 2)->nullable();
            $table->integer('actual_hours')->nullable();
            $table->text('work_summary')->nullable();
            
            // Tiempos
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            
            // Revisiones
            $table->integer('revision_count')->default(0);
            $table->integer('max_revisions')->default(3);
            
            $table->timestamps();
            $table->softDeletes();
            
            // Índices optimizados
            $table->index('service_request_id');
            $table->index('user_id');
            $table->index('status');
            $table->index('price');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
};
