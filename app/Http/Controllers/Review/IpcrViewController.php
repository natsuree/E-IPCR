<?php

namespace App\Http\Controllers\Review;

use App\Http\Controllers\Controller;
use App\Models\Ipcr;
use Illuminate\View\View;

/** Read-only view of any submitted IPCR in the reviewer's scope (opened from Analytics). */
class IpcrViewController extends Controller
{
    public function show(Ipcr $ipcr): View
    {
        $this->authorize('view', $ipcr);

        return view('review.ipcr', [
            'ipcr' => $ipcr->load(['user.college', 'user.department', 'period', 'template', 'items', 'reviews.user']),
        ]);
    }
}
