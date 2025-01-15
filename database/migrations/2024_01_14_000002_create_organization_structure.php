<?php

// database/migrations/2024_01_14_000002_create_organization_structure.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('divisions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('division_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('ai_assistants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->foreignId('assistant_role_id')->constrained();
            $table->foreignId('team_id')->nullable()->constrained();
            $table->foreignId('user_id')->nullable()->constrained(); // For personal assistants
            $table->json('configuration');
            $table->json('memory')->nullable(); // For persistent context
            $table->json('capabilities');
            $table->boolean('is_personal')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_interaction')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'is_active']);
            $table->index(['user_id', 'is_active']);
        });

        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_assistant_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->string('subject')->nullable();
            $table->string('status');
            $table->json('metadata')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['ai_assistant_id', 'status']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('conversation_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->onDelete('cascade');
            $table->string('role'); // 'user', 'assistant', or 'system'
            $table->text('content');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
        });

        // For tracking assistant delegations/referrals
        Schema::create('task_delegations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained();
            $table->foreignId('from_assistant_id')->constrained('ai_assistants');
            $table->foreignId('to_assistant_id')->constrained('ai_assistants');
            $table->text('reason');
            $table->json('context')->nullable();
            $table->string('status');
            $table->timestamps();

            $table->index(['conversation_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_delegations');
        Schema::dropIfExists('conversation_messages');
        Schema::dropIfExists('conversations');
        Schema::dropIfExists('ai_assistants');
        Schema::dropIfExists('teams');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('divisions');
    }
};
