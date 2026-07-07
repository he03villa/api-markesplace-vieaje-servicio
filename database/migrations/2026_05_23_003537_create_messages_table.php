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
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')
                  ->constrained('conversations')
                  ->cascadeOnDelete();
 
            $table->foreignId('sender_id')
                  ->constrained('users')
                  ->cascadeOnDelete();
 
            // Nullable: un mensaje puede ser solo adjunto
            $table->text('body')->nullable();
 
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->foreign('last_message_id')
                  ->references('id')
                  ->on('messages')
                  ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropForeign(['last_message_id']);
        });

        Schema::dropIfExists('messages');
    }
};
