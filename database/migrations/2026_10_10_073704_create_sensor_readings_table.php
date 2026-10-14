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
        Schema::create('sensor_readings', function (Blueprint $table) {
    $table->id();
    $table->foreignId('station_id')->constrained('stations')->onDelete('cascade');
    $table->float('water_level_cm');
    $table->float('flow_rate')->nullable();       
    $table->float('battery_voltage')->nullable(); 
    $table->timestamp('recorded_at')->useCurrent();
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sensor_readings');
    }
};
