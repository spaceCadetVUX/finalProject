@extends('layouts.admin')
@section('title', 'Thêm Ca Làm Việc')
@section('header', 'Thêm Ca Làm Việc')

@section('content')
<div class="max-w-lg">
    <div class="bg-white rounded-xl shadow-sm p-6">
        <form method="POST" action="{{ route('shifts.store') }}" class="space-y-5"
              x-data="{
                  color: '#3b82f6',
                  checkIn:  '{{ old('check_in_time',  '08:00') }}',
                  checkOut: '{{ old('check_out_time', '17:00') }}',
                  get overnight() { return this.checkIn && this.checkOut && this.checkOut <= this.checkIn }
              }">
            @csrf

            {{-- Tên ca --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Tên ca <span class="text-red-500">*</span>
                </label>
                <input type="text" name="name" value="{{ old('name') }}"
                       placeholder="Ca sáng, Ca hành chính..."
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

            {{-- Biên độ trễ --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Biên độ trễ (phút) <span class="text-red-500">*</span>
                </label>
                <input type="number" name="late_tolerance" value="{{ old('late_tolerance', 15) }}"
                       min="0" max="120"
                       class="w-32 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none
                              @error('late_tolerance') border-red-400 @enderror">
                <span class="text-xs text-gray-400 ml-2">Tối đa 120 phút</span>
                @error('late_tolerance')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- Màu hiển thị --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Màu hiển thị</label>
                <div class="flex items-center gap-3">
                    {{-- Swatches nhanh --}}
                    @foreach(['#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6','#06b6d4','#f97316','#64748b'] as $c)
                        <button type="button"
                                @click="color = '{{ $c }}'"
                                class="w-7 h-7 rounded-full border-2 transition-transform hover:scale-110"
                                :class="color === '{{ $c }}' ? 'border-gray-800 scale-110' : 'border-transparent'"
                                style="background-color: {{ $c }}"></button>
                    @endforeach

                    {{-- Custom color picker --}}
                    <label class="w-7 h-7 rounded-full border-2 border-dashed border-gray-300 flex items-center justify-center cursor-pointer hover:border-gray-400 overflow-hidden"
                           title="Chọn màu khác">
                        <input type="color" class="opacity-0 absolute w-px h-px"
                               x-model="color">
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
                       {{ old('is_active', true) ? 'checked' : '' }}>
                <label for="is_active" class="text-sm text-gray-700">Kích hoạt ngay</label>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit"
                        class="bg-blue-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-blue-700">
                    Tạo ca làm việc
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
