<?php

namespace App\Actions;

use App\Jobs\ParseReceipt;
use App\Models\Receipt;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StoreReceipt
{
    /** Keeps the upload on the private disk and queues parsing. No transaction is created. */
    public function handle(User $user, UploadedFile $image, string $purpose = 'expense'): Receipt
    {
        $name = Str::ulid().'-'.Str::slug(substr(pathinfo($image->getClientOriginalName(), PATHINFO_FILENAME), 0, 40)).'.'.$image->extension();

        $receipt = $user->receipts()->create(['path' => Storage::putFileAs("receipts/{$user->id}", $image, $name), 'purpose' => $purpose]);
        ParseReceipt::dispatch($receipt->id);

        return $receipt;
    }
}
