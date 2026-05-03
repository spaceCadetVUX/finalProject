<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AttendanceApiController extends Controller
{
    public function store(Request $request)
    {
        // Sprint 3.3
        return response()->json(['message' => 'ok'], 201);
    }

    public function batch(Request $request)
    {
        // Sprint 3.3
        return response()->json(['message' => 'ok'], 201);
    }
}
