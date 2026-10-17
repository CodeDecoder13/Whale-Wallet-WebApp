<?php

namespace App\Http\Controllers;

use App\Enums\RecurringKind;
use App\Http\Requests\SubscriptionRequest;
use App\Models\RecurringItem;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/** Subscriptions are recurring_items with kind=subscription. */
class SubscriptionController extends Controller
{
    public function index(): Response
    {
        $items = RecurringItem::where('kind', RecurringKind::Subscription)->orderBy('next_due_on')->get();

        return Inertia::render('subscriptions/Index', [
            'subscriptions' => $items->map(fn ($i) => [
                ...$i->only(['id', 'name', 'amount', 'cadence']),
                'next_due_on' => $i->next_due_on?->toDateString(),
                'monthly' => $i->monthlyAmount(),
            ]),
            'monthly_total' => $items->sum(fn ($i) => $i->monthlyAmount()),
        ]);
    }

    public function store(SubscriptionRequest $request): RedirectResponse
    {
        RecurringItem::create([...$request->validated(), 'kind' => RecurringKind::Subscription]);

        return back();
    }

    public function update(SubscriptionRequest $request, int $subscription): RedirectResponse
    {
        $this->find($subscription)->update($request->validated());

        return back();
    }

    public function destroy(int $subscription): RedirectResponse
    {
        $this->find($subscription)->delete();

        return back();
    }

    private function find(int $id): RecurringItem
    {
        return RecurringItem::where('kind', RecurringKind::Subscription)->findOrFail($id);
    }
}
