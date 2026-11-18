<?php

namespace App\Support;

final class DrainageStatusEvaluator
{
    /**
     * Classify drainage readings using the station warning threshold and flow.
     */
    public static function evaluate(mixed $waterLevelCm, mixed $flowRateLpm, mixed $warningThresholdCm = 60, bool $isOnline = true): array
    {
        if (!$isOnline || $waterLevelCm === null || $waterLevelCm === '' || !is_numeric($waterLevelCm)) {
            return [
                'code' => 'awaiting_data',
                'label' => 'Awaiting data',
                'status_code' => 'awaiting_data',
                'status_label' => 'Awaiting data',
                'blockage_status' => 'Awaiting data',
                'blockage_detected' => false,
                'severity' => 'offline',
                'badge' => 'OFFLINE',
                'flow_rate_lpm' => null,
            ];
        }

        $waterLevelCm = (float) $waterLevelCm;
        if (!is_finite($waterLevelCm)) {
            return [
                'code' => 'awaiting_data',
                'label' => 'Awaiting data',
                'status_code' => 'awaiting_data',
                'status_label' => 'Awaiting data',
                'blockage_status' => 'Awaiting data',
                'blockage_detected' => false,
                'severity' => 'offline',
                'badge' => 'OFFLINE',
                'flow_rate_lpm' => null,
            ];
        }

        $flowRateLpm = $flowRateLpm === null || $flowRateLpm === '' || !is_numeric($flowRateLpm)
            ? null
            : (float) $flowRateLpm;
        if ($flowRateLpm !== null && !is_finite($flowRateLpm)) {
            $flowRateLpm = null;
        }

        $warningThresholdCm = $warningThresholdCm === null || !is_numeric($warningThresholdCm)
            ? 60.0
            : (float) $warningThresholdCm;
        if (!is_finite($warningThresholdCm)) {
            $warningThresholdCm = 60.0;
        }

        $lowFlow = ($flowRateLpm ?? 0) < 1.0;

        if ($waterLevelCm >= $warningThresholdCm && $lowFlow) {
            return [
                'code' => 'blockage_detected',
                'label' => 'Blockage Detected',
                'status_code' => 'blockage_detected',
                'status_label' => 'Blockage Detected',
                'blockage_status' => 'Blockage Detected',
                'blockage_detected' => true,
                'severity' => 'critical',
                'badge' => 'CRITICAL',
                'flow_rate_lpm' => $flowRateLpm,
            ];
        }

        if ($waterLevelCm > 5.0 && $lowFlow) {
            return [
                'code' => 'stagnant_low_flow',
                'label' => 'Stagnant / Low Flow',
                'status_code' => 'stagnant_low_flow',
                'status_label' => 'Stagnant / Low Flow',
                'blockage_status' => 'Stagnant / Low Flow',
                'blockage_detected' => false,
                'severity' => 'warning',
                'badge' => 'WARNING',
                'flow_rate_lpm' => $flowRateLpm,
            ];
        }

        return [
            'code' => 'normal',
            'label' => 'Clear / Safe',
            'status_code' => 'normal',
            'status_label' => 'Clear / Safe',
            'blockage_status' => 'Clear / Safe',
            'blockage_detected' => false,
            'severity' => 'safe',
            'badge' => 'SAFE',
            'flow_rate_lpm' => $flowRateLpm,
        ];
    }
}