@php
    $dayLabels = [1=>'Thứ 2',2=>'Thứ 3',3=>'Thứ 4',4=>'Thứ 5',5=>'Thứ 6',6=>'Thứ 7',7=>'CN'];
@endphp

@extends('layouts.admin')
@section('title', 'Lịch Phân Ca')
@section('header', 'Lịch Phân Ca')

@section('content')

{{-- Actions --}}
<div class="flex justify-end mb-5">
    <a href="{{ route('shift-schedules.create') }}"
       class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700">
        + Phân ca mới
    </a>
</div>

{{-- ── Calendar tuần ────────────────────────────────────────────────────── --}}
<div class="bg-white rounded-xl shadow-sm p-5 mb-6">
    <div class="flex items-center justify-between mb-4">
        <h2 class="font-medium text-gray-800">
            Lịch tuần này
            <span class="text-xs font-normal text-gray-400 ml-1">
                {{ $weekDays->first()->format('d/m') }} – {{ $weekDays->last()->format('d/m/Y') }}
            </span>
        </h2>
    </div>

    <div class="grid grid-cols-7 gap-2">
        {{-- Header ngày --}}
        @foreach($weekDays as $i => $day)
            @php $dow = $i + 1; @endphp
            <div class="text-center">
                <div class="text-xs font-medium text-gray-500 mb-1">{{ $dayLabels[$dow] }}</div>
                <div class="text-xs text-gray-400">{{ $day->format('d/m') }}</div>
            </div>
        @endforeach

        {{-- Ca trong từng ngày --}}
        @foreach($weekDays as $i => $day)
            @php $dow = $i + 1; $daySchedules = $calendarData[$dow]; @endphp
            <div class="min-h-16 space-y-1">
                @forelse($daySchedules as $s)
                    <div class="text-white text-xs rounded px-2 py-1 leading-tight"
                         style="background-color: {{ $s->template->color }}80; border-left: 3px solid {{ $s->template->color }}">
                        <div class="font-medium truncate" style="color: {{ $s->template->color }}">
                            {{ $s->template->name }}
                        </div>
                        <div class="truncate text-gray-600">{{ $s->assigneeLabel() }}</div>
                        <div class="text-gray-500 mt-0.5">
                            {{ substr($s->template->check_in_time, 0, 5) }}–{{ substr($s->template->check_out_time, 0, 5) }}
                        </div>
                    </div>
                @empty
                    <div class="h-4"></div>
                @endforelse
            </div>
        @endforeach
    </div>
</div>

{{-- ── Danh sách phân ca ────────────────────────────────────────────────── --}}
<div class="bg-white rounded-xl shadow-sm overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100">
        <h2 class="font-medium text-gray-800">Tất cả lịch phân ca</h2>
    </div>

    <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b">
            <tr>
                <th class="text-left px-5 py-3 text-gray-500 font-medium">Ca làm việc</th>
                <th class="text-left px-4 py-3 text-gray-500 font-medium">Áp dụng cho</th>
                <th class="text-left px-4 py-3 text-gray-500 font-medium">Ngày trong tuần</th>
                <th class="text-left px-4 py-3 text-gray-500 font-medium">Thời gian</th>
                <th class="text-left px-4 py-3 text-gray-500 font-medium">Trạng thái</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse($schedules as $s)
            <tr class="{{ $s->is_active ? '' : 'opacity-50' }} hover:bg-gray-50">
                {{-- Ca --}}
                <td class="px-5 py-3">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full shrink-0"
                              style="background: {{ $s->template->color }}"></span>
                        <div>
                            <div class="font-medium text-gray-800">{{ $s->template->name }}</div>
                            <div class="text-xs text-gray-400 font-mono">
                                {{ substr($s->template->check_in_time,0,5) }} – {{ substr($s->template->check_out_time,0,5) }}
                            </div>
                        </div>
                    </div>
                </td>

                {{-- Assignee --}}
                <td class="px-4 py-3">
                    <span class="text-xs px-2 py-0.5 rounded-full mr-1
                        {{ $s->assignee_type === 'department' ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700' }}">
                        {{ $s->assignee_type === 'department' ? 'Phòng ban' : 'Nhân viên' }}
                    </span>
                    <span class="text-gray-700">{{ $s->assigneeLabel() }}</span>
                </td>

                {{-- Ngày trong tuần --}}
                <td class="px-4 py-3">
                    <div class="flex gap-1 flex-wrap">
                        @foreach($dayLabels as $d => $label)
                            <span class="text-xs px-1.5 py-0.5 rounded
                                {{ in_array($d, $s->days_of_week) ? 'bg-blue-100 text-blue-700 font-medium' : 'bg-gray-100 text-gray-300' }}">
                                {{ Str::substr($label, -1) === '2' ? 'T2' : ($label === 'CN' ? 'CN' : substr($label, -1)) }}
                            </span>
                        @endforeach
                    </div>
                </td>

                {{-- Ngày hiệu lực --}}
                <td class="px-4 py-3 text-gray-500 text-xs">
                    <div>Từ {{ $s->start_date->format('d/m/Y') }}</div>
                    @if($s->end_date)
                        <div>Đến {{ $s->end_date->format('d/m/Y') }}</div>
                    @else
                        <div class="text-gray-400">Vô thời hạn</div>
                    @endif
                </td>

                {{-- Status --}}
                <td class="px-4 py-3">
                    @if($s->is_active)
                        <span class="text-xs px-2 py-0.5 rounded-full bg-green-100 text-green-700">Hoạt động</span>
                    @else
                        <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-500">Tạm ngưng</span>
                    @endif
                </td>

                {{-- Actions --}}
                <td class="px-4 py-3 text-right whitespace-nowrap">
                    <form method="POST" action="{{ route('shift-schedules.toggle', $s) }}" class="inline">
                        @csrf
                        <button class="text-xs mr-3 {{ $s->is_active ? 'text-amber-500 hover:underline' : 'text-green-600 hover:underline' }}">
                            {{ $s->is_active ? 'Tạm ngưng' : 'Kích hoạt' }}
                        </button>
                    </form>
                    <form method="POST" action="{{ route('shift-schedules.destroy', $s) }}" class="inline"
                          onsubmit="return confirm('Xóa lịch phân ca này?')">
                        @csrf @method('DELETE')
                        <button class="text-red-400 hover:underline text-xs">Xóa</button>
                    </form>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="px-5 py-10 text-center text-gray-400">
                    Chưa có lịch phân ca nào.
                    <a href="{{ route('shift-schedules.create') }}" class="text-blue-500 hover:underline ml-1">Thêm ngay</a>
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    @if($schedules->hasPages())
        <div class="px-5 py-3 border-t">{{ $schedules->links() }}</div>
    @endif
</div>

@endsection
