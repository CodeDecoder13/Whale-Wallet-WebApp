<?php

namespace Tests\Feature;

use App\Mail\SupportNotice;
use App\Models\SupportMessage;
use App\Models\SupportThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SupportChatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Mail::fake();
    }

    private function admin(string $role = 'owner'): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['admin_role' => $role])->save();

        return $admin;
    }

    private function asAdmin(User $admin): static
    {
        return $this->actingAs($admin)->withSession(['admin_gate' => [$admin->id, now()->timestamp]]);
    }

    public function test_a_user_writes_and_the_admins_get_one_email_per_burst()
    {
        $owner = $this->admin();
        $support = $this->admin('support');
        $user = User::factory()->create(['name' => 'Ana Cruz']);

        $this->actingAs($user)->post(route('support.store'), ['body' => '  My loan total looks wrong  '])->assertSessionHasNoErrors();
        $this->post(route('support.store'), ['body' => 'Hello?']);

        $thread = SupportThread::withoutGlobalScopes()->sole();
        $this->assertSame([$user->id, 2, 'open'], [$thread->user_id, $thread->admin_unread, $thread->status]);
        $this->assertSame('My loan total looks wrong', SupportMessage::withoutGlobalScopes()->oldest('id')->first()->body); // trimmed

        // two messages inside 10 minutes = one email, for the first message, sent to every admin
        // (not counted by number: a test app reuses its terminating callbacks across requests, a real request never does)
        Mail::assertNotSent(SupportNotice::class, fn ($m) => str_contains($m->preview, 'Hello?'));
        Mail::assertSent(SupportNotice::class, fn ($m) => $m->hasTo($owner->email) && str_contains($m->render(), 'My loan total looks wrong') && str_contains($m->render(), '/privacy-policy'));
        Mail::assertSent(SupportNotice::class, fn ($m) => $m->hasTo($support->email));
    }

    public function test_messages_must_have_text_and_stay_under_1000_characters()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->post(route('support.store'), ['body' => '   '])->assertSessionHasErrors('body');
        $this->post(route('support.store'), ['body' => str_repeat('a', 1001)])->assertSessionHasErrors('body');
        $this->post(route('support.store'), ['body' => str_repeat('a', 1000)])->assertSessionHasNoErrors();
        $this->assertSame(1, SupportMessage::withoutGlobalScopes()->count());
    }

    public function test_a_user_only_ever_sees_their_own_chat_and_opening_it_marks_replies_read()
    {
        [$a, $b] = User::factory(2)->create();
        $admin = $this->admin();
        $this->actingAs($a)->post(route('support.store'), ['body' => 'from A']);
        $this->actingAs($b)->post(route('support.store'), ['body' => 'from B']);
        $threadA = SupportThread::withoutGlobalScopes()->where('user_id', $a->id)->first();

        $this->asAdmin($admin)->post(route('admin.support.reply', $threadA->id), ['body' => 'Hi Ana'])->assertSessionHasNoErrors();
        $this->assertSame(1, $threadA->fresh()->user_unread);

        // the unread badge reaches the page props; opening the chat clears it
        $this->actingAs($a)->get(route('cards.index'))->assertInertia(fn (Assert $p) => $p->where('supportUnread', 1));
        $this->get(route('support.index'))->assertInertia(fn (Assert $p) => $p
            ->has('messages', 2)->where('messages.0.body', 'from A')->where('messages.1.body', 'Hi Ana')->where('messages.1.from_admin', true));
        $this->get(route('cards.index'))->assertInertia(fn (Assert $p) => $p->where('supportUnread', 0));
        $this->assertNotNull(SupportMessage::withoutGlobalScopes()->where('from_admin', true)->first()->read_at);

        $this->actingAs($b)->get(route('support.index'))->assertInertia(fn (Assert $p) => $p->has('messages', 1)->where('messages.0.body', 'from B'));
    }

    public function test_the_admin_inbox_lists_unread_chats_and_opening_one_marks_it_seen()
    {
        $admin = $this->admin();
        $user = User::factory()->create(['name' => 'Ana Cruz']);
        $this->actingAs($user)->post(route('support.store'), ['body' => 'Need help']);
        $thread = SupportThread::withoutGlobalScopes()->sole();

        $this->asAdmin($admin)->get(route('admin.support', ['filter' => 'unread']))->assertInertia(fn (Assert $p) => $p
            ->has('threads', 1)->where('threads.0.unread', 1)->where('threads.0.preview', 'Need help')->where('open', null)->where('adminSupportUnread', 1));

        $this->get(route('admin.support', ['thread' => $thread->id]))->assertInertia(fn (Assert $p) => $p
            ->where('open.user.name', 'Ana Cruz')->has('messages', 1)->where('adminSupportUnread', 0));
        $this->assertSame(0, $thread->fresh()->admin_unread);
        $this->assertNotNull(SupportMessage::withoutGlobalScopes()->first()->read_at); // the user's "Seen"
        $this->assertDatabaseHas('admin_audit', ['action' => 'viewed_support', 'target_user_id' => $user->id]);

        $this->actingAs($user)->get(route('support.index'))->assertInertia(fn (Assert $p) => $p->where('messages.0.seen', true));
    }

    public function test_replying_emails_the_user_only_when_they_are_away_and_close_and_reopen_work()
    {
        $admin = $this->admin();
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('support.store'), ['body' => 'Help']);
        $thread = SupportThread::withoutGlobalScopes()->sole();

        // signed in a moment ago: the badge is enough, no email
        DB::table('sessions')->insert(['id' => 's1', 'user_id' => $user->id, 'payload' => '', 'last_activity' => now()->timestamp]);
        $this->asAdmin($admin)->post(route('admin.support.reply', $thread->id), ['body' => 'First']);
        Mail::assertNotSent(SupportNotice::class, fn ($m) => $m->hasTo($user->email));

        DB::table('sessions')->update(['last_activity' => now()->subHour()->timestamp]);
        $this->post(route('admin.support.reply', $thread->id), ['body' => 'Second']);
        Mail::assertSent(SupportNotice::class, fn ($m) => $m->hasTo($user->email) && $m->headline === 'Whale Wallet support replied');
        $this->post(route('admin.support.reply', $thread->id), ['body' => 'Third']); // inside 10 minutes: no second email
        Mail::assertSent(SupportNotice::class, fn ($m) => $m->hasTo($user->email));

        $this->post(route('admin.support.status', $thread->id), ['status' => 'closed']);
        $this->assertSame('closed', $thread->fresh()->status);
        $this->get(route('admin.support', ['filter' => 'closed']))->assertInertia(fn (Assert $p) => $p->has('threads', 1));
        $this->get(route('admin.support'))->assertInertia(fn (Assert $p) => $p->has('threads', 0));

        $this->actingAs($user)->post(route('support.store'), ['body' => 'Thanks, one more thing']); // writing reopens it
        $this->assertSame('open', $thread->fresh()->status);
        $this->assertDatabaseHas('admin_audit', ['action' => 'support_reply', 'target_user_id' => $user->id]);
    }

    public function test_a_support_admin_gets_the_inbox_but_not_user_data_and_a_normal_user_gets_a_404()
    {
        $support = $this->admin('support');
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('support.store'), ['body' => 'Help']);
        $thread = SupportThread::withoutGlobalScopes()->sole();

        $this->asAdmin($support)->get(route('admin.support', ['thread' => $thread->id]))->assertOk();
        $this->post(route('admin.support.reply', $thread->id), ['body' => 'On it'])->assertSessionHasNoErrors();
        $this->get(route('admin.users'))->assertNotFound();
        $this->get(route('admin.transactions'))->assertNotFound();

        // through the gate a support admin lands in the inbox, not on the (owner-only) overview
        $this->flushSession();
        \Illuminate\Support\Facades\Cache::put("admin-otp:{$support->id}", \Illuminate\Support\Facades\Hash::make('654321'), now()->addMinutes(10));
        $support->forceFill(['pin' => \Illuminate\Support\Facades\Hash::make('482913')])->save();
        $this->post(route('admin.gate.verify'), ['pin' => '482913', 'code' => '654321'])->assertRedirect(route('admin.support'));

        $this->actingAs($user)->get(route('admin.support'))->assertNotFound();
        $this->post(route('admin.support.reply', $thread->id), ['body' => 'x'])->assertNotFound();
    }
}
