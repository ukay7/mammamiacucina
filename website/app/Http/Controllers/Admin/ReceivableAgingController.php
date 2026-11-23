<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ReceivableAging;
use Illuminate\View\View;

class ReceivableAgingController extends Controller
{
    public function index(ReceivableAging $aging): View
    {
        return view('admin.reports.receivable-aging', ['aging' => $aging->report()]);
    }
}
