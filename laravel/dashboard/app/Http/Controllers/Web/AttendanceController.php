<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;

class AttendanceController extends Controller
{
    public function index()
    {
        return view('attendances.index');
    }

    public function show($attendance)
    {
        return view('attendances.show');
    }

    public function update($attendance)
    {
        return redirect()->back();
    }
}
