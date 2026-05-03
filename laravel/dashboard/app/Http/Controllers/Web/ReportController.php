<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;

class ReportController extends Controller
{
    public function index()
    {
        return view('reports.index');
    }

    public function export()
    {
        return redirect()->back();
    }
}
