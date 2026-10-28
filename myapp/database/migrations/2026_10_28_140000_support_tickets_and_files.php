<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // a chat becomes a ticket: a user can have many over time, each with a subject and who closed it
        Schema::table('support_threads', function (Blueprint $t) {
            $t->dropUnique(['user_id']);
            $t->index('user_id');
            $t->string('subject', 120)->nullable();
            $t->timestamp('closed_at')->nullable();
            $t->string('closed_by', 5)->nullable(); // user or admin
        });

        Schema::table('support_messages', function (Blueprint $t) {
            $t->string('attachment_path')->nullable();
            $t->string('attachment_name', 120)->nullable();
            $t->string('attachment_mime', 60)->nullable();
            $t->unsignedInteger('attachment_size')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('support_messages', fn (Blueprint $t) => $t->dropColumn(['attachment_path', 'attachment_name', 'attachment_mime', 'attachment_size']));
        Schema::table('support_threads', function (Blueprint $t) {
            $t->dropColumn(['subject', 'closed_at', 'closed_by']);
            $t->dropIndex(['user_id']);
            $t->unique('user_id');
        });
    }
};
