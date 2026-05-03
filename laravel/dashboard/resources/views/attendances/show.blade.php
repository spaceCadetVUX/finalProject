@extends('layouts.admin')
@section('title', 'Chi Tiết Chấm Công')
@section('header', 'Chi Tiết Chấm Công')

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

<div class="max-w-3xl space-y-5">

    {{-- Employee Info --}}
    <div class="bg-white rounded-xl shadow-sm p-5 flex items-center gap-4">
        @if($attendance->user->avatar)
            <img src="{{ Storage::url($attendance->user->avatar) }}" class="w-14 h-14 rounded-full object-cover">
        @else
            <div class="w-14 h-14 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 font-bold text-lg">
                {{ strtoupper(substr($attendance->user->name, 0, 2)) }}
            </div>
        @endif
        <div class="flex-1">
            <div class="font-semibold text-gray-800 text-lg">{{ $attendance->user->name }}</div>
            <div class="text-sm text-gray-500">{{ $attendance->user->code }} · {{ $attendance->user->department?->name ?? 'Chưa phân công' }}</div>
        </div>
        <span class="px-3 py-1 rounded-full text-sm font-medium {{ $statusBadge[$attendance->status] ?? 'bg-gray-100 text-gray-600' }}">
            {{ $statusLabel[$attendance->status] ?? $attendance->status }}
        </span>
    </div>

    {{-- Check-in / Check-out --}}
    <div class="grid grid-cols-2 gap-5">
        <div class="bg-white rounded-xl shadow-sm p-5">
            <div class="text-xs text-gray-400 font-medium uppercase tracking-wide mb-3">Check-in</div>
            @if($attendance->check_in_at)
                <div class="text-2xl font-bold text-gray-800 mb-1">{{ $attendance->check_in_at->format('H:i') }}</div>
                <div class="text-sm text-gray-500 mb-3">{{ $attendance->work_date->format('d/m/Y') }}</div>
                @if($attendance->check_in_confidence)
                    <div class="text-xs text-gray-500 mb-3">
                        Độ tin cậy:
                        <span class="font-semibold text-gray-700">{{ number_format($attendance->check_in_confidence * 100, 1) }}%</span>
                    </div>
                @endif
                @if($attendance->check_in_image)
                    <a href="{{ Storage::url($attendance->check_in_image) }}" target="_blank">
                        <img src="{{ Storage::url($attendance->check_in_image) }}" alt="Check-in"
                             class="w-full h-40 object-cover rounded-lg border border-gray-100 hover:opacity-90 transition-opacity cursor-zoom-in">
                    </a>
                @else
                    <div class="w-full h-40 bg-gray-50 rounded-lg border border-gray-100 flex items-center justify-center text-gray-300 text-sm">
                        Không có ảnh
                    </div>
                @endif
            @else
                <div class="text-gray-300 text-sm">Chưa check-in</div>
            @endif
        </div>

        <div class="bg-white rounded-xl shadow-sm p-5">
            <div class="text-xs text-gray-400 font-medium uppercase tracking-wide mb-3">Check-out</div>
            @if($attendance->check_out_at)
                <div class="text-2xl font-bold text-gray-800 mb-1">{{ $attendance->check_out_at->format('H:i') }}</div>
                <div class="text-sm text-gray-500 mb-3">{{ $attendance->work_date->format('d/m/Y') }}</div>
                @if($attendance->check_out_confidence)
                    <div class="text-xs text-gray-500 mb-3">
                        Độ tin cậy:
                        <span class="font-semibold text-gray-700">{{ number_format($attendance->check_out_confidence * 100, 1) }}%</span>
                    </div>
                @endif
                @if($attendance->check_out_image)
                    <a href="{{ Storage::url($attendance->check_out_image) }}" target="_blank">
                        <img src="{{ Storage::url($attendance->check_out_image) }}" alt="Check-out"
                             class="w-full h-40 object-cover rounded-lg border border-gray-100 hover:opacity-90 transition-opacity cursor-zoom-in">
                    </a>
                @else
                    <div class="w-full h-40 bg-gray-50 rounded-lg border border-gray-100 flex items-center justify-center text-gray-300 text-sm">
                        Không có ảnh
                    </div>
                @endif
            @else
                <div class="text-gray-300 text-sm">Chưa check-out</div>
            @endif
        </div>
    </div>

    {{-- Device Info --}}
    @if($attendance->device)
    <div class="bg-white rounded-xl shadow-sm p-5 text-sm text-gray-600">
        Thiết bị: <span class="font-medium text-gray-800">{{ $attendance->device->name }}</span>
        <span class="text-gray-400 ml-2">({{ $attendance->device->location }})</span>
    </div>
    @endif

    {{-- Override Form --}}
    <div class="bg-white rounded-xl shadow-sm p-5">
        <div class="text-sm font-medium text-gray-700 mb-4">Điều chỉnh thủ công</div>

        @if(session('success'))
            <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-800 rounded-lg text-sm">
                {{ session('success') }}
            </div>
        @endif

        <form method="POST" action="{{ route('attendances.update', $attendance) }}" class="space-y-4">
            @csrf @method('PUT')

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Trạng thái</label>
                <select name="status" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                    @foreach($statusLabel as $val => $label)
                        <option value="{{ $val }}" {{ $attendance->status === $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                @error('status') <div class="text-red-500 text-xs mt-1">{{ $message }}</div> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Ghi chú</label>
                <textarea name="note" rows="3"
                          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none resize-none"
                          placeholder="Lý do điều chỉnh...">{{ old('note', $attendance->note) }}</textarea>
                @error('note') <div class="text-red-500 text-xs mt-1">{{ $message }}</div> @enderror
            </div>

            <div class="flex gap-3">
                <button type="submit" class="bg-blue-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-blue-700">
                    Lưu thay đổi
                </button>
                <a href="{{ route('attendances.index', ['date' => $attendance->work_date->toDateString()]) }}"
                   class="bg-gray-100 text-gray-700 px-5 py-2 rounded-lg text-sm hover:bg-gray-200">
                    Quay lại
                </a>
            </div>
        </form>
    </div>

</div>
@endsection
