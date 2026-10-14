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
       Schema::create('stations', function (Blueprint $table) {
    $table->id();
    $table->string('station_code')->unique(); // e.g., CAL-DRN-01
    $table->string('location_name');          // e.g., Main Municipal Channel, Calauan
    $table->decimal('latitude', 10, 7);
    $table->decimal('longitude', 10, 7);
    $table->enum('status', ['active', 'maintenance', 'offline'])->default('active');
    $table->timestamps();
    $table->string('role')->default('viewer'); // options: 'admin', 'viewer'
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stations');
    }
};
