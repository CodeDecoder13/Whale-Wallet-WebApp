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
        $user->categories()->createMany(array_map(fn ($name) => ['name' => $name], self::DEFAULTS));
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
