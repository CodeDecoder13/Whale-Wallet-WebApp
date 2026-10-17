<?php

namespace App\Models;

use App\Enums\RecurringKind;
use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\CarbonInterface;

class RecurringItem extends Model
{
    use BelongsToUser, HasFactory;

    protected $guarded = ['user_id'];

    /** Next date (today included) that falls on this day of the month, clamped for short months. */
    public static function nextDue(int $day): CarbonInterface
    {
        $month = today()->startOfMonth();
        $due = $month->day(min($day, $month->daysInMonth));

        if ($due->isPast()) {
            $month = $month->addMonth();
            $due = $month->day(min($day, $month->daysInMonth));
        }

        return $due;
    }

    protected function casts(): array
    {
        return ['kind' => RecurringKind::class, 'next_due_on' => 'date'];
    }
}
