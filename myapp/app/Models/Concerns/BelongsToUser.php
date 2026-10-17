<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Scopes every query to the signed-in user and stamps user_id on create.
 * Route-model binding goes through the same scoped query, so another user's
 * id resolves to 404 and no per-model policy is needed for ownership.
 */
trait BelongsToUser
{
    protected static function bootBelongsToUser(): void
    {
        static::addGlobalScope('user', function (Builder $q) {
            if (auth()->check()) {
                $q->where($q->getModel()->getTable().'.user_id', auth()->id());
            }
        });

        static::creating(function ($model) {
            $model->user_id ??= auth()->id();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
