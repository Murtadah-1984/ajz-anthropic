<?php

// database/migrations/2024_01_14_000001_create_assistant_roles_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistant_roles', function (Blueprint $table) {
            $table->id();
            $table->string('role_name')->unique();
            $table->text('xml_config');
            $table->text('prompt')->nullable();
            $table->text('xml_output')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('role_name');
            $table->index('is_active');
            $table->index(['deleted_at', 'is_active']);
        });

        Schema::create('assistant_outputs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assistant_role_id')
                  ->constrained('assistant_roles')
                  ->onDelete('cascade');
            $table->text('output');
            $table->integer('feedback_score');
            $table->json('metadata')->nullable();
            $table->timestamp('generated_at');
            $table->timestamps();
            $table->softDeletes();

            $table->index('feedback_score');
            $table->index('generated_at');
            $table->index(['assistant_role_id', 'feedback_score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_outputs');
        Schema::dropIfExists('assistant_roles');
    }
};
