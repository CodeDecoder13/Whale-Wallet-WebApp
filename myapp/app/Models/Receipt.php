<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

class Receipt extends Model
{
    use BelongsToUser;

    protected $guarded = ['user_id'];

    protected function casts(): array
    {
        return ['parsed_json' => 'array'];
    }

    /** What the browser may see: status, a user-safe error and the draft. Never the path. */
    public function toClient(): array
    {
        return ['id' => $this->id, 'status' => $this->status, 'error' => $this->error, 'draft' => $this->parsed_json];
    }
}
