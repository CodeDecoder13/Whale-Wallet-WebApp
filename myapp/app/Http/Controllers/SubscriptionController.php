<?php

namespace App\Http\Controllers;

use App\Enums\RecurringKind;
use App\Http\Requests\SubscriptionRequest;
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

        return Inertia::render('subscriptions/Index', [
            'items' => $items->map(fn ($i) => [
                ...$i->only(['id', 'name', 'amount', 'cadence', 'installments']),
                'kind' => $i->kind->value,
                'next_due_on' => $i->next_due_on?->toDateString(),
                'left' => $i->installments ? $i->paymentsLeft() : null,
                'monthly' => $i->installments && ! $i->paymentsLeft() ? 0 : $i->monthlyAmount(),
            ]),
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
