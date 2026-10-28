<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

class SupportMessage extends Model
{
    use BelongsToUser;

    public $timestamps = false;

    protected $guarded = ['user_id'];

    protected function casts(): array
    {
        return ['from_admin' => 'boolean', 'read_at' => 'datetime', 'created_at' => 'datetime'];
    }

    /** What the chat page needs from a message; same shape for the user and the admin. */
    public function toClient(): array
    {
        return ['id' => $this->id, 'body' => $this->body, 'from_admin' => $this->from_admin, 'at' => $this->created_at->toIso8601String(), 'seen' => $this->read_at !== null];
    }
}
