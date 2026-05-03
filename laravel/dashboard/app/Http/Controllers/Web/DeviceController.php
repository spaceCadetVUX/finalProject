<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DeviceController extends Controller
{
    public function index()
    {
        $devices = Device::orderBy('name')->get();

        return view('devices.index', compact('devices'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'     => 'required|string|max:100',
            'location' => 'required|string|max:100',
        ]);

        $token = 'pi-' . Str::lower(Str::random(32));

        $device = Device::create([
            'name'     => $data['name'],
            'location' => $data['location'],
            'token'    => $token,
            'status'   => 'offline',
        ]);

        return redirect()->route('devices.index')
            ->with('new_token', $token)
            ->with('new_device', $device->name);
    }

    public function destroy(Device $device)
    {
        $device->delete();

        return redirect()->route('devices.index')
            ->with('success', "Đã xóa thiết bị \"{$device->name}\".");
    }
}
