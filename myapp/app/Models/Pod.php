<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pod extends Model
{
    use BelongsToUser, HasFactory;

    protected $guarded = ['user_id'];

    protected function casts(): array
    {
        return ['target_date' => 'date'];
    }
}
