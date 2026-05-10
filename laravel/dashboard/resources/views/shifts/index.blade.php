@extends('layouts.admin')
@section('title', 'Ca Làm Việc')
@section('header', 'Quản Lý Ca Làm Việc')

@section('content')
<div class="flex justify-end mb-5">
    <a href="{{ route('shifts.create') }}"
       class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700">
        + Thêm ca làm việc
    </a>
</div>

<div class="bg-white rounded-xl shadow-sm overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b">
            <tr>
                <th class="text-left px-5 py-3 text-gray-500 font-medium">Ca làm việc</th>
                <th class="text-left px-4 py-3 text-gray-500 font-medium">Giờ làm</th>
                <th class="text-left px-4 py-3 text-gray-500 font-medium">Thời lượng</th>
                <th class="text-left px-4 py-3 text-gray-500 font-medium">Biên độ trễ</th>
                <th class="text-left px-4 py-3 text-gray-500 font-medium">Lịch phân ca</th>
                <th class="text-left px-4 py-3 text-gray-500 font-medium">Trạng thái</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse($templates as $t)
            <tr class="hover:bg-gray-50">
                {{-- Tên + color badge --}}
                <td class="px-5 py-3">
                    <div class="flex items-center gap-2.5">
                        <span class="w-3 h-3 rounded-full shrink-0"
                              style="background-color: {{ $t->color }}"></span>
                        <span class="font-medium text-gray-800">{{ $t->name }}</span>
                    </div>
                </td>

                {{-- Giờ vào – ra --}}
                <td class="px-4 py-3 text-gray-600 font-mono text-xs">
                    {{ substr($t->check_in_time, 0, 5) }} – {{ substr($t->check_out_time, 0, 5) }}
                </td>

                {{-- Thời lượng --}}
                <td class="px-4 py-3 text-gray-500">
                    @php
                        $mins = $t->durationMinutes();
                        echo $mins >= 60
                            ? floor($mins / 60) . 'h' . ($mins % 60 ? ' ' . ($mins % 60) . 'p' : '')
                            : $mins . ' phút';
                    @endphp
                </td>

                {{-- Biên độ trễ --}}
                <td class="px-4 py-3 text-gray-500">{{ $t->late_tolerance }} phút</td>

                {{-- Số lịch phân ca đang active --}}
                <td class="px-4 py-3">
                    @if($t->active_schedules_count > 0)
                        <span class="text-blue-600 font-medium">{{ $t->active_schedules_count }}</span>
                        <span class="text-gray-400 text-xs">lịch</span>
                    @else
                        <span class="text-gray-300 text-xs">—</span>
                    @endif
                </td>

                {{-- Status --}}
                <td class="px-4 py-3">
                    @if($t->is_active)
                        <span class="text-xs px-2 py-0.5 rounded-full bg-green-100 text-green-700">Hoạt động</span>
                    @else
                        <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-500">Tạm ngưng</span>
                    @endif
                </td>

                {{-- Actions --}}
                <td class="px-4 py-3 text-right whitespace-nowrap">
                    <a href="{{ route('shifts.edit', $t) }}"
                       class="text-blue-600 hover:underline text-xs mr-3">Sửa</a>
                    <form method="POST" action="{{ route('shifts.destroy', $t) }}" class="inline"
                          onsubmit="return confirm('Xóa ca {{ $t->name }}?')">
                        @csrf @method('DELETE')
                        <button class="text-red-400 hover:underline text-xs">Xóa</button>
                    </form>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" class="px-5 py-10 text-center text-gray-400">
                    Chưa có ca làm việc nào.
                    <a href="{{ route('shifts.create') }}" class="text-blue-500 hover:underline ml-1">Thêm ngay</a>
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
