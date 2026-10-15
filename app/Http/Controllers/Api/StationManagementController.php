<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Station;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class StationManagementController extends Controller
{
    public function index(): JsonResponse
    {
        $stations = Station::with(['sensorReadings' => fn ($query) => $query->latest('recorded_at')->limit(1)])
            ->orderBy('station_code')
            ->get();

        return response()->json(['status' => 'success', 'stations' => $stations]);
    }

    public function destroy(Station $station): JsonResponse
    {
        $stationCode = $station->station_code;
        DB::transaction(function () use ($station) {
            $station->fieldReports()->delete();
            // Sensor readings and alerts are removed by their station foreign keys.
            $station->delete();
        });

        return response()->json(['message' => "Station {$stationCode} and its readings, alerts, and linked field reports were deleted."]);
    }

    public function store(Request $request): JsonResponse
    {
        $station = Station::create($this->validatedStation($request));

        return response()->json([
            'message' => 'Station added successfully.',
            'station' => $station->load(['sensorReadings' => fn ($query) => $query->latest('recorded_at')->limit(1)]),
        ], 201);
    }

    public function update(Request $request, Station $station): JsonResponse
    {
        $station->update($this->validatedStation($request, $station));

        return response()->json([
            'message' => 'Station updated successfully.',
            'station' => $station->fresh()->load(['sensorReadings' => fn ($query) => $query->latest('recorded_at')->limit(1)]),
        ]);
    }

    public function updateThreshold(Request $request, Station $station): JsonResponse
    {
        $validated = $request->validate([
            'warning_threshold_cm' => ['required', 'numeric', 'min:1', 'max:10000'],
        ]);

        $station->update(['warning_threshold_cm' => $validated['warning_threshold_cm']]);

        return response()->json([
            'message' => "Alert threshold for {$station->station_code} was updated.",
            'station' => $station->fresh(),
        ]);
    }

    private function validatedStation(Request $request, ?Station $station = null): array
    {
        $required = $station ? 'sometimes' : 'required';

        return $request->validate([
            'station_code' => [$required, 'string', 'max:32', Rule::unique('stations', 'station_code')->ignore($station?->id)],
            'location_name' => [$required, 'string', 'max:255'],
            'latitude' => [$required, 'numeric', 'between:-90,90'],
            'longitude' => [$required, 'numeric', 'between:-180,180'],
            'status' => [$required, Rule::in(['active', 'maintenance', 'offline'])],
            'warning_threshold_cm' => [$required, 'numeric', 'min:1', 'max:10000'],
        ]);
    }
}
