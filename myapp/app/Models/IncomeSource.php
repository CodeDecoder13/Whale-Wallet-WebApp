<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class IncomeSource extends Model
{
    use BelongsToUser, HasFactory;

    protected $guarded = ['user_id'];

    protected function casts(): array
    {
        return ['received_on' => 'date'];
    }

    /**
     * One received amount, from the web form, the API or chat. $d is validated input (amount in centavos).
     * A proof receipt, when given, is locked and confirmed with it; typed-in income has none.
     *
     * @param  array{amount: int, received_on: string, kind?: ?string, name?: ?string, reference?: ?string, receipt_id?: ?int}  $d
     */
    public static function record(array $d): self
    {
        return DB::transaction(function () use ($d) {
            $receipt = ($d['receipt_id'] ?? null) ? Receipt::lockForUpdate()->findOrFail($d['receipt_id']) : null;
            abort_unless(! $receipt || ($receipt->status === 'parsed' && $receipt->purpose === 'income'), 409, 'This proof was already handled.');

            // the card that received it gains the money, so net worth follows
            $card = ($d['card_id'] ?? null) ? Card::lockForUpdate()->findOrFail($d['card_id']) : null;
            $card?->increment('balance', $d['amount']);

            $kind = $d['kind'] ?? 'salary';
            $row = static::create([
                'card_id' => $card?->id,
                'name' => ($d['name'] ?? null) ?: ($kind === 'side' ? 'Side income' : 'Salary'),
                'kind' => $kind,
                'amount' => $d['amount'],
                'received_on' => $d['received_on'],
                'month' => substr($d['received_on'], 0, 7),
                'reference' => $d['reference'] ?? null,
                'receipt_id' => $receipt?->id,
            ]);
            $receipt?->update(['status' => 'confirmed']);

            return $row;
        });
    }

    /** Removes an entry: takes the money back off its card and deletes the proof image, if any. */
    public function forget(): void
    {
        DB::transaction(function () {
            $this->card_id && Card::lockForUpdate()->find($this->card_id)?->decrement('balance', $this->amount);

            if ($receipt = $this->receipt_id ? Receipt::find($this->receipt_id) : null) {
                $receipt->path && Storage::disk()->delete($receipt->path);
                $receipt->delete();
            }
            $this->delete();
        });
    }
}
