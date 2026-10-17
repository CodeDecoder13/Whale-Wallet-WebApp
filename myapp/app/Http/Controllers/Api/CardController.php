<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\CardRequest;
use App\Http\Resources\CardResource;
use App\Models\Card;
use Illuminate\Database\Eloquent\Builder;

class CardController extends ResourceController
{
    protected string $model = Card::class;

    protected string $form = CardRequest::class;

    protected string $resource = CardResource::class;

    protected function query(): Builder
    {
        return Card::withSpent()->orderBy('name');
    }
}
