<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Station;
use Illuminate\Http\Request;

class SensorDataController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'station_code' => 'required|exists:stations,station_code',
            'water_level_cm' => 'required|numeric',
            'flow_rate' => 'required|numeric',
            'battery_voltage' => 'nullable|numeric',
        ]);

        $station = Station::where('station_code', $validated['station_code'])->firstOrFail();

        $reading = $station->sensorReadings()->create([
            'water_level_cm' => $validated['water_level_cm'],
            'flow_rate' => $validated['flow_rate'],
            'battery_voltage' => $validated['battery_voltage'] ?? 12.4,
            'recorded_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Telemetry logged successfully',
            'data' => $reading
        ], 201);
    }
}