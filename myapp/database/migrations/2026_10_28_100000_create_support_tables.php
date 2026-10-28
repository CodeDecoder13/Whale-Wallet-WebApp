<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // one conversation per user with the support team (separate from the AI Whale Chat)
        Schema::create('support_threads', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $t->string('status', 8)->default('open'); // open or closed
            $t->timestamp('last_message_at')->nullable()->index();
            $t->unsignedInteger('user_unread')->default(0); // admin replies the user has not opened yet
            $t->unsignedInteger('admin_unread')->default(0); // user messages no admin has opened yet
            $t->timestamps();
        });

        Schema::create('support_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('support_thread_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete(); // the thread's owner, so the per-user scope applies
            $t->foreignId('sender_id')->nullable(); // who wrote it: the user, or the admin
            $t->boolean('from_admin')->default(false);
            $t->text('body');
            $t->timestamp('read_at')->nullable(); // when the other side opened it ("Seen")
            $t->timestamp('created_at')->useCurrent();
            $t->index(['support_thread_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_messages');
        Schema::dropIfExists('support_threads');
    }
};
