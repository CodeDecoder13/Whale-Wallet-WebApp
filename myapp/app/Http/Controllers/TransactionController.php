<?php

namespace App\Http\Controllers;

use App\Actions\CreateTransaction;
use App\Actions\DeleteTransaction;
use App\Actions\ParseQuickEntry;
use App\Actions\TransferBetween;
use App\Actions\UpdateTransaction;
use App\Enums\TransactionType;
use App\Http\Requests\QuickEntryRequest;
use App\Http\Requests\TransactionFilterRequest;
use App\Http\Requests\TransactionRequest;
use App\Http\Requests\TransferRequest;
use App\Models\Card;
use App\Models\Category;
use App\Models\Pod;
use App\Models\Receipt;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransactionController extends Controller
{
    public function index(TransactionFilterRequest $request): Response
    {
        $f = $request->safe()->except('per_page');

        $transactions = Transaction::with(['card:id,name', 'category:id,name', 'recurringItem:id,logo'])
            ->filter($f)
            ->latest('occurred_at')->latest('id')
            ->paginate((int) ($request->validated('per_page') ?? 7))->withQueryString();

        return Inertia::render('transactions/Index', [
            'transactions' => $transactions,
            'filters' => (object) $f,
            'cards' => Card::orderBy('name')->get(['id', 'name', 'balance', 'type']),
            'pods' => Pod::orderBy('name')->get(['id', 'name', 'saved_amount']),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'draft' => session('draft'),
            // receipts are scanned from a dialog on this page; the ones still to confirm show as a banner
            'consented' => (bool) $request->user()->ai_consent_at,
            'keepReceipts' => $request->user()->keep_receipts,
            'receipts' => Receipt::where('purpose', 'expense')->where('status', '!=', 'confirmed')->latest('id')->limit(10)->get()->map->toClient()->values(),
        ]);
    }

    /** A printable, document-style list of everything the current filters match; the browser saves it as a PDF. */
    public function report(TransactionFilterRequest $request): Response
    {
        $rows = Transaction::with(['card:id,name', 'category:id,name'])
            ->filter($request->safe()->except('per_page'))
            ->latest('occurred_at')->latest('id')->limit(1000)->get();

        return Inertia::render('transactions/Report', [
            'rows' => $rows->map(fn ($t) => [
                'id' => $t->id, 'type' => $t->type->value, 'merchant' => $t->merchant, 'amount' => $t->amount, 'occurred_at' => $t->occurred_at->toDateString(),
                'card' => $t->card->name, 'category' => $t->category?->name, 'source' => $t->source->value, 'reference' => $t->reference,
            ]),
            'name' => $request->user()->name,
            'generated' => now()->toDateString(),
            'filters' => (object) $request->safe()->except('per_page'),
        ]);
    }

    public function store(TransactionRequest $request, CreateTransaction $create): RedirectResponse
    {
        $create->handle($request->validated());

        return to_route('transactions.index');
    }

    /** Only expenses are editable; a transfer must be deleted and redone. */
    public function update(TransactionRequest $request, Transaction $transaction, UpdateTransaction $update): RedirectResponse
    {
        abort_unless($transaction->type === TransactionType::Expense, 422, __('Transfers cannot be edited.'));
        $update->handle($transaction, $request->validated());

        return back();
    }

    public function destroy(Transaction $transaction, DeleteTransaction $delete): RedirectResponse
    {
        $delete->handle($transaction);

        return back();
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

            foreach ($this->exportRows() as $row) {
                fputcsv($out, array_map($this->safeCell(...), $row));
            }
            fclose($out);
        }, 'transactions.csv', ['Content-Type' => 'text/csv']);
    }

    public function exportMarkdown(): StreamedResponse
    {
        return response()->streamDownload(function () {
            echo "| date | type | merchant | amount | card | category | note | source |\n|---|---|---|---|---|---|---|---|\n";

            foreach ($this->exportRows() as $row) {
                // pipes and line breaks would break the table
                echo '| '.implode(' | ', array_map(fn ($c) => preg_replace('/\s+/', ' ', str_replace('|', '\|', (string) $c)), $row))." |\n";
            }
        }, 'transactions.md', ['Content-Type' => 'text/markdown']);
    }

    /** All of the user's transactions as plain rows, oldest first (shared by both exports). */
    private function exportRows(): \Generator
    {
        foreach (Transaction::with(['card:id,name', 'category:id,name'])->orderBy('occurred_at')->orderBy('id')->lazy() as $t) {
            yield [
                $t->occurred_at->toDateString(),
                $t->type->value,
                $t->merchant,
                number_format($t->amount / 100, 2, '.', ''),
                $t->card->name,
                $t->category?->name,
                $t->note,
                $t->source->value,
            ];
        }
    }

    /** Stops spreadsheets from running user text that starts with a formula character. */
    private function safeCell(?string $v): ?string
    {
        return $v !== null && preg_match('/^[=+\-@\t\r]/', $v) ? "'".$v : $v;
    }
}
