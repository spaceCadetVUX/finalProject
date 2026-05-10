<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'description', 'manager_id',
        'check_in_time', 'check_out_time', 'late_tolerance',
    ];

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function employees()
    {
        return $this->hasMany(User::class);
    }

    public function shiftSchedules()
    {
        return $this->hasMany(ShiftSchedule::class, 'assignee_id')
                    ->where('assignee_type', 'department');
    }
}
