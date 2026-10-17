<?php

namespace App\Http\Controllers;

use App\Enums\RecurringKind;
use App\Http\Requests\SubscriptionRequest;
use App\Models\RecurringItem;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/** Bills and subscriptions are recurring_items; one page edits both and can flip an item's kind. */
class SubscriptionController extends Controller
{
    private const KINDS = [RecurringKind::Bill, RecurringKind::Subscription];

    public function index(): Response
    {
        $items = RecurringItem::whereIn('kind', self::KINDS)->orderBy('next_due_on')->get();

        return Inertia::render('subscriptions/Index', [
            'items' => $items->map(fn ($i) => [
                ...$i->only(['id', 'name', 'amount', 'cadence']),
                'kind' => $i->kind->value,
                'next_due_on' => $i->next_due_on?->toDateString(),
                'monthly' => $i->monthlyAmount(),
            ]),
        ]);
    }

    public function store(SubscriptionRequest $request): RedirectResponse
    {
        RecurringItem::create($request->validated());

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
        return RecurringItem::whereIn('kind', self::KINDS)->findOrFail($id);
    }
}
