<?php

namespace App\Actions;

use App\Models\Receipt;
use Illuminate\Support\Facades\Storage;

/** A scanned receipt the user checked and attached to a payment or transfer as proof. */
class SettleReceipt
{
    /** Locks a parsed expense receipt before it is attached; null when there is none. Call inside the transaction. */
    public function claim(?int $id): ?Receipt
    {
        $receipt = $id ? Receipt::lockForUpdate()->findOrFail($id) : null;
        abort_if($receipt && ($receipt->status !== 'parsed' || $receipt->purpose !== 'expense'), 422, 'This receipt was already handled.');

        return $receipt;
    }

    /** Closes the receipt, and drops the image unless the user keeps receipt images. */
    public function finish(?Receipt $receipt): void
    {
        if (! $receipt) {
            return;
        }

        $receipt->update(['status' => 'confirmed']);

        if (! auth()->user()->keep_receipts && $receipt->path) {
            Storage::disk()->delete($receipt->path);
            $receipt->update(['path' => null]);
        }
    }
}
