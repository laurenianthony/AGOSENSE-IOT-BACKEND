<?php

namespace App\Support;

final class DrainageStatusEvaluator
{
    /**
     * Sensor flow_rate is stored in L/min and is compared directly to the
     * dashboard's blockage rule.
     */
    public static function evaluate(mixed $waterLevelCm, mixed $flowRateLpm, mixed $thresholdCm = 60): array
    {
        if ($waterLevelCm === null) {
            return ['code' => 'unknown', 'label' => 'Awaiting data', 'flow_rate_lpm' => null];
        }

        $waterLevelCm = (float) $waterLevelCm;
        $thresholdCm = $thresholdCm === null ? 60 : (float) $thresholdCm;

        if ($waterLevelCm < $thresholdCm) {
            return ['code' => 'normal', 'label' => 'Clear / Safe', 'flow_rate_lpm' => $flowRateLpm === null ? null : (float) $flowRateLpm];
        }

        if ($flowRateLpm === null) {
            return ['code' => 'unknown', 'label' => 'Awaiting flow data', 'flow_rate_lpm' => null];
        }

        $flow = (float) $flowRateLpm;

        if ($waterLevelCm >= $thresholdCm && $flow < 1.0) {
            return ['code' => 'blockage_detected', 'label' => 'Blockage Detected', 'flow_rate_lpm' => $flow];
        }

        if ($waterLevelCm >= $thresholdCm && $flow >= 1.0) {
            return ['code' => 'elevated_water', 'label' => 'Elevated Water', 'flow_rate_lpm' => $flow];
        }

        return ['code' => 'normal', 'label' => 'Clear / Safe', 'flow_rate_lpm' => $flow];
    }
}
