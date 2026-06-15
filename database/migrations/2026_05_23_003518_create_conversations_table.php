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
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            // Siempre user_a_id < user_b_id → unicidad garantizada
            $table->foreignId('user_a_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_b_id')->constrained('users')->cascadeOnDelete();
 
            // Desnormalización para el inbox — se actualiza en cada mensaje
            $table->foreignId('last_message_id')
                  ->nullable()
                  ->constrained('messages')
                  ->nullOnDelete();
 
            $table->timestamp('last_message_at')->nullable()->index();
 
            // Contadores de no leídos por lado — evitan COUNT(*) en el inbox
            $table->unsignedSmallInteger('unread_a')->default(0);
            $table->unsignedSmallInteger('unread_b')->default(0);

            $table->timestamps();

            $table->unique(['user_a_id', 'user_b_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
