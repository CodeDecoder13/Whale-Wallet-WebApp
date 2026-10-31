<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // cash the user lent to a person: who, how much, when it is due, and when to remind them
    public function up(): void
    {
        Schema::create('lends', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('name', 80);
            $t->string('email', 120);
            $t->bigInteger('amount');
            $t->foreignId('card_id')->nullable()->constrained()->nullOnDelete();
            $t->date('lent_on');
            $t->date('due_on');
            $t->unsignedTinyInteger('remind_before')->default(3); // days before the due date; reminders also go out on the day and weekly after
            $t->string('reference', 100)->nullable();
            $t->date('reminded_on')->nullable(); // last reminder day, so a re-run never sends twice
            $t->timestamp('paid_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lends');
    }
};
