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
            $reading = $station->sensorReadings->first();
            $station->setAttribute('drainage_condition', DrainageStatusEvaluator::evaluate(
                $reading?->water_level_cm,
                $reading?->flow_rate,
                $station->warning_threshold_cm ?? 60,
            ));
        });

        return response()->json([
            'status' => 'success',
            'stations' => $stations
        ]);
    }
}
