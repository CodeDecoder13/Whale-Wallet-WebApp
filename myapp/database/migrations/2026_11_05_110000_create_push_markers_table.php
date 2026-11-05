<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // "this alert was already sent": lives in the database so a cache flush cannot repeat it
        Schema::create('push_markers', function (Blueprint $t) {
            $t->string('marker')->primary();
            $t->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_markers');
    }
};
