<?php

namespace Database\Seeders;

use App\Models\Station;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DrainageSeeder extends Seeder
{
    public function run(): void
    {
        // Define 3 distinct stations along actual water channels in Calauan, Laguna
        $stationsData = [
            [
                'code' => 'CAL-DRN-01',
                'name' => 'Main Municipal Channel, Calauan, Laguna',
                'lat' => 14.1488,  // Calauan River channel near Poblacion
                'lng' => 121.3145,
                'status' => 'active',
                'scenario' => 'normal'
            ],
            [
                'code' => 'CAL-DRN-02',
                'name' => 'Sector Drainage Node 2, Calauan, Laguna',
                'lat' => 14.1436,  // Prinza waterway canal junction
                'lng' => 121.3192,
                'status' => 'active',
                'scenario' => 'heavy_rain'
            ],
            [
                'code' => 'CAL-DRN-03',
                'name' => 'Sector Drainage Node 3, Calauan, Laguna',
                'lat' => 14.1382,  // Southern tributary stream channel
                'lng' => 121.3128,
                'status' => 'active',
                'scenario' => 'blockage'
            ],
        ];

        foreach ($stationsData as $data) {
            $station = Station::firstOrNew([
                'station_code' => $data['code']
            ]);

            $station->fill([
                'station_code' => $data['code'],
                'location_name' => $data['name'],
                'latitude' => $data['lat'],
                'longitude' => $data['lng'],
                'status' => $data['status'],
                'warning_threshold_cm' => 60,
            ]);
            $station->save();

            // Seed sample history only when the station has no readings
            if (! $station->sensorReadings()->exists()) {
                $this->seedScenarioReadings($station, $data['scenario']);
            }
        }
    }

    private function seedScenarioReadings(Station $station, string $scenario): void
    {
        $now = Carbon::now();

        for ($hoursAgo = 20; $hoursAgo >= 0; $hoursAgo--) {
            if ($scenario === 'normal') {
                $waterLevel = random_int(20, 45);
                $flowRate = random_int(15, 30) / 10;
            } elseif ($scenario === 'heavy_rain') {
                $waterLevel = random_int(65, 80);
                $flowRate = random_int(35, 55) / 10;
            } else {
                $waterLevel = random_int(82, 95); 
                $flowRate = random_int(1, 4) / 10;
            }

            if ($hoursAgo <= 2) {
                $waterLevel += random_int(-2, 2);
            }

            $station->sensorReadings()->create([
                'water_level_cm' => max(10, $waterLevel),
                'flow_rate' => max(0.1, $flowRate),
                'battery_voltage' => 12.4,
                'recorded_at' => $now->copy()->subHours($hoursAgo),
            ]);
        }
    }
}