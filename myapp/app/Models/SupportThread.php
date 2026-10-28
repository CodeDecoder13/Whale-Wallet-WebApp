<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A user's one conversation with the support team. Users see only their own (BelongsToUser); the admin reads it with withoutGlobalScopes(). */
class SupportThread extends Model
{
    use BelongsToUser;

    protected $guarded = ['user_id'];

    protected function casts(): array
    {
        return ['last_message_at' => 'datetime'];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class);
    }
}
