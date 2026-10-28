<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\SupportNotice;
use App\Models\AdminAudit;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Who is an admin. Owners only. An owner cannot change their own role, so there is always at least one owner left
 * (the one making the change). Every change is logged and emailed to the affected person and to all owners.
 */
class AdminController extends Controller
{
    private const ROLES = ['owner', 'support'];

    public function index(Request $request): Response
    {
        // owners first: "owner" sorts before "support"
        return Inertia::render('admin/Admins', [
            'admins' => User::whereNotNull('admin_role')->orderBy('admin_role')->orderBy('name')->get()->map(fn ($u) => [
                'id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'role' => $u->admin_role, 'me' => $u->id === $request->user()->id,
                'last_gate_at' => $u->last_admin_gate_at?->toIso8601String(), 'login_alerts' => $u->admin_role === 'owner' ? $u->login_alerts : null,
                'suspended' => $u->suspended_at !== null,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email'], 'role' => ['required', 'in:'.implode(',', self::ROLES)]]);
        $user = User::where('email', $data['email'])->first();

        // owner-only page, so the exact reason is spelled out
        $problem = match (true) {
            ! $user => 'No one has an account with that email. They need to sign up first.',
            $user->admin_role !== null => 'They are already an admin.',
            ! $user->hasVerifiedEmail() => 'They have not verified their email yet.',
            $user->suspended_at !== null => 'That account is suspended.',
            ! $user->pin => 'They need a login PIN first: Settings > Security > Login PIN. Ask them to set one, then add them.',
            default => null,
        };

        if ($problem) {
            throw ValidationException::withMessages(['email' => $problem]);
        }

        $user->forceFill(['admin_role' => $data['role']])->save();
        AdminAudit::record($request->user(), 'added_admin', $user, ['role' => $data['role']]);
        $this->tell($request->user(), $user, "{$user->name} is now an admin ({$data['role']})", "{$request->user()->name} made {$user->name} ({$user->email}) an admin with the {$data['role']} role.", "You now have {$data['role']} access to the Whale Wallet admin. Next time you open it you will confirm with your PIN and an emailed code.", route('admin.gate'), 'Open the admin');

        return back();
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate(['role' => ['required', 'in:'.implode(',', self::ROLES)]]);
        $this->guard($request, $user);

        if ($user->admin_role === $data['role']) {
            return back();
        }

        $from = $user->admin_role;
        $user->forceFill(['admin_role' => $data['role']])->save();
        AdminAudit::record($request->user(), 'changed_admin_role', $user, ['from' => $from, 'to' => $data['role']]);
        $this->tell($request->user(), $user, "{$user->name} is now {$data['role']}", "{$request->user()->name} changed {$user->name}'s admin role from {$from} to {$data['role']}.", "Your Whale Wallet admin role is now {$data['role']}.", route('admin.gate'), 'Open the admin');

        return back();
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->guard($request, $user);

        // the role is read from the database on every admin request, so their access ends with this request
        $user->forceFill(['admin_role' => null, 'last_admin_gate_at' => null])->save();
        AdminAudit::record($request->user(), 'removed_admin', $user);
        $this->tell($request->user(), $user, "{$user->name} is no longer an admin", "{$request->user()->name} removed {$user->name} ({$user->email}) as an admin.", 'Your Whale Wallet admin access was removed. Your normal account is not affected.', rtrim(config('app.url'), '/').'/dashboard', 'Open Whale Wallet');

        return back();
    }

    /** Admins only, and never yourself: that is what keeps at least one owner. */
    private function guard(Request $request, User $user): void
    {
        abort_unless($user->admin_role, 404);
        abort_if($user->id === $request->user()->id, 422, 'You cannot change your own admin role. Ask another owner.');
    }

    /** One email to the person, one to every owner. A mail problem never undoes the change. */
    private function tell(User $by, User $target, string $headline, string $ownersText, string $personText, string $url, string $cta): void
    {
        $owners = User::where('admin_role', 'owner')->whereNull('suspended_at')->get();

        // SupportNotice is the shared "headline, short text, one button" email; it is not only for support chat
        dispatch(function () use ($target, $owners, $headline, $ownersText, $personText, $url, $cta) {
            try {
                Mail::to($target)->send(new SupportNotice($target->name, "Your admin access: {$headline}", $personText, $url, $cta));
            } catch (Throwable $e) {
                report($e);
            }

            foreach ($owners->reject(fn ($o) => $o->id === $target->id) as $owner) {
                try {
                    Mail::to($owner)->send(new SupportNotice($owner->name, "Admin change: {$headline}", $ownersText, route('admin.admins'), 'Open Admins'));
                } catch (Throwable $e) {
                    report($e);
                }
            }
        })->afterResponse();
    }
}
