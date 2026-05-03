@extends('layouts.admin')
@section('title', 'Upload Khuôn Mặt')
@section('header', 'Upload Ảnh Khuôn Mặt — ' . $employee->name)

@section('content')
<div class="max-w-lg">
    <div class="bg-white rounded-xl shadow-sm p-6">

        @if($employee->faceEncodings->count() > 0)
        <div class="mb-5 p-3 bg-green-50 border border-green-200 rounded-lg text-sm text-green-800">
            Nhân viên này đã có {{ $employee->faceEncodings->count() }} ảnh khuôn mặt.
            Upload ảnh mới sẽ thêm vào danh sách (để nhận diện chính xác hơn).
        </div>
        @endif

        <div class="mb-5 p-3 bg-blue-50 border border-blue-200 rounded-lg text-sm text-blue-800">
            <strong>Yêu cầu ảnh:</strong> Chụp thẳng mặt, đủ ánh sáng, nền đơn giản. Định dạng JPG/PNG, tối đa 5MB.
        </div>

        <form method="POST" action="{{ route('employees.upload-face', $employee) }}" enctype="multipart/form-data" class="space-y-4">
            @csrf

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Chọn ảnh khuôn mặt <span class="text-red-500">*</span></label>
                <input type="file" name="face_image" accept="image/jpeg,image/png" required
                       class="w-full text-sm text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                @error('face_image')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit" class="bg-blue-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-blue-700">
                    Upload & Mã hóa
                </button>
                <a href="{{ route('employees.index') }}" class="bg-gray-100 text-gray-700 px-5 py-2 rounded-lg text-sm hover:bg-gray-200">
                    Bỏ qua
                </a>
            </div>
        </form>
    </div>
</div>
@endsection
