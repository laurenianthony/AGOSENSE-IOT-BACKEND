<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('field_reports', function (Blueprint $table) {
            $table->foreignId('station_id')
                ->nullable()
                ->after('user_id')
                ->constrained('stations')
                ->nullOnDelete();
        });

        DB::table('stations')
            ->where('station_code', 'CAL-DRN-01')
            ->update(['location_name' => 'Main Municipal Channel, Calauan, Laguna']);
    }

    public function down(): void
    {
        Schema::table('field_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('station_id');
        });
    }
};
