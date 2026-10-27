<?php

namespace App\Models;

use App\Enums\RecurringKind;
use App\Models\Concerns\BelongsToUser;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecurringItem extends Model
{
    use BelongsToUser, HasFactory;

    protected $guarded = ['user_id'];

    /** Everything that counts: not a cancelled subscription and not hidden for now (both stay on file but stop counting). */
    public function scopeActive($query)
    {
        return $query->whereNull('cancelled_at')->whereNull('hidden_at');
    }

    /** Hidden items drop out of income left, the forecast, due lists, tips and net worth until shown again. */
    public function hide(bool $hidden = true): void
    {
        $this->update(['hidden_at' => $hidden ? now() : null]);
    }

    /** A cancelled subscription stays on file with its reason, so it can be renewed. */
    public function cancel(string $reason): void
    {
        abort_unless($this->kind === RecurringKind::Subscription, 422, 'Only subscriptions can be cancelled.');
        $this->update(['cancelled_at' => now(), 'cancel_reason' => $reason]);
    }

    /** Back in play; a past charge date rolls forward to today or later. */
    public function renew(): void
    {
        $this->update(['cancelled_at' => null, 'cancel_reason' => null]);
        $this->advance();
    }

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

    /** Cadence as [unit, step]: days or months between charges. */
    private const STEPS = [
        'weekly' => ['d', 7], 'biweekly' => ['d', 14], 'monthly' => ['m', 1], 'quarterly' => ['m', 3], 'yearly' => ['m', 12],
    ];

    /** Cost per month in centavos, rounded half up (weekly = 52/12 charges a month). */
    public function monthlyAmount(): int
    {
        [$num, $den] = match ($this->cadence) {
            'weekly' => [52, 12], 'biweekly' => [26, 12], 'quarterly' => [1, 3], 'yearly' => [1, 12], default => [1, 1],
        };

        return intdiv($this->amount * $num * 2 + $den, 2 * $den);
    }

    /** The k-th charge date counted from the stored next_due_on (month steps clamp at month end). */
    private function nth(int $k): CarbonInterface
    {
        [$unit, $step] = self::STEPS[$this->cadence] ?? self::STEPS['monthly'];

        return $unit === 'd' ? $this->next_due_on->addDays($k * $step) : $this->next_due_on->addMonthsNoOverflow($k * $step);
    }

    /** Charge dates in [from, to], including ones already overdue. @return list<CarbonInterface> */
    public function occurrences(CarbonInterface $from, CarbonInterface $to): array
    {
        $dates = [];
        for ($k = 0; $this->next_due_on && $k < ($this->installments ?? PHP_INT_MAX) && ($d = $this->nth($k)) <= $to; $k++) {
            if ($d >= $from) {
                $dates[] = $d;
            }
        }

        return $dates;
    }

    /**
     * Roll next_due_on forward to the first charge date that is today or later.
     * ponytail: steps from the stored date, so a 31st clamped to the 28th stays on the 28th.
     */
    public function advance(): void
    {
        for ($k = 0; $this->next_due_on && $this->nth($k)->lt(today()); $k++);

        if ($k > 0) {
            $this->update(['next_due_on' => $this->nth($k)]);
        }
    }

    /** After a payment: the next charge date, and one fewer payment left on a loan (0 left means paid off). */
    public function advanceAfterPayment(): void
    {
        $left = $this->installments === null ? null : max(0, $this->installments - 1);

        $this->update(['installments' => $left, 'next_due_on' => $left === 0 ? $this->next_due_on : $this->nth(1)]);
    }

    /** Loan payments still to come (today included). */
    public function paymentsLeft(): int
    {
        $left = 0;
        for ($k = 0; $this->next_due_on && $k < $this->installments; $k++) {
            $left += $this->nth($k)->gte(today());
        }

        return $left;
    }

    /** What is still owed on a loan: the total less what was paid (older loans without a total: payments left x amount). */
    public function remaining(): int
    {
        if ($this->installments === 0) {
            return 0;
        }

        // ponytail: one sum per loan, fold into a grouped query if someone holds dozens
        return $this->total_amount === null
            ? $this->amount * $this->paymentsLeft()
            : max(0, $this->total_amount - (int) Transaction::withoutGlobalScopes()->where('recurring_item_id', $this->id)->sum('amount'));
    }

    protected function casts(): array
    {
        return ['kind' => RecurringKind::class, 'next_due_on' => 'date', 'cancelled_at' => 'datetime', 'hidden_at' => 'datetime'];
    }
}
