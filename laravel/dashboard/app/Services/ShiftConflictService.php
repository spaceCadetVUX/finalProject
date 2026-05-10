<?php

namespace App\Services;

use App\Models\Department;
use App\Models\ShiftSchedule;
use App\Models\ShiftTemplate;
use App\Models\User;

class ShiftConflictService
{
    /**
     * Phát hiện conflict khi gán lịch ca mới.
     *
     * @param array $new {
     *   assignee_type: 'employee'|'department',
     *   assignee_id:   int,
     *   days_of_week:  int[],        // [1..7], ISO-8601
     *   start_date:    string,       // Y-m-d
     *   end_date:      string|null,
     *   template:      ShiftTemplate,
     *   exclude_id:    int|null,     // bỏ qua khi edit chính nó
     * }
     * @return array  Danh sách conflict, mỗi phần tử gồm:
     *   type, schedule, overlap_days, assignee_label
     */
    public function detect(array $new): array
    {
        $conflicts   = [];
        $newDays     = $new['days_of_week'];
        $newStart    = $new['start_date'];
        $newEnd      = $new['end_date'];
        $newIn       = substr($new['template']->check_in_time,  0, 5);
        $newOut      = substr($new['template']->check_out_time, 0, 5);
        $excludeId   = $new['exclude_id'] ?? null;

        if ($new['assignee_type'] === 'employee') {
            $conflicts = array_merge(
                $conflicts,
                $this->checkEmployee($new['assignee_id'], $newDays, $newStart, $newEnd, $newIn, $newOut, $excludeId)
            );
        } else {
            $conflicts = array_merge(
                $conflicts,
                $this->checkDepartment($new['assignee_id'], $newDays, $newStart, $newEnd, $newIn, $newOut, $excludeId)
            );
        }

        return $conflicts;
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function checkEmployee(
        int $userId,
        array $newDays,
        string $newStart,
        ?string $newEnd,
        string $newIn,
        string $newOut,
        ?int $excludeId
    ): array {
        $conflicts = [];
        $user      = User::with('department')->find($userId);
        if (!$user) {
            return [];
        }

        // 1. Ca gán trực tiếp cho nhân viên này
        $direct = $this->activeSchedules('employee', $userId, $excludeId);
        foreach ($direct as $s) {
            if ($overlap = $this->overlap($s, $newDays, $newStart, $newEnd, $newIn, $newOut)) {
                $conflicts[] = [
                    'type'           => 'employee_direct',
                    'schedule'       => $s,
                    'overlap_days'   => $overlap,
                    'assignee_label' => $user->name,
                ];
            }
        }

        // 2. Ca của phòng ban mà nhân viên đang thuộc
        if ($user->department_id) {
            $deptSchedules = $this->activeSchedules('department', $user->department_id, $excludeId);
            foreach ($deptSchedules as $s) {
                if ($overlap = $this->overlap($s, $newDays, $newStart, $newEnd, $newIn, $newOut)) {
                    $conflicts[] = [
                        'type'           => 'employee_via_department',
                        'schedule'       => $s,
                        'overlap_days'   => $overlap,
                        'assignee_label' => $user->department->name,
                    ];
                }
            }
        }

        return $conflicts;
    }

    private function checkDepartment(
        int $deptId,
        array $newDays,
        string $newStart,
        ?string $newEnd,
        string $newIn,
        string $newOut,
        ?int $excludeId
    ): array {
        $conflicts = [];
        $dept      = Department::find($deptId);
        if (!$dept) {
            return [];
        }

        // 1. Ca gán trực tiếp cho phòng ban này
        $direct = $this->activeSchedules('department', $deptId, $excludeId);
        foreach ($direct as $s) {
            if ($overlap = $this->overlap($s, $newDays, $newStart, $newEnd, $newIn, $newOut)) {
                $conflicts[] = [
                    'type'           => 'department_direct',
                    'schedule'       => $s,
                    'overlap_days'   => $overlap,
                    'assignee_label' => $dept->name,
                ];
            }
        }

        // 2. Ca gán trực tiếp cho từng nhân viên thuộc phòng ban này
        $empIds = User::where('department_id', $deptId)->whereNull('deleted_at')->pluck('id');
        if ($empIds->isEmpty()) {
            return $conflicts;
        }

        $empSchedules = ShiftSchedule::with(['template', 'employee'])
            ->where('assignee_type', 'employee')
            ->whereIn('assignee_id', $empIds)
            ->where('is_active', true)
            ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
            ->get();

        foreach ($empSchedules as $s) {
            if ($overlap = $this->overlap($s, $newDays, $newStart, $newEnd, $newIn, $newOut)) {
                $conflicts[] = [
                    'type'           => 'department_via_employee',
                    'schedule'       => $s,
                    'overlap_days'   => $overlap,
                    'assignee_label' => $s->employee?->name ?? "User #{$s->assignee_id}",
                ];
            }
        }

        return $conflicts;
    }

    /** Lấy tất cả lịch ca đang active cho 1 assignee. */
    private function activeSchedules(string $type, int $id, ?int $excludeId)
    {
        return ShiftSchedule::with('template')
            ->where('assignee_type', $type)
            ->where('assignee_id', $id)
            ->where('is_active', true)
            ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
            ->get();
    }

    /**
     * Kiểm tra một lịch ca existing có conflict với params mới không.
     * Trả về mảng ngày bị chồng nếu conflict, null nếu không.
     */
    private function overlap(
        ShiftSchedule $existing,
        array $newDays,
        string $newStart,
        ?string $newEnd,
        string $newIn,
        string $newOut
    ): ?array {
        // 1. Ngày trong tuần chồng nhau
        $overlapDays = array_values(array_intersect($newDays, $existing->days_of_week ?? []));
        if (empty($overlapDays)) {
            return null;
        }

        // 2. Khoảng ngày chồng nhau
        $existStart = $existing->start_date->format('Y-m-d');
        $existEnd   = $existing->end_date ? $existing->end_date->format('Y-m-d') : '9999-12-31';
        $newEndStr  = $newEnd ?? '9999-12-31';

        if ($newStart > $existEnd || $newEndStr < $existStart) {
            return null;
        }

        // 3. Giờ chồng nhau (so sánh chuỗi HH:MM, 24h luôn đúng theo thứ tự alphabet)
        $existIn  = substr($existing->template->check_in_time,  0, 5);
        $existOut = substr($existing->template->check_out_time, 0, 5);

        if ($newIn >= $existOut || $newOut <= $existIn) {
            return null;
        }

        return $overlapDays;
    }
}
