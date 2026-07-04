<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The pre-generated schedule for a self-running demo event. Rows are
        // turned into real scores (and broadcast) by ScoreRelease as their
        // due_at passes — driven by viewers' browsers, no long-running process.
        Schema::create('staged_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rider_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_claim_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('lap');
            $table->unsignedTinyInteger('points');
            $table->string('idempotency_key');
            $table->timestamp('due_at');
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'released_at', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staged_scores');
    }
};
