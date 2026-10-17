<?php

namespace App\Models;

use App\Enums\RecurringKind;
use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecurringItem extends Model
{
    use BelongsToUser, HasFactory;

    protected $guarded = ['user_id'];

    protected function casts(): array
    {
        return ['kind' => RecurringKind::class, 'next_due_on' => 'date'];
    }
}
