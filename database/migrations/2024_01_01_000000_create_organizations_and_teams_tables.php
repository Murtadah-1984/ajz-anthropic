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
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('domain')->nullable();
            $table->json('settings')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('slug');
            $table->index('domain');
            $table->index('is_active');
        });

        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->foreignId('parent_team_id')->nullable()->constrained('teams')->onDelete('set null');
            $table->json('settings')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('slug');
            $table->index('is_active');
            $table->index(['organization_id', 'slug']);
        });

        Schema::create('organization_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('role', 50);
            $table->json('permissions')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('joined_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('role');
            $table->index('joined_at');
            $table->index('expires_at');
            $table->unique(['organization_id', 'user_id']);
        });

        Schema::create('team_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('role', 50);
            $table->json('permissions')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('joined_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('role');
            $table->index('joined_at');
            $table->index('expires_at');
            $table->unique(['team_id', 'user_id']);
        });

        Schema::create('organization_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->string('email');
            $table->string('role', 50);
            $table->string('token', 100)->unique();
            $table->foreignId('invited_by')->constrained('users')->onDelete('cascade');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            // Indexes
            $table->index('email');
            $table->index('token');
            $table->index('expires_at');
            $table->unique(['organization_id', 'email']);
        });

        Schema::create('team_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->onDelete('cascade');
            $table->string('email');
            $table->string('role', 50);
            $table->string('token', 100)->unique();
            $table->foreignId('invited_by')->constrained('users')->onDelete('cascade');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            // Indexes
            $table->index('email');
            $table->index('token');
            $table->index('expires_at');
            $table->unique(['team_id', 'email']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('team_invitations');
        Schema::dropIfExists('organization_invitations');
        Schema::dropIfExists('team_users');
        Schema::dropIfExists('organization_users');
        Schema::dropIfExists('teams');
        Schema::dropIfExists('organizations');
    }
};
