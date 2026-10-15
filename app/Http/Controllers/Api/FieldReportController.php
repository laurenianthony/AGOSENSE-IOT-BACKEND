<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FieldReport;
use App\Http\Requests\StoreFieldReportRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class FieldReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (! $this->reportStorageReady()) {
            return response()->json([
                'status' => 'unavailable',
                'message' => 'Field report storage is not initialized. Apply the pending Laravel migrations.',
                'reports' => [],
            ], 503);
        }

        $query = FieldReport::with(['user:id,name', 'station:id,station_code,location_name'])->latest();

        if ($request->user()->role !== 'admin') {
            $query->where('user_id', $request->user()->id);
        }

        return response()->json([
            'status' => 'success',
            'reports' => $query->limit(100)->get(),
        ]);
    }

    public function store(StoreFieldReportRequest $request): JsonResponse
    {
        if (! $this->reportStorageReady()) {
            return response()->json([
                'status' => 'unavailable',
                'message' => 'Field report storage is not initialized. Apply the pending Laravel migrations.',
            ], 503);
        }

        $validated = $request->validated();

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

    public function updateStatus(Request $request, int|string $fieldReportId): JsonResponse
    {
        if (! $this->reportStorageReady()) {
            return response()->json([
                'status' => 'unavailable',
                'message' => 'Field report storage is not initialized. Apply the pending Laravel migrations.',
            ], 503);
        }

        $fieldReport = FieldReport::findOrFail($fieldReportId);

        $validated = $request->validate([
            'status' => ['required', 'in:pending,in_progress,resolved'],
        ]);

        $fieldReport->update($validated);

        return response()->json([
            'message' => 'Field report status updated.',
            'report' => $fieldReport->fresh()->load(['user:id,name', 'station:id,station_code,location_name']),
        ]);
    }

    private function reportStorageReady(): bool
    {
        return Schema::hasTable('field_reports')
            && Schema::hasColumns('field_reports', [
                'id', 'user_id', 'station_id', 'subject', 'message', 'status', 'created_at', 'updated_at',
            ]);
    }
}
