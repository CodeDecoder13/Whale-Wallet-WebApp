<?php

namespace App\Models;

use App\Enums\CardType;
use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Card extends Model
{
    use BelongsToUser, HasFactory;

    protected $guarded = ['user_id'];

    protected function casts(): array
    {
        return ['type' => CardType::class];
    }

    /** Adds `spent`: this month's expenses in centavos (transfers and income do not count). */
    public function scopeWithSpent(Builder $query): void
    {
        $query->withSum(
            ['transactions as spent' => fn ($q) => $q->where('type', 'expense')->where('occurred_at', '>=', now()->startOfMonth())],
            'amount',
        );
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
