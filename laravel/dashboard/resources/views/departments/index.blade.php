@extends('layouts.admin')
@section('title', 'Phòng Ban')
@section('header', 'Quản Lý Phòng Ban')

@section('content')
<div class="flex justify-end mb-5">
    <a href="{{ route('departments.create') }}"
       class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700">
        + Thêm phòng ban
    </a>
</div>

<div class="bg-white rounded-xl shadow-sm overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b">
            <tr>
                <th class="text-left px-4 py-3 text-gray-600 font-medium">Tên phòng ban</th>
                <th class="text-left px-4 py-3 text-gray-600 font-medium">Quản lý</th>
                <th class="text-left px-4 py-3 text-gray-600 font-medium">Giờ làm việc</th>
                <th class="text-left px-4 py-3 text-gray-600 font-medium">Biên độ trễ</th>
                <th class="text-left px-4 py-3 text-gray-600 font-medium">Nhân viên</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse($departments as $dept)
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3">
                    <div class="font-medium text-gray-800">{{ $dept->name }}</div>
                    @if($dept->description)
                        <div class="text-xs text-gray-400">{{ $dept->description }}</div>
                    @endif
                </td>
                <td class="px-4 py-3 text-gray-600">{{ $dept->manager?->name ?? '—' }}</td>
                <td class="px-4 py-3 text-gray-600">
                    {{ substr($dept->check_in_time, 0, 5) }} — {{ substr($dept->check_out_time, 0, 5) }}
                </td>
                <td class="px-4 py-3 text-gray-600">{{ $dept->late_tolerance }} phút</td>
                <td class="px-4 py-3">
                    <span class="font-semibold text-gray-800">{{ $dept->employees_count }}</span>
                    <span class="text-gray-400 text-xs">người</span>
                </td>
                <td class="px-4 py-3 text-right">
                    <a href="{{ route('departments.edit', $dept) }}" class="text-blue-600 hover:underline text-xs mr-3">Sửa</a>
                    <form method="POST" action="{{ route('departments.destroy', $dept) }}" class="inline"
                          onsubmit="return confirm('Xóa phòng ban {{ $dept->name }}?')">
                        @csrf @method('DELETE')
                        <button class="text-red-500 hover:underline text-xs">Xóa</button>
                    </form>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="px-4 py-8 text-center text-gray-400">Chưa có phòng ban nào</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
