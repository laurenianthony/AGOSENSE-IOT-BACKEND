<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SensorRecordsController extends Controller
{
    private const PH_TIME_ZONE = 'Asia/Manila';

    public function index(Request $request): JsonResponse
    {
        $filters = $this->validatedFilters($request);
        $perPage = (int) ($filters['per_page'] ?? 25);
        $page = (int) ($filters['page'] ?? 1);
        if (! DB::getSchemaBuilder()->hasTable('daily_sensor_summaries')) {
            return response()->json([
                'message' => 'Daily summaries are not initialized. Run the database migrations and the sensors:aggregate --scope=daily command.',
            ], 503);
        }

        $query = DB::table('daily_sensor_summaries as summary')
            ->join('stations', 'stations.id', '=', 'summary.station_id')
            ->select('summary.*', 'stations.station_code', 'stations.location_name', 'stations.warning_threshold_cm');

        $this->applySummaryFilters($query, $filters);
        $paginator = $query->orderByDesc('bucket_start')->orderByDesc('summary.id')->paginate($perPage, ['*'], 'page', $page);
        $items = collect($paginator->items())->map(function (object $row): array {
            $blocked = (int) $row->blockage_count > 0;
            $anomaly = (int) $row->anomaly_count > 0;
            $condition = $blocked
                ? ['code' => 'blockage_detected', 'label' => 'Blockage Detected']
                : ($anomaly
                    ? ['code' => 'elevated_water', 'label' => 'Elevated Water']
                    : ['code' => 'normal', 'label' => 'Clear / Safe']);

            return [
                'id' => $row->id,
                'station_id' => $row->station_id,
                'station_code' => $row->station_code,
                'recorded_at' => $row->bucket_start,
                'water_level_cm' => $row->water_avg_cm,
                'flow_rate' => $row->flow_avg_lpm,
                'water_min_cm' => $row->water_min_cm,
                'water_max_cm' => $row->water_max_cm,
                'flow_min_lpm' => $row->flow_min_lpm,
                'flow_max_lpm' => $row->flow_max_lpm,
                'readings_count' => (int) $row->readings_count,
                'anomaly_count' => (int) $row->anomaly_count,
                'blockage_count' => (int) $row->blockage_count,
                'period' => 'daily',
                'station' => [
                    'id' => $row->station_id,
                    'station_code' => $row->station_code,
                    'location_name' => $row->location_name,
                    'warning_threshold_cm' => $row->warning_threshold_cm,
                ],
                'drainage_condition' => $condition,
            ];
        })->all();

        return response()->json([
            'status' => 'success',
            'records' => $items,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse|JsonResponse
    {
        $filters = $this->validatedFilters($request, true);
        if (! DB::getSchemaBuilder()->hasTable('daily_sensor_summaries')) {
            return response()->json([
                'message' => 'Daily summaries are not initialized. Run the database migrations and the sensors:aggregate --scope=daily command.',
            ], 503);
        }
        $format = $filters['format'];
        $query = $this->exportQuery($filters);
        $filename = 'agosense-' . $filters['period'] . '-records-' . now()->format('Ymd-His');

        if ($format === 'csv') {
            return response()->streamDownload(function () use ($query): void {
                $output = fopen('php://output', 'wb');
                fwrite($output, "\xEF\xBB\xBF");
                fputcsv($output, ['Date and time (PST, UTC+8)', 'Station', 'Water level (cm)', 'Flow rate (L/min)', 'Status']);

                $query->orderBy('recorded_at')->orderBy('row_id')->chunk(500, function ($rows) use ($output): void {
                    foreach ($rows as $row) {
                        fputcsv($output, [
                            $this->formatExportTime($row->recorded_at),
                            $row->station_code,
                            $row->water_level_cm,
                            $row->flow_rate,
                            $row->status,
                        ]);
                    }
                });

                fclose($output);
            }, "{$filename}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        return response()->streamDownload(function () use ($query): void {
            $this->streamPdf($query);
        }, "{$filename}.pdf", ['Content-Type' => 'application/pdf']);
    }

    private function validatedFilters(Request $request, bool $includeFormat = false): array
    {
        $rules = [
            'station_id' => ['sometimes', 'nullable', 'integer', 'exists:stations,id'],
            'date_from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'date_to' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'period' => ['sometimes', 'in:daily'],
            'anomalies' => ['sometimes', 'boolean'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];

        if ($includeFormat) {
            $rules['format'] = ['required', 'in:csv,pdf'];
        }

        $validated = $request->validate($rules);
        $validated['period'] = 'daily';

        return $validated;
    }

    private function applySummaryFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['station_id'])) {
            $query->where('summary.station_id', $filters['station_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->where('summary.bucket_start', '>=', $this->localDateStart($filters['date_from']));
        }

        if (! empty($filters['date_to'])) {
            $query->where('summary.bucket_start', '<=', $this->localDateEnd($filters['date_to']));
        }

        if (filter_var($filters['anomalies'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $query->where('summary.anomaly_count', '>', 0);
        }
    }

    private function localDateStart(string $date): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d', $date, self::PH_TIME_ZONE)->startOfDay()->utc();
    }

    private function localDateEnd(string $date): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d', $date, self::PH_TIME_ZONE)->endOfDay()->utc();
    }

    private function exportQuery(array $filters): Builder
    {
        $query = DB::table('daily_sensor_summaries as summary')
            ->join('stations', 'stations.id', '=', 'summary.station_id')
            ->select('summary.id as row_id', 'summary.bucket_start as recorded_at', 'stations.station_code', 'summary.water_avg_cm as water_level_cm', 'summary.flow_avg_lpm as flow_rate')
            ->selectRaw("CASE WHEN summary.blockage_count > 0 THEN 'Blockage Detected' WHEN summary.anomaly_count > 0 THEN 'Elevated Water' ELSE 'Clear / Safe' END as status");

        if (filter_var($filters['anomalies'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $query->where('summary.anomaly_count', '>', 0);
        }

        $this->applyExportCommonFilters($query, $filters, 'summary.station_id', 'summary.bucket_start');

        return $query;
    }

    private function applyExportCommonFilters(Builder $query, array $filters, string $stationColumn, string $dateColumn): void
    {
        if (! empty($filters['station_id'])) {
            $query->where($stationColumn, $filters['station_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->where($dateColumn, '>=', $this->localDateStart($filters['date_from']));
        }

        if (! empty($filters['date_to'])) {
            $query->where($dateColumn, '<=', $this->localDateEnd($filters['date_to']));
        }
    }

    private function formatExportTime(string $value): string
    {
        return CarbonImmutable::parse($value, 'UTC')->setTimezone(self::PH_TIME_ZONE)->format('Y-m-d H:i:s') . ' PST';
    }

    private function streamPdf(Builder $query): void
    {
        $recordsPerPage = 40;
        $recordCount = (clone $query)->count();
        $pageCount = max(1, (int) ceil($recordCount / $recordsPerPage));
        $fontObjectId = 3 + ($pageCount * 2);
        $temporary = fopen('php://temp/maxmemory:2097152', 'w+b');
        fwrite($temporary, "%PDF-1.4\n%AGOSENSE\n");
        $offsets = [];

        $writeObject = static function (int $id, string $body) use ($temporary, &$offsets): void {
            $offsets[$id] = ftell($temporary);
            fwrite($temporary, "{$id} 0 obj\n{$body}\nendobj\n");
        };

        $writeObject(1, '<< /Type /Catalog /Pages 2 0 R >>');
        $kids = implode(' ', array_map(static fn (int $page) => (3 + ($page * 2)) . ' 0 R', range(0, $pageCount - 1)));
        $writeObject(2, "<< /Type /Pages /Kids [{$kids}] /Count {$pageCount} >>");

        $pageNumber = 0;
        $pageLines = ['AGOSENSE - Historical Sensor Records', 'Date/time shown in Philippine Standard Time (UTC+8)', 'DATE / TIME (PST) | STATION | WATER CM | FLOW L/MIN | STATUS'];
        $recordsOnPage = 0;
        $writePage = function (array $lines) use (&$pageNumber, $fontObjectId, $writeObject): void {
            $pageId = 3 + ($pageNumber * 2);
            $contentId = $pageId + 1;
            $commands = "BT\n/F1 8 Tf\n40 754 Td\n";

            foreach ($lines as $index => $line) {
                if ($index > 0) {
                    $commands .= "0 -16 Td\n";
                }

                $line = substr($this->pdfText($line), 0, 130);
                $commands .= '(' . str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line) . ") Tj\n";
            }

            $commands .= "ET\n";
            $writeObject($pageId, "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 {$fontObjectId} 0 R >> >> /Contents {$contentId} 0 R >>");
            $writeObject($contentId, "<< /Length " . strlen($commands) . " >>\nstream\n{$commands}endstream");
            $pageNumber++;
        };

        foreach ($query->orderBy('recorded_at')->orderBy('row_id')->cursor() as $row) {
            $pageLines[] = sprintf('%s | %s | %s | %s | %s',
                $this->formatExportTime($row->recorded_at),
                $row->station_code,
                $row->water_level_cm,
                $row->flow_rate ?? '--',
                $row->status,
            );
            $recordsOnPage++;

            if ($recordsOnPage >= $recordsPerPage) {
                $writePage($pageLines);
                $pageLines = ['AGOSENSE - Historical Sensor Records', 'DATE / TIME (PST) | STATION | WATER CM | FLOW L/MIN | STATUS'];
                $recordsOnPage = 0;
            }
        }

        if ($recordsOnPage > 0 || $pageNumber === 0) {
            $writePage($pageLines);
        }

        $writeObject($fontObjectId, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>');
        $xrefOffset = ftell($temporary);
        $objectCount = $fontObjectId + 1;
        fwrite($temporary, "xref\n0 {$objectCount}\n0000000000 65535 f \n");

        for ($id = 1; $id < $objectCount; $id++) {
            fwrite($temporary, sprintf("%010d 00000 n \n", $offsets[$id] ?? 0));
        }

        fwrite($temporary, "trailer\n<< /Size {$objectCount} /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF");
        rewind($temporary);
        fpassthru($temporary);
        fclose($temporary);
    }

    private function pdfText(string $value): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

        return preg_replace('/[^\x20-\x7E]/', '', $ascii === false ? $value : $ascii) ?? '';
    }
}
