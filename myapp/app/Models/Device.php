<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    use BelongsToUser;

    protected $guarded = ['user_id'];

    protected function casts(): array
    {
        return ['last_seen_at' => 'datetime'];
    }
}
