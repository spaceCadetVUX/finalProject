@extends('layouts.admin')
@section('title', 'Sửa Phòng Ban')
@section('header', 'Chỉnh Sửa: ' . $department->name)

@section('content')
<div class="max-w-xl">
    <div class="bg-white rounded-xl shadow-sm p-6">
        <form method="POST" action="{{ route('departments.update', $department) }}" class="space-y-5">
            @csrf @method('PUT')

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tên phòng ban <span class="text-red-500">*</span></label>
                <input type="text" name="name" value="{{ old('name', $department->name) }}" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Mô tả</label>
                <textarea name="description" rows="2"
                          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">{{ old('description', $department->description) }}</textarea>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Quản lý phòng ban</label>
                <select name="manager_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                    <option value="">— Chưa chọn —</option>
                    @foreach($managers as $manager)
                        <option value="{{ $manager->id }}" {{ old('manager_id', $department->manager_id) == $manager->id ? 'selected' : '' }}>
                            {{ $manager->name }} ({{ $manager->code }})
                        </option>
                    @endforeach
                </select>
            </div>


            <div class="flex gap-3 pt-2">
                <button type="submit" class="bg-blue-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-blue-700">
                    Lưu thay đổi
                </button>
                <a href="{{ route('departments.index') }}" class="bg-gray-100 text-gray-700 px-5 py-2 rounded-lg text-sm hover:bg-gray-200">
                    Huỷ
                </a>
            </div>
        </form>
    </div>
</div>
@endsection
