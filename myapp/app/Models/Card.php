<?php

namespace App\Models;

use App\Enums\CardType;
use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Card extends Model
{
    use BelongsToUser, HasFactory;

    protected $guarded = ['user_id'];

    protected function casts(): array
    {
        return ['type' => CardType::class];
    }
}
