@extends('layouts.admin')
@section('title', 'Dashboard')
@section('header', 'Dashboard')

@section('content')

{{-- Offline device alert --}}
@if($offlineDevices->isNotEmpty())
<div class="mb-5 flex items-start gap-3 px-4 py-3 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm">
    <svg class="w-5 h-5 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
    </svg>
    <div>
        <div class="font-medium">Cảnh báo thiết bị offline</div>
        <div class="mt-0.5">
            @foreach($offlineDevices as $dev)
                <span class="mr-3">{{ $dev->name }} ({{ $dev->location }})
                    — {{ $dev->last_ping ? 'last ping ' . $dev->last_ping->diffForHumans() : 'chưa kết nối lần nào' }}
                </span>
            @endforeach
        </div>
    </div>
</div>
@endif

{{-- Stats cards --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-5">

    <div class="bg-white rounded-xl shadow-sm p-5 border-l-4 border-blue-500">
        <div class="text-xs text-gray-500 font-medium uppercase tracking-wide">Tổng nhân viên</div>
        <div class="text-3xl font-bold text-gray-800 mt-1">{{ $totalEmployees }}</div>
        <div class="text-xs text-gray-400 mt-1">Đang hoạt động</div>
    </div>

    <div class="bg-white rounded-xl shadow-sm p-5 border-l-4 border-green-500">
        <div class="text-xs text-gray-500 font-medium uppercase tracking-wide">Đi làm hôm nay</div>
        <div class="text-3xl font-bold text-gray-800 mt-1">{{ $presentToday }}</div>
        <div class="text-xs text-gray-400 mt-1">
            {{ $totalEmployees > 0 ? number_format($presentToday / $totalEmployees * 100, 1) : 0 }}% tổng số
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm p-5 border-l-4 border-red-500">
        <div class="text-xs text-gray-500 font-medium uppercase tracking-wide">Vắng hôm nay</div>
        <div class="text-3xl font-bold text-gray-800 mt-1">{{ $absentToday }}</div>
        <div class="text-xs text-gray-400 mt-1">Chưa chấm công</div>
    </div>

    <div class="bg-white rounded-xl shadow-sm p-5 border-l-4 border-yellow-500">
        <div class="text-xs text-gray-500 font-medium uppercase tracking-wide">Đi trễ hôm nay</div>
        <div class="text-3xl font-bold text-gray-800 mt-1">{{ $lateToday }}</div>
        <div class="text-xs text-gray-400 mt-1">Quá giờ quy định</div>
    </div>

</div>

{{-- Chart + Recent check-ins --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

    {{-- Attendance rate chart --}}
    <div class="lg:col-span-2 bg-white rounded-xl shadow-sm p-5">
        <div class="text-sm font-medium text-gray-700 mb-4">Tỉ lệ chấm công 7 ngày gần nhất</div>
        <canvas id="attendanceChart" height="100"></canvas>
    </div>

    {{-- Recent check-ins (polling) --}}
    <div class="bg-white rounded-xl shadow-sm p-5"
         x-data="{
             rows: @json($recentCheckIns),
             async poll() {
                 try {
                     const r = await fetch('{{ route('dashboard.recent') }}');
                     this.rows = await r.json();
                 } catch {}
             },
             badgeClass(s) {
                 return { present: 'bg-green-100 text-green-700', late: 'bg-yellow-100 text-yellow-700',
                          early_leave: 'bg-orange-100 text-orange-700', absent: 'bg-red-100 text-red-700',
                          leave: 'bg-gray-100 text-gray-600' }[s] ?? 'bg-gray-100 text-gray-600';
             },
             statusLabel(s) {
                 return { present: 'Đúng giờ', late: 'Trễ', early_leave: 'Về sớm',
                          absent: 'Vắng', leave: 'Nghỉ phép' }[s] ?? s;
             }
         }"
         x-init="setInterval(() => poll(), 10000)">

        <div class="flex items-center justify-between mb-3">
            <div class="text-sm font-medium text-gray-700">Check-in gần nhất</div>
            <div class="text-xs text-gray-400">Cập nhật mỗi 10 giây</div>
        </div>

        <div class="space-y-2">
            <template x-for="row in rows" :key="row.code + row.time">
                <div class="flex items-center gap-3 py-2 border-b border-gray-50 last:border-0">
                    <template x-if="row.avatar">
                        <img :src="row.avatar" class="w-8 h-8 rounded-full object-cover shrink-0">
                    </template>
                    <template x-if="!row.avatar">
                        <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 font-semibold text-xs shrink-0"
                             x-text="row.initials"></div>
                    </template>
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-medium text-gray-800 truncate" x-text="row.name"></div>
                        <div class="text-xs text-gray-400" x-text="row.code"></div>
                    </div>
                    <div class="text-right shrink-0">
                        <div class="text-sm font-semibold text-gray-700" x-text="row.time"></div>
                        <span class="text-xs px-1.5 py-0.5 rounded-full font-medium"
                              :class="badgeClass(row.status)"
                              x-text="statusLabel(row.status)"></span>
                    </div>
                </div>
            </template>
            <template x-if="rows.length === 0">
                <div class="text-center text-gray-400 text-sm py-6">Chưa có check-in hôm nay</div>
            </template>
        </div>
    </div>

</div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('attendanceChart'), {
    type: 'line',
    data: {
        labels: @json($chartLabels),
        datasets: [{
            label: 'Tỉ lệ chấm công (%)',
            data: @json($chartRates),
            borderColor: '#3b82f6',
            backgroundColor: 'rgba(59,130,246,0.08)',
            borderWidth: 2,
            pointBackgroundColor: '#3b82f6',
            pointRadius: 4,
            tension: 0.3,
            fill: true,
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                min: 0,
                max: 100,
                ticks: { callback: v => v + '%' },
                grid: { color: '#f3f4f6' }
            },
            x: { grid: { display: false } }
        },
        plugins: {
            legend: { display: false },
            tooltip: { callbacks: { label: ctx => ctx.parsed.y + '%' } }
        }
    }
});
</script>
@endpush
