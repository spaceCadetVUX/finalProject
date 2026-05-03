<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Device;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

class DashboardController extends Controller
{
    public function index()
    {
        $today          = now()->toDateString();
        $totalEmployees = User::whereNull('deleted_at')->count();

        $presentToday = Attendance::where('work_date', $today)
            ->whereIn('status', ['present', 'late', 'early_leave'])->count();

        $absentToday = Attendance::where('work_date', $today)
            ->where('status', 'absent')->count();

        $lateToday = Attendance::where('work_date', $today)
            ->where('status', 'late')->count();

        [$chartLabels, $chartRates] = $this->buildChartData($totalEmployees);

        $recentCheckIns = $this->buildRecentRows();

        $offlineDevices = Device::where(function ($q) {
            $q->whereNull('last_ping')
              ->orWhere('last_ping', '<', now()->subMinutes(5));
        })->get();

        return view('dashboard', compact(
            'totalEmployees', 'presentToday', 'absentToday', 'lateToday',
            'chartLabels', 'chartRates', 'recentCheckIns', 'offlineDevices'
        ));
    }

    public function recentCheckins()
    {
        return response()->json($this->buildRecentRows());
    }

    private function buildChartData(int $total): array
    {
        $labels = [];
        $rates  = [];

        for ($i = 6; $i >= 0; $i--) {
            $date     = now()->subDays($i)->toDateString();
            $present  = Attendance::where('work_date', $date)
                ->whereIn('status', ['present', 'late', 'early_leave'])
                ->count();
            $labels[] = now()->subDays($i)->format('d/m');
            $rates[]  = $total > 0 ? round($present / $total * 100, 1) : 0;
        }

        return [$labels, $rates];
    }

    private function buildRecentRows(): array
    {
        return Attendance::with('user:id,name,code,avatar')
            ->whereNotNull('check_in_at')
            ->orderByDesc('check_in_at')
            ->limit(10)
            ->get()
            ->map(fn ($a) => [
                'name'     => $a->user->name,
                'code'     => $a->user->code,
                'time'     => $a->check_in_at->format('H:i'),
                'date'     => $a->check_in_at->format('d/m'),
                'status'   => $a->status,
                'initials' => strtoupper(substr($a->user->name, 0, 2)),
                'avatar'   => $a->user->avatar ? Storage::url($a->user->avatar) : null,
            ])
            ->all();
    }
}
