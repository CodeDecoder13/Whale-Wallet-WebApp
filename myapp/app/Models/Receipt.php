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
        return ['parsed_json' => 'array', 'corrections_json' => 'array'];
    }

    /** What the browser may see: status, a user-safe error and the draft. Never the path. */
    public function toClient(): array
    {
        // no worker, or one that died: don't leave the user in a "Reading..." loop (a parse takes seconds, the job timeout is 90s)
        if (in_array($this->status, ['queued', 'parsing'], true) && $this->updated_at?->lt(now()->subSeconds(120))) {
            $this->update(['status' => 'failed', 'error' => 'Reading took too long. Try again or add the expense manually.']);
        }

        return ['id' => $this->id, 'status' => $this->status, 'error' => $this->error, 'draft' => $this->parsed_json, 'claimed' => $this->claimed_amount];
    }
}
