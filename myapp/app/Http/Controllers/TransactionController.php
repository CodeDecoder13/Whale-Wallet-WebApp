<?php

namespace App\Http\Controllers;

use App\Actions\CreateTransaction;
use App\Actions\ParseQuickEntry;
use App\Actions\TransferBetween;
use App\Http\Requests\QuickEntryRequest;
use App\Http\Requests\TransactionRequest;
use App\Http\Requests\TransferRequest;
use App\Models\Card;
use App\Models\Category;
use App\Models\Pod;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransactionController extends Controller
{
    public function index(Request $request): Response
    {
        $f = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'card_id' => ['nullable', 'integer'],
            'category_id' => ['nullable', 'integer'],
        ]);

        $transactions = Transaction::with(['card:id,name', 'category:id,name'])
            ->when($f['q'] ?? null, fn ($q, $s) => $q->where(
                fn ($q) => $q->whereRaw('lower(merchant) like ?', ['%'.mb_strtolower($s).'%'])
                    ->orWhereRaw('lower(note) like ?', ['%'.mb_strtolower($s).'%'])
            ))
            ->when($f['from'] ?? null, fn ($q, $d) => $q->whereDate('occurred_at', '>=', $d))
            ->when($f['to'] ?? null, fn ($q, $d) => $q->whereDate('occurred_at', '<=', $d))
            ->when($f['card_id'] ?? null, fn ($q, $id) => $q->where('card_id', $id))
            ->when($f['category_id'] ?? null, fn ($q, $id) => $q->where('category_id', $id))
            ->latest('occurred_at')->latest('id')
            ->paginate(20)->withQueryString();

        return Inertia::render('transactions/Index', [
            'transactions' => $transactions,
            'filters' => (object) $f,
            'cards' => Card::orderBy('name')->get(['id', 'name', 'balance']),
            'pods' => Pod::orderBy('name')->get(['id', 'name', 'saved_amount']),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'draft' => session('draft'),
        ]);
    }

    public function store(TransactionRequest $request, CreateTransaction $create): RedirectResponse
    {
        $create->handle($request->validated());

        return to_route('transactions.index');
    }

    public function transfer(TransferRequest $request, TransferBetween $transfer): RedirectResponse
    {
        $transfer->handle(...$request->safe()->only(['from', 'to', 'amount']));

        return to_route('transactions.index');
    }

    /** Parses quick text into a draft; nothing is saved until the user confirms the expense form. */
    public function quick(QuickEntryRequest $request, ParseQuickEntry $parse): RedirectResponse
    {
        $draft = $parse->handle($request->validated('text'));

        if (! $draft) {
            return back()->withErrors(['text' => __('Try something like "Jollibee 250".')]);
        }

        $card = $draft['card'] ? Card::whereRaw('lower(name) = ?', [mb_strtolower($draft['card'])])->first() : null;

        return to_route('transactions.index')->with('draft', [
            'merchant' => $draft['merchant'],
            'amount' => $draft['amount'],
            'card_id' => $card?->id,
        ]);
    }

    public function export(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['date', 'type', 'merchant', 'amount', 'card', 'category', 'note', 'source']);

            foreach (Transaction::with(['card:id,name', 'category:id,name'])->orderBy('occurred_at')->orderBy('id')->lazy() as $t) {
                fputcsv($out, array_map($this->safeCell(...), [
                    $t->occurred_at->toDateString(),
                    $t->type->value,
                    $t->merchant,
                    number_format($t->amount / 100, 2, '.', ''),
                    $t->card->name,
                    $t->category?->name,
                    $t->note,
                    $t->source->value,
                ]));
            }
            fclose($out);
        }, 'transactions.csv', ['Content-Type' => 'text/csv']);
    }

    /** Stops spreadsheets from running user text that starts with a formula character. */
    private function safeCell(?string $v): ?string
    {
        return $v !== null && preg_match('/^[=+\-@\t\r]/', $v) ? "'".$v : $v;
    }
}
