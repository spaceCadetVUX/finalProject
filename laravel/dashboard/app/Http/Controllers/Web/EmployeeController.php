<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Jobs\EncodeFaceJob;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $query = User::with('department')->whereNotNull('id');

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('code', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%");
            });
        }

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        $employees   = $query->orderBy('name')->paginate(20)->withQueryString();
        $departments = Department::orderBy('name')->get();

        return view('employees.index', compact('employees', 'departments'));
    }

    public function create()
    {
        $departments = Department::orderBy('name')->get();
        return view('employees.create', compact('departments'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'email'         => 'required|email|unique:users',
            'code'          => 'required|string|max:50|unique:users',
            'password'      => 'required|string|min:8',
            'role'          => 'required|in:super_admin,admin,manager,employee',
            'department_id' => 'nullable|exists:departments,id',
            'avatar'        => 'nullable|image|max:2048',
        ]);

        $data['password'] = bcrypt($data['password']);

        if ($request->hasFile('avatar')) {
            $data['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        $employee = User::create($data);

        return redirect()->route('employees.show-face', $employee)
            ->with('success', 'Thêm nhân viên thành công. Vui lòng upload ảnh khuôn mặt.');
    }

    public function edit(User $employee)
    {
        $departments = Department::orderBy('name')->get();
        return view('employees.edit', compact('employee', 'departments'));
    }

    public function update(Request $request, User $employee)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'email'         => "required|email|unique:users,email,{$employee->id}",
            'code'          => "required|string|max:50|unique:users,code,{$employee->id}",
            'role'          => 'required|in:super_admin,admin,manager,employee',
            'department_id' => 'nullable|exists:departments,id',
            'avatar'        => 'nullable|image|max:2048',
        ]);

        if ($request->filled('password')) {
            $request->validate(['password' => 'min:8']);
            $data['password'] = bcrypt($request->password);
        }

        if ($request->hasFile('avatar')) {
            if ($employee->avatar) Storage::disk('public')->delete($employee->avatar);
            $data['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        $employee->update($data);

        return redirect()->route('employees.index')->with('success', 'Cập nhật thành công.');
    }

    public function destroy(User $employee)
    {
        $employee->delete(); // soft delete
        return redirect()->route('employees.index')->with('success', 'Đã xóa nhân viên.');
    }

    public function showFace(User $employee)
    {
        return view('employees.face', compact('employee'));
    }

    public function uploadFace(Request $request, User $employee)
    {
        $request->validate([
            'face_image' => 'required|image|mimes:jpg,jpeg,png|max:5120',
        ]);

        $path = $request->file('face_image')->store("faces/{$employee->id}", 'local');

        EncodeFaceJob::dispatch($employee, $path);

        return redirect()->route('employees.index')
            ->with('success', 'Ảnh đã upload. Hệ thống đang mã hóa khuôn mặt...');
    }
}
