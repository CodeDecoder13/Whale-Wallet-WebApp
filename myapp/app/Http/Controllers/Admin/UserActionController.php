<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AdminAudit;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * What an owner can do to a user's account. None of it touches their money data: an admin can suspend, sign out
 * and resend a code, never edit transactions, receipts or chats, and never sign in as the user.
 */
class UserActionController extends Controller
{
    /** Admins are managed on their own page (phase 8), so these actions never reach an admin account, including the caller's own. */
    private function target(User $user): User
    {
        abort_if($user->admin_role, 422, 'Admin accounts are managed from Admin management.');

        return $user;
    }

    private function toast(string $message): void
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);
    }

    /** Ends every web session and API token the user has. */
    private function signOutEverywhere(User $user): void
    {
        DB::table('sessions')->where('user_id', $user->id)->delete();
        $user->tokens()->delete();
    }

    public function suspend(Request $request, User $user): RedirectResponse
    {
        $this->target($user)->forceFill(['suspended_at' => now()])->save();
        $this->signOutEverywhere($user);
        AdminAudit::record($request->user(), 'suspended_user', $user);
        ActivityLog::record('suspended', $user->id);
        $this->toast("{$user->name} is suspended and signed out.");

        return back();
    }

    public function unsuspend(Request $request, User $user): RedirectResponse
    {
        $this->target($user)->forceFill(['suspended_at' => null])->save();
        AdminAudit::record($request->user(), 'unsuspended_user', $user);
        ActivityLog::record('unsuspended', $user->id);
        $this->toast("{$user->name} can sign in again.");

        return back();
    }

    public function signOut(Request $request, User $user): RedirectResponse
    {
        $this->signOutEverywhere($this->target($user));
        AdminAudit::record($request->user(), 'signed_out_user', $user);
        $this->toast("{$user->name} was signed out everywhere.");

        return back();
    }

    public function resendCode(Request $request, User $user): RedirectResponse
    {
        abort_if($this->target($user)->hasVerifiedEmail(), 422, 'That email is already verified.');

        $user->sendEmailVerificationNotification();
        AdminAudit::record($request->user(), 'resent_verification_code', $user);
        $this->toast("A new code is on its way to {$user->email}.");

        return back();
    }
}
