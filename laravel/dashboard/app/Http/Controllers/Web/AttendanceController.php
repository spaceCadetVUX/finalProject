<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Department;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->input('date', now()->toDateString());

        $query = Attendance::with(['user.department', 'device'])
            ->whereHas('user')
            ->where('work_date', $date);

        if ($request->filled('department_id')) {
            $query->whereHas('user', fn ($q) => $q->where('department_id', $request->department_id));
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('user', fn ($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%"));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $attendances = $query->orderBy('check_in_at')->paginate(20)->withQueryString();
        $departments  = Department::orderBy('name')->get();

        return view('attendances.index', compact('attendances', 'departments', 'date'));
    }

    public function show(Attendance $attendance)
    {
        $attendance->load(['user.department', 'device']);

        return view('attendances.show', compact('attendance'));
    }

    public function update(Request $request, Attendance $attendance)
    {
        $data = $request->validate([
            'status' => 'required|in:present,late,early_leave,absent,leave',
            'note'   => 'nullable|string|max:500',
        ]);

        $attendance->update($data);

        return redirect()->route('attendances.show', $attendance)
            ->with('success', 'Đã cập nhật trạng thái chấm công.');
    }
}
