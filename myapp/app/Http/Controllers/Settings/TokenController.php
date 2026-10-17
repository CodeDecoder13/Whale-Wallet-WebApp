<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** The signed-in user's mobile API tokens (one per device name). */
class TokenController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('settings/Tokens', [
            'tokens' => $request->user()->tokens()->latest('id')->get(['id', 'name', 'created_at', 'last_used_at']),
        ]);
    }

    /** Only the user's own tokens resolve, so another user's id is a 404. */
    public function destroy(Request $request, int $token): RedirectResponse
    {
        $request->user()->tokens()->findOrFail($token)->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Device signed out.')]);

        return back();
    }
}
