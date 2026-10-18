<?php

namespace App\Http\Controllers\Api;

use App\Actions\CreateTransaction;
use App\Actions\DeleteTransaction;
use App\Actions\TransferBetween;
use App\Actions\UpdateTransaction;
use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\TransactionFilterRequest;
use App\Http\Requests\TransactionRequest;
use App\Http\Requests\TransferRequest;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TransactionController extends Controller
{
    public function index(TransactionFilterRequest $request): AnonymousResourceCollection
    {
        $f = $request->validated();

        return TransactionResource::collection(
            Transaction::filter($f)->with('card:id,name', 'category:id,name')->latest('occurred_at')->latest('id')->paginate($f['per_page'] ?? 20)->withQueryString()
        );
    }

    public function store(TransactionRequest $request, CreateTransaction $create): JsonResponse
    {
        return (new TransactionResource($create->handle($request->validated())->fresh()))->response()->setStatusCode(201);
    }

    public function show(int $id): TransactionResource
    {
        return new TransactionResource(Transaction::findOrFail($id));
    }

    /** Only expenses are editable; a transfer must be deleted and redone. PATCH takes the same body as POST. */
    public function update(TransactionRequest $request, int $id, UpdateTransaction $update): TransactionResource
    {
        $transaction = Transaction::findOrFail($id);
        abort_unless($transaction->type === TransactionType::Expense, 422, 'Transfers cannot be edited.');

        return new TransactionResource($update->handle($transaction, $request->validated())->fresh());
    }

    public function destroy(int $id, DeleteTransaction $delete): JsonResponse
    {
        $delete->handle(Transaction::findOrFail($id));

        return response()->json(null, 204);
    }

    /** from and to are "card:ID" or "pod:ID". */
    public function transfer(TransferRequest $request, TransferBetween $transfer): JsonResponse
    {
        return (new TransactionResource($transfer->handle(...$request->safe()->only(['from', 'to', 'amount']))->fresh()))
            ->response()->setStatusCode(201);
    }
}
