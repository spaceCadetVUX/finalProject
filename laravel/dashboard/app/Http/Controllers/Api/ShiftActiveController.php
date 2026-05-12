<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ShiftSchedule;
use App\Models\User;
use Carbon\Carbon;

class ShiftActiveController extends Controller
{
    public function __invoke(int $user_id)
    {
        $user = User::withTrashed()->find($user_id);
        if (!$user || $user->deleted_at) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $today       = Carbon::today();
        $todayShifts = $this->getShiftsForDate($user, $today);

        // Nếu hôm nay không có ca → tìm ca gần nhất trong tương lai (tối đa 30 ngày)
        $nextShift = null;
        if ($todayShifts->isEmpty()) {
            $nextShift = $this->getNextShift($user, $today->copy()->addDay());
        }

        return response()->json([
            'today'      => $todayShifts->map(fn($s) => $this->formatShift($s))->values(),
            'next_shift' => $nextShift,
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function getShiftsForDate(User $user, Carbon $date): \Illuminate\Support\Collection
    {
        $candidates = ShiftSchedule::with('template')
            ->where('is_active', true)
            ->where('start_date', '<=', $date)
            ->where(fn($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $date))
            ->where(function ($q) use ($user) {
                $q->where(fn($q2) => $q2
                    ->where('assignee_type', 'employee')
                    ->where('assignee_id', $user->id));
                if ($user->department_id) {
                    $q->orWhere(fn($q2) => $q2
                        ->where('assignee_type', 'department')
                        ->where('assignee_id', $user->department_id));
                }
            })
            ->get();

        return $candidates
            ->filter(fn($s) => $s->appliesToDate($date))
            ->sortBy(fn($s) => $s->template->check_in_time)
            ->values();
    }

    private function getNextShift(User $user, Carbon $from): ?array
    {
        for ($i = 0; $i < 30; $i++) {
            $date   = $from->copy()->addDays($i);
            $shifts = $this->getShiftsForDate($user, $date);
            if ($shifts->isNotEmpty()) {
                $shift = $shifts->first();
                return array_merge(
                    $this->formatShift($shift),
                    ['date' => $date->toDateString()]
                );
            }
        }
        return null;
    }

    private function formatShift(ShiftSchedule $s): array
    {
        $tpl = $s->template;
        return [
            'shift_schedule_id' => $s->id,
            'shift_name'        => $tpl->name,
            'check_in_time'     => substr($tpl->check_in_time, 0, 5),
            'check_out_time'    => substr($tpl->check_out_time, 0, 5),
            'late_tolerance'    => $tpl->late_tolerance,
        ];
    }
}
