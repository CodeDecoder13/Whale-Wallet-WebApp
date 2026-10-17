<?php

namespace App\Http\Controllers;

use App\Http\Requests\IncomeRequest;
use App\Models\IncomeSource;
use App\Models\Receipt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Income actually received in a month (salary varies); it replaces the onboarding baseline for that month. */
class IncomeController extends Controller
{
    /** The proof receipt stays on the private disk as the record of this salary. */
    public function store(IncomeRequest $request): RedirectResponse
    {
        $d = $request->validated();

        DB::transaction(function () use ($d) {
            $receipt = Receipt::lockForUpdate()->findOrFail($d['receipt_id']);
            abort_unless($receipt->status === 'parsed' && $receipt->purpose === 'income', 409, 'This proof was already handled.');

            IncomeSource::create([
                'name' => $d['name'] ?? null ?: 'Salary',
                'amount' => $d['amount'],
                'received_on' => $d['received_on'],
                'month' => substr($d['received_on'], 0, 7),
                'reference' => $d['reference'],
                'receipt_id' => $receipt->id,
            ]);
            $receipt->update(['status' => 'confirmed']);
        });

        return back();
    }

    public function destroy(int $income): RedirectResponse
    {
        $row = IncomeSource::whereNotNull('month')->findOrFail($income);

        if ($receipt = $row->receipt_id ? Receipt::find($row->receipt_id) : null) {
            $receipt->path && Storage::disk('local')->delete($receipt->path);
            $receipt->delete();
        }
        $row->delete();

        return back();
    }
}
