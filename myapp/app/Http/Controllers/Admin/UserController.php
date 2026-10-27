<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AdminAudit;
use App\Models\RecurringItem;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $counts = fn (?string $kind = null) => fn ($q) => $q->withoutGlobalScopes()->when($kind, fn ($q) => $q->where('kind', $kind));
        $active30 = DB::table('sessions')->whereNotNull('user_id')->where('last_activity', '>=', now()->subDays(30)->timestamp)->select('user_id');
        $term = '%'.mb_strtolower(trim((string) $request->query('q'))).'%';

        $users = User::query()
            ->select('users.*')
            ->addSelect(['last_active' => DB::table('sessions')->selectRaw('max(last_activity)')->whereColumn('user_id', 'users.id')])
            ->withCount(['cards' => $counts(), 'transactions' => $counts(), 'recurringItems as loans_count' => $counts('debt'), 'recurringItems as subs_count' => $counts('subscription')])
            ->when($request->query('q'), fn ($q) => $q->where(fn ($q) => $q->whereRaw('lower(name) like ?', [$term])->orWhereRaw('lower(email) like ?', [$term])))
            ->when($request->query('filter') === 'verified', fn ($q) => $q->whereNotNull('email_verified_at'))
            ->when($request->query('filter') === 'unverified', fn ($q) => $q->whereNull('email_verified_at'))
            ->when($request->query('filter') === 'inactive', fn ($q) => $q->whereNotIn('id', $active30))
            ->when($request->query('filter') === 'suspended', fn ($q) => $q->whereNotNull('suspended_at'))
            ->latest('id')
            ->paginate(25)->withQueryString()
            ->through(fn ($u) => [
                'id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'joined' => $u->created_at->toDateString(),
                'verified' => $u->email_verified_at !== null, 'suspended' => $u->suspended_at !== null, 'admin_role' => $u->admin_role,
                'last_active' => $u->last_active ? (int) $u->last_active : null,
                'cards' => $u->cards_count, 'transactions' => $u->transactions_count, 'loans' => $u->loans_count, 'subs' => $u->subs_count,
            ]);

        return Inertia::render('admin/Users', ['users' => $users, 'filters' => $request->only('q', 'filter')]);
    }

    public function show(Request $request, User $user): Response
    {
        AdminAudit::record($request->user(), 'viewed_user', $user);

        $items = RecurringItem::withoutGlobalScopes()->where('user_id', $user->id)->whereIn('kind', ['debt', 'subscription', 'bill'])->orderBy('next_due_on')->get();

        return Inertia::render('admin/UserShow', [
            'profile' => [
                'id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'joined' => $user->created_at->toDateString(),
                'verified' => $user->email_verified_at !== null, 'suspended' => $user->suspended_at !== null, 'admin_role' => $user->admin_role,
                'has_pin' => (bool) $user->pin, 'has_2fa' => $user->two_factor_confirmed_at !== null,
                'ai_consent' => $user->ai_consent_at !== null, 'digest_enabled' => (bool) $user->digest_enabled, 'digest_time' => $user->digest_time,
                'pay_dates' => $user->pay_dates ?? [],
            ],
            'counts' => [
                'cards' => $user->cards()->withoutGlobalScopes()->count(),
                'transactions' => $user->transactions()->withoutGlobalScopes()->count(),
                'pods' => $user->pods()->withoutGlobalScopes()->count(),
                'receipts' => $user->receipts()->withoutGlobalScopes()->count(),
                'chats' => $user->chatThreads()->withoutGlobalScopes()->count(),
                'devices' => $user->devices()->withoutGlobalScopes()->count(),
            ],
            'sessions' => DB::table('sessions')->where('user_id', $user->id)->latest('last_activity')->limit(10)->get(['ip_address', 'user_agent', 'last_activity']),
            'items' => $items->map(fn ($i) => LoanController::row($i)),
            'activity' => ActivityLog::where('user_id', $user->id)->latest('id')->limit(20)->get(['event', 'meta', 'ip', 'created_at']),
        ]);
    }
}
