<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->uuid('tenant_id');
            $table->string('event_type');
            $table->uuid('actor_id')->nullable();
            $table->string('actor_role')->nullable();
            $table->json('event_data')->nullable();
            $table->uuid('correlation_id')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['tenant_id', 'occurred_at']);
            $table->index(['tenant_id', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
    }
};