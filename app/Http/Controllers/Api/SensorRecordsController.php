<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SensorReading;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Support\DrainageStatusEvaluator;

class SensorRecordsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate(['station_id' => ['sometimes', 'integer', 'exists:stations,id']]);
        $query = SensorReading::with('station:id,station_code,location_name,warning_threshold_cm');

        if (!empty($validated['station_id'])) {
            $query->where('station_id', $validated['station_id']);
        }

        $records = $query->latest('recorded_at')->limit(100)->get();
        $records->each(function (SensorReading $record) {
            $record->setAttribute('drainage_condition', DrainageStatusEvaluator::evaluate(
                $record->water_level_cm,
                $record->flow_rate,
                $record->station?->warning_threshold_cm ?? 60,
            ));
        });

        return response()->json([
            'status' => 'success',
            'records' => $records,
        ]);
    }
}
