<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** A cancelled subscription stays on file (it can be renewed) but stops counting toward bills and the forecast. */
    public function up(): void
    {
        Schema::table('recurring_items', function (Blueprint $t) {
            $t->timestamp('cancelled_at')->nullable();
            $t->string('cancel_reason', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('recurring_items', fn (Blueprint $t) => $t->dropColumn(['cancelled_at', 'cancel_reason']));
    }
};
