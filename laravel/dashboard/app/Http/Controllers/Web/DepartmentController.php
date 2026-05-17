<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function index()
    {
        $departments = Department::withCount('employees')->with('manager')->orderBy('name')->get();
        return view('departments.index', compact('departments'));
    }

    public function create()
    {
        $managers = User::whereIn('role', ['manager', 'admin', 'super_admin'])->orderBy('name')->get();
        return view('departments.create', compact('managers'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255|unique:departments',
            'description' => 'nullable|string|max:500',
            'manager_id'  => 'nullable|exists:users,id',
        ]);

        Department::create($data);

        return redirect()->route('departments.index')->with('success', 'Thêm phòng ban thành công.');
    }

    public function show(Department $department)
    {
        $department->load(['manager', 'employees' => fn($q) => $q->orderBy('name')]);
        $available = User::whereNull('department_id')
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'name', 'code']);
        return view('departments.show', compact('department', 'available'));
    }

    public function addEmployee(Request $request, Department $department)
    {
        $request->validate(['user_id' => 'required|exists:users,id']);
        User::where('id', $request->user_id)->update(['department_id' => $department->id]);
        return redirect()->route('departments.show', $department)
            ->with('success', 'Đã thêm nhân viên vào phòng ban.');
    }

    public function removeEmployee(Department $department, User $user)
    {
        $user->update(['department_id' => null]);
        return redirect()->route('departments.show', $department)
            ->with('success', 'Đã xóa nhân viên khỏi phòng ban.');
    }

    public function edit(Department $department)
    {
        $managers = User::whereIn('role', ['manager', 'admin', 'super_admin'])->orderBy('name')->get();
        return view('departments.edit', compact('department', 'managers'));
    }

    public function update(Request $request, Department $department)
    {
        $data = $request->validate([
            'name'        => "required|string|max:255|unique:departments,name,{$department->id}",
            'description' => 'nullable|string|max:500',
            'manager_id'  => 'nullable|exists:users,id',
        ]);

        $department->update($data);

        return redirect()->route('departments.index')->with('success', 'Cập nhật thành công.');
    }

    public function destroy(Department $department)
    {
        if ($department->employees()->count() > 0) {
            return redirect()->route('departments.index')
                ->with('error', 'Không thể xóa phòng ban đang có nhân viên.');
        }

        $department->delete();
        return redirect()->route('departments.index')->with('success', 'Đã xóa phòng ban.');
    }
}
