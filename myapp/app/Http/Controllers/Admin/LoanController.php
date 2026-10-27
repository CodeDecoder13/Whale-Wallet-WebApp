<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RecurringItem;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Every user's loans, subscriptions and bills in one place. */
class LoanController extends Controller
{
    private const KINDS = ['debt', 'subscription', 'bill'];

    /** One table row, the same shape for the list and for a user's page. */
    public static function row(RecurringItem $i): array
    {
        $loan = $i->kind->value === 'debt';
        $done = $loan && $i->installments === 0;

        return [
            'id' => $i->id, 'kind' => $i->kind->value, 'name' => $i->name, 'amount' => $i->amount, 'cadence' => $i->cadence,
            'total' => $i->total_amount, 'remaining' => $loan ? $i->remaining() : null,
            'left' => $loan && $i->installments !== null ? $i->paymentsLeft() : null,
            'next_due_on' => $i->next_due_on?->toDateString(),
            'overdue' => ! $done && ! $i->cancelled_at && ! $i->hidden_at && $i->next_due_on?->lt(today()),
            'status' => $i->cancelled_at ? 'cancelled' : ($i->hidden_at ? 'hidden' : ($done ? 'paid off' : 'active')),
            'user' => $i->relationLoaded('user') ? $i->user->only('id', 'name', 'email') : null,
        ];
    }

    public function index(Request $request): Response
    {
        $kind = in_array($request->query('kind'), self::KINDS, true) ? $request->query('kind') : 'debt';
        $all = fn () => RecurringItem::withoutGlobalScopes()->active();
        $loans = fn () => $all()->where('kind', 'debt')->where(fn ($q) => $q->whereNull('installments')->orWhere('installments', '>', 0));

        $items = RecurringItem::withoutGlobalScopes()->with('user:id,name,email')->where('kind', $kind)
            ->orderBy('next_due_on')->paginate(25)->withQueryString()
            ->through(fn ($i) => self::row($i));

        return Inertia::render('admin/Loans', [
            'kind' => $kind,
            'items' => $items,
            // ponytail: sums in PHP because remaining and monthly cost have rules, not columns; fine to a few thousand rows, then store them
            'totals' => [
                'loans' => $loans()->count(),
                'owed' => (int) $loans()->get()->sum(fn ($i) => $i->remaining()),
                'overdue_loans' => $loans()->where('next_due_on', '<', today())->count(),
                'subscriptions' => $all()->where('kind', 'subscription')->count(),
                'subs_monthly' => (int) $all()->where('kind', 'subscription')->get()->sum(fn ($i) => $i->monthlyAmount()),
            ],
            'popular' => $all()->where('kind', 'subscription')->selectRaw('lower(name) as name, count(*) as users')
                ->groupBy('name')->orderByDesc('users')->orderBy('name')->limit(10)->get(),
        ]);
    }
}
