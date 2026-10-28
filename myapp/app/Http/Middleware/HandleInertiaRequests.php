<?php

namespace App\Http\Middleware;

use App\Models\RecurringItem;
use App\Models\ReleaseNote;
use Illuminate\Support\Facades\DB;
use App\Models\SupportThread;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /** Only notes published since the user joined, and in the last 30 days, so nobody opens an old changelog on day one. */
    private function notice(Request $request): ?array
    {
        $user = $request->user();

        if (! $user || AdminHostOnly::isAdminHost($request)) {
            return null;
        }

        $since = $user->created_at->gt(now()->subDays(30)) ? $user->created_at : now()->subDays(30);

        return ReleaseNote::where('status', 'published')->where('show_popup', true)->where('published_at', '>=', $since)
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('release_note_user')
                ->whereColumn('release_note_id', 'release_notes.id')->where('user_id', $user->id)->whereNotNull('seen_at'))
            ->latest('published_at')->first()?->toNotice();
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            // what the Whale Chat button flags: bills, subscriptions and loan payments due today or already late
            'whaleDue' => fn () => $request->user()
                ? RecurringItem::active()->where('next_due_on', '<=', today()->endOfDay())
                    ->where(fn ($q) => $q->whereNull('installments')->orWhere('installments', '>', 0))->count()
                : 0,
            // the newest published release note this user has not dismissed (shown once as a popup)
            'releaseNotice' => fn () => $this->notice($request),
            // support chat badges: admin replies the user has not opened, and (for admins) user messages nobody has opened
            'supportUnread' => fn () => $request->user() ? (int) SupportThread::sum('user_unread') : 0,
            'adminSupportUnread' => fn () => $request->user()?->admin_role ? (int) SupportThread::withoutGlobalScopes()->sum('admin_unread') : 0,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
