<?php

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AggregateSensorData extends Command
{
    protected $signature = 'sensors:aggregate {--scope=daily : daily summaries only}';

    protected $description = 'Build compact daily sensor summaries for historical views.';

    public function handle(): int
    {
        $scope = $this->option('scope');

        if ($scope !== 'daily') {
            $this->error('Only daily summary aggregation is supported.');

            return self::FAILURE;
        }

        $now = CarbonImmutable::now();

        $lastDailyBucket = DB::table('daily_sensor_summaries')->max('bucket_start');
        $dailyAfter = $lastDailyBucket
            ? CarbonImmutable::parse($lastDailyBucket, 'UTC')->startOfDay()
            : null;
        // Include the current day so incoming readings appear without waiting until tomorrow.
        $this->aggregateDaily($now->addDay()->startOfDay(), $dailyAfter);

        $this->info('Sensor summaries are up to date.');

        return self::SUCCESS;
    }

    private function aggregateDaily(CarbonImmutable $before, ?CarbonImmutable $after = null): void
    {
        $summaryTable = 'daily_sensor_summaries';
        $bucketExpression = "date_trunc('day', sensor_readings.recorded_at)";
        $query = DB::table('sensor_readings')
            ->join('stations', 'stations.id', '=', 'sensor_readings.station_id')
            ->selectRaw("sensor_readings.station_id, {$bucketExpression} as bucket_start")
            ->selectRaw('MIN(sensor_readings.water_level_cm) as water_min_cm')
            ->selectRaw('MAX(sensor_readings.water_level_cm) as water_max_cm')
            ->selectRaw('AVG(sensor_readings.water_level_cm) as water_avg_cm')
            ->selectRaw('MIN(sensor_readings.flow_rate) as flow_min_lpm')
            ->selectRaw('MAX(sensor_readings.flow_rate) as flow_max_lpm')
            ->selectRaw('AVG(sensor_readings.flow_rate) as flow_avg_lpm')
            ->selectRaw('COUNT(*) as readings_count')
            ->selectRaw('SUM(CASE WHEN sensor_readings.water_level_cm >= COALESCE(stations.warning_threshold_cm, 60) THEN 1 ELSE 0 END) as anomaly_count')
            ->selectRaw('SUM(CASE WHEN sensor_readings.water_level_cm >= COALESCE(stations.warning_threshold_cm, 60) AND sensor_readings.flow_rate IS NOT NULL AND sensor_readings.flow_rate < 1.0 THEN 1 ELSE 0 END) as blockage_count')
            ->where('sensor_readings.recorded_at', '<', $before)
            ->groupBy('sensor_readings.station_id')
            ->groupByRaw($bucketExpression);

        if ($after) {
            $query->where('sensor_readings.recorded_at', '>=', $after);
        }

        $now = now();
        $query->orderBy('sensor_readings.station_id')->orderByRaw($bucketExpression)->chunk(500, function ($buckets) use ($summaryTable, $now): void {
            foreach ($buckets as $bucket) {
                DB::table($summaryTable)->updateOrInsert(
                    ['station_id' => $bucket->station_id, 'bucket_start' => $bucket->bucket_start],
                    [
                        'water_min_cm' => $bucket->water_min_cm,
                        'water_max_cm' => $bucket->water_max_cm,
                        'water_avg_cm' => $bucket->water_avg_cm,
                        'flow_min_lpm' => $bucket->flow_min_lpm,
                        'flow_max_lpm' => $bucket->flow_max_lpm,
                        'flow_avg_lpm' => $bucket->flow_avg_lpm,
                        'readings_count' => $bucket->readings_count,
                        'anomaly_count' => $bucket->anomaly_count,
                        'blockage_count' => $bucket->blockage_count,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                );
            }
        });

        $this->line("Aggregated {$summaryTable}.");
    }
}
