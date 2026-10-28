<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // what's new: written in the admin, emailed to users and shown once as a popup
        Schema::create('release_notes', function (Blueprint $t) {
            $t->id();
            $t->string('title', 120);
            $t->string('version', 20)->nullable();
            $t->text('body');
            $t->boolean('send_email')->default(true);
            $t->boolean('show_popup')->default(true);
            $t->string('status', 10)->default('draft'); // draft or published
            $t->timestamp('published_at')->nullable();
            $t->string('email_status', 10)->default('none'); // none, sending, done
            $t->unsignedInteger('emailed_count')->default(0);
            $t->foreignId('created_by')->nullable();
            $t->timestamps();
        });

        // one row per user per note: when the email went out (or failed) and when they dismissed the popup
        Schema::create('release_note_user', function (Blueprint $t) {
            $t->id();
            $t->foreignId('release_note_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->timestamp('emailed_at')->nullable();
            $t->timestamp('failed_at')->nullable();
            $t->timestamp('seen_at')->nullable();
            $t->unique(['release_note_id', 'user_id']);
        });

        Schema::table('users', fn (Blueprint $t) => $t->boolean('release_emails')->default(true)); // one-click unsubscribe from product update emails
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('release_emails'));
        Schema::dropIfExists('release_note_user');
        Schema::dropIfExists('release_notes');
    }
};
