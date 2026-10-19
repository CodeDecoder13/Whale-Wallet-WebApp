<?php

namespace App\Actions;

use App\Enums\RecurringKind;
use App\Enums\TransactionType;
use App\Http\Requests\PesoRequest;
use App\Models\Card;
use App\Models\Category;
use App\Models\RecurringItem;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Throwable;

/**
 * Changes made from Whale Chat: add, edit, cancel, renew or delete bills, subscriptions and loans, and edit or
 * delete an expense. The model only picks ids from the lists in context(); draft() checks them against this
 * user's own rows and words the change, and nothing happens until apply() runs from the confirm endpoint.
 */
class ManageByChat
{
    private const CADENCES = ['weekly', 'biweekly', 'monthly', 'quarterly', 'yearly'];

    public function __construct(private UpdateTransaction $update, private DeleteTransaction $delete) {}

    /** What the model needs to name an item or a transaction by id. */
    public function context(User $user): array
    {
        return [
            'items' => $user->recurringItems()->orderBy('name')->limit(40)->get()->map(fn ($i) => [
                'id' => $i->id, 'name' => $i->name, 'kind' => $i->kind->value, 'amount' => $i->amount / 100, 'cadence' => $i->cadence,
                'next_due_on' => $i->next_due_on?->toDateString(), 'cancelled' => $i->cancelled_at !== null,
            ])->all(),
            'transactions' => Transaction::where('type', TransactionType::Expense)->latest('occurred_at')->latest('id')->limit(15)->get(['id', 'merchant', 'amount', 'occurred_at'])
                ->map(fn ($t) => ['id' => $t->id, 'merchant' => $t->merchant, 'amount' => $t->amount / 100, 'date' => $t->occurred_at->toDateString()])->all(),
        ];
    }

    /** @return array{draft?: array, reply?: string} a confirmable draft, or a sentence saying why not */
    public function draft(array $a): array
    {
        $id = (int) ($a['target_id'] ?? 0);
        $type = (string) ($a['type'] ?? '');

        if (str_ends_with($type, '_transaction')) {
            $t = $id ? Transaction::with('card:id,name')->where('type', TransactionType::Expense)->find($id) : null;

            return $t ? ($type === 'delete_transaction' ? $this->deleteTransaction($t) : $this->editTransaction($t, $a)) : ['reply' => 'I could not find that expense. Say which one, for example “delete my Jollibee expense”.'];
        }

        if ($type === 'add_recurring') {
            return $this->add($a);
        }

        $item = $id ? RecurringItem::find($id) : null;

        if (! $item) {
            return ['reply' => 'I could not find that bill or subscription. Say its name again.'];
        }

        $name = $item->name;
        $sub = $item->kind === RecurringKind::Subscription;

        return match ($type) {
            'edit_recurring' => $this->edit($item, $a),
            'cancel_subscription' => ! $sub ? ['reply' => "{$name} is not a subscription, so it cannot be cancelled."]
                : ($item->cancelled_at ? ['reply' => "{$name} is already cancelled."] : self::manage($type, $item->id, "Cancel {$name}?", ['It stays on file with its payments, so you can renew it any time.', 'Reason: '.($reason = self::text($a['reason'] ?? '') ?: 'Cancelled in chat')], 'Cancel subscription', ['reason' => $reason])),
            'renew_subscription' => ! $item->cancelled_at ? ['reply' => "{$name} is not cancelled."]
                : self::manage($type, $item->id, "Renew {$name}?", [self::peso($item->amount).' '.$item->cadence.' counts toward your bills again.'], 'Renew'),
            'delete_recurring' => self::manage($type, $item->id, "Delete {$name}?", ['Payments you already logged stay in your transactions.', 'Only the schedule is removed.'], 'Delete', [], true),
            default => ['reply' => 'I am not sure what to change. Try “cancel Netflix” or “change Rent to ₱8,500”.'],
        };
    }

    /** Runs a confirmed draft; the rows are looked up again, so a stale draft cannot do the wrong thing. */
    public function apply(array $d): string
    {
        $c = $d['changes'] ?? [];

        if ($d['action'] === 'add_recurring') {
            $item = RecurringItem::create(['name' => $c['name'], 'amount' => $c['amount'], 'kind' => RecurringKind::from($c['kind']), 'cadence' => $c['cadence'], 'next_due_on' => $c['next_due_on']]);

            return "Added {$item->name} to Bills & Subs.";
        }

        if (str_ends_with($d['action'], '_transaction')) {
            $t = Transaction::where('type', TransactionType::Expense)->findOrFail($d['target_id']);

            if ($d['action'] === 'delete_transaction') {
                $this->delete->handle($t);

                return 'Expense deleted and the card balance restored.';
            }

            $this->update->handle($t, [...$c, 'card_id' => $c['card_id'] ?? $t->card_id, 'amount' => $c['amount'] ?? $t->amount]);

            return 'Expense updated.';
        }

        $item = RecurringItem::findOrFail($d['target_id']);

        return match ($d['action']) {
            'edit_recurring' => tap("{$item->name} updated.", fn () => $item->update($c)),
            'cancel_subscription' => tap("{$item->name} cancelled. You can renew it from Bills & Subs.", fn () => $item->cancel($c['reason'])),
            'renew_subscription' => tap("{$item->name} renewed.", fn () => $item->renew()),
            'delete_recurring' => tap("{$item->name} deleted. Its past payments are kept.", fn () => $item->delete()),
        };
    }

    private function add(array $a): array
    {
        $name = mb_substr(self::text($a['name'] ?? ''), 0, 80);
        $kind = ($a['kind'] ?? '') === 'subscription' ? 'subscription' : 'bill';
        $cadence = in_array($a['cadence'] ?? '', self::CADENCES, true) ? $a['cadence'] : 'monthly';
        $amount = self::centavos($a['amount'] ?? null);
        $due = self::date($a['next_due_on'] ?? null);

        if (! $name || ! $amount || ! $due) {
            return ['reply' => "To add a {$kind} I need its name, amount and next charge date, for example “add Spotify subscription 149 on Nov 5”."];
        }

        if (RecurringItem::whereRaw('lower(name) = ?', [mb_strtolower($name)])->exists()) {
            return ['reply' => "You already have {$name}. Say “change {$name} to ₱…” to edit it."];
        }

        return self::manage('add_recurring', null, "Add {$name}?", [ucfirst($kind).' · '.$cadence, self::peso($amount).' per charge', 'First charge '.Date::parse($due)->format('M j, Y')], 'Add '.$kind,
            ['name' => $name, 'kind' => $kind, 'amount' => $amount, 'cadence' => $cadence, 'next_due_on' => $due]);
    }

    private function edit(RecurringItem $item, array $a): array
    {
        $changes = [];
        $lines = [];

        if (($amount = self::centavos($a['amount'] ?? null)) && $amount !== $item->amount) {
            $changes['amount'] = $amount;
            $lines[] = 'Amount '.self::peso($item->amount).' → '.self::peso($amount);
        }

        if (($due = self::date($a['next_due_on'] ?? null)) && $due !== $item->next_due_on?->toDateString()) {
            $changes['next_due_on'] = $due;
            $lines[] = 'Next charge '.$item->next_due_on?->format('M j').' → '.Date::parse($due)->format('M j, Y');
        }

        if ($item->kind !== RecurringKind::Debt && in_array($a['cadence'] ?? '', self::CADENCES, true) && $a['cadence'] !== $item->cadence) {
            $changes['cadence'] = $a['cadence'];
            $lines[] = "Repeats {$item->cadence} → {$a['cadence']}";
        }

        if (($name = mb_substr(self::text($a['name'] ?? ''), 0, 80)) && $name !== $item->name) {
            $changes['name'] = $name;
            $lines[] = "Name {$item->name} → {$name}";
        }

        return $changes ? self::manage('edit_recurring', $item->id, "Change {$item->name}?", $lines, 'Apply changes', $changes)
            : ['reply' => "What should I change on {$item->name}? For example the amount or the due date."];
    }

    private function editTransaction(Transaction $t, array $a): array
    {
        $changes = [];
        $lines = [];

        if (($amount = self::centavos($a['amount'] ?? null)) && $amount !== $t->amount) {
            $changes['amount'] = $amount;
            $lines[] = 'Amount '.self::peso($t->amount).' → '.self::peso($amount);
        }

        if (($merchant = mb_substr(self::text($a['merchant'] ?? ''), 0, 120)) && $merchant !== $t->merchant) {
            $changes['merchant'] = $merchant;
            $lines[] = "Merchant {$t->merchant} → {$merchant}";
        }

        $date = self::date($a['occurred_at'] ?? null);

        if ($date && $date <= today()->toDateString() && $date !== $t->occurred_at->toDateString()) {
            $changes['occurred_at'] = $date;
            $lines[] = 'Date '.$t->occurred_at->format('M j').' → '.Date::parse($date)->format('M j, Y');
        }

        if (($card = $this->byName(Card::class, $a['card'] ?? null)) && $card->id !== $t->card_id) {
            $changes['card_id'] = $card->id;
            $lines[] = "Card {$t->card->name} → {$card->name}";
        }

        if (($category = $this->byName(Category::class, $a['category'] ?? null)) && $category->id !== $t->category_id) {
            $changes['category_id'] = $category->id;
            $lines[] = "Category → {$category->name}";
        }

        return $changes ? self::manage('edit_transaction', $t->id, "Change {$t->merchant}?", [...$lines, 'Card balances are adjusted to match.'], 'Apply changes', $changes)
            : ['reply' => "What should I change on {$t->merchant}? For example the amount, date, card or category."];
    }

    private function deleteTransaction(Transaction $t): array
    {
        return self::manage('delete_transaction', $t->id, "Delete {$t->merchant} ".self::peso($t->amount).'?', ["The amount goes back to {$t->card->name}.", 'This cannot be undone.'], 'Delete', [], true);
    }

    /** A draft the chat shows as a confirm card; the keys the older cards read are kept so nothing else changes. */
    private static function manage(string $action, ?int $id, string $title, array $lines, string $label, array $changes = [], bool $destructive = false): array
    {
        return ['draft' => [
            'kind' => 'manage', 'action' => $action, 'target_id' => $id, 'changes' => $changes,
            'title' => $title, 'lines' => $lines, 'label' => $label, 'destructive' => $destructive,
            'merchant' => $title, 'amount' => null, 'card' => null, 'category' => null, 'occurred_at' => today()->toDateString(), 'missing' => [],
        ]];
    }

    private function byName(string $model, ?string $name): ?object
    {
        return $name ? $model::get()->first(fn ($r) => mb_strtolower($r->name) === mb_strtolower(trim($name))) : null;
    }

    private static function text(mixed $v): string
    {
        return trim((string) $v);
    }

    private static function date(mixed $v): ?string
    {
        try {
            return $v ? Date::parse((string) $v)->toDateString() : null;
        } catch (Throwable) {
            return null;
        }
    }

    private static function centavos(mixed $pesos): ?int
    {
        $n = PesoRequest::centavos(str_replace(',', '', (string) $pesos));

        return is_int($n) && $n > 0 ? $n : null;
    }

    private static function peso(int $centavos): string
    {
        return '₱'.number_format($centavos / 100, 2);
    }
}
