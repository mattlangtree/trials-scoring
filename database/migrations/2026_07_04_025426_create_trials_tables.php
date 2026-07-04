<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('event_date');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('rider_classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedTinyInteger('laps');
            $table->unsignedTinyInteger('section_count');
            $table->timestamps();

            $table->unique(['event_id', 'name']);
        });

        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('number');
            $table->string('claim_code', 8);
            $table->timestamps();

            $table->unique(['event_id', 'number']);
            $table->unique(['event_id', 'claim_code']);
        });

        Schema::create('riders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rider_class_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('rider_number');
            $table->string('name');
            $table->timestamps();

            $table->unique(['event_id', 'rider_number']);
        });

        Schema::create('section_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->string('device_id');
            $table->string('observer_name');
            $table->string('token', 64)->unique();
            $table->timestamp('claimed_at');
            $table->timestamps();
        });

        Schema::create('scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rider_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_claim_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('lap');
            $table->unsignedTinyInteger('points');
            $table->string('status', 16); // official | self
            $table->string('idempotency_key')->unique();
            $table->string('device_id');
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['event_id', 'rider_id', 'lap']);
            $table->index(['section_id', 'rider_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scores');
        Schema::dropIfExists('section_claims');
        Schema::dropIfExists('riders');
        Schema::dropIfExists('sections');
        Schema::dropIfExists('rider_classes');
        Schema::dropIfExists('events');
    }
};
