<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    protected $fillable = ['name', 'location', 'token', 'last_ping', 'status'];

    protected $casts = [
        'last_ping' => 'datetime',
    ];

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }
}
