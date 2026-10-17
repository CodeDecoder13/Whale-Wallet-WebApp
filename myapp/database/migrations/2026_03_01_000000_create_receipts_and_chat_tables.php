<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->boolean('keep_receipts')->default(false));

        Schema::create('receipts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->enum('status', ['queued', 'parsing', 'parsed', 'failed', 'confirmed'])->default('queued');
            $t->string('path')->nullable(); // private disk; null once the image is deleted
            $t->json('parsed_json')->nullable(); // the draft, duplicate_of ids included
            $t->decimal('confidence', 3, 2)->nullable();
            $t->string('error')->nullable(); // user-safe text only
            $t->timestamps();
        });

        Schema::table('transactions', fn (Blueprint $t) => $t->foreign('receipt_id')->references('id')->on('receipts')->nullOnDelete());

        Schema::create('chat_threads', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->timestamps();
        });

        Schema::create('chat_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('chat_thread_id')->constrained()->cascadeOnDelete();
            $t->enum('role', ['user', 'assistant']);
            $t->text('content');
            $t->json('draft_json')->nullable(); // expense draft; saved only when confirmed
            $t->foreignId('receipt_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_threads');
        Schema::table('transactions', fn (Blueprint $t) => $t->dropForeign(['receipt_id']));
        Schema::dropIfExists('receipts');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('keep_receipts'));
    }
};
