<?php

namespace App\Http\Controllers;

use App\Actions\LendMoney;
use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Http\Requests\LendRequest;
use App\Models\Card;
use App\Models\Lend;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** "Who Owes You": cash the user lent to people, with the card and proof it went out on, and reminder emails to the borrower. */
class LendController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('lends/Index', [
            'lends' => Lend::orderByRaw('paid_at is not null')->orderBy('due_on')->get()->map(fn ($l) => [
                ...$l->only('id', 'name', 'email', 'amount', 'reference', 'remind_before', 'card_id'),
                'lent_on' => $l->lent_on->toDateString(), 'due_on' => $l->due_on->toDateString(), 'paid' => $l->paid_at !== null,
            ]),
            'cards' => Card::orderBy('name')->get(['id', 'name', 'balance']),
            'consented' => (bool) $request->user()->ai_consent_at,
        ]);
    }

    public function store(LendRequest $request, LendMoney $lend): RedirectResponse
    {
        $lend->handle($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Saved. We emailed them the details.']);

        return back();
    }

    /** Paid back: the money lands on a card and the reminders stop. */
    public function paid(Request $request, Lend $lend): RedirectResponse
    {
        $card_id = $request->validate(['card_id' => ['required', 'integer', Rule::exists('cards', 'id')->where('user_id', $request->user()->id)]])['card_id'];

        DB::transaction(function () use ($lend, $card_id) {
            $lend = Lend::lockForUpdate()->findOrFail($lend->id);

            if ($lend->paid_at) {
                return; // a double tap must not pay twice
            }

            Card::lockForUpdate()->findOrFail($card_id)->increment('balance', $lend->amount);
            // ponytail: deleting this row later does not take the money back off the card
            Transaction::create([
                'card_id' => $card_id, 'type' => TransactionType::Income, 'amount' => $lend->amount, 'merchant' => 'Paid back by '.$lend->name,
                'occurred_at' => now(), 'source' => TransactionSource::Manual,
            ]);
            $lend->update(['paid_at' => now()]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Marked as paid back.']);

        return back();
    }

    public function destroy(Lend $lend): RedirectResponse
    {
        $lend->delete();

        return back();
    }
}
