<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    protected $fillable = [
        'user_id', 'device_id', 'work_date',
        'check_in_at', 'check_in_confidence', 'check_in_image',
        'check_out_at', 'check_out_confidence', 'check_out_image',
        'status', 'note',
    ];

    protected $casts = [
        'work_date'    => 'date',
        'check_in_at'  => 'datetime',
        'check_out_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function device()
    {
        return $this->belongsTo(Device::class);
    }
}
