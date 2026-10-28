<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** How often this owner gets an email when a user signs in. */
class LoginAlertSettingController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['login_alerts' => ['required', 'in:all,new,off']]);

        $request->user()->forceFill($data)->save();
        AdminAudit::record($request->user(), 'changed_login_alerts', null, ['to' => $data['login_alerts']]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Login alerts updated.']);

        return back();
    }
}
