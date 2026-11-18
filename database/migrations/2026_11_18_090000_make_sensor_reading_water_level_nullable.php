<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sensor_readings', function (Blueprint $table) {
            $table->float('water_level_cm')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (\Illuminate\Support\Facades\DB::table('sensor_readings')->whereNull('water_level_cm')->exists()) {
            throw new RuntimeException('Cannot make water_level_cm required while failed sensor pings are stored as null.');
        }

        Schema::table('sensor_readings', function (Blueprint $table) {
            $table->float('water_level_cm')->nullable(false)->change();
        });
    }
};
