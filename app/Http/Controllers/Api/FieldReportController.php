<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FieldReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FieldReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = FieldReport::with(['user:id,name', 'station:id,station_code,location_name'])->latest();

        if ($request->user()->role !== 'admin') {
            $query->where('user_id', $request->user()->id);
        }

        return response()->json([
            'status' => 'success',
            'reports' => $query->limit(100)->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:160'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'station_id' => ['required', 'integer', 'exists:stations,id'],
        ]);

        $report = FieldReport::create([
            ...$validated,
            'user_id' => $request->user()->id,
        ]);

        $report->load(['user:id,name', 'station:id,station_code,location_name']);

        return response()->json([
            'message' => 'Field report submitted successfully.',
            'report' => $report,
        ], 201);
    }

    public function updateStatus(Request $request, FieldReport $fieldReport): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:pending,in_progress,resolved'],
        ]);

        $fieldReport->update($validated);

        return response()->json([
            'message' => 'Field report status updated.',
            'report' => $fieldReport->fresh()->load(['user:id,name', 'station:id,station_code,location_name']),
        ]);
    }
}
