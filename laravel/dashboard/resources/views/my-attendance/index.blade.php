@extends('layouts.admin')
@section('title', 'Chấm Công Của Tôi')
@section('header', 'Chấm Công Của Tôi')

@section('content')
@php
$statusLabel = ['present'=>'Đúng giờ','late'=>'Trễ','early_leave'=>'Về sớm','absent'=>'Vắng','leave'=>'Nghỉ phép'];
$statusBadge = [
    'present'     => 'bg-green-100 text-green-700',
    'late'        => 'bg-yellow-100 text-yellow-700',
    'early_leave' => 'bg-orange-100 text-orange-700',
    'absent'      => 'bg-red-100 text-red-700',
    'leave'       => 'bg-gray-100 text-gray-600',
];
$monthLabel = \Carbon\Carbon::createFromFormat('Y-m', $month)->locale('vi')->isoFormat('MMMM YYYY');
@endphp

{{-- Profile card --}}
<div class="bg-white rounded-xl shadow-sm p-5 mb-5 flex items-center gap-4">
    @if($user->avatar)
        <img src="{{ Storage::url($user->avatar) }}" class="w-14 h-14 rounded-full object-cover">
    @else
        <div class="w-14 h-14 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 font-bold text-lg">
            {{ strtoupper(substr($user->name, 0, 2)) }}
        </div>
    @endif
    <div class="flex-1">
        <div class="font-semibold text-gray-800 text-lg">{{ $user->name }}</div>
        <div class="text-sm text-gray-500">
            {{ $user->code }}
            @if($user->department)
                · {{ $user->department->name }}
            @endif
        </div>
    </div>
    <form method="GET">
        <input type="month" name="month" value="{{ $month }}"
               onchange="this.form.submit()"
               class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
    </form>
</div>

{{-- Stats --}}
<div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-5">
    <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-blue-500">
        <div class="text-xs text-gray-500 uppercase tracking-wide font-medium">Tổng ngày</div>
        <div class="text-2xl font-bold text-gray-800 mt-1">{{ $stats['total'] }}</div>
        <div class="text-xs text-gray-400">bản ghi</div>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-green-500">
        <div class="text-xs text-gray-500 uppercase tracking-wide font-medium">Đi làm</div>
        <div class="text-2xl font-bold text-green-700 mt-1">{{ $stats['attended'] }}</div>
        <div class="text-xs text-gray-400">ngày</div>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-red-500">
        <div class="text-xs text-gray-500 uppercase tracking-wide font-medium">Vắng mặt</div>
        <div class="text-2xl font-bold text-red-600 mt-1">{{ $stats['absent'] }}</div>
        <div class="text-xs text-gray-400">ngày</div>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-yellow-500">
        <div class="text-xs text-gray-500 uppercase tracking-wide font-medium">Đi trễ</div>
        <div class="text-2xl font-bold text-yellow-600 mt-1">{{ $stats['late'] }}</div>
        <div class="text-xs text-gray-400">lần</div>
    </div>
    @php
        $rateBorder = $stats['rate'] >= 90 ? 'border-green-400' : ($stats['rate'] >= 70 ? 'border-yellow-400' : 'border-red-400');
        $rateText   = $stats['rate'] >= 90 ? 'text-green-600' : ($stats['rate'] >= 70 ? 'text-yellow-600' : 'text-red-600');
    @endphp
    <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 {{ $rateBorder }}">
        <div class="text-xs text-gray-500 uppercase tracking-wide font-medium">Tỉ lệ</div>
        <div class="text-2xl font-bold mt-1 {{ $rateText }}">{{ $stats['rate'] }}%</div>
        <div class="text-xs text-gray-400">chuyên cần</div>
    </div>
</div>

{{-- Attendance records table --}}
<div class="bg-white rounded-xl shadow-sm overflow-hidden">
    <div class="px-4 py-2.5 border-b bg-gray-50 text-xs text-gray-500">
        Lịch sử tháng {{ $monthLabel }}
    </div>

    <div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b">
            <tr>
                <th class="text-left px-4 py-3 text-gray-600 font-medium">Ngày</th>
                <th class="text-left px-4 py-3 text-gray-600 font-medium">Check-in</th>
                <th class="text-left px-4 py-3 text-gray-600 font-medium">Check-out</th>
                <th class="text-left px-4 py-3 text-gray-600 font-medium">Trạng thái</th>
                <th class="text-left px-4 py-3 text-gray-600 font-medium">Ghi chú</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse($records as $rec)
            @php
                $dow = $rec->work_date->locale('vi')->isoFormat('ddd');
                $isWeekend = $rec->work_date->isWeekend();
            @endphp
            <tr class="{{ $isWeekend ? 'bg-gray-50/50' : 'hover:bg-gray-50' }}">
                <td class="px-4 py-3">
                    <div class="font-medium {{ $isWeekend ? 'text-gray-400' : 'text-gray-800' }}">
                        {{ $rec->work_date->format('d/m/Y') }}
                    </div>
                    <div class="text-xs {{ $isWeekend ? 'text-gray-300' : 'text-gray-400' }}">{{ $dow }}</div>
                </td>
                <td class="px-4 py-3">
                    @if($rec->check_in_at)
                        <div class="font-medium text-gray-800">{{ $rec->check_in_at->format('H:i') }}</div>
                        @if($rec->check_in_confidence)
                            <div class="text-xs text-gray-400">{{ number_format($rec->check_in_confidence * 100, 1) }}%</div>
                        @endif
                    @else
                        <span class="text-gray-300">—</span>
                    @endif
                </td>
                <td class="px-4 py-3">
                    @if($rec->check_out_at)
                        <div class="font-medium text-gray-800">{{ $rec->check_out_at->format('H:i') }}</div>
                        @if($rec->check_in_at && $rec->check_out_at)
                            @php
                                $hours = $rec->check_in_at->diffInMinutes($rec->check_out_at);
                                $h = intdiv($hours, 60); $m = $hours % 60;
                            @endphp
                            <div class="text-xs text-gray-400">{{ $h }}h{{ $m > 0 ? $m.'p' : '' }}</div>
                        @endif
                    @else
                        <span class="text-gray-300">—</span>
                    @endif
                </td>
                <td class="px-4 py-3">
                    <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $statusBadge[$rec->status] ?? 'bg-gray-100 text-gray-600' }}">
                        {{ $statusLabel[$rec->status] ?? $rec->status }}
                    </span>
                </td>
                <td class="px-4 py-3 text-gray-500 text-xs">{{ $rec->note ?? '—' }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="5" class="px-4 py-12 text-center">
                    <div class="text-gray-400 text-sm">Không có dữ liệu tháng {{ $monthLabel }}</div>
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
    </div>{{-- /overflow-x-auto --}}
</div>
@endsection
