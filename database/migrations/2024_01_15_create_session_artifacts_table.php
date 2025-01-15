<?php

// database/migrations/2024_01_15_create_session_artifacts_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_artifacts', function (Blueprint $table) {
            $table->id();
            $table->string('session_id')->index();
            $table->string('step');
            $table->string('type')->default('json');
            $table->json('content');
            $table->json('metadata')->nullable();
            $table->string('status')->default('created');
            $table->integer('version')->default(1);
            $table->timestamps();
            $table->softDeletes();

            // Indexes for common queries
            $table->index(['session_id', 'step']);
            $table->index(['session_id', 'status']);
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_artifacts');
    }
};
