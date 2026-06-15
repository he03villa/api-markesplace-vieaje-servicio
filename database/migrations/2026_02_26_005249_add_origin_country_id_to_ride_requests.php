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
        Schema::table('ride_requests', function (Blueprint $table) {
            // Campos de geolocalización para ORIGEN
            $table->foreignId('origin_country_id')->nullable()->after('origin_lng')->constrained('countries')->nullOnDelete();
            $table->foreignId('origin_state_id')->nullable()->after('origin_country_id')->constrained('states')->nullOnDelete();
            $table->foreignId('origin_city_id')->nullable()->after('origin_state_id')->constrained('cities')->nullOnDelete();

            // Campos de geolocalización para DESTINO
            $table->foreignId('destination_country_id')->nullable()->after('destination_lng')->constrained('countries')->nullOnDelete();
            $table->foreignId('destination_state_id')->nullable()->after('destination_country_id')->constrained('states')->nullOnDelete();
            $table->foreignId('destination_city_id')->nullable()->after('destination_state_id')->constrained('cities')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ride_requests', function (Blueprint $table) {
            // Eliminar foreign keys y columnas de origen
            $table->dropForeign(['origin_country_id']);
            $table->dropForeign(['origin_state_id']);
            $table->dropForeign(['origin_city_id']);
            
            // Eliminar foreign keys y columnas de destino
            $table->dropForeign(['destination_country_id']);
            $table->dropForeign(['destination_state_id']);
            $table->dropForeign(['destination_city_id']);

            // Eliminar columnas
            $table->dropColumn([
                'origin_country_id',
                'origin_state_id',
                'origin_city_id',
                'destination_country_id',
                'destination_state_id',
                'destination_city_id',
            ]);
        });
    }
};
