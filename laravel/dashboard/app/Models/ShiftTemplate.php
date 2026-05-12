<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShiftTemplate extends Model
{
    protected $fillable = [
        'name', 'check_in_time', 'check_out_time',
        'late_tolerance', 'checkin_before', 'checkin_after',
        'color', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function schedules()
    {
        return $this->hasMany(ShiftSchedule::class);
    }

    /** Thời lượng ca làm (phút). */
    public function durationMinutes(): int
    {
        $in  = strtotime($this->check_in_time);
        $out = strtotime($this->check_out_time);
        return (int) (($out - $in) / 60);
    }
}
