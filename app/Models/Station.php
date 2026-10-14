<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Station extends Model
{
    protected $fillable = [
        'station_code', 
        'location_name', 
        'latitude', 
        'longitude', 
        'status',
        'warning_threshold_cm',
    ];

    /**
     * A station has many sensor readings over time.
     */
    public function sensorReadings(): HasMany
    {
        return $this->hasMany(SensorReading::class);
    }

    /**
     * A station can have multiple alerts triggered.
     */
    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    public function fieldReports(): HasMany
    {
        return $this->hasMany(FieldReport::class);
    }
}
