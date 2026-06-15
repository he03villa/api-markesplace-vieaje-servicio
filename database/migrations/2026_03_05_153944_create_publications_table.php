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
        Schema::create('publications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Relación polimórfica
            $table->morphs('publishable');
            
            // Campos comunes para UI
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category'); // 'service' o 'ride'
            $table->string('sub_category')->nullable(); // categoría específica
            
            // Estado unificado
            $table->enum('status', [
                'active',      // open/available
                'in_progress', 
                'completed',   
                'cancelled',   
                'expired'      
            ])->default('active');
            
            // Métricas
            $table->integer('offers_count')->default(0);
            $table->integer('views_count')->default(0);
            
            // Metadata flexible para la UI
            $table->json('ui_metadata')->nullable();
            
            // Para ordenamiento
            $table->timestamp('published_at')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Índices optimizados
            $table->index(['user_id', 'status', 'created_at']);
            $table->index(['category', 'status']);
            $table->index('publishable_type');
            $table->index('published_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('publications');
    }
};
