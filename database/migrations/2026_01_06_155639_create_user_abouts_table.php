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
        Schema::create('user_abouts', function (Blueprint $table) {
             $table->id();
            $table->foreignId('user_id')
                ->constrained()
                ->onDelete('cascade')
                ->unique();
            
            // Campos de contacto e identificación
            $table->string('phone')->nullable();
            $table->string('avatar')->nullable();
            $table->text('bio')->nullable();
            
            // Información personal
            $table->string('address')->nullable();
            $table->date('birth_date')->nullable();
            $table->enum('gender', ['male', 'female', 'other', 'prefer_not_to_say'])->nullable();
            $table->string('occupation')->nullable();
            $table->string('education')->nullable();
            
            // Información adicional
            $table->json('interests')->nullable();
            $table->json('languages')->nullable();
            $table->json('social_links')->nullable();
            
            // Verificación
            $table->timestamp('phone_verified_at')->nullable();
            $table->string('id_document_path')->nullable();
            $table->timestamp('id_verified_at')->nullable();
            
            // Preferencias
            $table->boolean('notifications_enabled')->default(true);
            $table->boolean('sms_notifications')->default(false);
            $table->string('preferred_language')->default('es');
            $table->enum('privacy_level', ['public', 'friends_only', 'private'])->default('public');
            
            $table->timestamps();
            
            // Índices optimizados
            $table->index('user_id');
            $table->index('phone');
            $table->index('gender');
            $table->index('privacy_level');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_abouts');
    }
};
