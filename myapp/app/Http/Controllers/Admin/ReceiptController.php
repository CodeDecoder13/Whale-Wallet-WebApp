<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAudit;
use App\Models\Receipt;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Every scanned receipt, the image included. Images are only served through the gated route below, never by a public link. */
class ReceiptController extends Controller
{
    private function find(int $id): Receipt
    {
        return Receipt::withoutGlobalScopes()->with('user:id,name,email')->findOrFail($id);
    }

    public function index(Request $request): Response
    {
        $stuck = $request->boolean('stuck');

        $receipts = Receipt::withoutGlobalScopes()->with('user:id,name,email')
            ->when($request->query('user'), fn ($q, $id) => $q->where('user_id', (int) $id))
            ->when(in_array($request->query('status'), ['queued', 'parsing', 'parsed', 'failed', 'confirmed'], true), fn ($q) => $q->where('status', $request->query('status')))
            ->when($stuck, fn ($q) => $q->whereIn('status', ['queued', 'parsing'])->where('updated_at', '<', now()->subMinutes(2)))
            ->latest('id')->paginate(25)->withQueryString()
            ->through(fn (Receipt $r) => [
                'id' => $r->id, 'status' => $r->status, 'at' => $r->created_at->toIso8601String(), 'error' => $r->error,
                'merchant' => $r->parsed_json['merchant'] ?? null, 'amount' => $r->parsed_json['amount'] ?? null,
                'confidence' => $r->confidence, 'has_image' => $r->path !== null, 'user' => $r->user?->only('id', 'name', 'email'),
            ]);

        return Inertia::render('admin/Receipts', ['receipts' => $receipts, 'filters' => $request->only('user', 'status', 'stuck')]);
    }

    public function show(Request $request, int $receipt): Response
    {
        $r = $this->find($receipt);
        AdminAudit::record($request->user(), 'viewed_receipt', $r->user, ['receipt_id' => $r->id]);

        return Inertia::render('admin/ReceiptShow', [
            'receipt' => [
                'id' => $r->id, 'status' => $r->status, 'at' => $r->created_at->toIso8601String(), 'error' => $r->error, 'confidence' => $r->confidence,
                'claimed' => $r->claimed_amount, 'draft' => $r->parsed_json, 'corrections' => $r->corrections_json,
                'has_image' => $r->path !== null, 'user' => $r->user?->only('id', 'name', 'email'),
            ],
            'transactions' => Transaction::withoutGlobalScopes()->where('receipt_id', $r->id)->get(['id', 'merchant', 'amount', 'occurred_at', 'type']),
        ]);
    }

    public function image(Request $request, int $receipt): StreamedResponse
    {
        $r = $this->find($receipt);
        abort_unless($r->path && Storage::disk()->exists($r->path), 404);
        AdminAudit::record($request->user(), 'opened_receipt_image', $r->user, ['receipt_id' => $r->id]);

        return Storage::disk()->response($r->path, null, ['Cache-Control' => 'private, no-store']);
    }
}
