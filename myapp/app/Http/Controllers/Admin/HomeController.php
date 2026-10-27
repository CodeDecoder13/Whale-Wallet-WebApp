<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Card;
use App\Models\ContactMessage;
use App\Models\Receipt;
use App\Models\Recommendation;
use App\Models\RecurringItem;
use App\Models\Transaction;
use App\Models\User;
use App\Services\DailyDigest;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The admin overview. Models that are scoped to the signed-in user are read with withoutGlobalScopes():
 * that is the one place the admin sees across users, and only counts and sums come out of it here.
 */
class HomeController extends Controller
{
    public function __invoke(): Response
    {
        $users = User::query();
        $total = (clone $users)->count();
        $verified = (clone $users)->whereNotNull('email_verified_at')->count();

        // "active" = had a session request in the window (sessions live in the database)
        $active = fn (int $hours) => DB::table('sessions')->whereNotNull('user_id')
            ->where('last_activity', '>=', now()->subHours($hours)->timestamp)->distinct()->count('user_id');

        $receipts = fn () => Receipt::withoutGlobalScopes()->where('created_at', '>=', now()->subWeek());
        $loans = RecurringItem::withoutGlobalScopes()->active()->where('kind', 'debt')
            ->where(fn ($q) => $q->whereNull('installments')->orWhere('installments', '>', 0));
        $today = now(DailyDigest::TZ)->toDateString();

        return Inertia::render('admin/Home', [
            'users' => [
                'total' => $total,
                'today' => (clone $users)->where('created_at', '>=', now()->startOfDay())->count(),
                'week' => (clone $users)->where('created_at', '>=', now()->subDays(7))->count(),
                'month' => (clone $users)->where('created_at', '>=', now()->subDays(30))->count(),
                'verified_pct' => $total ? (int) round($verified / $total * 100) : 0,
                'with_pin' => (clone $users)->whereNotNull('pin')->count(),
                'with_2fa' => (clone $users)->whereNotNull('two_factor_confirmed_at')->count(),
                'active_day' => $active(24),
                'active_week' => $active(24 * 7),
            ],
            'money' => [
                'cards' => Card::withoutGlobalScopes()->count(),
                'transactions_month' => Transaction::withoutGlobalScopes()->where('occurred_at', '>=', now()->startOfMonth())->count(),
                'loans' => (clone $loans)->count(),
                'subscriptions' => RecurringItem::withoutGlobalScopes()->active()->where('kind', 'subscription')->count(),
            ],
            'ai' => [
                'receipts_week' => $receipts()->count(),
                'receipts_failed' => $receipts()->where('status', 'failed')->count(),
                'receipts_stuck' => Receipt::withoutGlobalScopes()->whereIn('status', ['queued', 'parsing'])->where('updated_at', '<', now()->subMinutes(2))->count(),
                'tips_week' => Recommendation::withoutGlobalScopes()->where('created_at', '>=', now()->subWeek())->count(),
            ],
            'health' => [
                'digests_today' => User::where('digest_sent_on', $today)->count(),
                'digest_failures_today' => ActivityLog::where('event', 'digest_failed')->where('created_at', '>=', now()->startOfDay())->count(),
                'last_digest_on' => User::max('digest_sent_on'),
                'failed_jobs' => DB::table('failed_jobs')->count(),
                'contact_messages' => ContactMessage::count(),
            ],
        ]);
    }
}
