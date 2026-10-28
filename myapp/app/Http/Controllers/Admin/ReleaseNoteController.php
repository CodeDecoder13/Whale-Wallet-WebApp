<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\ReleaseNoteMail;
use App\Models\AdminAudit;
use App\Models\ReleaseNote;
use App\Services\ReleaseNoteSender;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/** Release notes: write them, test them on yourself, publish them (email + popup). Owner only. */
class ReleaseNoteController extends Controller
{
    private function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'version' => ['nullable', 'string', 'max:20'],
            'body' => ['required', 'string', 'max:5000'],
            'send_email' => ['boolean'],
            'show_popup' => ['boolean'],
        ];
    }

    public function index(): Response
    {
        $seen = DB::table('release_note_user')->whereNotNull('seen_at')->selectRaw('release_note_id, count(*) as n')->groupBy('release_note_id')->pluck('n', 'release_note_id');

        return Inertia::render('admin/ReleaseNotes', [
            'notes' => ReleaseNote::latest('id')->get()->map(fn ($n) => [
                'id' => $n->id, 'title' => $n->title, 'version' => $n->version, 'status' => $n->status, 'send_email' => $n->send_email,
                'show_popup' => $n->show_popup, 'published_at' => $n->published_at?->toIso8601String(), 'email_status' => $n->email_status,
                'emailed' => $n->emailed_count, 'seen' => (int) ($seen[$n->id] ?? 0),
            ]),
            'recipients' => ReleaseNoteSender::recipients()->count(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/ReleaseNoteForm', ['note' => null, 'recipients' => ReleaseNoteSender::recipients()->count()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $note = ReleaseNote::create([...$request->validate($this->rules()), 'created_by' => $request->user()->id]);
        AdminAudit::record($request->user(), 'created_release_note', null, ['id' => $note->id]);

        return to_route('admin.release-notes.edit', $note->id);
    }

    public function edit(int $note): Response
    {
        $n = ReleaseNote::findOrFail($note);

        return Inertia::render('admin/ReleaseNoteForm', [
            'note' => [...$n->only('id', 'title', 'version', 'body', 'send_email', 'show_popup', 'status', 'email_status', 'emailed_count'), 'blocks' => $n->blocks()],
            'recipients' => ReleaseNoteSender::recipients()->count(),
        ]);
    }

    public function update(Request $request, int $note): RedirectResponse
    {
        // a published note can still be corrected; what it did (email, popup) is not redone
        ReleaseNote::findOrFail($note)->update($request->validate($this->rules()));
        AdminAudit::record($request->user(), 'edited_release_note', null, ['id' => $note]);

        return back();
    }

    public function destroy(Request $request, int $note): RedirectResponse
    {
        ReleaseNote::findOrFail($note)->delete();
        AdminAudit::record($request->user(), 'deleted_release_note', null, ['id' => $note]);

        return to_route('admin.release-notes');
    }

    public function publish(Request $request, int $note): RedirectResponse
    {
        $n = ReleaseNote::findOrFail($note);
        abort_unless($n->status === 'draft', 422, 'That note is already published.');

        $n->update(['status' => 'published', 'published_at' => now(), 'email_status' => $n->send_email ? 'sending' : 'none']);
        AdminAudit::record($request->user(), 'published_release_note', null, ['id' => $n->id, 'email' => $n->send_email, 'popup' => $n->show_popup]);

        // emails go out right after this response; the scheduler picks up whatever is left
        $n->send_email && dispatch(fn () => app(ReleaseNoteSender::class)->sendBatch($n))->afterResponse();

        Inertia::flash('toast', ['type' => 'success', 'message' => $n->send_email ? 'Published. Emails are going out now.' : 'Published.']);

        return back();
    }

    /** Sends the note to the signed-in admin only, exactly as users get it. */
    public function test(Request $request, int $note): RedirectResponse
    {
        $n = ReleaseNote::findOrFail($note);

        try {
            Mail::to($request->user())->send(new ReleaseNoteMail($n, $request->user()));
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['test' => 'Could not send the test: '.$e->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "Test sent to {$request->user()->email}."]);

        return back();
    }
}
