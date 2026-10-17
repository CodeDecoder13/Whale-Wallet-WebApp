<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\PodRequest;
use App\Http\Resources\PodResource;
use App\Models\Pod;

class PodController extends ResourceController
{
    protected string $model = Pod::class;

    protected string $form = PodRequest::class;

    protected string $resource = PodResource::class;
}
