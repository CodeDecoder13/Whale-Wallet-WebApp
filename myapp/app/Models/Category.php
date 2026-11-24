<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use BelongsToUser, HasFactory;

    protected $guarded = ['user_id'];

    public const DEFAULTS = ['Food', 'Transport', 'Bills', 'Shopping', 'Health', 'Entertainment', 'Other'];

    public static function seedFor(User $user): void
    {
        // idempotent: a double submit or a repeated onboarding must not list every default twice
        foreach (self::DEFAULTS as $name) {
            $user->categories()->firstOrCreate(['name' => $name]);
        }
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }
}
