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
        $user = auth()->user();
        $date = $request->input('date', now()->toDateString());

        $query = Attendance::with(['user.department', 'device'])
            ->whereHas('user')
            ->where('work_date', $date);

        // Role scoping
        match ($user->role) {
            'employee' => $query->where('user_id', $user->id),
            'manager'  => $query->whereHas('user', fn ($q) => $q->where('department_id', $user->department_id)),
            default    => null,
        };

        // Extra filters (admin/manager only — employees have no filter bar)
        if (!in_array($user->role, ['employee'])) {
            if ($request->filled('department_id')) {
                $query->whereHas('user', fn ($q) => $q->where('department_id', $request->department_id));
            }
            if ($request->filled('search')) {
                $search = $request->search;
                $query->whereHas('user', fn ($q) => $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%"));
            }
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $attendances = $query->orderBy('check_in_at')->paginate(20)->withQueryString();
        $departments  = in_array($user->role, ['super_admin', 'admin'])
            ? Department::orderBy('name')->get()
            : collect();

        return view('attendances.index', compact('attendances', 'departments', 'date'));
    }

    public function show(Attendance $attendance)
    {
        $user = auth()->user();

        match ($user->role) {
            'employee' => $attendance->user_id !== $user->id ? abort(403) : null,
            'manager'  => $attendance->user?->department_id !== $user->department_id ? abort(403) : null,
            default    => null,
        };

        $attendance->load(['user.department', 'device']);

        return view('attendances.show', compact('attendance'));
    }

    public function update(Request $request, Attendance $attendance)
    {
        $user = auth()->user();

        // Manager can only override their own department
        if ($user->role === 'manager'
            && $attendance->user?->department_id !== $user->department_id) {
            abort(403);
        }

        $data = $request->validate([
            'status' => 'required|in:present,late,early_leave,absent,leave',
            'note'   => 'nullable|string|max:500',
        ]);

        $attendance->update($data);

        return redirect()->route('attendances.show', $attendance)
            ->with('success', 'Đã cập nhật trạng thái chấm công.');
    }
}
