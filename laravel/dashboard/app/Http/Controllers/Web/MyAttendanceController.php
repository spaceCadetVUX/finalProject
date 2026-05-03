<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use Illuminate\Http\Request;

class MyAttendanceController extends Controller
{
    public function index(Request $request)
    {
        $user  = auth()->user();
        $month = $request->input('month', now()->format('Y-m'));

        [$year, $mon] = explode('-', $month);

        $records = Attendance::where('user_id', $user->id)
            ->whereYear('work_date', $year)
            ->whereMonth('work_date', $mon)
            ->orderBy('work_date')
            ->get();

        $attended = $records->whereIn('status', ['present', 'late', 'early_leave'])->count();
        $total    = $records->count();

        $stats = [
            'attended'    => $attended,
            'absent'      => $records->where('status', 'absent')->count(),
            'late'        => $records->where('status', 'late')->count(),
            'early_leave' => $records->where('status', 'early_leave')->count(),
            'total'       => $total,
            'rate'        => $total > 0 ? round($attended / $total * 100, 1) : 0,
        ];

        return view('my-attendance.index', compact('user', 'month', 'records', 'stats'));
    }
}
