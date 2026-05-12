<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ShiftTemplate;
use Illuminate\Http\Request;

class ShiftTemplateController extends Controller
{
    public function index()
    {
        $templates = ShiftTemplate::withCount(['schedules as active_schedules_count' => fn($q) => $q->where('is_active', true)])
            ->orderBy('check_in_time')
            ->get();

        return view('shifts.index', compact('templates'));
    }

    public function create()
    {
        return view('shifts.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'            => 'required|string|max:100|unique:shift_templates',
            'check_in_time'   => 'required|date_format:H:i',
            'check_out_time'  => 'required|date_format:H:i',
            'late_tolerance'  => 'required|integer|min:0|max:480',
            'checkin_before'  => 'required|integer|min:0|max:480',
            'checkin_after'   => 'required|integer|min:0|max:480',
            'color'           => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'is_active'       => 'boolean',
        ]);

        $data['is_active'] = $request->boolean('is_active', true);

        ShiftTemplate::create($data);

        return redirect()->route('shifts.index')->with('success', 'Đã tạo ca làm việc.');
    }

    public function edit(ShiftTemplate $shift)
    {
        $shift->loadCount(['schedules as active_schedules_count' => fn($q) => $q->where('is_active', true)]);
        return view('shifts.edit', compact('shift'));
    }

    public function update(Request $request, ShiftTemplate $shift)
    {
        $data = $request->validate([
            'name'           => "required|string|max:100|unique:shift_templates,name,{$shift->id}",
            'check_in_time'  => 'required|date_format:H:i',
            'check_out_time' => 'required|date_format:H:i',
            'late_tolerance' => 'required|integer|min:0|max:480',
            'checkin_before' => 'required|integer|min:0|max:480',
            'checkin_after'  => 'required|integer|min:0|max:480',
            'color'          => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'is_active'      => 'boolean',
        ]);

        $data['is_active'] = $request->boolean('is_active');

        $shift->update($data);

        return redirect()->route('shifts.index')->with('success', 'Đã cập nhật ca làm việc.');
    }

    public function destroy(ShiftTemplate $shift)
    {
        $active = $shift->schedules()->where('is_active', true)->count();
        if ($active > 0) {
            return redirect()->route('shifts.index')
                ->with('error', "Không thể xóa — ca đang có {$active} lịch phân ca đang hoạt động.");
        }

        $shift->delete();
        return redirect()->route('shifts.index')->with('success', 'Đã xóa ca làm việc.');
    }
}
