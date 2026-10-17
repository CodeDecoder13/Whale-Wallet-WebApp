<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\RecurringItemRequest;
use App\Http\Resources\RecurringItemResource;
use App\Models\RecurringItem;
use Illuminate\Database\Eloquent\Builder;

class RecurringItemController extends ResourceController
{
    protected string $model = RecurringItem::class;

    protected string $form = RecurringItemRequest::class;

    protected string $resource = RecurringItemResource::class;

    protected function query(): Builder
    {
        return RecurringItem::orderBy('next_due_on');
    }
}
