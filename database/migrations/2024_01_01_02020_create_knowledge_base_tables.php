<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateKnowledgeBaseTables extends Migration
{
    public function up()
    {
        // Knowledge Collections
        Schema::create('knowledge_collections', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        // Knowledge Entries
        Schema::create('knowledge_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_id')->constrained('knowledge_collections')->onDelete('cascade');
            $table->string('title');
            $table->text('content');
            $table->string('type')->default('text'); // text, code, image, etc.
            $table->json('metadata')->nullable();
            $table->json('embeddings')->nullable(); // For vector search
            $table->timestamps();

            // Full-text search index
            $table->fullText(['title', 'content']);
        });

        // Agent Knowledge Access
        Schema::create('agent_knowledge_access', function (Blueprint $table) {
            $table->id();
            $table->string('agent_id');
            $table->foreignId('collection_id')->constrained('knowledge_collections')->onDelete('cascade');
            $table->json('access_permissions');
            $table->timestamps();
        });

        // Knowledge Categories
        Schema::create('knowledge_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->nestedSet(); // For hierarchical categories
            $table->timestamps();
        });

        // Knowledge Entry Categories
        Schema::create('knowledge_entry_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entry_id')->constrained('knowledge_entries')->onDelete('cascade');
            $table->foreignId('category_id')->constrained('knowledge_categories')->onDelete('cascade');
            $table->timestamps();
        });

        // Knowledge References
        Schema::create('knowledge_references', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entry_id')->constrained('knowledge_entries')->onDelete('cascade');
            $table->string('reference_type');
            $table->string('reference_url')->nullable();
            $table->text('reference_text')->nullable();
            $table->timestamps();
        });

        // Knowledge Usage Logs
        Schema::create('knowledge_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entry_id')->constrained('knowledge_entries')->onDelete('cascade');
            $table->string('agent_id');
            $table->string('action_type');
            $table->json('context')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('knowledge_usage_logs');
        Schema::dropIfExists('knowledge_references');
        Schema::dropIfExists('knowledge_entry_categories');
        Schema::dropIfExists('knowledge_categories');
        Schema::dropIfExists('agent_knowledge_access');
        Schema::dropIfExists('knowledge_entries');
        Schema::dropIfExists('knowledge_collections');
    }
}
