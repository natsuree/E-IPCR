<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AuditFilterRequest;
use App\Models\AuditLog;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(AuditFilterRequest $request): View
    {
        $filters = $request->validated();

        return view('admin.audit', [
            'logs' => AuditLog::with('user')
                ->when($filters['group'] ?? null, fn ($q, $group) => $q->where('event', 'like', $group.'.%'))
                ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w
                    ->where('description', 'like', "%{$term}%")
                    ->orWhereHas('user', fn ($u) => $u->withTrashed()->where('name', 'like', "%{$term}%"))))
                ->when($filters['from'] ?? null, fn ($q, $from) => $q->where('created_at', '>=', $from.' 00:00:00'))
                ->when($filters['to'] ?? null, fn ($q, $to) => $q->where('created_at', '<=', $to.' 23:59:59'))
                ->latest('id')
                ->paginate(30)
                ->withQueryString(),
            'filters' => $filters,
        ]);
    }
}
