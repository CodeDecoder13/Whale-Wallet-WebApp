<?php

namespace App\Actions;

use App\Models\Pod;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

/** Moves money from a card into a pod, with the reference number and receipt that prove it. */
class FundPod
{
    public function __construct(private TransferBetween $transfer, private SettleReceipt $receipts) {}

    /** @param  array{card_id: int, amount: int, paid_on: string, reference?: ?string, receipt_id?: ?int}  $d  validated, amount in centavos */
    public function handle(Pod $pod, array $d): Transaction
    {
        return DB::transaction(function () use ($pod, $d) {
            $receipt = $this->receipts->claim($d['receipt_id'] ?? null);
            $transfer = $this->transfer->handle("card:{$d['card_id']}", "pod:{$pod->id}", $d['amount'], $d['reference'] ?? null, $receipt?->id, $d['paid_on']);
            $this->receipts->finish($receipt);

            return $transfer;
        });
    }
}
