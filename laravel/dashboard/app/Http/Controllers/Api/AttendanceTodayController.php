<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use Illuminate\Http\Request;

class AttendanceTodayController extends Controller
{
    public function __invoke(int $user_id, Request $request)
    {
        $query = Attendance::where('user_id', $user_id)
            ->where('work_date', today());

        // When a shift_schedule_id is given, return that shift's record specifically.
        // Employees with multiple shifts per day each have their own attendance row.
        if ($request->filled('shift_schedule_id')) {
            $query->where('shift_schedule_id', $request->shift_schedule_id);
        }

        $record = $query->orderByDesc('created_at')->first();

        if (!$record) {
            return response()->json(['check_in_at' => null, 'check_out_at' => null, 'status' => null]);
        }

        return response()->json([
            'check_in_at'  => $record->check_in_at  ? substr($record->check_in_at,  11, 5) : null,
            'check_out_at' => $record->check_out_at ? substr($record->check_out_at, 11, 5) : null,
            'status'       => $record->status,
        ]);
    }
}
