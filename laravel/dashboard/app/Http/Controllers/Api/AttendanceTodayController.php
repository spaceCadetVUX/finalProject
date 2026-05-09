<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;

class AttendanceTodayController extends Controller
{
    public function __invoke(int $user_id)
    {
        $record = Attendance::where('user_id', $user_id)
            ->where('work_date', today())
            ->first();

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
