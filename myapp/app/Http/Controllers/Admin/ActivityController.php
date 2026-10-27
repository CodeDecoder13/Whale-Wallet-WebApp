<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActivityController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('admin/Activity', [
            'events' => ActivityLog::with('user:id,name,email')
                ->when($request->query('event'), fn ($q, $e) => $q->where('event', $e))
                ->latest('id')->paginate(30)->withQueryString()
                ->through(fn ($a) => [
                    'id' => $a->id, 'event' => $a->event, 'meta' => $a->meta, 'ip' => $a->ip,
                    'at' => $a->created_at->toIso8601String(), 'user' => $a->user?->only('id', 'name', 'email'),
                ]),
            'names' => ActivityLog::query()->distinct()->orderBy('event')->pluck('event'),
            'filters' => $request->only('event'),
        ]);
    }
}
