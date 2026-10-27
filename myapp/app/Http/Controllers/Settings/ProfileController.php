<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            'pay_dates' => $request->user()->pay_dates ?? [],
            'digest' => $request->user()->only(['digest_enabled', 'digest_time']),
            'hasPin' => (bool) $request->user()->pin,
            'consented' => (bool) $request->user()->ai_consent_at,
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    /** The profile picture, shrunk to a small JPEG by the browser; null removes it. */
    public function avatar(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'avatar' => ['nullable', 'string', 'max:40000', 'starts_with:data:image/jpeg;base64,'],
        ]);

        $request->user()->forceFill(['avatar' => $data['avatar']])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile picture updated.')]);

        return to_route('profile.edit');
    }

    /** The daily Whale tips email: on or off, and the Philippine-time hour it goes out (09:00 by default). */
    public function digest(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'digest_enabled' => ['required', 'boolean'],
            'digest_time' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
        ]);

        // a changed time that is still ahead today should still fire today, so the "sent today" mark is cleared
        $request->user()->forceFill([...$data, 'digest_sent_on' => null])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Daily digest updated.')]);

        return to_route('profile.edit');
    }

    /** Days of the month income arrives; the forecast counts down to the next one. */
    public function payday(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'pay_dates' => ['required', 'array', 'min:1', 'max:2'],
            'pay_dates.*' => ['integer', 'between:1,31', 'distinct'],
        ]);
        $days = array_map('intval', $data['pay_dates']);
        sort($days);

        $request->user()->forceFill(['pay_dates' => $days, 'pay_cycle' => count($days) > 1 ? 'semi_monthly' : 'monthly'])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payday updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
