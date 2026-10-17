<?php

namespace App\Http\Controllers;

use App\Enums\RecurringKind;
use App\Actions\PayRecurring;
use App\Http\Requests\PayRequest;
use App\Http\Requests\SubscriptionRequest;
use App\Models\Card;
use App\Models\Transaction;
use Illuminate\Support\Facades\Date;
use App\Models\RecurringItem;
use Illuminate\Http\RedirectResponse;
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

        return Inertia::render('subscriptions/Index', [
            'items' => $items->map(fn ($i) => [
                ...$i->only(['id', 'name', 'amount', 'cadence', 'installments', 'logo', 'card_id']),
                'kind' => $i->kind->value,
                'next_due_on' => $i->next_due_on?->toDateString(),
                'left' => $i->installments !== null ? $i->paymentsLeft() : null,
                'monthly' => $i->installments !== null && ! $i->paymentsLeft() ? 0 : $i->monthlyAmount(),
                'last_paid' => ($p = $paid[$i->id] ?? null) ? Date::parse($p)->toDateString() : null,
            ]),
            'cards' => Card::orderBy('name')->get(['id', 'name', 'balance']),
        ]);
    }

    public function store(SubscriptionRequest $request): RedirectResponse
    {
        RecurringItem::create($this->shape($request->validated()));

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
        $pay->handle($this->find($subscription), $d['card_id'], $d['amount'], $d['paid_on']);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Payment recorded.']);

        return back();
    }

    public function destroy(int $subscription): RedirectResponse
    {
        $this->find($subscription)->delete();

        return back();
    }

    /** Loans pay monthly for a fixed count; anything else repeats with no end. */
    private function shape(array $data): array
    {
        return $data['kind'] === 'debt'
            ? [...$data, 'cadence' => 'monthly']
            : [...$data, 'installments' => null];
    }

    private function find(int $id): RecurringItem
    {
        return RecurringItem::whereIn('kind', self::KINDS)->findOrFail($id);
    }
}
