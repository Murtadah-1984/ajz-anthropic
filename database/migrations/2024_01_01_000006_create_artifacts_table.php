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
        Schema::create('artifacts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 50);
            $table->text('content')->nullable();
            $table->string('mime_type');
            $table->bigInteger('size');
            $table->string('path', 1024)->nullable();
            $table->json('metadata')->nullable();
            $table->morphs('artifactable');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('name');
            $table->index('type');
            $table->index('mime_type');
            $table->index('expires_at');
            $table->index(['artifactable_type', 'artifactable_id']);
            $table->index(['user_id', 'type']);
        });

        // Create artifact versions table
        Schema::create('artifact_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artifact_id')->constrained()->onDelete('cascade');
            $table->string('version');
            $table->text('content')->nullable();
            $table->string('path', 1024)->nullable();
            $table->bigInteger('size');
            $table->json('metadata')->nullable();
            $table->morphs('creator');
            $table->timestamp('created_at');

            // Indexes
            $table->index('version');
            $table->index('created_at');
            $table->unique(['artifact_id', 'version']);
        });

        // Create artifact transformations table
        Schema::create('artifact_transformations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artifact_id')->constrained()->onDelete('cascade');
            $table->string('type', 50);
            $table->text('content')->nullable();
            $table->string('path', 1024)->nullable();
            $table->bigInteger('size');
            $table->json('parameters')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('type');
            $table->unique(['artifact_id', 'type']);
        });

        // Create artifact access logs table
        Schema::create('artifact_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artifact_id')->constrained()->onDelete('cascade');
            $table->morphs('accessor');
            $table->string('action', 50);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('accessed_at');

            // Indexes
            $table->index('action');
            $table->index('ip_address');
            $table->index('accessed_at');
            $table->index(['artifact_id', 'action']);
            $table->index(['artifact_id', 'accessed_at']);
        });

        // Create artifact shares table
        Schema::create('artifact_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artifact_id')->constrained()->onDelete('cascade');
            $table->morphs('shareable');
            $table->string('token', 100)->unique();
            $table->json('permissions')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('token');
            $table->index('expires_at');
            $table->unique(['artifact_id', 'shareable_type', 'shareable_id']);
        });

        // Create artifact tags table
        Schema::create('artifact_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artifact_id')->constrained()->onDelete('cascade');
            $table->string('tag', 100);
            $table->timestamps();

            // Indexes
            $table->unique(['artifact_id', 'tag']);
            $table->index('tag');
        });

        // Create artifact relationships table
        Schema::create('artifact_relationships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artifact_id')->constrained()->onDelete('cascade');
            $table->foreignId('related_artifact_id')->constrained('artifacts')->onDelete('cascade');
            $table->string('relationship_type', 50);
            $table->json('metadata')->nullable();
            $table->timestamps();

            // Indexes
            $table->unique(['artifact_id', 'related_artifact_id', 'relationship_type']);
            $table->index('relationship_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('artifact_relationships');
        Schema::dropIfExists('artifact_tags');
        Schema::dropIfExists('artifact_shares');
        Schema::dropIfExists('artifact_access_logs');
        Schema::dropIfExists('artifact_transformations');
        Schema::dropIfExists('artifact_versions');
        Schema::dropIfExists('artifacts');
    }
};
