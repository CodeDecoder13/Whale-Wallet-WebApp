<?php

namespace App\Http\Controllers;

use App\Http\Requests\PodRequest;
use App\Models\Pod;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PodController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('pods/Index', ['pods' => Pod::orderBy('name')->get()]);
    }

    public function store(PodRequest $request): RedirectResponse
    {
        Pod::create($request->validated());

        return back();
    }

    public function update(PodRequest $request, Pod $pod): RedirectResponse
    {
        $pod->update($request->validated());

        return back();
    }

    public function destroy(Pod $pod): RedirectResponse
    {
        $pod->delete();

        return back();
    }
}
