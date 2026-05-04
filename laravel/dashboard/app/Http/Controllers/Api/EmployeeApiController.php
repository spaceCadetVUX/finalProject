<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FaceEncoding;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EmployeeApiController extends Controller
{
    /**
     * Tạo nhân viên mới từ Pi4 kèm face encoding.
     * Pi4 tự encode ảnh và gửi encoding vector lên đây.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'code'     => 'required|string|max:50|unique:users,code',
            'encoding' => 'required|array|min:128|max:128',
            'encoding.*' => 'required|numeric',
        ]);

        $user = User::create([
            'name'     => $data['name'],
            'code'     => $data['code'],
            // email tự sinh để tránh NULL constraint, không dùng đăng nhập
            'email'    => Str::slug($data['code']) . '@device.local',
            'password' => bcrypt(Str::random(24)),
            'role'     => 'employee',
        ]);

        FaceEncoding::create([
            'user_id'    => $user->id,
            'encoding'   => $data['encoding'],
            'image_path' => null,
        ]);

        return response()->json([
            'user_id' => $user->id,
            'name'    => $user->name,
            'code'    => $user->code,
        ], 201);
    }
}
