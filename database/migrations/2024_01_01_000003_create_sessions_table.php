<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('type', 50);
            $table->string('status', 20)->default('pending');
            $table->json('context');
            $table->json('state')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('assistant_id')->constrained('ai_assistants')->onDelete('cascade');
            $table->foreignId('parent_session_id')->nullable()->constrained('sessions')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('uuid');
            $table->index('type');
            $table->index('status');
            $table->index('started_at');
            $table->index('ended_at');
            $table->index(['user_id', 'type']);
            $table->index(['assistant_id', 'type']);
        });

        // Create session metrics table
        Schema::create('session_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained()->onDelete('cascade');
            $table->string('metric', 50);
            $table->decimal('value', 10, 4);
            $table->json('metadata')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();

            // Indexes
            $table->index('metric');
            $table->index('recorded_at');
            $table->index(['session_id', 'metric']);
            $table->index(['session_id', 'recorded_at']);
        });

        // Create session events table
        Schema::create('session_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained()->onDelete('cascade');
            $table->string('event_type', 50);
            $table->json('data');
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            // Indexes
            $table->index('event_type');
            $table->index('occurred_at');
            $table->index(['session_id', 'event_type']);
            $table->index(['session_id', 'occurred_at']);
        });

        // Create session tags table
        Schema::create('session_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained()->onDelete('cascade');
            $table->string('tag', 100);
            $table->timestamps();

            // Indexes
            $table->unique(['session_id', 'tag']);
            $table->index('tag');
        });

        // Create session participants table
        Schema::create('session_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained()->onDelete('cascade');
            $table->morphs('participant');
            $table->string('role', 50);
            $table->json('permissions')->nullable();
            $table->timestamp('joined_at');
            $table->timestamp('left_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('role');
            $table->index('joined_at');
            $table->index('left_at');
            $table->unique(['session_id', 'participant_type', 'participant_id', 'role']);
        });

        // Create session references table for linking related sessions
        Schema::create('session_references', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained()->onDelete('cascade');
            $table->foreignId('referenced_session_id')->constrained('sessions')->onDelete('cascade');
            $table->string('reference_type', 50);
            $table->json('metadata')->nullable();
            $table->timestamps();

            // Indexes
            $table->unique(['session_id', 'referenced_session_id', 'reference_type']);
            $table->index('reference_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('session_references');
        Schema::dropIfExists('session_participants');
        Schema::dropIfExists('session_tags');
        Schema::dropIfExists('session_events');
        Schema::dropIfExists('session_metrics');
        Schema::dropIfExists('sessions');
    }
};
