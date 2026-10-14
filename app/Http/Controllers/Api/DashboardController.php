<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Station;
use App\Models\Alert;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $stations = Station::with(['sensorReadings' => function ($query) {
            $query->latest('recorded_at')->limit(1);
        }])->get();

        return response()->json([
            'status' => 'success',
            'stations' => $stations
        ]);
    }
}