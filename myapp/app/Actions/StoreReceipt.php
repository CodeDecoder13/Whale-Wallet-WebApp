<?php

namespace App\Actions;

use App\Jobs\ParseReceipt;
use App\Models\Receipt;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class StoreReceipt
{
    /** Keeps the upload on the private disk and queues parsing. No transaction is created. */
    public function handle(User $user, UploadedFile $image): Receipt
    {
        $name = Str::ulid().'-'.Str::slug(substr(pathinfo($image->getClientOriginalName(), PATHINFO_FILENAME), 0, 40)).'.'.$image->extension();

        $receipt = $user->receipts()->create(['path' => $image->storeAs("receipts/{$user->id}", $name, 'local')]);
        ParseReceipt::dispatch($receipt->id);

        return $receipt;
    }
}
