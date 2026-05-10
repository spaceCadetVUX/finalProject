@php use Illuminate\Support\Facades\Storage; @endphp
@extends('layouts.admin')
@section('title', $department->name)
@section('header', $department->name)

@section('content')

{{-- Flash messages --}}
@if(session('success'))
    <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm">
        {{ session('success') }}
    </div>
@endif
@if(session('error'))
    <div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">
        {{ session('error') }}
    </div>
@endif

{{-- Header actions --}}
<div class="flex items-center justify-between mb-5">
    <a href="{{ route('departments.index') }}" class="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1">
        ← Danh sách phòng ban
    </a>
    <a href="{{ route('departments.edit', $department) }}"
       class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700">
        Chỉnh sửa
    </a>
</div>

{{-- Department info card --}}
<div class="bg-white rounded-xl shadow-sm p-5 mb-6">
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div>
            <div class="text-xs text-gray-400 mb-1">Quản lý</div>
            <div class="text-sm font-medium text-gray-800">{{ $department->manager?->name ?? '—' }}</div>
        </div>
        <div>
            <div class="text-xs text-gray-400 mb-1">Giờ vào</div>
            <div class="text-sm font-medium text-gray-800">{{ substr($department->check_in_time, 0, 5) }}</div>
        </div>
        <div>
            <div class="text-xs text-gray-400 mb-1">Giờ ra</div>
            <div class="text-sm font-medium text-gray-800">{{ substr($department->check_out_time, 0, 5) }}</div>
        </div>
        <div>
            <div class="text-xs text-gray-400 mb-1">Biên độ trễ</div>
            <div class="text-sm font-medium text-gray-800">{{ $department->late_tolerance }} phút</div>
        </div>
    </div>
    @if($department->description)
        <div class="mt-4 pt-4 border-t border-gray-100 text-sm text-gray-500">{{ $department->description }}</div>
    @endif
</div>

{{-- Employee list --}}
<div class="bg-white rounded-xl shadow-sm overflow-hidden">
    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
        <div class="font-medium text-gray-800">
            Nhân viên
            <span class="ml-2 text-xs font-normal text-gray-400 bg-gray-100 px-2 py-0.5 rounded-full">
                {{ $department->employees->count() }} người
            </span>
        </div>
        @if($available->count() > 0)
            <button onclick="document.getElementById('modal-add').classList.remove('hidden')"
                    class="bg-blue-600 text-white px-3 py-1.5 rounded-lg text-sm hover:bg-blue-700">
                + Thêm nhân viên
            </button>
        @else
            <span class="text-xs text-gray-400">Không còn nhân viên chưa có phòng ban</span>
        @endif
    </div>

    <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b">
            <tr>
                <th class="text-left px-5 py-3 text-gray-500 font-medium">Nhân viên</th>
                <th class="text-left px-4 py-3 text-gray-500 font-medium">Mã số</th>
                <th class="text-left px-4 py-3 text-gray-500 font-medium">Chức vụ</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse($department->employees as $emp)
            <tr class="hover:bg-gray-50">
                <td class="px-5 py-3">
                    <div class="flex items-center gap-3">
                        @if($emp->avatar)
                            <img src="{{ Storage::url($emp->avatar) }}" class="w-8 h-8 rounded-full object-cover">
                        @else
                            <div class="w-8 h-8 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-xs font-bold">
                                {{ strtoupper(substr($emp->name, 0, 2)) }}
                            </div>
                        @endif
                        <div class="font-medium text-gray-800">{{ $emp->name }}</div>
                    </div>
                </td>
                <td class="px-4 py-3 text-gray-500">#{{ $emp->code }}</td>
                <td class="px-4 py-3">
                    @php
                        $roleMap = [
                            'super_admin' => ['label' => 'Quản Trị Hệ Thống', 'class' => 'bg-red-100 text-red-700'],
                            'admin'       => ['label' => 'Quản Trị Viên',     'class' => 'bg-orange-100 text-orange-700'],
                            'manager'     => ['label' => 'Quản Lý',           'class' => 'bg-blue-100 text-blue-700'],
                            'employee'    => ['label' => 'Nhân Viên',         'class' => 'bg-gray-100 text-gray-600'],
                        ];
                        $r = $roleMap[$emp->role] ?? ['label' => $emp->role, 'class' => 'bg-gray-100 text-gray-600'];
                    @endphp
                    <span class="text-xs px-2 py-0.5 rounded-full {{ $r['class'] }}">{{ $r['label'] }}</span>
                </td>
                <td class="px-4 py-3 text-right">
                    <form method="POST"
                          action="{{ route('departments.employees.remove', [$department, $emp]) }}"
                          onsubmit="return confirm('Xóa {{ $emp->name }} khỏi phòng ban?')"
                          class="inline">
                        @csrf @method('DELETE')
                        <button class="text-red-400 hover:text-red-600 text-xs hover:underline">
                            Xóa khỏi PB
                        </button>
                    </form>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="4" class="px-5 py-10 text-center text-gray-400">
                    Phòng ban chưa có nhân viên nào
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- Modal thêm nhân viên --}}
<div id="modal-add" class="hidden fixed inset-0 bg-black/40 flex items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-sm mx-4 p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-semibold text-gray-800">Thêm nhân viên vào phòng ban</h3>
            <button onclick="document.getElementById('modal-add').classList.add('hidden')"
                    class="text-gray-400 hover:text-gray-600 text-lg leading-none">&times;</button>
        </div>

        <form method="POST" action="{{ route('departments.employees.add', $department) }}">
            @csrf
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Chọn nhân viên</label>
                <select name="user_id" required
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                    <option value="">— Chọn nhân viên —</option>
                    @foreach($available as $u)
                        <option value="{{ $u->id }}">{{ $u->name }} (#{{ $u->code }})</option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-400 mt-1">Chỉ hiển thị nhân viên chưa thuộc phòng ban nào.</p>
            </div>
            <div class="flex gap-3">
                <button type="submit"
                        class="flex-1 bg-blue-600 text-white py-2 rounded-lg text-sm font-medium hover:bg-blue-700">
                    Thêm
                </button>
                <button type="button"
                        onclick="document.getElementById('modal-add').classList.add('hidden')"
                        class="flex-1 bg-gray-100 text-gray-700 py-2 rounded-lg text-sm hover:bg-gray-200">
                    Hủy
                </button>
            </div>
        </form>
    </div>
</div>

@endsection
