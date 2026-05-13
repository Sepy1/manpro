<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class AsetTiController extends Controller
{
    public function dataCenter(): View
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        return view('pages.dashboard.aset-ti.data-center');
    }
}
