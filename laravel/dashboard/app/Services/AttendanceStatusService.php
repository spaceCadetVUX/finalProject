<?php

namespace App\Services;

use App\Models\User;

class AttendanceStatusService
{
    /**
     * Tính trạng thái check-in: 'present' | 'late'
     */
    public function calculateCheckInStatus(User $user, string $recordedAt): string
    {
        $dept = $user->department;

        if (!$dept) {
            return 'present';
        }

        $date         = date('Y-m-d', strtotime($recordedAt));
        $allowedUntil = strtotime("{$date} {$dept->check_in_time}") + ($dept->late_tolerance * 60);

        return strtotime($recordedAt) > $allowedUntil ? 'late' : 'present';
    }

    /**
     * Kiểm tra check-out có về sớm không: 'present' | 'early_leave'
     */
    public function calculateCheckOutStatus(User $user, string $recordedAt): string
    {
        $dept = $user->department;

        if (!$dept) {
            return 'present';
        }

        $date         = date('Y-m-d', strtotime($recordedAt));
        $checkoutTime = strtotime("{$date} {$dept->check_out_time}");

        return strtotime($recordedAt) < $checkoutTime ? 'early_leave' : 'present';
    }
}
