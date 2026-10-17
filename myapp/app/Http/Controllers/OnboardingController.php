<?php

namespace App\Http\Controllers;

use App\Actions\SaveOnboarding;
use App\Enums\CardType;
use App\Http\Requests\OnboardingRequest;
use App\Models\Card;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    public function show(): Response|RedirectResponse
    {
        return Card::exists()
            ? to_route('dashboard')
            : Inertia::render('Onboarding', ['types' => array_column(CardType::cases(), 'value')]);
    }

    public function store(OnboardingRequest $request, SaveOnboarding $save): RedirectResponse
    {
        if (! Card::exists()) {
            $save->handle($request->user(), $request->validated());
        }

        return to_route('dashboard');
    }
}
