<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\RequireAdminGate;
use App\Mail\VerifyCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** The admin gate: the 6-digit PIN plus a code emailed to the admin, then 2 hours of access. */
class GateController extends Controller
{
    private const MAX_TRIES = 5;

    public function show(Request $request): Response|RedirectResponse
    {
        if (RequireAdminGate::passed($request)) {
            return redirect()->intended($this->landing($request));
        }

        $user = $request->user();

        return Inertia::render('admin/Gate', [
            'hasPin' => (bool) $user->pin,
            'codeSent' => Cache::has("admin-otp:{$user->id}"),
            'email' => $user->email,
        ]);
    }

    public function code(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->pin) {
            throw ValidationException::withMessages(['pin' => __('Set a login PIN first in Settings > Security.')]);
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        Cache::put("admin-otp:{$user->id}", Hash::make($code), now()->addMinutes(10));
        dispatch(fn () => Mail::to($user)->send(new VerifyCode($user->name, $code, 'admin')))->afterResponse();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('We emailed you a code.')]);

        return back();
    }

    public function verify(Request $request): RedirectResponse
    {
        $data = $request->validate(['pin' => ['required', 'digits:6'], 'code' => ['required', 'digits:6']]);
        $user = $request->user();
        $hash = Cache::get("admin-otp:{$user->id}");
        $tries = "admin-tries:{$user->id}";

        if (Cache::get($tries, 0) >= self::MAX_TRIES) {
            throw ValidationException::withMessages(['code' => __('Too many tries. Wait 15 minutes.')]);
        }

        if (! $user->pin || ! $hash) {
            throw ValidationException::withMessages(['code' => __('That code has expired. Send a new one.')]);
        }

        // both must be right; one counter for the pair so neither can be guessed on its own
        if (! Hash::check($data['pin'], $user->pin) || ! Hash::check($data['code'], $hash)) {
            Cache::put($tries, Cache::get($tries, 0) + 1, now()->addMinutes(15));

            throw ValidationException::withMessages(['code' => __('The PIN or the code is not right.')]);
        }

        Cache::forget("admin-otp:{$user->id}");
        Cache::forget($tries);
        $request->session()->put('admin_gate', [$user->id, now()->timestamp]);

        return redirect()->intended($this->landing($request));
    }

    /** Owners open on the overview; a support admin has no overview, so they land in the support inbox. */
    private function landing(Request $request): string
    {
        return $request->user()->admin_role === 'owner' ? route('admin.home') : route('admin.support');
    }
}
