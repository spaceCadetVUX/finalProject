<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        Department::insert([
            [
                'name'           => 'Phòng Kỹ Thuật',
                'description'    => 'Bộ phận phát triển và vận hành hệ thống',
                'check_in_time'  => '08:00:00',
                'check_out_time' => '17:00:00',
                'late_tolerance' => 15,
                'created_at'     => now(),
                'updated_at'     => now(),
            ],
            [
                'name'           => 'Phòng Kinh Doanh',
                'description'    => 'Bộ phận kinh doanh và chăm sóc khách hàng',
                'check_in_time'  => '08:30:00',
                'check_out_time' => '17:30:00',
                'late_tolerance' => 10,
                'created_at'     => now(),
                'updated_at'     => now(),
            ],
            [
                'name'           => 'Phòng Hành Chính',
                'description'    => 'Bộ phận hành chính và nhân sự',
                'check_in_time'  => '08:00:00',
                'check_out_time' => '17:00:00',
                'late_tolerance' => 15,
                'created_at'     => now(),
                'updated_at'     => now(),
            ],
        ]);
    }
}
