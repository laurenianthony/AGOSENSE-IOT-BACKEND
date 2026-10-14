<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Station;
use App\Models\SensorReading;
use Carbon\Carbon;

class DrainageSeeder extends Seeder
{
    public function run(): void
    {
        $station = Station::create([
            'station_code' => 'CAL-DRN-01',
            'location_name' => 'Pilot Drainage Channel, Calauan, Laguna',
            'latitude' => 14.1417,
            'longitude' => 121.3167,
            'status' => 'active',
        ]);

        for ($i = 20; $i >= 0; $i--) {
            SensorReading::create([
                'station_id' => $station->id,
                'water_level_cm' => rand(15, 65),
                'flow_rate' => rand(5, 30) / 10,
                'battery_voltage' => 12.4,
                'recorded_at' => Carbon::now()->subHours($i),
            ]);
        }
    }
}