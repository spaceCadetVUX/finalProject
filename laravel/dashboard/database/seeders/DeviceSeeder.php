<?php

namespace Database\Seeders;

use App\Models\Device;
use Illuminate\Database\Seeder;

class DeviceSeeder extends Seeder
{
    public function run(): void
    {
        Device::firstOrCreate(
            ['token' => 'pi4-device-token-001'],
            [
                'name'     => 'Pi4 - Cổng Chính',
                'location' => 'Lobby',
                'status'   => 'offline',
            ]
        );
    }
}
