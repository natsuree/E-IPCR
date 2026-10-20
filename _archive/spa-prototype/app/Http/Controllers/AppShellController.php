<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class AppShellController extends Controller
{
    /** Serve the E-IPCR single-page application shell (prototype SPA). */
    public function __invoke(): View
    {
        return view('app');
    }
}
