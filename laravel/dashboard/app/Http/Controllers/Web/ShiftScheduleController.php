<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\ShiftSchedule;
use App\Models\ShiftTemplate;
use App\Models\User;
use App\Services\ShiftConflictService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ShiftScheduleController extends Controller
{
    public function __construct(private ShiftConflictService $conflictService) {}

    public function index()
    {
        $schedules = ShiftSchedule::with(['template', 'employee', 'department', 'creator'])
            ->orderByDesc('is_active')
            ->orderByDesc('created_at')
            ->paginate(25);

        [$weekDays, $calendarData] = $this->buildCalendar();

        return view('shift-schedules.index', compact('schedules', 'weekDays', 'calendarData'));
    }

    public function create()
    {
        $templates   = ShiftTemplate::where('is_active', true)->orderBy('check_in_time')->get();
        $departments = Department::orderBy('name')->get(['id', 'name']);
        $employees   = User::whereNull('deleted_at')->orderBy('name')->get(['id', 'name', 'code', 'department_id']);
        $conflicts   = session('shift_conflicts', []);

        return view('shift-schedules.create', compact('templates', 'departments', 'employees', 'conflicts'));
    }

    public function store(Request $request)
    {
        $data = $this->validateRequest($request);
        $force = $request->boolean('force');

        if (!$force) {
            $template  = ShiftTemplate::find($data['shift_template_id']);
            $rawConflicts = $this->conflictService->detect([
                'assignee_type' => $data['assignee_type'],
                'assignee_id'   => (int) $data['assignee_id'],
                'days_of_week'  => $data['days_of_week'],
                'start_date'    => $data['start_date'],
                'end_date'      => $data['end_date'] ?? null,
                'template'      => $template,
            ]);

            if (!empty($rawConflicts)) {
                // Serialize conflict data (plain arrays, session-safe)
                $serialized = array_map(fn($c) => [
                    'type'           => $c['type'],
                    'assignee_label' => $c['assignee_label'],
                    'overlap_days'   => $c['overlap_days'],
                    'template_name'  => $c['schedule']->template->name,
                    'check_in_time'  => substr($c['schedule']->template->check_in_time,  0, 5),
                    'check_out_time' => substr($c['schedule']->template->check_out_time, 0, 5),
                    'schedule_days'  => $c['schedule']->days_of_week,
                    'start_date'     => $c['schedule']->start_date->format('d/m/Y'),
                    'end_date'       => $c['schedule']->end_date?->format('d/m/Y'),
                ], $rawConflicts);

                return back()->withInput()->with('shift_conflicts', $serialized);
            }
        }

        ShiftSchedule::create(array_merge($data, ['created_by' => auth()->id()]));

        return redirect()->route('shift-schedules.index')
            ->with('success', 'Đã phân ca thành công.');
    }

    public function toggle(ShiftSchedule $shiftSchedule)
    {
        $shiftSchedule->update(['is_active' => !$shiftSchedule->is_active]);
        $msg = $shiftSchedule->is_active ? 'Đã kích hoạt lịch phân ca.' : 'Đã tạm ngưng lịch phân ca.';
        return back()->with('success', $msg);
    }

    public function destroy(ShiftSchedule $shiftSchedule)
    {
        $shiftSchedule->delete();
        return back()->with('success', 'Đã xóa lịch phân ca.');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function validateRequest(Request $request): array
    {
        $type  = $request->input('assignee_type');
        $table = $type === 'department' ? 'departments' : 'users';

        return $request->validate([
            'shift_template_id' => 'required|exists:shift_templates,id',
            'assignee_type'     => 'required|in:department,employee',
            'assignee_id'       => "required|integer|exists:{$table},id",
            'days_of_week'      => 'required|array|min:1',
            'days_of_week.*'    => 'integer|between:1,7',
            'start_date'        => 'required|date',
            'end_date'          => 'nullable|date|after_or_equal:start_date',
            'note'              => 'nullable|string|max:255',
        ]);
    }

    private function buildCalendar(): array
    {
        $weekStart = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $weekDays  = collect(range(0, 6))->map(fn($i) => $weekStart->copy()->addDays($i));

        $active = ShiftSchedule::with(['template', 'employee', 'department'])
            ->where('is_active', true)
            ->where('start_date', '<=', $weekStart->copy()->endOfWeek())
            ->where(fn($q) => $q->whereNull('end_date')
                               ->orWhere('end_date', '>=', $weekStart))
            ->get();

        // ISO dow 1=Mon…7=Sun
        $calendarData = [];
        foreach (range(1, 7) as $dow) {
            $date = $weekStart->copy()->addDays($dow - 1);
            $calendarData[$dow] = $active->filter(fn($s) => $s->appliesToDate($date))->values();
        }

        return [$weekDays, $calendarData];
    }
}
