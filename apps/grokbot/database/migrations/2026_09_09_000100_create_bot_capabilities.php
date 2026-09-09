<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('skills', function (Blueprint $table): void {
            $table->id(); $table->uuid('public_id')->unique(); $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name'); $table->string('slug'); $table->text('description')->nullable(); $table->longText('instructions');
            $table->json('tool_policy')->nullable(); $table->unsignedInteger('version')->default(1); $table->boolean('enabled')->default(true); $table->timestamps();
            $table->unique(['workspace_id','slug']);
        });
        Schema::create('bot_skill', function (Blueprint $table): void {
            $table->foreignId('bot_id')->constrained()->cascadeOnDelete(); $table->foreignId('skill_id')->constrained()->cascadeOnDelete();
            $table->json('configuration')->nullable(); $table->timestamps(); $table->primary(['bot_id','skill_id']);
        });
        Schema::create('bot_memories', function (Blueprint $table): void {
            $table->id(); $table->ulid('public_id')->unique(); $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bot_id')->constrained()->cascadeOnDelete(); $table->string('scope', 32)->default('long_term');
            $table->string('key')->nullable(); $table->longText('content'); $table->json('metadata')->nullable(); $table->decimal('importance', 4, 3)->default(0.500);
            $table->timestamp('last_accessed_at')->nullable(); $table->timestamps(); $table->index(['bot_id','scope','created_at']);
        });
        Schema::create('routines', function (Blueprint $table): void {
            $table->id(); $table->uuid('public_id')->unique(); $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bot_id')->constrained()->cascadeOnDelete(); $table->string('name'); $table->text('prompt');
            $table->string('schedule'); $table->string('timezone')->default('UTC'); $table->boolean('enabled')->default(true);
            $table->timestamp('last_run_at')->nullable(); $table->timestamp('next_run_at')->nullable(); $table->timestamps();
        });
        Schema::create('audit_log', function (Blueprint $table): void {
            $table->id(); $table->ulid('public_id')->unique(); $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete(); $table->foreignId('actor_bot_id')->nullable()->constrained('bots')->nullOnDelete();
            $table->string('action'); $table->string('subject_type'); $table->string('subject_id'); $table->json('context')->nullable();
            $table->string('ip_address', 45)->nullable(); $table->timestamp('occurred_at')->index(); $table->timestamps();
            $table->index(['workspace_id','action','occurred_at']);
        });
    }

    public function down(): void
    {
        foreach (['audit_log','routines','bot_memories','bot_skill','skills'] as $table) Schema::dropIfExists($table);
    }
};
