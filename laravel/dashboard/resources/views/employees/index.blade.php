@extends('layouts.admin')
@section('title', 'Nhân Viên')
@section('header', 'Quản Lý Nhân Viên')

@section('content')
<div class="flex items-center justify-between mb-5">
    <form method="GET" class="flex gap-2 flex-1 max-w-2xl">
        <input type="text" name="search" value="{{ request('search') }}"
               placeholder="Tìm tên, mã, email..."
               class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
        <select name="department_id" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
            <option value="">Tất cả phòng ban</option>
            @foreach($departments as $dept)
                <option value="{{ $dept->id }}" {{ request('department_id') == $dept->id ? 'selected' : '' }}>
                    {{ $dept->name }}
                </option>
            @endforeach
        </select>
        <select name="role" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
            <option value="">Tất cả role</option>
            <option value="admin" {{ request('role') == 'admin' ? 'selected' : '' }}>Admin</option>
            <option value="manager" {{ request('role') == 'manager' ? 'selected' : '' }}>Manager</option>
            <option value="employee" {{ request('role') == 'employee' ? 'selected' : '' }}>Nhân viên</option>
        </select>
        <button type="submit" class="bg-gray-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-gray-700">Lọc</button>
    </form>
    <a href="{{ route('employees.create') }}"
       class="ml-4 bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 whitespace-nowrap">
        + Thêm nhân viên
    </a>
</div>

<div class="bg-white rounded-xl shadow-sm overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b">
            <tr>
                <th class="text-left px-4 py-3 text-gray-600 font-medium">Nhân viên</th>
                <th class="text-left px-4 py-3 text-gray-600 font-medium">Mã NV</th>
                <th class="text-left px-4 py-3 text-gray-600 font-medium">Phòng ban</th>
                <th class="text-left px-4 py-3 text-gray-600 font-medium">Role</th>
                <th class="text-left px-4 py-3 text-gray-600 font-medium">Khuôn mặt</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse($employees as $emp)
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3">
                    <div class="flex items-center gap-3">
                        @if($emp->avatar)
                            <img src="{{ Storage::url($emp->avatar) }}" class="w-8 h-8 rounded-full object-cover">
                        @else
                            <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 font-semibold text-xs">
                                {{ strtoupper(substr($emp->name, 0, 2)) }}
                            </div>
                        @endif
                        <div>
                            <div class="font-medium text-gray-800">{{ $emp->name }}</div>
                            <div class="text-gray-400 text-xs">{{ $emp->email }}</div>
                        </div>
                    </div>
                </td>
                <td class="px-4 py-3 text-gray-600">{{ $emp->code }}</td>
                <td class="px-4 py-3 text-gray-600">{{ $emp->department?->name ?? '—' }}</td>
                <td class="px-4 py-3">
                    @php $roleColors = ['super_admin'=>'purple','admin'=>'blue','manager'=>'yellow','employee'=>'gray']; @endphp
                    <span class="px-2 py-0.5 rounded-full text-xs font-medium
                        bg-{{ $roleColors[$emp->role] ?? 'gray' }}-100
                        text-{{ $roleColors[$emp->role] ?? 'gray' }}-700">
                        {{ $emp->role }}
                    </span>
                </td>
                <td class="px-4 py-3">
                    @if($emp->faceEncodings->count() > 0)
                        <span class="text-green-600 text-xs font-medium">✓ Đã có</span>
                    @else
                        <a href="{{ route('employees.show-face', $emp) }}" class="text-orange-500 text-xs hover:underline">Upload ảnh</a>
                    @endif
                </td>
                <td class="px-4 py-3 text-right">
                    <a href="{{ route('employees.edit', $emp) }}" class="text-blue-600 hover:underline text-xs mr-3">Sửa</a>
                    <form method="POST" action="{{ route('employees.destroy', $emp) }}" class="inline"
                          onsubmit="return confirm('Xóa nhân viên {{ $emp->name }}?')">
                        @csrf @method('DELETE')
                        <button class="text-red-500 hover:underline text-xs">Xóa</button>
                    </form>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="px-4 py-8 text-center text-gray-400">Không có nhân viên nào</td>
            </tr>
            @endforelse
        </tbody>
    </table>
    <div class="px-4 py-3 border-t">{{ $employees->links() }}</div>
</div>
@endsection
