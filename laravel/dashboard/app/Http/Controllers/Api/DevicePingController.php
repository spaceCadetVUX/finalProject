<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DevicePingController extends Controller
{
    public function __invoke(Request $request)
    {
        // Sprint 3.4
        $device = $request->attributes->get('device');
        $device->update(['last_ping' => now(), 'status' => 'online']);

        return response()->json(['message' => 'pong']);
    }
}
