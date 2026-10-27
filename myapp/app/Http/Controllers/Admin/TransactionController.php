<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Models\AdminAudit;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Every transaction (expense, income, transfer) of every user, with filters, totals and a CSV export. */
class TransactionController extends Controller
{
    /** The filtered set, unordered. Related rows are read without the per-user scope, or cards and categories would come back empty. */
    private function filtered(Request $request): Builder
    {
        $pesos = fn (?string $v) => is_numeric($v) ? (int) round($v * 100) : null;
        $category = trim((string) $request->query('category'));

        return Transaction::withoutGlobalScopes()
            ->filter(['q' => $request->query('q'), 'from' => $request->query('from'), 'to' => $request->query('to')])
            ->when($request->query('user'), fn ($q, $id) => $q->where('user_id', (int) $id))
            ->when(in_array($request->query('type'), array_column(TransactionType::cases(), 'value'), true), fn ($q) => $q->where('type', $request->query('type')))
            ->when(in_array($request->query('source'), array_column(TransactionSource::cases(), 'value'), true), fn ($q) => $q->where('source', $request->query('source')))
            ->when($category !== '', fn ($q) => $q->whereIn('category_id', Category::withoutGlobalScopes()->whereRaw('lower(name) = ?', [mb_strtolower($category)])->select('id')))
            ->when($pesos($request->query('min')), fn ($q, $c) => $q->where('amount', '>=', $c))
            ->when($pesos($request->query('max')), fn ($q, $c) => $q->where('amount', '<=', $c));
    }

    public function index(Request $request): Response
    {
        $target = $request->query('user') ? User::find($request->query('user')) : null;
        AdminAudit::record($request->user(), 'viewed_transactions', $target, array_filter($request->only('q', 'type', 'source', 'category', 'from', 'to', 'min', 'max')));

        $rows = $this->filtered($request)
            ->with(['user:id,name,email', 'card' => fn ($q) => $q->withoutGlobalScopes(), 'category' => fn ($q) => $q->withoutGlobalScopes()])
            ->latest('occurred_at')->latest('id')->paginate(25)->withQueryString()
            ->through(fn (Transaction $t) => [
                'id' => $t->id, 'date' => $t->occurred_at->toDateString(), 'type' => $t->type->value, 'source' => $t->source->value,
                'merchant' => $t->merchant, 'amount' => $t->amount, 'reference' => $t->reference, 'note' => $t->note,
                'category' => $t->category?->name, 'card' => $t->card?->name, 'user' => $t->user?->only('id', 'name', 'email'),
            ]);

        return Inertia::render('admin/Transactions', [
            'rows' => $rows,
            'totals' => $this->filtered($request)->selectRaw('type, count(*) as count, sum(amount) as total')->groupBy('type')->get()
                ->mapWithKeys(fn ($r) => [$r->type->value => ['count' => (int) $r->count, 'total' => (int) $r->total]]),
            'filters' => $request->only('q', 'user', 'type', 'source', 'category', 'from', 'to', 'min', 'max'),
            'types' => array_column(TransactionType::cases(), 'value'),
            'sources' => array_column(TransactionSource::cases(), 'value'),
            'owner' => $target?->only('id', 'name'),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        AdminAudit::record($request->user(), 'exported_transactions', null, array_filter($request->only('q', 'user', 'type', 'source', 'category', 'from', 'to', 'min', 'max')));

        // a leading = + - @ would run as a formula in a spreadsheet, so those cells start with an apostrophe
        $safe = fn ($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'".$v : $v;

        return response()->streamDownload(function () use ($request, $safe) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['date', 'user', 'email', 'type', 'merchant', 'amount', 'card', 'category', 'source', 'reference', 'note']);

            // ponytail: capped at 20,000 rows so one click cannot hold the server; narrow the filters for more
            $this->filtered($request)->with(['user:id,name,email', 'card' => fn ($q) => $q->withoutGlobalScopes(), 'category' => fn ($q) => $q->withoutGlobalScopes()])
                ->latest('occurred_at')->latest('id')->limit(20_000)->get() // get(), not cursor(): cursor() skips the eager loads above
                ->each(fn (Transaction $t) => fputcsv($out, array_map($safe, [
                    $t->occurred_at->toDateString(), $t->user?->name, $t->user?->email, $t->type->value, $t->merchant, number_format($t->amount / 100, 2, '.', ''),
                    $t->card?->name, $t->category?->name, $t->source->value, $t->reference, $t->note,
                ])));
            fclose($out);
        }, 'whale-wallet-transactions.csv', ['Content-Type' => 'text/csv']);
    }
}
