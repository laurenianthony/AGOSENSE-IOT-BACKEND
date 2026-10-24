<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sensor_readings', function (Blueprint $table) {
            $table->index(['station_id', 'recorded_at'], 'sensor_readings_station_recorded_idx');
        });

        Schema::create('daily_sensor_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('station_id')->constrained('stations')->cascadeOnDelete();
            $table->timestamp('bucket_start');
            $table->decimal('water_min_cm', 10, 2)->nullable();
            $table->decimal('water_max_cm', 10, 2)->nullable();
            $table->decimal('water_avg_cm', 10, 2)->nullable();
            $table->decimal('flow_min_lpm', 10, 2)->nullable();
            $table->decimal('flow_max_lpm', 10, 2)->nullable();
            $table->decimal('flow_avg_lpm', 10, 2)->nullable();
            $table->unsignedInteger('readings_count')->default(0);
            $table->unsignedInteger('anomaly_count')->default(0);
            $table->unsignedInteger('blockage_count')->default(0);
            $table->timestamps();
            $table->unique(['station_id', 'bucket_start']);
            $table->index('bucket_start');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_sensor_summaries');

        Schema::table('sensor_readings', function (Blueprint $table) {
            $table->dropIndex('sensor_readings_station_recorded_idx');
        });
    }
};
