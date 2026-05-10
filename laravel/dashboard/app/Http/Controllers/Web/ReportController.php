<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Department;
use App\Models\ShiftTemplate;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $user  = auth()->user();
        $month = $request->input('month', now()->format('Y-m'));

        $departmentId    = $user->role === 'manager'
            ? $user->department_id
            : ($request->input('department_id') ?: null);
        $shiftTemplateId = $request->input('shift_template_id') ?: null;

        $report      = $this->buildReport($month, $departmentId, $shiftTemplateId);
        $departments = in_array($user->role, ['super_admin', 'admin'])
            ? Department::orderBy('name')->get()
            : collect();
        $shiftTemplates = ShiftTemplate::where('is_active', true)->orderBy('check_in_time')->get();

        [$chartLabels, $chartRates] = $this->buildDeptChart($month);

        $summary = [
            'employees'    => $report->count(),
            'avg_rate'     => $report->avg('rate') ? round($report->avg('rate'), 1) : 0,
            'total_absent' => $report->sum('absent'),
            'total_late'   => $report->sum('late'),
        ];

        return view('reports.index', compact(
            'report', 'departments', 'shiftTemplates', 'month', 'summary', 'chartLabels', 'chartRates'
        ));
    }

    public function export(Request $request)
    {
        $user            = auth()->user();
        $month           = $request->input('month', now()->format('Y-m'));
        $departmentId    = $user->role === 'manager'
            ? $user->department_id
            : ($request->input('department_id') ?: null);
        $shiftTemplateId = $request->input('shift_template_id') ?: null;
        $format          = $request->input('format', 'excel');
        $report          = $this->buildReport($month, $departmentId, $shiftTemplateId);

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('reports.pdf', compact('report', 'month'));
            return $pdf->download("bao-cao-{$month}.pdf");
        }

        $shiftName = $shiftTemplateId
            ? ShiftTemplate::find($shiftTemplateId)?->name
            : null;

        return $this->exportExcel($report, $month, $shiftName);
    }

    // ─── Private helpers ───────────────────────────────────────────────────

    private function buildReport(string $month, ?int $departmentId, ?int $shiftTemplateId = null): Collection
    {
        [$year, $mon] = explode('-', $month);

        $grouped = Attendance::with('shiftSchedule.template')
            ->whereYear('work_date', $year)
            ->whereMonth('work_date', $mon)
            ->whereHas('user', fn ($q) => $q->whereNull('deleted_at')
                ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId)))
            ->when($shiftTemplateId, fn ($q) => $q->whereHas('shiftSchedule', fn ($q2) =>
                $q2->where('shift_template_id', $shiftTemplateId)))
            ->get()
            ->groupBy('user_id');

        return User::with('department')
            ->whereNull('deleted_at')
            ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId))
            ->orderBy('name')
            ->get()
            ->map(function ($user) use ($grouped) {
                $records  = $grouped->get($user->id, collect());
                $attended = $records->whereIn('status', ['present', 'late', 'early_leave'])->count();
                $late     = $records->where('status', 'late')->count();
                $early    = $records->where('status', 'early_leave')->count();
                $absent   = $records->where('status', 'absent')->count();
                $total    = $records->count();

                // Collect distinct shift names worked this month
                $shifts = $records
                    ->map(fn ($r) => $r->shiftSchedule?->template?->name)
                    ->filter()
                    ->unique()
                    ->values()
                    ->implode(', ');

                return [
                    'code'        => $user->code,
                    'name'        => $user->name,
                    'department'  => $user->department?->name ?? '—',
                    'avatar'      => $user->avatar,
                    'shifts'      => $shifts ?: '—',
                    'attended'    => $attended,
                    'late'        => $late,
                    'early_leave' => $early,
                    'absent'      => $absent,
                    'total'       => $total,
                    'rate'        => $total > 0 ? round($attended / $total * 100, 1) : 0,
                ];
            })
            ->sortByDesc('rate')
            ->values();
    }

    private function buildDeptChart(string $month): array
    {
        [$year, $mon] = explode('-', $month);

        $depts = Department::orderBy('name')->get();

        $usersByDept = User::whereNull('deleted_at')
            ->whereNotNull('department_id')
            ->get()
            ->groupBy('department_id');

        $allAttendances = Attendance::whereYear('work_date', $year)
            ->whereMonth('work_date', $mon)
            ->get()
            ->groupBy('user_id');

        $labels = [];
        $rates  = [];

        foreach ($depts as $dept) {
            $users = $usersByDept->get($dept->id, collect());
            if ($users->isEmpty()) {
                continue;
            }

            $total   = 0;
            $present = 0;

            foreach ($users as $u) {
                $records  = $allAttendances->get($u->id, collect());
                $total   += $records->count();
                $present += $records->whereIn('status', ['present', 'late', 'early_leave'])->count();
            }

            $labels[] = $dept->name;
            $rates[]  = $total > 0 ? round($present / $total * 100, 1) : 0;
        }

        return [$labels, $rates];
    }

    private function exportExcel(Collection $report, string $month, ?string $filteredShift = null)
    {
        $filename = "bao-cao-{$month}" . ($filteredShift ? "-{$filteredShift}" : '') . ".csv";
        $headers  = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return response()->stream(function () use ($report, $filteredShift) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
            fputcsv($handle, ['Mã NV', 'Tên nhân viên', 'Phòng ban', 'Ca làm việc', 'Đi làm', 'Vắng', 'Trễ', 'Về sớm', 'Tổng ngày', 'Tỉ lệ (%)']);
            foreach ($report as $row) {
                fputcsv($handle, [
                    $row['code'],
                    $row['name'],
                    $row['department'],
                    $filteredShift ?? $row['shifts'],
                    $row['attended'],
                    $row['absent'],
                    $row['late'],
                    $row['early_leave'],
                    $row['total'],
                    $row['rate'] . '%',
                ]);
            }
            fclose($handle);
        }, 200, $headers);
    }
}
