<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

/** Cash the user lent to a person. Counts as an asset in net worth until it is paid back. */
class Lend extends Model
{
    use BelongsToUser;

    protected $guarded = ['user_id'];

    protected function casts(): array
    {
        return ['lent_on' => 'date', 'due_on' => 'date', 'reminded_on' => 'date', 'paid_at' => 'datetime'];
    }

    public function scopeOwed($query)
    {
        return $query->whereNull('paid_at');
    }

    /** Which reminder is due today: 'soon' (the agreed days before), 'today', 'overdue' (every 7 days after), or null. */
    public function reminderToday(): ?string
    {
        if ($this->paid_at || $this->reminded_on?->isToday()) {
            return null;
        }

        $left = (int) today()->diffInDays($this->due_on->startOfDay(), false); // negative once overdue

        return match (true) {
            $left === 0 => 'today',
            $left > 0 && $left === $this->remind_before => 'soon',
            $left < 0 && $left % 7 === 0 => 'overdue',
            default => null,
        };
    }
}
