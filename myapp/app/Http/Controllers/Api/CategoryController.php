<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\CategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;

/** Deleting a category keeps its transactions (the foreign key nulls them out). */
class CategoryController extends ResourceController
{
    protected string $model = Category::class;

    protected string $form = CategoryRequest::class;

    protected string $resource = CategoryResource::class;
}
