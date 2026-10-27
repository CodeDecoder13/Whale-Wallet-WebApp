<?php

namespace App\Http\Controllers;

use App\Enums\RecurringKind;
use App\Actions\PayRecurring;
use App\Http\Requests\PayRequest;
use App\Http\Requests\SubscriptionRequest;
use App\Models\ActivityLog;
use App\Models\Card;
use App\Models\Transaction;
use Illuminate\Support\Facades\Date;
use App\Models\RecurringItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Bills, subscriptions and loans (kind debt) are recurring_items; one page edits them and can flip bill/subscription. */
class SubscriptionController extends Controller
{
    private const KINDS = [RecurringKind::Bill, RecurringKind::Subscription, RecurringKind::Debt];

    public function index(): Response
    {
        $items = RecurringItem::whereIn('kind', self::KINDS)->orderBy('next_due_on')->get();
        $paid = Transaction::whereIn('recurring_item_id', $items->modelKeys())
            ->selectRaw('recurring_item_id, max(occurred_at) as last')->groupBy('recurring_item_id')->pluck('last', 'recurring_item_id');

        // payment history for the detail panel: the latest 10 per item
        $payments = Transaction::with('card:id,name')->whereIn('recurring_item_id', $items->modelKeys())
            ->latest('occurred_at')->latest('id')->limit(500)->get()->groupBy('recurring_item_id');

        return Inertia::render('subscriptions/Index', [
            'items' => $items->map(fn ($i) => [
                'payments' => ($payments[$i->id] ?? collect())->take(10)->map(fn ($t) => [
                    'id' => $t->id, 'amount' => $t->amount, 'card' => $t->card->name, 'paid_on' => $t->occurred_at->toDateString(),
                ])->values(),
                ...$i->only(['id', 'name', 'amount', 'cadence', 'installments', 'total_amount', 'logo', 'card_id', 'cancel_reason']),
                'cancelled' => $i->cancelled_at !== null,
                'hidden' => $i->hidden_at !== null,
                'kind' => $i->kind->value,
                'next_due_on' => $i->next_due_on?->toDateString(),
                'left' => $i->installments !== null ? $i->paymentsLeft() : null,
                // hidden items stay visible but count for nothing; hidden_monthly is what hiding them takes off
                'monthly' => $i->cancelled_at || $i->hidden_at || ($i->installments !== null && ! $i->paymentsLeft()) ? 0 : $i->monthlyAmount(),
                'hidden_monthly' => $i->hidden_at && ! $i->cancelled_at && ($i->installments === null || $i->paymentsLeft()) ? $i->monthlyAmount() : 0,
                'last_paid' => ($p = $paid[$i->id] ?? null) ? Date::parse($p)->toDateString() : null,
            ]),
            'cards' => Card::orderBy('name')->get(['id', 'name', 'balance']),
            'consented' => (bool) request()->user()->ai_consent_at,
        ]);
    }

    public function store(SubscriptionRequest $request): RedirectResponse
    {
        $item = RecurringItem::create($this->shape($request->validated()));
        ActivityLog::record($item->kind->value.'_added', $request->user()->id, ['name' => $item->name]);

        return back();
    }

    public function update(SubscriptionRequest $request, int $subscription): RedirectResponse
    {
        $this->find($subscription)->update($this->shape($request->validated()));

        return back();
    }

    /** Marks one payment as made: an expense on the chosen card, and the item moves to its next due date. */
    public function pay(PayRequest $request, int $subscription, PayRecurring $pay): RedirectResponse
    {
        $d = $request->validated();
        $pay->handle($this->find($subscription), $d['card_id'], $d['amount'], $d['paid_on'], reference: $d['reference'] ?? null, receiptId: $d['receipt_id'] ?? null);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Payment recorded.']);

        return back();
    }

    /** A cancelled subscription stays on file with its reason and payment history, so it can be renewed later. */
    public function cancel(Request $request, int $subscription): RedirectResponse
    {
        $this->find($subscription)->cancel($request->validate(['reason' => ['required', 'string', 'max:255']])['reason']);

        return back();
    }

    /** Hide for now: the item stays on file but drops out of every calculation until it is shown again. */
    public function hide(int $subscription): RedirectResponse
    {
        $this->find($subscription)->hide();

        return back();
    }

    public function show(int $subscription): RedirectResponse
    {
        $this->find($subscription)->hide(false);

        return back();
    }

    public function renew(int $subscription): RedirectResponse
    {
        $this->find($subscription)->renew();

        return back();
    }

    public function destroy(int $subscription): RedirectResponse
    {
        $this->find($subscription)->delete();

        return back();
    }

    /** A loan is entered as its total and paid monthly (total / count, rounded up); anything else repeats with no end. */
    private function shape(array $data): array
    {
        return $data['kind'] === 'debt'
            ? [...$data, 'cadence' => 'monthly', 'total_amount' => $data['amount'], 'amount' => (int) ceil($data['amount'] / $data['installments'])]
            : [...$data, 'installments' => null, 'total_amount' => null];
    }

    private function find(int $id): RecurringItem
    {
        return RecurringItem::whereIn('kind', self::KINDS)->findOrFail($id);
    }
}
