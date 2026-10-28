<?php

namespace Tests\Feature;

use App\Mail\ReleaseNoteMail;
use App\Models\ReleaseNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReleaseNotesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Mail::fake();
        $this->admin = User::factory()->create(['email_verified_at' => now()->subYear()]);
        $this->admin->forceFill(['admin_role' => 'owner'])->save();
    }

    private function asAdmin(): static
    {
        return $this->actingAs($this->admin)->withSession(['admin_gate' => [$this->admin->id, now()->timestamp]]);
    }

    private function note(array $extra = []): ReleaseNote
    {
        return ReleaseNote::create(['title' => 'Support chat', 'body' => "Talk to us in the app.\n\n- Open Support\n- We reply by email", ...$extra]);
    }

    public function test_the_body_becomes_paragraphs_and_bullets_and_is_never_html()
    {
        $blocks = $this->note(['body' => "First line\nsecond line\n\n- one\n- two\n\nBye <script>alert(1)</script>"])->blocks();

        $this->assertSame(['p', 'p', 'ul', 'p'], array_column($blocks, 'type'));
        $this->assertSame(['one', 'two'], $blocks[2]['items']);

        $html = (new ReleaseNoteMail($this->note(['body' => 'Bye <script>alert(1)</script>']), $this->admin))->render();
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_an_owner_can_create_edit_and_delete_notes_and_it_is_audited_and_owner_only()
    {
        $this->asAdmin()->post(route('admin.release-notes.store'), ['title' => '', 'body' => ''])->assertSessionHasErrors(['title', 'body']);

        $this->post(route('admin.release-notes.store'), ['title' => 'Hello', 'version' => 'v1.4', 'body' => 'Body', 'send_email' => true, 'show_popup' => false])->assertRedirect();
        $note = ReleaseNote::sole();
        $this->assertSame(['draft', false], [$note->status, $note->show_popup]);

        $this->put(route('admin.release-notes.update', $note->id), ['title' => 'Hello again', 'body' => 'Body 2', 'send_email' => true, 'show_popup' => true])->assertSessionHasNoErrors();
        $this->assertSame('Hello again', $note->fresh()->title);
        $this->get(route('admin.release-notes'))->assertInertia(fn (Assert $p) => $p->has('notes', 1)->where('notes.0.title', 'Hello again'));
        $this->get(route('admin.release-notes.edit', $note->id))->assertInertia(fn (Assert $p) => $p->where('note.body', 'Body 2'));

        $this->delete(route('admin.release-notes.destroy', $note->id))->assertRedirect(route('admin.release-notes'));
        $this->assertSame(0, ReleaseNote::count());
        foreach (['created_release_note', 'edited_release_note', 'deleted_release_note'] as $a) {
            $this->assertDatabaseHas('admin_audit', ['action' => $a]);
        }

        $support = User::factory()->create();
        $support->forceFill(['admin_role' => 'support'])->save();
        $this->actingAs($support)->withSession(['admin_gate' => [$support->id, now()->timestamp]])->get(route('admin.release-notes'))->assertNotFound();
    }

    public function test_the_test_button_sends_only_to_the_admin()
    {
        $note = $this->note();
        User::factory()->create();

        $this->asAdmin()->post(route('admin.release-notes.test', $note->id))->assertSessionHasNoErrors();

        Mail::assertSent(ReleaseNoteMail::class, 1);
        Mail::assertSent(ReleaseNoteMail::class, fn ($m) => $m->hasTo($this->admin->email));
        $this->assertSame('draft', $note->fresh()->status);
    }

    public function test_publishing_emails_each_eligible_user_once_and_shows_the_popup_until_dismissed()
    {
        $ana = User::factory()->create(['created_at' => now()->subMonth()]);
        $unverified = User::factory()->unverified()->create();
        $suspended = User::factory()->create();
        $suspended->forceFill(['suspended_at' => now()])->save();
        $unsub = User::factory()->create(['release_emails' => false]);
        $note = $this->note();

        $this->asAdmin()->post(route('admin.release-notes.publish', $note->id))->assertRedirect();
        $this->post(route('admin.release-notes.publish', $note->id))->assertStatus(422); // only once

        $note->refresh();
        $this->assertSame(['published', 'done'], [$note->status, $note->email_status]);
        foreach ([$ana, $this->admin] as $u) {
            Mail::assertSent(ReleaseNoteMail::class, fn ($m) => $m->hasTo($u->email));
        }
        foreach ([$unverified, $suspended, $unsub] as $u) {
            Mail::assertNotSent(ReleaseNoteMail::class, fn ($m) => $m->hasTo($u->email));
        }
        // a test app replays its after-response callbacks on every request, which the per-user rows make harmless
        Mail::assertSent(ReleaseNoteMail::class, fn ($m) => $m->hasTo($ana->email) && str_contains($m->render(), 'Open Support') && str_contains($m->render(), 'Unsubscribe'));
        $this->assertSame(2, $note->emailed_count);

        // the popup: once, until they say "Got it"
        $this->actingAs($ana)->get(route('cards.index'))->assertInertia(fn (Assert $p) => $p->where('releaseNotice.title', 'Support chat')->has('releaseNotice.blocks', 2));
        $this->post(route('notice.seen', $note->id))->assertRedirect();
        $this->get(route('cards.index'))->assertInertia(fn (Assert $p) => $p->where('releaseNotice', null));
        $this->assertDatabaseHas('release_note_user', ['release_note_id' => $note->id, 'user_id' => $ana->id]);
    }

    public function test_the_popup_skips_notes_older_than_the_user_or_30_days_or_with_it_turned_off_or_still_a_draft()
    {
        $this->note(['status' => 'draft', 'show_popup' => true]);
        $this->note(['title' => 'Quiet', 'status' => 'published', 'published_at' => now(), 'show_popup' => false]);
        $this->note(['title' => 'Ancient', 'status' => 'published', 'published_at' => now()->subDays(40), 'show_popup' => true]);
        $joinedAfter = User::factory()->create(['created_at' => now()]);
        $this->note(['title' => 'Before they joined', 'status' => 'published', 'published_at' => now()->subHour(), 'show_popup' => true]);
        $old = User::factory()->create(['created_at' => now()->subYear()]);

        $this->actingAs($joinedAfter)->get(route('cards.index'))->assertInertia(fn (Assert $p) => $p->where('releaseNotice', null));
        $this->actingAs($old)->get(route('cards.index'))->assertInertia(fn (Assert $p) => $p->where('releaseNotice.title', 'Before they joined'));
    }

    public function test_dismissing_a_note_also_dismisses_the_older_ones_so_they_do_not_stack()
    {
        $user = User::factory()->create(['created_at' => now()->subMonth()]);
        $a = $this->note(['title' => 'A', 'status' => 'published', 'published_at' => now()->subDays(2), 'show_popup' => true]);
        $b = $this->note(['title' => 'B', 'status' => 'published', 'published_at' => now()->subDay(), 'show_popup' => true]);

        $this->actingAs($user)->get(route('cards.index'))->assertInertia(fn (Assert $p) => $p->where('releaseNotice.title', 'B'));
        $this->post(route('notice.seen', $b->id));
        $this->get(route('cards.index'))->assertInertia(fn (Assert $p) => $p->where('releaseNotice', null));
        $this->assertNotNull($a);
    }

    public function test_batches_resume_without_double_sending_and_one_click_unsubscribe_works_and_is_signed()
    {
        $users = User::factory(3)->create();
        $note = $this->note(['status' => 'published', 'published_at' => now(), 'email_status' => 'sending'])->fresh(); // fresh: reads the column defaults
        $sender = app(\App\Services\ReleaseNoteSender::class);

        $this->assertSame(2, $sender->sendBatch($note, 2));
        $this->assertSame('sending', $note->fresh()->email_status);
        $this->artisan('release:send', ['--batch' => 50])->assertSuccessful(); // the scheduler finishes the rest (the admin is a recipient too)
        $this->assertSame('done', $note->fresh()->email_status);
        $this->assertSame(0, $sender->sendBatch($note->fresh())); // nothing left, nobody twice
        $this->assertSame(4, $note->fresh()->emailed_count);

        $url = URL::signedRoute('release.unsubscribe', ['user' => $users[0]->id]);
        $this->get($url)->assertOk()->assertInertia(fn (Assert $p) => $p->component('legal/Unsubscribed'));
        $this->assertFalse($users[0]->fresh()->release_emails);

        $this->get(route('release.unsubscribe', ['user' => $users[1]->id]))->assertForbidden(); // not signed
        $this->assertTrue($users[1]->fresh()->release_emails);
    }
}
