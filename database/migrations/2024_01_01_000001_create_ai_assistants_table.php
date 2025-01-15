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
        Schema::create('ai_assistants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 50);
            $table->string('model', 100);
            $table->json('capabilities');
            $table->json('configuration');
            $table->boolean('is_active')->default(true);
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->json('metadata')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('type');
            $table->index('model');
            $table->index('is_active');
            $table->index('last_used_at');
            $table->index(['organization_id', 'type']);
            $table->index(['user_id', 'type']);
        });

        // Create training history table
        Schema::create('ai_assistant_training_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assistant_id')->constrained('ai_assistants')->onDelete('cascade');
            $table->string('type', 50);
            $table->json('data');
            $table->json('metrics')->nullable();
            $table->string('status', 20);
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('type');
            $table->index('status');
            $table->index(['assistant_id', 'type']);
            $table->index(['assistant_id', 'status']);
        });

        // Create performance metrics table
        Schema::create('ai_assistant_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assistant_id')->constrained('ai_assistants')->onDelete('cascade');
            $table->string('metric', 50);
            $table->decimal('value', 10, 4);
            $table->json('metadata')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();

            // Indexes
            $table->index('metric');
            $table->index('recorded_at');
            $table->index(['assistant_id', 'metric']);
            $table->index(['assistant_id', 'recorded_at']);
        });

        // Create capabilities table for more efficient querying
        Schema::create('ai_assistant_capabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assistant_id')->constrained('ai_assistants')->onDelete('cascade');
            $table->string('capability', 100);
            $table->json('configuration')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Indexes
            $table->unique(['assistant_id', 'capability']);
            $table->index('capability');
            $table->index('is_active');
        });

        // Create tags table for categorization
        Schema::create('ai_assistant_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assistant_id')->constrained('ai_assistants')->onDelete('cascade');
            $table->string('tag', 100);
            $table->timestamps();

            // Indexes
            $table->unique(['assistant_id', 'tag']);
            $table->index('tag');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_assistant_tags');
        Schema::dropIfExists('ai_assistant_capabilities');
        Schema::dropIfExists('ai_assistant_metrics');
        Schema::dropIfExists('ai_assistant_training_records');
        Schema::dropIfExists('ai_assistants');
    }
};
