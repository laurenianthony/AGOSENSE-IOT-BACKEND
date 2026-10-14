<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SensorReading extends Model
{
    protected $fillable = [
        'station_id',
        'water_level_cm',
        'flow_rate',
        'battery_voltage',
        'recorded_at',
    ];

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }
}
