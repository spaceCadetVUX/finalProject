@extends('layouts.admin')
@section('title', 'Báo Cáo')
@section('header', 'Báo Cáo Chấm Công')

@section('content')

{{-- Filter + Export bar --}}
<div class="flex flex-wrap items-center gap-2 mb-5">
    <form method="GET" class="flex flex-wrap gap-2 flex-1">
        <input type="month" name="month" value="{{ $month }}"
               onchange="this.form.submit()"
               class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">

        <select name="department_id" onchange="this.form.submit()"
                class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
            <option value="">Tất cả phòng ban</option>
            @foreach($departments as $dept)
                <option value="{{ $dept->id }}" {{ request('department_id') == $dept->id ? 'selected' : '' }}>
                    {{ $dept->name }}
                </option>
            @endforeach
        </select>
    </form>

    <a href="{{ route('reports.export', array_merge(request()->all(), ['format' => 'excel'])) }}"
       class="flex items-center gap-1.5 bg-green-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700 whitespace-nowrap">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
        </svg>
        Xuất CSV
    </a>

    <a href="{{ route('reports.export', array_merge(request()->all(), ['format' => 'pdf'])) }}"
       class="flex items-center gap-1.5 bg-red-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-red-700 whitespace-nowrap">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
        </svg>
        Xuất PDF
    </a>
</div>

{{-- Summary cards --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
    <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-blue-500">
        <div class="text-xs text-gray-500 uppercase tracking-wide font-medium">Nhân viên</div>
        <div class="text-2xl font-bold text-gray-800 mt-1">{{ $summary['employees'] }}</div>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-green-500">
        <div class="text-xs text-gray-500 uppercase tracking-wide font-medium">Tỉ lệ TB</div>
        <div class="text-2xl font-bold text-gray-800 mt-1">{{ $summary['avg_rate'] }}%</div>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-red-500">
        <div class="text-xs text-gray-500 uppercase tracking-wide font-medium">Tổng vắng</div>
        <div class="text-2xl font-bold text-gray-800 mt-1">{{ $summary['total_absent'] }}</div>
        <div class="text-xs text-gray-400">lượt</div>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-yellow-500">
        <div class="text-xs text-gray-500 uppercase tracking-wide font-medium">Tổng trễ</div>
        <div class="text-2xl font-bold text-gray-800 mt-1">{{ $summary['total_late'] }}</div>
        <div class="text-xs text-gray-400">lượt</div>
    </div>
</div>

{{-- Bar chart by department --}}
@if(count($chartLabels) > 0)
<div class="bg-white rounded-xl shadow-sm p-5 mb-5">
    <div class="text-sm font-medium text-gray-700 mb-4">
        Tỉ lệ chấm công theo phòng ban — {{ \Carbon\Carbon::createFromFormat('Y-m', $month)->format('m/Y') }}
    </div>
    <canvas id="deptChart" height="80"></canvas>
</div>
@endif

{{-- Report table --}}
<div class="bg-white rounded-xl shadow-sm overflow-hidden">
    <div class="px-4 py-2.5 border-b bg-gray-50 text-xs text-gray-500">
        {{ $report->count() }} nhân viên · tháng {{ \Carbon\Carbon::createFromFormat('Y-m', $month)->format('m/Y') }}
    </div>

    <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b">
            <tr>
                <th class="text-left px-4 py-3 text-gray-600 font-medium">Nhân viên</th>
                <th class="text-left px-4 py-3 text-gray-600 font-medium">Phòng ban</th>
                <th class="text-center px-3 py-3 text-gray-600 font-medium">Đi làm</th>
                <th class="text-center px-3 py-3 text-gray-600 font-medium">Vắng</th>
                <th class="text-center px-3 py-3 text-gray-600 font-medium">Trễ</th>
                <th class="text-center px-3 py-3 text-gray-600 font-medium">Về sớm</th>
                <th class="text-center px-3 py-3 text-gray-600 font-medium">Tổng</th>
                <th class="text-right px-4 py-3 text-gray-600 font-medium">Tỉ lệ</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse($report as $row)
            @php
                $rate      = $row['rate'];
                $rateColor = $rate >= 90 ? 'text-green-600' : ($rate >= 70 ? 'text-yellow-600' : 'text-red-600');
                $barColor  = $rate >= 90 ? 'bg-green-500' : ($rate >= 70 ? 'bg-yellow-400' : 'bg-red-400');
            @endphp
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3">
                    <div class="flex items-center gap-3">
                        @if($row['avatar'])
                            <img src="{{ Storage::url($row['avatar']) }}" class="w-8 h-8 rounded-full object-cover">
                        @else
                            <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 font-semibold text-xs">
                                {{ strtoupper(substr($row['name'], 0, 2)) }}
                            </div>
                        @endif
                        <div>
                            <div class="font-medium text-gray-800">{{ $row['name'] }}</div>
                            <div class="text-xs text-gray-400">{{ $row['code'] }}</div>
                        </div>
                    </div>
                </td>
                <td class="px-4 py-3 text-gray-600">{{ $row['department'] }}</td>
                <td class="px-3 py-3 text-center font-medium text-green-700">{{ $row['attended'] }}</td>
                <td class="px-3 py-3 text-center font-medium text-red-500">{{ $row['absent'] }}</td>
                <td class="px-3 py-3 text-center font-medium text-yellow-600">{{ $row['late'] }}</td>
                <td class="px-3 py-3 text-center font-medium text-orange-500">{{ $row['early_leave'] }}</td>
                <td class="px-3 py-3 text-center text-gray-500">{{ $row['total'] }}</td>
                <td class="px-4 py-3 text-right">
                    <div class="flex items-center justify-end gap-2">
                        <div class="w-16 bg-gray-100 rounded-full h-1.5">
                            <div class="h-1.5 rounded-full {{ $barColor }}" style="width: {{ min($rate, 100) }}%"></div>
                        </div>
                        <span class="font-semibold {{ $rateColor }} w-12 text-right">{{ $rate }}%</span>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="8" class="px-4 py-12 text-center text-gray-400 text-sm">
                    Không có dữ liệu cho tháng {{ \Carbon\Carbon::createFromFormat('Y-m', $month)->format('m/Y') }}
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
@if(count($chartLabels) > 0)
new Chart(document.getElementById('deptChart'), {
    type: 'bar',
    data: {
        labels: @json($chartLabels),
        datasets: [{
            data: @json($chartRates),
            backgroundColor: @json($chartRates).map(r =>
                r >= 90 ? 'rgba(34,197,94,0.75)' : r >= 70 ? 'rgba(234,179,8,0.75)' : 'rgba(239,68,68,0.75)'
            ),
            borderRadius: 4,
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: { min: 0, max: 100, ticks: { callback: v => v + '%' }, grid: { color: '#f3f4f6' } },
            x: { grid: { display: false } }
        },
        plugins: {
            legend: { display: false },
            tooltip: { callbacks: { label: ctx => ctx.parsed.y + '%' } }
        }
    }
});
@endif
</script>
@endpush
