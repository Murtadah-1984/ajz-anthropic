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
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('type', 50);
            $table->json('content');
            $table->json('metadata')->nullable();
            $table->string('role', 20);
            $table->string('status', 20)->default('pending');
            $table->integer('tokens')->default(0);
            $table->foreignId('session_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('agent_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('parent_message_id')->nullable()->constrained('messages')->onDelete('set null');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('uuid');
            $table->index('type');
            $table->index('role');
            $table->index('status');
            $table->index('sent_at');
            $table->index('delivered_at');
            $table->index('read_at');
            $table->index(['session_id', 'type']);
            $table->index(['session_id', 'role']);
            $table->index(['user_id', 'type']);
            $table->index(['agent_id', 'type']);
        });

        // Create message reactions table
        Schema::create('message_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained()->onDelete('cascade');
            $table->morphs('reactor');
            $table->string('reaction', 50);
            $table->json('metadata')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('reaction');
            $table->unique(['message_id', 'reactor_type', 'reactor_id', 'reaction']);
        });

        // Create message references table
        Schema::create('message_references', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained()->onDelete('cascade');
            $table->morphs('referenceable');
            $table->string('reference_type', 50);
            $table->json('metadata')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('reference_type');
            $table->unique([
                'message_id',
                'referenceable_type',
                'referenceable_id',
                'reference_type'
            ]);
        });

        // Create message annotations table
        Schema::create('message_annotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained()->onDelete('cascade');
            $table->morphs('annotator');
            $table->string('type', 50);
            $table->json('data');
            $table->json('metadata')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('type');
            $table->index(['message_id', 'type']);
        });

        // Create message metrics table
        Schema::create('message_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained()->onDelete('cascade');
            $table->string('metric', 50);
            $table->decimal('value', 10, 4);
            $table->json('metadata')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();

            // Indexes
            $table->index('metric');
            $table->index('recorded_at');
            $table->index(['message_id', 'metric']);
            $table->index(['message_id', 'recorded_at']);
        });

        // Create message tags table
        Schema::create('message_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained()->onDelete('cascade');
            $table->string('tag', 100);
            $table->timestamps();

            // Indexes
            $table->unique(['message_id', 'tag']);
            $table->index('tag');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('message_tags');
        Schema::dropIfExists('message_metrics');
        Schema::dropIfExists('message_annotations');
        Schema::dropIfExists('message_references');
        Schema::dropIfExists('message_reactions');
        Schema::dropIfExists('messages');
    }
};
