<?php

// database/migrations/create_knowledge_sessions_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateKnowledgeSessionsTable extends Migration
{
    public function up()
    {
        Schema::create('knowledge_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_id')->unique();
            $table->string('type')->default('individual');
            $table->json('team_agents')->nullable();
            $table->json('options')->nullable();
            $table->json('activity_log')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('knowledge_sessions');
    }
}

