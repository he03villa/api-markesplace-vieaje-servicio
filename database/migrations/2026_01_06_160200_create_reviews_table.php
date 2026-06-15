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
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reviewer_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('reviewed_user_id')->constrained('users')->onDelete('cascade');
            
            // Relación polimórfica
            $table->string('reviewable_type');
            $table->unsignedBigInteger('reviewable_id');
            
            // Calificación
            $table->integer('rating');
            $table->text('comment')->nullable();
            
            // Calificaciones específicas
            $table->integer('punctuality_rating')->nullable();
            $table->integer('communication_rating')->nullable();
            $table->integer('quality_rating')->nullable();
            $table->integer('professionalism_rating')->nullable();
            
            // Metadatos
            $table->boolean('is_public')->default(true);
            $table->boolean('is_verified')->default(false);
            $table->enum('type', ['service', 'ride', 'general'])->default('general');
            
            // Respuesta del revisado
            $table->text('owner_response')->nullable();
            $table->timestamp('responded_at')->nullable();
            
            // Reportes
            $table->boolean('is_reported')->default(false);
            $table->text('report_reason')->nullable();
            $table->boolean('is_hidden')->default(false);
            
            // Estadísticas
            $table->integer('helpful_count')->default(0);
            $table->integer('not_helpful_count')->default(0);
            
            $table->timestamps();
            $table->softDeletes();
            
            // Índices optimizados
            $table->index(['reviewable_type', 'reviewable_id']);
            $table->index('reviewed_user_id');
            $table->index('reviewer_id');
            $table->index('rating');
            $table->index('created_at');
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
