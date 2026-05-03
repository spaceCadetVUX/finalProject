<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Super Admin — không thuộc phòng ban
        User::create([
            'name'          => 'Super Admin',
            'email'         => 'admin@attendance.com',
            'code'          => 'AD001',
            'password'      => bcrypt('password'),
            'role'          => 'super_admin',
            'department_id' => null,
        ]);

        // Manager phòng Kỹ Thuật
        User::create([
            'name'          => 'Nguyễn Văn Manager',
            'email'         => 'manager.kt@attendance.com',
            'code'          => 'KT001',
            'password'      => bcrypt('password'),
            'role'          => 'manager',
            'department_id' => 1,
        ]);

        // Nhân viên mẫu
        User::create([
            'name'          => 'Trần Thị Nhân Viên',
            'email'         => 'nv001@attendance.com',
            'code'          => 'KT002',
            'password'      => bcrypt('password'),
            'role'          => 'employee',
            'department_id' => 1,
        ]);
    }
}
