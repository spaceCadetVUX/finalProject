<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShiftSchedule extends Model
{
    protected $fillable = [
        'shift_template_id', 'assignee_type', 'assignee_id',
        'days_of_week', 'start_date', 'end_date',
        'is_active', 'note', 'created_by',
    ];

    protected $casts = [
        'days_of_week' => 'array',
        'start_date'   => 'date',
        'end_date'     => 'date',
        'is_active'    => 'boolean',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function template()
    {
        return $this->belongsTo(ShiftTemplate::class, 'shift_template_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    // Khi assignee_type = 'employee'
    public function employee()
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    // Khi assignee_type = 'department'
    public function department()
    {
        return $this->belongsTo(Department::class, 'assignee_id');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Trả về tên assignee (tên nhân viên hoặc tên phòng ban). */
    public function assigneeLabel(): string
    {
        if ($this->assignee_type === 'employee') {
            return $this->employee?->name ?? "User #{$this->assignee_id}";
        }
        return $this->department?->name ?? "Dept #{$this->assignee_id}";
    }

    /**
     * Kiểm tra lịch này có áp dụng cho một ngày cụ thể không.
     * $date: Carbon hoặc string Y-m-d
     */
    public function appliesToDate(mixed $date): bool
    {
        $carbon = \Carbon\Carbon::parse($date);

        if (!$this->is_active) {
            return false;
        }
        if ($carbon->lt($this->start_date)) {
            return false;
        }
        if ($this->end_date && $carbon->gt($this->end_date)) {
            return false;
        }
        // ISO-8601: 1=Mon … 7=Sun
        return in_array($carbon->isoWeekday(), $this->days_of_week ?? []);
    }
}
