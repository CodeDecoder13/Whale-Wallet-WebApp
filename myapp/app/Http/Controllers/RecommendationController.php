<?php

namespace App\Http\Controllers;

use App\Actions\GenerateRecommendations;
use App\Models\Recommendation;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class RecommendationController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('tips/Index', [
            'tips' => Recommendation::whereNull('dismissed_at')->latest('id')->get(['id', 'text']),
            'consented' => (bool) request()->user()->ai_consent_at,
        ]);
    }

    /** No consent gives a 403 from the action. */
    public function store(Request $request, GenerateRecommendations $generate): RedirectResponse
    {
        try {
            $generate->handle($request->user());
        } catch (DomainException $e) { // our own wording, safe to show
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        } catch (AuthorizationException $e) {
            throw $e;
        } catch (Throwable $e) {
            // Gemini errors can carry request details, so show a fixed message
            report($e);
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Could not reach the AI service. Try again in a minute.']);
        }

        return back();
    }

    public function dismiss(Recommendation $recommendation): RedirectResponse
    {
        $recommendation->update(['dismissed_at' => now()]);

        return back();
    }

    /** Free-tier Gemini inputs may be used by Google, so nothing is sent before this. */
    public function consent(Request $request): RedirectResponse
    {
        $request->user()->forceFill(['ai_consent_at' => $request->user()->ai_consent_at ?? now()])->save();

        return back();
    }
}
