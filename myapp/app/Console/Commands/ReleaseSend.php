<?php

namespace App\Console\Commands;

use App\Models\ReleaseNote;
use App\Services\ReleaseNoteSender;
use Illuminate\Console\Command;

/** Sends the next batch of every release note that is still emailing; the scheduler runs it every minute. */
class ReleaseSend extends Command
{
    protected $signature = 'release:send {--batch=50}';

    protected $description = 'Send the next batch of release note emails';

    public function handle(ReleaseNoteSender $sender): int
    {
        foreach (ReleaseNote::where('email_status', 'sending')->get() as $note) {
            $this->info("Note {$note->id}: sent {$sender->sendBatch($note, (int) $this->option('batch'))}.");
        }

        return 0;
    }
}
