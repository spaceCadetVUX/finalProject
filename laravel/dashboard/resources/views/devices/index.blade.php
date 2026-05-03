@extends('layouts.admin')
@section('title', 'Thiết Bị')
@section('header', 'Quản Lý Thiết Bị')

@section('content')
<div x-data="{
    confirmOpen: false,
    confirmName: '',
    confirmForm: null,
    openConfirm(name, formEl) {
        this.confirmName = name;
        this.confirmForm = formEl;
        this.confirmOpen = true;
    },
    doDelete() {
        if (this.confirmForm) this.confirmForm.submit();
    }
}" @keydown.escape.window="confirmOpen = false">

{{-- Delete confirm modal --}}
<div x-show="confirmOpen"
     x-transition:enter="transition ease-out duration-150"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-100"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4"
     style="display:none;">
    <div @click.stop
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         class="bg-white rounded-xl shadow-xl p-6 max-w-sm w-full">
        <div class="flex items-center gap-3 mb-3">
            <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
            </div>
            <div>
                <div class="font-semibold text-gray-800">Xóa thiết bị</div>
                <div class="text-sm text-gray-500">Hành động này không thể hoàn tác</div>
            </div>
        </div>
        <p class="text-sm text-gray-600 mb-5">
            Bạn có chắc muốn xóa thiết bị
            <span class="font-medium text-gray-800" x-text="'«' + confirmName + '»'"></span>?
            Các bản ghi chấm công liên quan vẫn được giữ lại.
        </p>
        <div class="flex gap-3 justify-end">
            <button @click="confirmOpen = false"
                    class="px-4 py-2 text-sm text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200">
                Hủy
            </button>
            <button @click="doDelete()"
                    class="px-4 py-2 text-sm text-white bg-red-600 rounded-lg hover:bg-red-700">
                Xóa thiết bị
            </button>
        </div>
    </div>
</div>

{{-- New token banner (shown once after creating a device) --}}
@if(session('new_token'))
<div class="mb-5 bg-blue-50 border border-blue-200 rounded-xl p-4"
     x-data="{ copied: false }">
    <div class="flex items-start gap-3">
        <svg class="w-5 h-5 text-blue-500 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
        </svg>
        <div class="flex-1 min-w-0">
            <div class="font-medium text-blue-800 text-sm">Thiết bị "{{ session('new_device') }}" đã được tạo</div>
            <div class="text-xs text-blue-600 mt-0.5">Sao chép token bên dưới và cấu hình vào Pi. Token chỉ hiển thị một lần.</div>
            <div class="mt-2 flex items-center gap-2">
                <code class="flex-1 min-w-0 bg-white border border-blue-200 rounded-lg px-3 py-1.5 text-sm font-mono text-blue-900 break-all select-all">{{ session('new_token') }}</code>
                <button @click="navigator.clipboard.writeText('{{ session('new_token') }}'); copied = true; setTimeout(() => copied = false, 2000)"
                        class="shrink-0 px-3 py-1.5 bg-blue-600 text-white text-xs rounded-lg hover:bg-blue-700 transition-colors">
                    <span x-show="!copied">Sao chép</span>
                    <span x-show="copied">✓ Đã sao chép</span>
                </button>
            </div>
        </div>
    </div>
</div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

    {{-- Device list --}}
    <div class="lg:col-span-2">
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b bg-gray-50 text-xs text-gray-500">
                {{ $devices->count() }} thiết bị
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="text-left px-4 py-3 text-gray-600 font-medium">Thiết bị</th>
                            <th class="text-left px-4 py-3 text-gray-600 font-medium">Token</th>
                            <th class="text-left px-4 py-3 text-gray-600 font-medium">Trạng thái</th>
                            <th class="text-left px-4 py-3 text-gray-600 font-medium">Last ping</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($devices as $dev)
                        @php
                            $isOnline = $dev->status === 'online'
                                && $dev->last_ping
                                && $dev->last_ping->gt(now()->subMinutes(5));
                        @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-800">{{ $dev->name }}</div>
                                <div class="text-xs text-gray-400">{{ $dev->location }}</div>
                            </td>
                            <td class="px-4 py-3" x-data="{ copied: false }">
                                <div class="flex items-center gap-1.5">
                                    <code class="text-xs text-gray-500 font-mono">{{ substr($dev->token, 0, 12) }}…</code>
                                    <button @click="navigator.clipboard.writeText('{{ $dev->token }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                            class="text-xs text-blue-500 hover:text-blue-700">
                                        <span x-show="!copied">copy</span>
                                        <span x-show="copied" class="text-green-500">✓</span>
                                    </button>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                @if($isOnline)
                                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">
                                        <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span>
                                        Online
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-600">
                                        <span class="w-1.5 h-1.5 rounded-full bg-red-400"></span>
                                        Offline
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-500">
                                @if($dev->last_ping)
                                    <div>{{ $dev->last_ping->format('H:i') }}</div>
                                    <div class="text-xs text-gray-400">{{ $dev->last_ping->diffForHumans() }}</div>
                                @else
                                    <span class="text-gray-300 text-xs">Chưa kết nối</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <form id="delete-device-{{ $dev->id }}"
                                      method="POST" action="{{ route('devices.destroy', $dev) }}"
                                      data-no-loading>
                                    @csrf @method('DELETE')
                                </form>
                                <button type="button"
                                        @click="openConfirm('{{ addslashes($dev->name) }}', document.getElementById('delete-device-{{ $dev->id }}'))"
                                        class="text-red-400 hover:text-red-600 text-xs hover:underline">
                                    Xóa
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="px-4 py-10 text-center text-gray-400 text-sm">
                                Chưa có thiết bị nào. Thêm thiết bị đầu tiên →
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Add device form --}}
    <div>
        <div class="bg-white rounded-xl shadow-sm p-5">
            <div class="text-sm font-medium text-gray-700 mb-4">Thêm thiết bị mới</div>

            <form method="POST" action="{{ route('devices.store') }}" class="space-y-4">
                @csrf

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tên thiết bị <span class="text-red-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name') }}"
                           placeholder="VD: Pi4 - Cổng Chính"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none
                                  {{ $errors->has('name') ? 'border-red-400' : '' }}">
                    @error('name') <div class="text-red-500 text-xs mt-1">{{ $message }}</div> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Vị trí <span class="text-red-500">*</span></label>
                    <input type="text" name="location" value="{{ old('location') }}"
                           placeholder="VD: Lobby, Tầng 1"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none
                                  {{ $errors->has('location') ? 'border-red-400' : '' }}">
                    @error('location') <div class="text-red-500 text-xs mt-1">{{ $message }}</div> @enderror
                </div>

                <div class="bg-gray-50 rounded-lg px-3 py-2.5 text-xs text-gray-500 border border-gray-100">
                    Token sẽ được tạo tự động và hiển thị sau khi thêm thiết bị.
                </div>

                <button type="submit"
                        class="w-full bg-blue-600 text-white py-2 rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors">
                    Thêm thiết bị
                </button>
            </form>
        </div>

        {{-- Pi config hint --}}
        <div class="mt-4 bg-gray-50 rounded-xl p-4 text-xs text-gray-500 space-y-1.5 border border-gray-100">
            <div class="font-medium text-gray-700 text-sm mb-2">Cấu hình Pi</div>
            <div>1. Sao chép token sau khi tạo thiết bị</div>
            <div>2. Dán vào <code class="bg-gray-200 px-1 rounded">config.py</code> trên Pi:</div>
            <code class="block bg-gray-200 rounded px-2 py-1 mt-1 text-gray-700">DEVICE_TOKEN = "your-token"</code>
            <div class="mt-1.5">3. Khởi động lại service trên Pi</div>
        </div>
    </div>

</div>
</div>
@endsection
