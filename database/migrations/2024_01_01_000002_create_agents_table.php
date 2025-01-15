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
        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 50);
            $table->json('capabilities');
            $table->json('configuration');
            $table->json('state')->nullable();
            $table->string('status', 20)->default('idle');
            $table->json('metadata')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_active_at')->nullable();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->foreignId('team_id')->nullable()->constrained()->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('type');
            $table->index('status');
            $table->index('is_active');
            $table->index('last_active_at');
            $table->index(['organization_id', 'type']);
            $table->index(['team_id', 'type']);
            $table->index(['user_id', 'type']);
        });

        // Create agent metrics table
        Schema::create('agent_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained()->onDelete('cascade');
            $table->string('metric', 50);
            $table->decimal('value', 10, 4);
            $table->json('metadata')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();

            // Indexes
            $table->index('metric');
            $table->index('recorded_at');
            $table->index(['agent_id', 'metric']);
            $table->index(['agent_id', 'recorded_at']);
        });

        // Create agent capabilities table for more efficient querying
        Schema::create('agent_capabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained()->onDelete('cascade');
            $table->string('capability', 100);
            $table->json('configuration')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Indexes
            $table->unique(['agent_id', 'capability']);
            $table->index('capability');
            $table->index('is_active');
        });

        // Create agent sessions pivot table
        Schema::create('agent_session', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained()->onDelete('cascade');
            $table->foreignId('session_id')->constrained()->onDelete('cascade');
            $table->string('role', 50);
            $table->timestamp('joined_at');
            $table->timestamp('left_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('role');
            $table->index('joined_at');
            $table->index('left_at');
            $table->unique(['agent_id', 'session_id', 'role']);
        });

        // Create agent specializations table
        Schema::create('agent_specializations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained()->onDelete('cascade');
            $table->string('specialization', 100);
            $table->integer('proficiency_level');
            $table->json('configuration')->nullable();
            $table->timestamps();

            // Indexes
            $table->unique(['agent_id', 'specialization']);
            $table->index('specialization');
            $table->index('proficiency_level');
        });

        // Create agent relationships table
        Schema::create('agent_relationships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained()->onDelete('cascade');
            $table->foreignId('related_agent_id')->constrained('agents')->onDelete('cascade');
            $table->string('relationship_type', 50);
            $table->json('configuration')->nullable();
            $table->timestamps();

            // Indexes
            $table->unique(['agent_id', 'related_agent_id', 'relationship_type']);
            $table->index('relationship_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_relationships');
        Schema::dropIfExists('agent_specializations');
        Schema::dropIfExists('agent_session');
        Schema::dropIfExists('agent_capabilities');
        Schema::dropIfExists('agent_metrics');
        Schema::dropIfExists('agents');
    }
};
