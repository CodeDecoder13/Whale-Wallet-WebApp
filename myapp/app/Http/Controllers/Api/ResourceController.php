<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Plain CRUD for a user-owned model. The global scope turns another user's id into a 404.
 * PATCH takes the same body as POST (no partial updates).
 */
abstract class ResourceController extends Controller
{
    /** @var class-string<Model> */
    protected string $model;

    /** @var class-string<FormRequest> */
    protected string $form;

    /** @var class-string<JsonResource> */
    protected string $resource;

    protected function query(): Builder
    {
        return $this->model::query()->orderBy('name');
    }

    public function index(): AnonymousResourceCollection
    {
        return $this->resource::collection($this->query()->get());
    }

    public function store(): JsonResponse
    {
        $item = $this->model::create(app($this->form)->validated());

        return (new $this->resource($this->query()->findOrFail($item->id)))->response()->setStatusCode(201);
    }

    public function show(int $id): JsonResource
    {
        return new $this->resource($this->query()->findOrFail($id));
    }

    public function update(int $id): JsonResource
    {
        $item = $this->model::findOrFail($id);
        $item->update(app($this->form)->validated());

        return new $this->resource($this->query()->findOrFail($id));
    }

    public function destroy(int $id): JsonResponse
    {
        $this->model::findOrFail($id)->delete();

        return response()->json(null, 204);
    }
}
