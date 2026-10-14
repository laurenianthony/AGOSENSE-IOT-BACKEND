<?php

namespace Database\Seeders;

use App\Models\Station;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DrainageSeeder extends Seeder
{
    public function run(): void
    {
        // Define 3 distinct stations with different operational scenarios for defense day
        $stationsData = [
            [
                'code' => 'CAL-DRN-01',
                'name' => 'Main Municipal Channel, Calauan, Laguna',
                'status' => 'active',
                'scenario' => 'normal' // Low water, steady flow
            ],
            [
                'code' => 'CAL-DRN-02',
                'name' => 'Sector Drainage Node 2, Calauan, Laguna',
                'status' => 'active',
                'scenario' => 'heavy_rain' // High water, high flow (Safe/Elevated)
            ],
            [
                'code' => 'CAL-DRN-03',
                'name' => 'Sector Drainage Node 3, Calauan, Laguna',
                'status' => 'active',
                'scenario' => 'blockage' // High water, near-zero flow (Critical Blockage)
            ],
        ];

        foreach ($stationsData as $data) {
            $station = Station::updateOrCreate(
                ['station_code' => $data['code']],
                [
                    'location_name' => $data['name'],
                    'latitude' => 14.1417 + (intval(substr($data['code'], -2)) * 0.001),
                    'longitude' => 121.3167 + (intval(substr($data['code'], -2)) * 0.001),
                    'status' => $data['status'],
                    'warning_threshold_cm' => 60,
                ]
            );

            // Clear old readings and seed specific scenario data
            $station->sensorReadings()->delete();
            $this->seedScenarioReadings($station, $data['scenario']);
        }
    }

    private function seedScenarioReadings(Station $station, string $scenario): void
    {
        $now = Carbon::now();

        for ($hoursAgo = 20; $hoursAgo >= 0; $hoursAgo--) {
            if ($scenario === 'normal') {
                // Scenario 1: Normal operating conditions
                $waterLevel = random_int(20, 45); // 20cm - 45cm (Below 60cm threshold)
                $flowRate = random_int(15, 30) / 10; // 1.5 - 3.0 L/min
            } elseif ($scenario === 'heavy_rain') {
                // Scenario 2: Heavy rainfall, rushing water (High volume, but flowing smoothly)
                $waterLevel = random_int(65, 80); // Above 60cm threshold
                $flowRate = random_int(35, 55) / 10; // 3.5 - 5.5 L/min (High flow rate)
            } else {
                // Scenario 3: Critical blockage / trash clog (High water backing up, flow stops)
                $waterLevel = random_int(82, 95); // High water level
                $flowRate = random_int(1, 4) / 10; // 0.1 - 0.4 L/min (Near-zero flow)
            }

            // Add slight natural fluctuation to recent hours
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