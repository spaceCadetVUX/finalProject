<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AuthDeviceController extends Controller
{
    public function __invoke(Request $request)
    {
        $device = $request->attributes->get('device');

        $device->update([
            'status'    => 'online',
            'last_ping' => now(),
        ]);

        return response()->json([
            'id'       => $device->id,
            'name'     => $device->name,
            'location' => $device->location,
            'status'   => $device->status,
        ]);
    }
}
