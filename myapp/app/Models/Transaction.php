<?php

namespace App\Models;

use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use BelongsToUser, HasFactory;

    protected $guarded = ['user_id'];

    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'source' => TransactionSource::class,
            'occurred_at' => 'datetime',
        ];
    }

    /** @param array{q?: ?string, from?: ?string, to?: ?string, card_id?: ?int, category_id?: ?int} $f */
    public function scopeFilter(Builder $query, array $f): void
    {
        $query
            ->when($f['q'] ?? null, fn ($q, $s) => $q->where(
                fn ($q) => $q->whereRaw('lower(merchant) like ?', ['%'.mb_strtolower($s).'%'])
                    ->orWhereRaw('lower(note) like ?', ['%'.mb_strtolower($s).'%'])
                    ->orWhereRaw('lower(reference) like ?', ['%'.mb_strtolower($s).'%'])
            ))
            ->when($f['from'] ?? null, fn ($q, $d) => $q->whereDate('occurred_at', '>=', $d))
            ->when($f['to'] ?? null, fn ($q, $d) => $q->whereDate('occurred_at', '<=', $d))
            ->when($f['card_id'] ?? null, fn ($q, $id) => $q->where('card_id', $id))
            ->when($f['category_id'] ?? null, fn ($q, $id) => $q->where('category_id', $id));
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    /** The bill, subscription or loan this paid; its logo is shown on the transaction. */
    public function recurringItem(): BelongsTo
    {
        return $this->belongsTo(RecurringItem::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
