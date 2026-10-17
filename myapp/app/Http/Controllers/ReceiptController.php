<?php

namespace App\Http\Controllers;

use App\Actions\CreateTransaction;
use App\Actions\StoreReceipt;
use App\Enums\TransactionSource;
use App\Http\Requests\ReceiptConfirmRequest;
use App\Models\Card;
use App\Models\Category;
use App\Models\Receipt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ReceiptController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('receipts/Index', [
            'consented' => (bool) $request->user()->ai_consent_at,
            'keepReceipts' => $request->user()->keep_receipts,
            'receipts' => Receipt::where('status', '!=', 'confirmed')->latest('id')->limit(10)->get()->map->toClient()->values(),
            'cards' => Card::orderBy('name')->get(['id', 'name']),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** Returns 202; the client polls show() until the queue worker has parsed it. */
    public function store(Request $request, StoreReceipt $store): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192', 'dimensions:max_width=8000,max_height=8000'],
        ]);

        return response()->json($store->handle($request->user(), $request->file('image'))->refresh()->toClient(), 202);
    }

    public function show(Receipt $receipt): JsonResponse
    {
        return response()->json($receipt->toClient());
    }

    /** The only place a receipt becomes a transaction, with the fields as the user edited them. */
    public function confirm(ReceiptConfirmRequest $request, Receipt $receipt, CreateTransaction $create): JsonResponse
    {
        DB::transaction(function () use ($request, $receipt, $create) {
            $receipt = Receipt::lockForUpdate()->findOrFail($receipt->id);
            abort_unless($receipt->status === 'parsed', 409, 'This receipt was already handled.');

            $data = $request->validated();
            $create->handle([...$data, 'source' => TransactionSource::Receipt, 'receipt_id' => $receipt->id]);

            // what the user changed on the confirm card, so parsing mistakes can be measured later
            $draft = $receipt->parsed_json ?? [];
            $corrections = collect(['merchant', 'amount', 'card_id', 'category_id', 'occurred_at'])
                ->mapWithKeys(fn ($k) => [$k => [substr((string) ($draft[$k] ?? ''), 0, 10), substr((string) ($data[$k] ?? ''), 0, 10)]])
                ->reject(fn ($v) => $v[0] === $v[1])
                ->map(fn ($v) => ['from' => $v[0], 'to' => $v[1]]);
            $receipt->update(['status' => 'confirmed', 'corrections_json' => $corrections->all()]);

            if (! $request->user()->keep_receipts && $receipt->path) {
                Storage::disk('local')->delete($receipt->path);
                $receipt->update(['path' => null]);
            }
        });

        return response()->json(['status' => 'confirmed']);
    }

    public function destroy(Receipt $receipt): JsonResponse
    {
        if ($receipt->path) {
            Storage::disk('local')->delete($receipt->path);
        }
        $receipt->delete();

        return response()->json(null, 204);
    }

    public function keep(Request $request): RedirectResponse
    {
        $request->user()->forceFill(['keep_receipts' => $request->boolean('keep')])->save();

        return back();
    }
}
