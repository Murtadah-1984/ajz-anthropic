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
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('type', 50);
            $table->string('title');
            $table->text('description');
            $table->integer('priority')->default(0);
            $table->string('status', 20)->default('pending');
            $table->integer('progress')->default(0);
            $table->json('context')->nullable();
            $table->json('result')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('agent_id')->constrained()->onDelete('cascade');
            $table->foreignId('session_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('parent_task_id')->nullable()->constrained('tasks')->onDelete('set null');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('uuid');
            $table->index('type');
            $table->index('status');
            $table->index('priority');
            $table->index('progress');
            $table->index('started_at');
            $table->index('completed_at');
            $table->index('due_at');
            $table->index(['agent_id', 'status']);
            $table->index(['session_id', 'status']);
            $table->index(['user_id', 'status']);
        });

        // Create task dependencies table
        Schema::create('task_dependencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->onDelete('cascade');
            $table->foreignId('dependency_id')->constrained('tasks')->onDelete('cascade');
            $table->string('type', 50);
            $table->json('metadata')->nullable();
            $table->timestamps();

            // Indexes
            $table->unique(['task_id', 'dependency_id', 'type']);
            $table->index('type');
        });

        // Create task assignments table
        Schema::create('task_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->onDelete('cascade');
            $table->morphs('assignee');
            $table->string('role', 50);
            $table->json('permissions')->nullable();
            $table->timestamp('assigned_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('role');
            $table->index('assigned_at');
            $table->index('accepted_at');
            $table->index('completed_at');
            $table->unique(['task_id', 'assignee_type', 'assignee_id', 'role']);
        });

        // Create task events table
        Schema::create('task_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->onDelete('cascade');
            $table->string('event_type', 50);
            $table->json('data');
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            // Indexes
            $table->index('event_type');
            $table->index('occurred_at');
            $table->index(['task_id', 'event_type']);
            $table->index(['task_id', 'occurred_at']);
        });

        // Create task metrics table
        Schema::create('task_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->onDelete('cascade');
            $table->string('metric', 50);
            $table->decimal('value', 10, 4);
            $table->json('metadata')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();

            // Indexes
            $table->index('metric');
            $table->index('recorded_at');
            $table->index(['task_id', 'metric']);
            $table->index(['task_id', 'recorded_at']);
        });

        // Create task tags table
        Schema::create('task_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->onDelete('cascade');
            $table->string('tag', 100);
            $table->timestamps();

            // Indexes
            $table->unique(['task_id', 'tag']);
            $table->index('tag');
        });

        // Create task comments table
        Schema::create('task_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->onDelete('cascade');
            $table->morphs('commenter');
            $table->text('content');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['task_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_comments');
        Schema::dropIfExists('task_tags');
        Schema::dropIfExists('task_metrics');
        Schema::dropIfExists('task_events');
        Schema::dropIfExists('task_assignments');
        Schema::dropIfExists('task_dependencies');
        Schema::dropIfExists('tasks');
    }
};
