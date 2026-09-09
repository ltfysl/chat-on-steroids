<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id(); $table->string('name'); $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable(); $table->string('password');
            $table->rememberToken(); $table->timestamps();
        });
        Schema::create('workspaces', function (Blueprint $table): void {
            $table->id(); $table->uuid('public_id')->unique(); $table->string('name');
            $table->string('slug')->unique(); $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete(); $table->timestamps();
        });
        Schema::create('workspace_user', function (Blueprint $table): void {
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 32)->default('member'); $table->timestamps();
            $table->primary(['workspace_id', 'user_id']);
        });
        Schema::create('bots', function (Blueprint $table): void {
            $table->id(); $table->uuid('public_id')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->string('name'); $table->string('slug'); $table->text('description')->nullable();
            $table->string('state', 32)->default('draft'); $table->string('model')->default('gpt-5.6');
            $table->longText('system_prompt')->nullable(); $table->json('tools')->nullable();
            $table->json('network_policy')->nullable(); $table->json('runtime_profile')->nullable();
            $table->boolean('memory_enabled')->default(true); $table->unsignedInteger('current_version')->default(1);
            $table->timestamp('deployed_at')->nullable(); $table->timestamps();
            $table->unique(['workspace_id', 'slug']); $table->index(['workspace_id', 'state']);
        });
        Schema::create('bot_versions', function (Blueprint $table): void {
            $table->id(); $table->foreignId('bot_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version'); $table->json('configuration');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete(); $table->timestamps();
            $table->unique(['bot_id', 'version']);
        });
        Schema::create('bot_runtimes', function (Blueprint $table): void {
            $table->id(); $table->foreignId('bot_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('provider', 40); $table->string('external_id')->unique(); $table->string('state', 32);
            $table->unsignedSmallInteger('vcpu'); $table->unsignedInteger('memory_mb'); $table->unsignedInteger('disk_gb');
            $table->string('image'); $table->string('endpoint')->nullable(); $table->unsignedBigInteger('generation')->default(1);
            $table->timestamp('last_heartbeat_at')->nullable(); $table->timestamp('lease_expires_at')->nullable(); $table->timestamps();
        });
        Schema::create('conversations', function (Blueprint $table): void {
            $table->id(); $table->uuid('public_id')->unique(); $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete(); $table->string('title');
            $table->string('state', 32)->default('active'); $table->timestamps();
        });
        Schema::create('conversation_bot', function (Blueprint $table): void {
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete(); $table->foreignId('bot_id')->constrained()->cascadeOnDelete();
            $table->timestamps(); $table->primary(['conversation_id', 'bot_id']);
        });
        Schema::create('messages', function (Blueprint $table): void {
            $table->id(); $table->ulid('public_id')->unique(); $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('sender_bot_id')->nullable()->constrained('bots')->nullOnDelete();
            $table->string('role', 24); $table->string('status', 24)->default('complete'); $table->unsignedBigInteger('sequence');
            $table->longText('content')->default(''); $table->json('metadata')->nullable(); $table->unsignedInteger('token_count')->nullable();
            $table->timestamp('started_at')->nullable(); $table->timestamp('completed_at')->nullable(); $table->timestamps();
            $table->unique(['conversation_id', 'sequence']);
        });
        Schema::create('message_chunks', function (Blueprint $table): void {
            $table->id(); $table->foreignId('message_id')->constrained()->cascadeOnDelete(); $table->unsignedInteger('sequence');
            $table->string('kind', 24)->default('text'); $table->text('delta'); $table->timestamps();
            $table->unique(['message_id', 'sequence']);
        });
        Schema::create('bot_bus_messages', function (Blueprint $table): void {
            $table->id(); $table->ulid('public_id')->unique(); $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_bot_id')->constrained('bots')->cascadeOnDelete();
            $table->foreignId('recipient_bot_id')->nullable()->constrained('bots')->cascadeOnDelete();
            $table->string('topic'); $table->uuid('correlation_id')->index(); $table->uuid('causation_id')->nullable();
            $table->json('payload'); $table->string('status', 24)->default('queued');
            $table->timestamp('delivered_at')->nullable(); $table->timestamp('acknowledged_at')->nullable(); $table->timestamps();
            $table->index(['workspace_id', 'topic', 'created_at']);
        });
        Schema::create('bot_activities', function (Blueprint $table): void {
            $table->id(); $table->ulid('public_id')->unique(); $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bot_id')->constrained()->cascadeOnDelete(); $table->foreignId('runtime_id')->nullable()->constrained('bot_runtimes')->nullOnDelete();
            $table->string('type', 60); $table->string('level', 16)->default('info'); $table->string('summary');
            $table->json('payload')->nullable(); $table->timestamp('occurred_at')->index(); $table->timestamps();
        });
        Schema::create('approval_requests', function (Blueprint $table): void {
            $table->id(); $table->ulid('public_id')->unique(); $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bot_id')->constrained()->cascadeOnDelete(); $table->string('action'); $table->string('risk', 16);
            $table->json('arguments'); $table->string('status', 24)->default('pending');
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at')->nullable(); $table->timestamp('resolved_at')->nullable(); $table->timestamps();
            $table->index(['workspace_id', 'status', 'created_at']);
        });
        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary(); $table->foreignId('user_id')->nullable()->index(); $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable(); $table->longText('payload'); $table->integer('last_activity')->index();
        });
        Schema::create('cache', function (Blueprint $table): void { $table->string('key')->primary(); $table->mediumText('value'); $table->integer('expiration'); });
        Schema::create('cache_locks', function (Blueprint $table): void { $table->string('key')->primary(); $table->string('owner'); $table->integer('expiration'); });
        Schema::create('jobs', function (Blueprint $table): void {
            $table->id(); $table->string('queue')->index(); $table->longText('payload'); $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable(); $table->unsignedInteger('available_at'); $table->unsignedInteger('created_at');
        });
    }

    public function down(): void
    {
        foreach (['jobs','cache_locks','cache','sessions','approval_requests','bot_activities','bot_bus_messages','message_chunks','messages','conversation_bot','conversations','bot_runtimes','bot_versions','bots','workspace_user','workspaces','users'] as $table) Schema::dropIfExists($table);
    }
};
