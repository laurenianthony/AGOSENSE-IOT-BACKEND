<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Station;
use App\Models\SensorReading;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class SensorDataController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'station_code' => 'required|exists:stations,station_code',
            'water_level_cm' => 'nullable|numeric|min:0',
            'flow_rate_lpm' => 'sometimes|nullable|numeric|min:0',
            'flow_rate' => 'sometimes|nullable|numeric|min:0',
            'battery_voltage' => 'nullable|numeric|min:0',
        ]);

        $station = Station::where('station_code', $validated['station_code'])->firstOrFail();
        $waterLevelCm = $validated['water_level_cm'];
        $previous = $station->sensorReadings()->latest('recorded_at')->first();
        $now = now();

        // A lone near-zero echo immediately after a healthy reading is usually
        // a sensor glitch. Store it as null; accept a following near-zero as
        // confirmation when the preceding sample was that null candidate.
        if ($waterLevelCm !== null && (float) $waterLevelCm <= 0.5 && $previous) {
            $previousAt = $previous->recorded_at ? Carbon::parse($previous->recorded_at)->utc() : null;
            $withinHeartbeatWindow = $previousAt && $previousAt->diffInSeconds($now->copy()->utc()) <= 30;
            $previousWasZeroCandidate = $previous->water_level_cm === null
                && $previous->created_at
                && Carbon::parse($previous->created_at)->utc()->diffInSeconds($now->copy()->utc()) <= 30;

            if ($withinHeartbeatWindow && (float) $previous->water_level_cm > 5.0) {
                $waterLevelCm = null;
            } elseif ($withinHeartbeatWindow && $previousWasZeroCandidate) {
                $waterLevelCm = (float) $waterLevelCm;
            }
        }

        $reading = $station->sensorReadings()->create([
            'water_level_cm' => $waterLevelCm,
            'flow_rate' => $validated['flow_rate_lpm'] ?? $validated['flow_rate'] ?? null,
            'battery_voltage' => $validated['battery_voltage'] ?? null,
            'recorded_at' => $now->utc(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Telemetry logged successfully',
            'data' => $reading
        ], 201);
    }
}
