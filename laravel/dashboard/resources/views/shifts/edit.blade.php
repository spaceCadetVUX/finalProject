@extends('layouts.admin')
@section('title', 'Sửa Ca: ' . $shift->name)
@section('header', 'Sửa Ca: ' . $shift->name)

@section('content')
<div class="max-w-lg">
    <div class="bg-white rounded-xl shadow-sm p-6">
        <form method="POST" action="{{ route('shifts.update', $shift) }}" class="space-y-5"
              x-data="{
                  color:    '{{ old('color', $shift->color) }}',
                  checkIn:  '{{ old('check_in_time',  substr($shift->check_in_time,  0, 5)) }}',
                  checkOut: '{{ old('check_out_time', substr($shift->check_out_time, 0, 5)) }}',
                  get overnight() { return this.checkIn && this.checkOut && this.checkOut <= this.checkIn }
              }">
            @csrf @method('PUT')

            {{-- Cảnh báo nếu có lịch đang dùng --}}
            @if($shift->active_schedules_count > 0)
                <div class="flex items-start gap-2 px-3 py-2.5 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-700">
                    <svg class="w-4 h-4 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                    </svg>
                    Ca này đang có <strong class="mx-1">{{ $shift->active_schedules_count }}</strong> lịch phân ca hoạt động.
                    Thay đổi giờ sẽ ảnh hưởng đến tính toán điểm danh từ lúc này.
                </div>
            @endif

            {{-- Tên ca --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Tên ca <span class="text-red-500">*</span>
                </label>
                <input type="text" name="name" value="{{ old('name', $shift->name) }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none
                              @error('name') border-red-400 @enderror">
                @error('name')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- Giờ vào – ra --}}
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Giờ vào <span class="text-red-500">*</span>
                    </label>
                    <input type="time" name="check_in_time" x-model="checkIn"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none
                                  @error('check_in_time') border-red-400 @enderror">
                    @error('check_in_time')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Giờ ra <span class="text-red-500">*</span>
                    </label>
                    <input type="time" name="check_out_time" x-model="checkOut"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none
                                  @error('check_out_time') border-red-400 @enderror">
                    @error('check_out_time')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Badge ca qua đêm --}}
                <div class="col-span-2" x-show="overnight" x-cloak>
                    <div class="flex items-center gap-2 px-3 py-2 bg-indigo-50 border border-indigo-200 rounded-lg text-xs text-indigo-700">
                        <span>🌙</span>
                        <span>Ca qua đêm — giờ ra tính sang ngày hôm sau</span>
                    </div>
                </div>
            </div>

            {{-- Biên độ trễ + cửa sổ check-in --}}
            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Biên độ trễ (phút) <span class="text-red-500">*</span>
                    </label>
                    <input type="number" name="late_tolerance"
                           value="{{ old('late_tolerance', $shift->late_tolerance) }}"
                           min="0" max="480"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none
                                  @error('late_tolerance') border-red-400 @enderror">
                    @error('late_tolerance')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Vào sớm nhất (phút) <span class="text-red-500">*</span>
                    </label>
                    <input type="number" name="checkin_before"
                           value="{{ old('checkin_before', $shift->checkin_before) }}"
                           min="0" max="480"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none
                                  @error('checkin_before') border-red-400 @enderror">
                    <p class="text-xs text-gray-400 mt-1">Trước giờ vào</p>
                    @error('checkin_before')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Vào trễ nhất (phút) <span class="text-red-500">*</span>
                    </label>
                    <input type="number" name="checkin_after"
                           value="{{ old('checkin_after', $shift->checkin_after) }}"
                           min="0" max="480"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none
                                  @error('checkin_after') border-red-400 @enderror">
                    <p class="text-xs text-gray-400 mt-1">Sau giờ vào</p>
                    @error('checkin_after')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Màu hiển thị --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Màu hiển thị</label>
                <div class="flex items-center gap-3">
                    @foreach(['#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6','#06b6d4','#f97316','#64748b'] as $c)
                        <button type="button"
                                @click="color = '{{ $c }}'"
                                class="w-7 h-7 rounded-full border-2 transition-transform hover:scale-110"
                                :class="color === '{{ $c }}' ? 'border-gray-800 scale-110' : 'border-transparent'"
                                style="background-color: {{ $c }}"></button>
                    @endforeach
                    <label class="w-7 h-7 rounded-full border-2 border-dashed border-gray-300 flex items-center justify-center cursor-pointer hover:border-gray-400 overflow-hidden"
                           title="Chọn màu khác">
                        <input type="color" class="opacity-0 absolute w-px h-px" x-model="color">
                        <span class="text-gray-400 text-xs">+</span>
                    </label>
                </div>
                <input type="hidden" name="color" :value="color">
                <div class="mt-2 flex items-center gap-2">
                    <span class="w-4 h-4 rounded-full" :style="'background:' + color"></span>
                    <span class="text-xs text-gray-500 font-mono" x-text="color"></span>
                </div>
                @error('color')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- Trạng thái --}}
            <div class="flex items-center gap-2">
                <input type="checkbox" name="is_active" id="is_active" value="1"
                       class="rounded border-gray-300 text-blue-600"
                       {{ old('is_active', $shift->is_active) ? 'checked' : '' }}>
                <label for="is_active" class="text-sm text-gray-700">Kích hoạt</label>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit"
                        class="bg-blue-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-blue-700">
                    Lưu thay đổi
                </button>
                <a href="{{ route('shifts.index') }}"
                   class="bg-gray-100 text-gray-700 px-5 py-2 rounded-lg text-sm hover:bg-gray-200">
                    Huỷ
                </a>
            </div>
        </form>
    </div>
</div>
@endsection
