<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatThread extends Model
{
    use BelongsToUser;

    protected $guarded = ['user_id'];

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }
}
