@extends('layouts.admin')
@section('title', '403 — Không có quyền')
@section('header', 'Truy cập bị từ chối')

@section('content')
<div class="max-w-lg mx-auto mt-16 text-center">
    <div class="text-6xl font-bold text-gray-200 mb-4">403</div>
    <div class="text-xl font-semibold text-gray-700 mb-2">Bạn không có quyền truy cập trang này</div>
    <div class="text-sm text-gray-400 mb-8">
        Tài khoản <span class="font-medium text-gray-600">{{ auth()->user()->name }}</span>
        ({{ auth()->user()->role }}) không được phép thực hiện thao tác này.
    </div>
    <a href="{{ route('dashboard') }}"
       class="inline-flex items-center gap-2 bg-blue-600 text-white px-5 py-2.5 rounded-lg text-sm hover:bg-blue-700">
        ← Về Dashboard
    </a>
</div>
@endsection
