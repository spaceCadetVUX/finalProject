@php
    $dayLabels = [1=>'Thứ 2',2=>'Thứ 3',3=>'Thứ 4',4=>'Thứ 5',5=>'Thứ 6',6=>'Thứ 7',7=>'Chủ Nhật'];
    $conflictsJson = json_encode($conflicts);
@endphp

@extends('layouts.admin')
@section('title', 'Phân Ca Mới')
@section('header', 'Phân Ca Mới')

@section('content')

<div class="max-w-2xl"
     x-data="shiftAssign({{ $conflictsJson }})"
     x-init="init()">

    {{-- ── Form ──────────────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-xl shadow-sm p-6 space-y-6">

        <form id="assign-form" method="POST" action="{{ route('shift-schedules.store') }}">
            @csrf
            <input type="hidden" name="force" x-model="force">

            {{-- 1. Chọn template ca --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Ca làm việc <span class="text-red-500">*</span>
                </label>
                <div class="grid grid-cols-1 gap-2">
                    @foreach($templates as $t)
                        <label class="flex items-center gap-3 p-3 rounded-lg border-2 cursor-pointer transition-colors
                                      hover:border-blue-300"
                               :class="templateId == {{ $t->id }} ? 'border-blue-500 bg-blue-50' : 'border-gray-200'">
                            <input type="radio" name="shift_template_id" value="{{ $t->id }}"
                                   x-model="templateId" class="sr-only">
                            <span class="w-3 h-3 rounded-full shrink-0" style="background:{{ $t->color }}"></span>
                            <div class="flex-1 min-w-0">
                                <div class="font-medium text-gray-800 text-sm">{{ $t->name }}</div>
                                <div class="text-xs text-gray-500 font-mono">
                                    {{ substr($t->check_in_time,0,5) }} – {{ substr($t->check_out_time,0,5) }}
                                    · biên độ {{ $t->late_tolerance }} phút
                                </div>
                            </div>
                            <svg x-show="templateId == {{ $t->id }}"
                                 class="w-4 h-4 text-blue-600 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                            </svg>
                        </label>
                    @endforeach
                </div>
                @error('shift_template_id')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="border-t border-gray-100"></div>

            {{-- 2. Loại assignee --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Áp dụng cho <span class="text-red-500">*</span>
                </label>
                <div class="flex gap-3">
                    <label class="flex-1 flex items-center gap-2 p-3 rounded-lg border-2 cursor-pointer"
                           :class="assigneeType === 'department' ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-gray-300'">
                        <input type="radio" name="assignee_type" value="department"
                               x-model="assigneeType" class="sr-only">
                        <svg class="w-4 h-4 text-blue-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1"/>
                        </svg>
                        <span class="text-sm font-medium text-gray-700">Phòng ban</span>
                    </label>
                    <label class="flex-1 flex items-center gap-2 p-3 rounded-lg border-2 cursor-pointer"
                           :class="assigneeType === 'employee' ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-gray-300'">
                        <input type="radio" name="assignee_type" value="employee"
                               x-model="assigneeType" class="sr-only">
                        <svg class="w-4 h-4 text-purple-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                        <span class="text-sm font-medium text-gray-700">Nhân viên cụ thể</span>
                    </label>
                </div>
                @error('assignee_type')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- 3. Chọn assignee (search) --}}
            <div x-show="assigneeType">
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    <span x-text="assigneeType === 'department' ? 'Phòng ban' : 'Nhân viên'"></span>
                    <span class="text-red-500">*</span>
                </label>
                <input type="hidden" name="assignee_id" :value="assigneeId">

                {{-- Search box --}}
                <div class="relative">
                    <input type="text"
                           x-model="search"
                           @focus="dropdownOpen = true"
                           @click.outside="dropdownOpen = false"
                           :placeholder="assigneeType === 'department' ? 'Tìm phòng ban...' : 'Tìm theo tên hoặc mã số...'"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">

                    {{-- Selected badge --}}
                    <div x-show="selectedLabel" class="mt-1.5 flex items-center gap-1.5">
                        <span class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full"
                              x-text="selectedLabel"></span>
                        <button type="button" @click="clearAssignee()"
                                class="text-gray-400 hover:text-gray-600 text-xs">✕</button>
                    </div>

                    {{-- Dropdown --}}
                    <div x-show="dropdownOpen && filteredAssignees.length > 0"
                         class="absolute z-10 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                        <template x-for="item in filteredAssignees" :key="item.id">
                            <div @click="selectAssignee(item)"
                                 class="flex items-center gap-2.5 px-3 py-2.5 hover:bg-gray-50 cursor-pointer text-sm border-b border-gray-50 last:border-0">
                                <div class="flex-1 min-w-0">
                                    <div class="font-medium text-gray-800 truncate" x-text="item.name"></div>
                                    <div x-show="item.code" class="text-xs text-gray-400" x-text="'#' + item.code"></div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                @error('assignee_id')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="border-t border-gray-100"></div>

            {{-- 4. Ngày trong tuần --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Áp dụng ngày <span class="text-red-500">*</span>
                </label>
                <div class="flex flex-wrap gap-2">
                    @foreach($dayLabels as $dow => $label)
                        <label class="cursor-pointer">
                            <input type="checkbox" name="days_of_week[]" value="{{ $dow }}"
                                   class="sr-only peer"
                                   {{ in_array($dow, old('days_of_week', [])) ? 'checked' : '' }}>
                            <span class="inline-block px-3 py-1.5 rounded-lg border-2 text-sm font-medium transition-colors
                                         border-gray-200 text-gray-500
                                         peer-checked:border-blue-500 peer-checked:bg-blue-50 peer-checked:text-blue-700">
                                {{ $label }}
                            </span>
                        </label>
                    @endforeach
                </div>
                <div class="flex gap-2 mt-2">
                    <button type="button" onclick="checkDays([1,2,3,4,5])"
                            class="text-xs text-blue-500 hover:underline">T2–T6</button>
                    <button type="button" onclick="checkDays([1,2,3,4,5,6])"
                            class="text-xs text-blue-500 hover:underline">T2–T7</button>
                    <button type="button" onclick="checkDays([1,2,3,4,5,6,7])"
                            class="text-xs text-blue-500 hover:underline">Cả tuần</button>
                </div>
                @error('days_of_week')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- 5. Ngày hiệu lực --}}
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Từ ngày <span class="text-red-500">*</span>
                    </label>
                    <input type="date" name="start_date"
                           value="{{ old('start_date', date('Y-m-d')) }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none
                                  @error('start_date') border-red-400 @enderror">
                    @error('start_date')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Đến ngày
                        <span class="text-xs text-gray-400 font-normal">(trống = vô thời hạn)</span>
                    </label>
                    <input type="date" name="end_date"
                           value="{{ old('end_date') }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none
                                  @error('end_date') border-red-400 @enderror">
                    @error('end_date')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- 6. Ghi chú --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Ghi chú</label>
                <input type="text" name="note" value="{{ old('note') }}"
                       placeholder="Ghi chú thêm (tuỳ chọn)..."
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
            </div>

            <div class="flex gap-3 pt-2">
                <button type="button" @click="submitForm()"
                        class="bg-blue-600 text-white px-6 py-2 rounded-lg text-sm font-medium hover:bg-blue-700">
                    Phân ca
                </button>
                <a href="{{ route('shift-schedules.index') }}"
                   class="bg-gray-100 text-gray-700 px-5 py-2 rounded-lg text-sm hover:bg-gray-200">
                    Huỷ
                </a>
            </div>
        </form>
    </div>

    {{-- ── Conflict Modal ───────────────────────────────────────────────── --}}
    <div x-show="showConflictModal"
         x-cloak
         class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4">

            {{-- Header --}}
            <div class="flex items-center gap-3 px-5 py-4 border-b">
                <div class="w-8 h-8 rounded-full bg-amber-100 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-amber-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <div>
                    <div class="font-semibold text-gray-800">Phát hiện xung đột lịch ca</div>
                    <div class="text-xs text-gray-500" x-text="conflicts.length + ' xung đột được tìm thấy'"></div>
                </div>
            </div>

            {{-- Conflict list --}}
            <div class="px-5 py-4 max-h-64 overflow-y-auto space-y-3">
                <template x-for="(c, i) in conflicts" :key="i">
                    <div class="border border-amber-200 rounded-lg p-3 bg-amber-50 text-sm">
                        <div class="font-medium text-gray-800 mb-1">
                            <span x-text="conflictTypeLabel(c.type)"></span>
                            <span class="font-semibold" x-text="' ' + c.assignee_label"></span>
                        </div>
                        <div class="text-gray-600">
                            Ca <strong x-text="c.template_name"></strong>
                            (<span x-text="c.check_in_time + ' – ' + c.check_out_time"></span>)
                        </div>
                        <div class="text-xs text-gray-500 mt-1">
                            Ngày bị trùng:
                            <span x-text="c.overlap_days.map(d => dayLabel(d)).join(', ')"></span>
                        </div>
                    </div>
                </template>
            </div>

            {{-- Footer --}}
            <div class="px-5 py-4 border-t bg-gray-50 rounded-b-xl">
                <p class="text-xs text-gray-500 mb-3">
                    Bạn có thể override (ghi đè) để tiếp tục phân ca này.
                    Hệ thống sẽ tính ca theo lịch mới nhất khi điểm danh.
                </p>
                <div class="flex gap-3">
                    <button @click="showConflictModal = false; force = '0'"
                            class="flex-1 bg-white border border-gray-300 text-gray-700 py-2 rounded-lg text-sm hover:bg-gray-50">
                        Hủy — Chỉnh lại
                    </button>
                    <button @click="confirmOverride()"
                            class="flex-1 bg-amber-500 hover:bg-amber-600 text-white py-2 rounded-lg text-sm font-medium">
                        Xác nhận Override
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>[x-cloak]{display:none!important}</style>

<script>
function checkDays(days) {
    document.querySelectorAll('input[name="days_of_week[]"]').forEach(cb => {
        cb.checked = days.includes(parseInt(cb.value));
    });
}

function shiftAssign(conflicts) {
    return {
        templateId:        '{{ old("shift_template_id", "") }}',
        assigneeType:      '{{ old("assignee_type", "department") }}',
        assigneeId:        '{{ old("assignee_id", "") }}',
        selectedLabel:     '',
        search:            '',
        dropdownOpen:      false,
        force:             '0',
        showConflictModal: conflicts.length > 0,
        conflicts:         conflicts,

        departments: @json($departments),
        employees:   @json($employees),

        dayNames: {1:'Thứ 2',2:'Thứ 3',3:'Thứ 4',4:'Thứ 5',5:'Thứ 6',6:'Thứ 7',7:'CN'},

        init() {
            // Restore selected label nếu có old input
            const id = parseInt(this.assigneeId);
            if (id) {
                const list = this.assigneeType === 'department' ? this.departments : this.employees;
                const found = list.find(x => x.id === id);
                if (found) this.selectedLabel = found.name + (found.code ? ` (#${found.code})` : '');
            }
        },

        get filteredAssignees() {
            const list = this.assigneeType === 'department' ? this.departments : this.employees;
            const q = this.search.trim().toLowerCase();
            const filtered = q
                ? list.filter(x => x.name.toLowerCase().includes(q) || (x.code || '').toLowerCase().includes(q))
                : list;
            return filtered.slice(0, 10);
        },

        selectAssignee(item) {
            this.assigneeId    = item.id;
            this.selectedLabel = item.name + (item.code ? ` (#${item.code})` : '');
            this.search        = '';
            this.dropdownOpen  = false;
        },

        clearAssignee() {
            this.assigneeId    = '';
            this.selectedLabel = '';
            this.search        = '';
        },

        submitForm() {
            document.getElementById('assign-form').submit();
        },

        confirmOverride() {
            this.force = '1';
            this.showConflictModal = false;
            this.$nextTick(() => this.submitForm());
        },

        conflictTypeLabel(type) {
            const map = {
                'employee_direct':         'Nhân viên',
                'employee_via_department': 'Phòng ban của',
                'department_direct':       'Phòng ban',
                'department_via_employee': 'Nhân viên',
            };
            return map[type] || type;
        },

        dayLabel(d) { return this.dayNames[d] || d; },
    };
}
</script>

@endsection
