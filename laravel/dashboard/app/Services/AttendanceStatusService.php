<?php

namespace App\Services;

use App\Models\ShiftSchedule;
use App\Models\User;

class AttendanceStatusService
{
    /**
     * Tính trạng thái check-in: 'present' | 'late'
     *
     * Ưu tiên giờ của ca (shift) nếu có, fallback về phòng ban.
     * Không có ca và không thuộc phòng ban → luôn 'present'.
     */
    public function calculateStatus(User $user, string $recordedAt, ?ShiftSchedule $shift = null): string
    {
        $date = date('Y-m-d', strtotime($recordedAt));

        if ($shift) {
            $deadline = strtotime("{$date} {$shift->template->check_in_time}")
                      + ($shift->template->late_tolerance * 60);
            return strtotime($recordedAt) > $deadline ? 'late' : 'present';
        }

        $dept = $user->department;
        if (!$dept) {
            return 'present';
        }

        $deadline = strtotime("{$date} {$dept->check_in_time}") + ($dept->late_tolerance * 60);
        return strtotime($recordedAt) > $deadline ? 'late' : 'present';
    }

    /**
     * Tính trạng thái check-out: 'present' | 'early_leave'
     *
     * Ưu tiên giờ của ca (shift) nếu có, fallback về phòng ban.
     */
    public function calculateCheckOutStatus(User $user, string $recordedAt, ?ShiftSchedule $shift = null): string
    {
        $date = date('Y-m-d', strtotime($recordedAt));

        if ($shift) {
            $checkoutTime = strtotime("{$date} {$shift->template->check_out_time}");
            return strtotime($recordedAt) < $checkoutTime ? 'early_leave' : 'present';
        }

        $dept = $user->department;
        if (!$dept) {
            return 'present';
        }

        $checkoutTime = strtotime("{$date} {$dept->check_out_time}");
        return strtotime($recordedAt) < $checkoutTime ? 'early_leave' : 'present';
    }
}
