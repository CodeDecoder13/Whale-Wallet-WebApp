<?php

namespace App\Http\Controllers\Api;

use App\Actions\GenerateRecommendations;
use App\Http\Controllers\Controller;
use App\Http\Resources\RecommendationResource;
use App\Models\Recommendation;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Throwable;

/** Tips and AI consent. Receipts and chat reuse the web controllers. */
class AiController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return RecommendationResource::collection(Recommendation::whereNull('dismissed_at')->latest('id')->get());
    }

    /** Behind ai.consent and a per-user throttle. Replaces the current tips. */
    public function generate(Request $request, GenerateRecommendations $generate): AnonymousResourceCollection|JsonResponse
    {
        try {
            return RecommendationResource::collection($generate->handle($request->user()));
        } catch (DomainException $e) { // our own wording, safe to show
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            report($e); // provider errors can carry request details, so the client gets a fixed sentence

            return response()->json(['message' => 'Could not reach the AI service. Try again in a minute.'], 503);
        }
    }

    public function dismiss(Recommendation $recommendation): JsonResponse
    {
        $recommendation->update(['dismissed_at' => now()]);

        return response()->json(null, 204);
    }

    public function consent(Request $request): JsonResponse
    {
        $request->user()->forceFill(['ai_consent_at' => $request->user()->ai_consent_at ?? now()])->save();

        return response()->json(['ai_consent_at' => $request->user()->ai_consent_at]);
    }

    /** Switching "AI features" off in the app; chat, receipts and tips answer 422 (with consent_url) until consent is given again. */
    public function revoke(Request $request): JsonResponse
    {
        $request->user()->forceFill(['ai_consent_at' => null])->save();

        return response()->json(['ai_consent_at' => null]);
    }
}
