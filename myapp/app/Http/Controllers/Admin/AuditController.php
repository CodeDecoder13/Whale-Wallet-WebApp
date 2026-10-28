<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAudit;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Who opened or did what. Read-only: there is no route that edits or deletes a row. */
class AuditController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('admin/Audit', [
            'entries' => AdminAudit::with(['admin:id,name,email', 'target:id,name,email'])
                ->when($request->query('action'), fn ($q, $a) => $q->where('action', $a))
                ->when($request->query('admin'), fn ($q, $id) => $q->where('admin_id', (int) $id))
                ->when($request->query('user'), fn ($q, $id) => $q->where('target_user_id', (int) $id))
                ->latest('id')->paginate(30)->withQueryString()
                ->through(fn ($a) => [
                    'id' => $a->id, 'action' => $a->action, 'meta' => $a->meta, 'ip' => $a->ip, 'at' => $a->created_at->toIso8601String(),
                    'admin' => $a->admin?->only('id', 'name', 'email'), 'target' => $a->target?->only('id', 'name', 'email'),
                ]),
            'actions' => AdminAudit::query()->distinct()->orderBy('action')->pluck('action'),
            'filters' => $request->only('action', 'admin', 'user'),
        ]);
    }
}
