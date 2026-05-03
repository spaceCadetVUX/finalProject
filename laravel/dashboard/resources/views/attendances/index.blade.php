@extends('layouts.admin')
@section('title', 'Chấm Công')
@section('header', 'Quản Lý Chấm Công')

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
@endphp

<div class="flex items-center justify-between mb-5">
    <form method="GET" class="flex flex-wrap gap-2 flex-1" id="filter-form">

        {{-- Date with prev/next arrows --}}
        <div class="flex items-center gap-1">
            <a href="{{ route('attendances.index', array_merge(request()->except('date'), ['date' => \Carbon\Carbon::parse($date)->subDay()->toDateString()])) }}"
               class="px-2 py-2 rounded-lg hover:bg-gray-100 text-gray-500 text-base leading-none">&#8592;</a>
            <input type="date" name="date" value="{{ $date }}"
                   onchange="this.form.submit()"
                   class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
            <a href="{{ route('attendances.index', array_merge(request()->except('date'), ['date' => \Carbon\Carbon::parse($date)->addDay()->toDateString()])) }}"
               class="px-2 py-2 rounded-lg hover:bg-gray-100 text-gray-500 text-base leading-none">&#8594;</a>
        </div>

        <input type="text" name="search" value="{{ request('search') }}"
               placeholder="Tìm tên, mã nhân viên..."
               class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-52 focus:ring-2 focus:ring-blue-500 focus:outline-none">

        <select name="department_id" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
            <option value="">Tất cả phòng ban</option>
            @foreach($departments as $dept)
                <option value="{{ $dept->id }}" {{ request('department_id') == $dept->id ? 'selected' : '' }}>
                    {{ $dept->name }}
                </option>
            @endforeach
        </select>

        <select name="status" onchange="this.form.submit()"
                class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
            <option value="">Tất cả trạng thái</option>
            @foreach($statusLabel as $val => $label)
                <option value="{{ $val }}" {{ request('status') == $val ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>

        <button type="submit" class="bg-gray-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-gray-700">Lọc</button>

        @if(request()->hasAny(['search','department_id','status']))
            <a href="{{ route('attendances.index', ['date' => $date]) }}"
               class="px-4 py-2 rounded-lg text-sm text-gray-500 hover:bg-gray-100">Xóa bộ lọc</a>
        @endif
    </form>
</div>

<div class="bg-white rounded-xl shadow-sm overflow-hidden">

    {{-- Record count header --}}
    <div class="px-4 py-2.5 border-b bg-gray-50 flex items-center justify-between">
        <span class="text-xs text-gray-500">
            {{ $attendances->total() }} bản ghi · ngày {{ \Carbon\Carbon::parse($date)->format('d/m/Y') }}
        </span>
        @if($attendances->total() > 0)
            <span class="text-xs text-gray-400">Trang {{ $attendances->currentPage() }}/{{ $attendances->lastPage() }}</span>
        @endif
    </div>

    <div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b">
            <tr>
                <th class="text-left px-4 py-3 text-gray-600 font-medium">Nhân viên</th>
                <th class="text-left px-4 py-3 text-gray-600 font-medium">Phòng ban</th>
                <th class="text-left px-4 py-3 text-gray-600 font-medium">Check-in</th>
                <th class="text-left px-4 py-3 text-gray-600 font-medium">Check-out</th>
                <th class="text-left px-4 py-3 text-gray-600 font-medium">Trạng thái</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse($attendances as $att)
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3">
                    <div class="flex items-center gap-3">
                        @if($att->user->avatar)
                            <img src="{{ Storage::url($att->user->avatar) }}" class="w-8 h-8 rounded-full object-cover">
                        @else
                            <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 font-semibold text-xs">
                                {{ strtoupper(substr($att->user->name, 0, 2)) }}
                            </div>
                        @endif
                        <div>
                            <div class="font-medium text-gray-800">{{ $att->user->name }}</div>
                            <div class="text-gray-400 text-xs">{{ $att->user->code }}</div>
                        </div>
                    </div>
                </td>
                <td class="px-4 py-3 text-gray-600">{{ $att->user->department?->name ?? '—' }}</td>
                <td class="px-4 py-3">
                    @if($att->check_in_at)
                        <div class="font-medium text-gray-800">{{ $att->check_in_at->format('H:i') }}</div>
                        @if($att->check_in_confidence)
                            <div class="text-xs text-gray-400">{{ number_format($att->check_in_confidence * 100, 1) }}%</div>
                        @endif
                    @else
                        <span class="text-gray-300">—</span>
                    @endif
                </td>
                <td class="px-4 py-3">
                    @if($att->check_out_at)
                        <div class="font-medium text-gray-800">{{ $att->check_out_at->format('H:i') }}</div>
                        @if($att->check_out_confidence)
                            <div class="text-xs text-gray-400">{{ number_format($att->check_out_confidence * 100, 1) }}%</div>
                        @endif
                    @else
                        <span class="text-gray-300">—</span>
                    @endif
                </td>
                <td class="px-4 py-3">
                    <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $statusBadge[$att->status] ?? 'bg-gray-100 text-gray-600' }}">
                        {{ $statusLabel[$att->status] ?? $att->status }}
                    </span>
                </td>
                <td class="px-4 py-3 text-right">
                    <a href="{{ route('attendances.show', $att) }}" class="text-blue-600 hover:underline text-xs">Chi tiết</a>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="px-4 py-12 text-center">
                    <div class="text-gray-400 text-sm">Không có dữ liệu chấm công</div>
                    <div class="text-gray-300 text-xs mt-1">{{ \Carbon\Carbon::parse($date)->format('d/m/Y') }}</div>
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
    </div>{{-- /overflow-x-auto --}}
    <div class="px-4 py-3 border-t">{{ $attendances->links() }}</div>
</div>
@endsection
