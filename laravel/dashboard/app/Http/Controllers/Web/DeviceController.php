<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;

class DeviceController extends Controller
{
    public function index()
    {
        return view('devices.index');
    }

    public function store()
    {
        return redirect()->back();
    }

    public function destroy($device)
    {
        return redirect()->back();
    }
}
