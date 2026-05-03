<?php

namespace App\Services;

use App\Models\User;

class AttendanceStatusService
{
    /**
     * Tính trạng thái check-in: 'present' | 'late'
     *
     * So sánh giờ check-in với department.check_in_time + late_tolerance (phút).
     * Nhân viên không thuộc phòng ban nào luôn được tính là present.
     */
    public function calculateStatus(User $user, string $recordedAt): string
    {
        $dept = $user->department;

        if (!$dept) {
            return 'present';
        }

        $date         = date('Y-m-d', strtotime($recordedAt));
        $deadline     = strtotime("{$date} {$dept->check_in_time}") + ($dept->late_tolerance * 60);

        return strtotime($recordedAt) > $deadline ? 'late' : 'present';
    }

    /**
     * Tính trạng thái check-out: 'present' | 'early_leave'
     *
     * So sánh giờ check-out với department.check_out_time.
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
