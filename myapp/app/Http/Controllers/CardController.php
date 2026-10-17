<?php

namespace App\Http\Controllers;

use App\Enums\CardType;
use App\Http\Requests\CardRequest;
use App\Models\Card;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CardController extends Controller
{
    public function index(): Response
    {
        $cards = Card::withSum(
            ['transactions as spent' => fn ($q) => $q->where('type', 'expense')->where('occurred_at', '>=', now()->startOfMonth())],
            'amount',
        )->orderBy('name')->get()->each(fn ($c) => $c->spent = (int) $c->spent);

        return Inertia::render('cards/Index', [
            'cards' => $cards,
            'types' => array_column(CardType::cases(), 'value'),
        ]);
    }

    public function store(CardRequest $request): RedirectResponse
    {
        Card::create($request->validated());

        return back();
    }

    public function update(CardRequest $request, Card $card): RedirectResponse
    {
        $card->update($request->validated());

        return back();
    }

    public function destroy(Card $card): RedirectResponse
    {
        $card->delete();

        return back();
    }
}
