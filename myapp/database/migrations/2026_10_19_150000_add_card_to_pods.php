<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** The card a pod is normally funded from; money added to the pod defaults to it. */
    public function up(): void
    {
        Schema::table('pods', fn (Blueprint $t) => $t->foreignId('card_id')->nullable()->constrained()->nullOnDelete());
    }

    public function down(): void
    {
        Schema::table('pods', fn (Blueprint $t) => $t->dropConstrainedForeignId('card_id'));
    }
};
