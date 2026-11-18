<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Station;
use Illuminate\Http\JsonResponse;
use App\Support\DrainageStatusEvaluator;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $stations = Station::with(['sensorReadings' => function ($query) {
            $query->latest('recorded_at')->limit(1);
        }])->orderBy('station_code')->orderBy('id')->get();

        $stations->each(function (Station $station) {
            $latestReading = $station->sensorReadings->first();
            $lastSyncedAt = $latestReading?->recorded_at;
            $isOnline = $latestReading !== null
                && $latestReading->recorded_at !== null
                && $latestReading->recorded_at->utc()->diffInSeconds(now()->utc()) <= 20;

            $station->setAttribute('is_online', $isOnline);
            $station->setAttribute('last_synced_at', $lastSyncedAt);
            if (!$isOnline) {
                $station->setRelation('sensorReadings', collect());
                $station->setAttribute('latest_reading', null);
                $station->setAttribute('live_water_level_cm', null);
                $station->setAttribute('live_flow_rate_lpm', null);
                $station->setAttribute('water_level_cm', null);
                $station->setAttribute('flow_rate_lpm', null);
            } else {
                $station->setAttribute('latest_reading', $latestReading);
                $station->setAttribute('live_water_level_cm', $latestReading->water_level_cm);
                $station->setAttribute('live_flow_rate_lpm', $latestReading->flow_rate);
                $station->setAttribute('water_level_cm', $latestReading->water_level_cm);
                $station->setAttribute('flow_rate_lpm', $latestReading->flow_rate);
            }
            $latestReading?->makeHidden('battery_voltage');
            $station->setAttribute('drainage_condition', DrainageStatusEvaluator::evaluate(
                $isOnline ? $latestReading?->water_level_cm : null,
                $isOnline ? $latestReading?->flow_rate : null,
                $station->warning_threshold_cm ?? 60,
                $isOnline,
            ));
        });

        return response()->json([
            'status' => 'success',
            'stations' => $stations
        ]);
    }
}
