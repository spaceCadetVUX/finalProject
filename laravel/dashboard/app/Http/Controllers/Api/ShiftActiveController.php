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

        $today = Carbon::today();

        $candidates = ShiftSchedule::with('template')
            ->where('is_active', true)
            ->where('start_date', '<=', $today)
            ->where(fn($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $today))
            ->where(function ($q) use ($user_id, $user) {
                // Employee-level schedule
                $q->where(fn($q2) => $q2
                    ->where('assignee_type', 'employee')
                    ->where('assignee_id', $user_id));
                // Department-level schedule (if user belongs to a department)
                if ($user->department_id) {
                    $q->orWhere(fn($q2) => $q2
                        ->where('assignee_type', 'department')
                        ->where('assignee_id', $user->department_id));
                }
            })
            ->get();

        // Filter by day-of-week; prefer employee-specific over department
        $schedule = $candidates
            ->filter(fn($s) => $s->appliesToDate($today))
            ->sortBy(fn($s) => $s->assignee_type === 'employee' ? 0 : 1)
            ->first();

        if (!$schedule) {
            return response()->json(null, 200);
        }

        $tpl = $schedule->template;

        return response()->json([
            'shift_schedule_id' => $schedule->id,
            'shift_name'        => $tpl->name,
            'check_in_time'     => substr($tpl->check_in_time, 0, 5),
            'check_out_time'    => substr($tpl->check_out_time, 0, 5),
            'late_tolerance'    => $tpl->late_tolerance,
        ]);
    }
}
