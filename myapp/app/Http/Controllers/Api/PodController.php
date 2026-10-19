<?php

namespace App\Http\Controllers\Api;

use App\Actions\FundPod;
use App\Http\Requests\FundPodRequest;
use App\Http\Requests\PodRequest;
use App\Http\Resources\PodResource;
use App\Http\Resources\TransactionResource;
use App\Models\Pod;

class PodController extends ResourceController
{
    protected string $model = Pod::class;

    protected string $form = PodRequest::class;

    protected string $resource = PodResource::class;

    /** Add money from a card (amount in centavos), with the reference number and receipt as proof. Returns the transfer. */
    public function fund(FundPodRequest $request, Pod $pod, FundPod $fund): TransactionResource
    {
        return new TransactionResource($fund->handle($pod, $request->validated())->load('card:id,name'));
    }
}
