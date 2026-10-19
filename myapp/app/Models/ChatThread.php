<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChatThread extends Model
{
    use BelongsToUser, SoftDeletes;

    protected $guarded = ['user_id'];

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }
}
