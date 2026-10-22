<?php

namespace App\Actions;

use App\Enums\CardType;
use App\Enums\RecurringKind;
use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Models\Card;
use App\Models\Category;
use App\Models\IncomeSource;
use App\Models\Pod;
use App\Models\RecurringItem;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Loads a Whale Wallet export (Settings, Data) into the signed-in account, either merged with what is there
 * or, with $replace, as an exact copy (the account's cards, bills, pods, categories, transactions and income are cleared first).
 * When merging, rows that are already there are matched, not duplicated, so importing the same file twice is safe.
 * Ids in the file are only labels: each is mapped to the new row's id before anything that points at it is written.
 * Card balances come from the file for new cards and are never changed for existing ones; imported
 * transactions do not touch balances (the exported balance already includes them).
 * Receipt images, chat history and tips are not part of an import.
 */
class ImportUserData
{
    private const MAX_ROWS = 20000;

    /** @return array<string, int> what was added, and "skipped" for rows that were already there */
    public function handle(User $user, array $data, bool $replace = false): array
    {
        $d = $this->validate($data);
        $new = ['cards' => 0, 'pods' => 0, 'categories' => 0, 'recurring_items' => 0, 'transactions' => 0, 'income_sources' => 0, 'skipped' => 0];
        $map = ['categories' => [], 'cards' => [], 'pods' => [], 'recurring_items' => []];
        $id = function (string $table, $old) use (&$map) { // by reference: the map fills up as tables are imported
            return $old === null ? null : ($map[$table][$old] ?? null);
        };

        DB::transaction(function () use ($user, $d, $replace, &$new, &$map, $id) {
            // replace: this account becomes an exact copy of the file, so what is here is cleared first (same transaction: a failure keeps it all)
            if ($replace) {
                Transaction::query()->delete();
                IncomeSource::get()->each->forget(); // also deletes salary proof images
                RecurringItem::query()->delete();
                Pod::query()->delete();
                Card::query()->delete();
                Category::query()->delete();
            }

            // categories first (a parent may come after its child in the file, so parents are linked in a second pass)
            foreach ($d['categories'] ?? [] as $row) {
                $found = Category::whereRaw('lower(name) = ?', [mb_strtolower($row['name'])])->first();
                $category = $found ?? Category::create(Arr::only($row, ['name', 'icon']));
                $found || $new['categories']++;
                $map['categories'][$row['id']] = $category->id;
            }
            foreach ($d['categories'] ?? [] as $row) {
                if ($parent = $id('categories', $row['parent_id'] ?? null)) {
                    Category::whereKey($map['categories'][$row['id']])->whereNull('parent_id')->where('id', '!=', $parent)->update(['parent_id' => $parent]);
                }
            }

            foreach ($d['cards'] ?? [] as $row) {
                $found = Card::whereRaw('lower(name) = ?', [mb_strtolower($row['name'])])->where('type', $row['type'])->first();
                $card = $found ?? Card::create(Arr::only($row, ['name', 'type', 'balance', 'monthly_limit', 'currency', 'color', 'account_name', 'account_number', 'qr']));
                $found || $new['cards']++;
                $map['cards'][$row['id']] = $card->id;
            }

            foreach ($d['pods'] ?? [] as $row) {
                $found = Pod::whereRaw('lower(name) = ?', [mb_strtolower($row['name'])])->first();
                $pod = $found ?? Pod::create(Arr::only($row, ['name', 'target_amount', 'saved_amount', 'target_date', 'monthly_amount', 'currency']) + ['card_id' => $id('cards', $row['card_id'] ?? null)]);
                $found || $new['pods']++;
                $map['pods'][$row['id']] = $pod->id;
            }

            // What is already here, as signature => ids. A file row claims one of them, so two identical rows in the file
            // (two loans with the same name and amount, two purchases in the same minute) both come across instead of collapsing into one.
            $take = fn (array &$have, string $sig): ?int => isset($have[$sig]) ? (array_shift($have[$sig]) ?: null) : null;

            $haveItems = [];
            foreach (RecurringItem::get(['id', 'name', 'amount', 'cadence']) as $r) {
                $haveItems[mb_strtolower($r->name).'|'.$r->amount.'|'.$r->cadence][] = $r->id;
            }
            foreach ($d['recurring_items'] ?? [] as $row) {
                $foundId = $take($haveItems, mb_strtolower($row['name']).'|'.$row['amount'].'|'.$row['cadence']);
                $itemId = $foundId ?? RecurringItem::create(
                    Arr::only($row, ['name', 'amount', 'currency', 'cadence', 'next_due_on', 'kind', 'installments', 'logo', 'cancelled_at', 'cancel_reason', 'hidden_at'])
                    + ['card_id' => $id('cards', $row['card_id'] ?? null), 'category_id' => $id('categories', $row['category_id'] ?? null)],
                )->id;
                $foundId || $new['recurring_items']++;
                $map['recurring_items'][$row['id']] = $itemId;
            }

            $haveTx = [];
            foreach (Transaction::get(['id', 'card_id', 'type', 'amount', 'occurred_at', 'merchant']) as $t) {
                $haveTx[$t->card_id.'|'.$t->type->value.'|'.$t->amount.'|'.$t->occurred_at->format('Y-m-d H:i:s').'|'.$t->merchant][] = $t->id;
            }
            foreach ($d['transactions'] ?? [] as $row) {
                // the export writes UTC ("Z"); the database holds app-timezone clock time
                $row['occurred_at'] = Carbon::parse($row['occurred_at'])->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s');
                $cardId = $id('cards', $row['card_id']);

                if (! $cardId) {
                    $new['skipped']++; // its card is not in the file

                    continue;
                }

                if ($take($haveTx, $cardId.'|'.$row['type'].'|'.$row['amount'].'|'.$row['occurred_at'].'|'.($row['merchant'] ?? ''))) {
                    $new['skipped']++;

                    continue;
                }

                Transaction::create(Arr::only($row, ['type', 'amount', 'currency', 'merchant', 'occurred_at', 'note', 'source', 'reference']) + [
                    'card_id' => $cardId,
                    'category_id' => $id('categories', $row['category_id'] ?? null),
                    'to_card_id' => $id('cards', $row['to_card_id'] ?? null),
                    'to_pod_id' => $id('pods', $row['to_pod_id'] ?? null),
                    'from_pod_id' => $id('pods', $row['from_pod_id'] ?? null),
                    'recurring_item_id' => $id('recurring_items', $row['recurring_item_id'] ?? null),
                ]);
                $new['transactions']++;
            }

            // income is the same entry when the reference number matches, else when name and date do
            $incomeSig = fn ($amount, $kind, $month, $ref, $name, $on) => implode('|', [$amount, $kind ?: 'salary', $month, $ref ?: $name.'#'.$on]);
            $haveIncome = [];
            foreach (IncomeSource::get() as $i) {
                $haveIncome[$incomeSig($i->amount, $i->kind, $i->month, $i->reference, $i->name, $i->received_on?->toDateString())][] = $i->id;
            }
            foreach ($d['income_sources'] ?? [] as $row) {
                if ($take($haveIncome, $incomeSig($row['amount'], $row['kind'] ?? null, $row['month'] ?? null, $row['reference'] ?? null, $row['name'], isset($row['received_on']) ? Carbon::parse($row['received_on'])->toDateString() : null))) {
                    $new['skipped']++;

                    continue;
                }

                // proofs (receipt images) stay behind: the unique reference number is kept as the record
                IncomeSource::create(Arr::only($row, ['name', 'amount', 'currency', 'schedule', 'month', 'received_on', 'reference', 'kind']) + ['card_id' => $id('cards', $row['card_id'] ?? null)]);
                $new['income_sources']++;
            }

            // settings follow the data into an account with no payday yet, or always when replacing; a merge never overwrites a working account's settings
            if (($replace || ! $user->pay_dates) && ! empty($d['user']['pay_dates'])) {
                $user->forceFill(array_filter(Arr::only($d['user'], ['pay_dates', 'digest_enabled', 'digest_time', 'keep_receipts']), fn ($v) => $v !== null)
                    +['pay_cycle' => count($d['user']['pay_dates']) > 1 ? 'semi_monthly' : 'monthly'])->save();
            }
        });

        return $new;
    }

    /** Throws a validation error on "file" naming the first problems, so a wrong file never half-imports. */
    private function validate(array $data): array
    {
        $max = 'max:'.self::MAX_ROWS;
        $int = ['nullable', 'integer'];
        $v = Validator::make($data, [
            'user.pay_dates' => ['nullable', 'array', 'min:1', 'max:2'],
            'user.pay_dates.*' => ['integer', 'between:1,31', 'distinct'],
            'user.digest_enabled' => ['nullable', 'boolean'],
            'user.digest_time' => ['nullable', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'user.keep_receipts' => ['nullable', 'boolean'],

            'categories' => ['nullable', 'array', $max],
            'categories.*.id' => ['required', 'integer'],
            'categories.*.name' => ['required', 'string', 'max:80'],
            'categories.*.parent_id' => $int,
            'categories.*.icon' => ['nullable', 'string', 'max:60'],

            'cards' => ['nullable', 'array', $max],
            'cards.*.id' => ['required', 'integer'],
            'cards.*.name' => ['required', 'string', 'max:120'],
            'cards.*.type' => ['required', Rule::enum(CardType::class)],
            'cards.*.balance' => ['required', 'integer'],
            'cards.*.monthly_limit' => $int,
            'cards.*.currency' => ['nullable', 'string', 'max:8'],
            'cards.*.color' => ['nullable', 'string', 'max:30'],
            'cards.*.account_name' => ['nullable', 'string', 'max:120'],
            'cards.*.account_number' => ['nullable', 'string', 'max:60'],
            'cards.*.qr' => ['nullable', 'string', 'max:700000'],

            'pods' => ['nullable', 'array', $max],
            'pods.*.id' => ['required', 'integer'],
            'pods.*.name' => ['required', 'string', 'max:120'],
            'pods.*.target_amount' => ['required', 'integer'],
            'pods.*.saved_amount' => ['required', 'integer'],
            'pods.*.target_date' => ['nullable', 'date'],
            'pods.*.monthly_amount' => ['nullable', 'integer'],
            'pods.*.currency' => ['nullable', 'string', 'max:8'],
            'pods.*.card_id' => $int,

            'recurring_items' => ['nullable', 'array', $max],
            'recurring_items.*.id' => ['required', 'integer'],
            'recurring_items.*.name' => ['required', 'string', 'max:120'],
            'recurring_items.*.amount' => ['required', 'integer'],
            'recurring_items.*.currency' => ['nullable', 'string', 'max:8'],
            'recurring_items.*.card_id' => $int,
            'recurring_items.*.category_id' => $int,
            'recurring_items.*.cadence' => ['required', 'in:weekly,biweekly,monthly,quarterly,yearly'],
            'recurring_items.*.next_due_on' => ['nullable', 'date'],
            'recurring_items.*.kind' => ['required', Rule::enum(RecurringKind::class)],
            'recurring_items.*.installments' => $int,
            'recurring_items.*.logo' => ['nullable', 'string', 'max:200000'],
            'recurring_items.*.cancelled_at' => ['nullable', 'date'],
            'recurring_items.*.hidden_at' => ['nullable', 'date'],
            'recurring_items.*.cancel_reason' => ['nullable', 'string', 'max:500'],

            'transactions' => ['nullable', 'array', $max],
            'transactions.*.card_id' => ['required', 'integer'],
            'transactions.*.category_id' => $int,
            'transactions.*.to_card_id' => $int,
            'transactions.*.to_pod_id' => $int,
            'transactions.*.from_pod_id' => $int,
            'transactions.*.recurring_item_id' => $int,
            'transactions.*.type' => ['required', Rule::enum(TransactionType::class)],
            'transactions.*.amount' => ['required', 'integer'],
            'transactions.*.currency' => ['nullable', 'string', 'max:8'],
            'transactions.*.merchant' => ['nullable', 'string', 'max:255'],
            'transactions.*.occurred_at' => ['required', 'date'],
            'transactions.*.note' => ['nullable', 'string', 'max:2000'],
            'transactions.*.source' => ['required', Rule::enum(TransactionSource::class)],
            'transactions.*.reference' => ['nullable', 'string', 'max:255'],

            'income_sources' => ['nullable', 'array', $max],
            'income_sources.*.name' => ['required', 'string', 'max:255'],
            'income_sources.*.amount' => ['required', 'integer'],
            'income_sources.*.currency' => ['nullable', 'string', 'max:8'],
            'income_sources.*.schedule' => ['nullable', 'string', 'max:20'],
            'income_sources.*.month' => ['nullable', 'string', 'max:7'],
            'income_sources.*.received_on' => ['nullable', 'date'],
            'income_sources.*.reference' => ['nullable', 'string', 'max:80'],
            'income_sources.*.kind' => ['nullable', 'in:salary,side'],
            'income_sources.*.card_id' => $int,
        ]);

        if (! Arr::hasAny($data, ['cards', 'transactions', 'recurring_items', 'pods', 'categories', 'income_sources'])) {
            throw ValidationException::withMessages(['file' => 'This does not look like a Whale Wallet export.']);
        }

        if ($v->fails()) {
            throw ValidationException::withMessages(['file' => 'The file has problems, nothing was imported: '.implode(' ', array_slice($v->errors()->all(), 0, 3))]);
        }

        return $v->validated();
    }
}
