<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('tenant_id', 36);
            $table->timestamp('occurred_at');
            $table->string('actor_id', 36);                 // Identity user_id (FK nae)
            $table->string('actor_role', 10)->nullable();
            $table->string('method', 10);
            $table->string('route', 150);
            $table->string('path', 255);
            $table->unsignedSmallInteger('status_code');
            $table->enum('outcome', ['SUCCESS', 'DENIED', 'FAILED']);
            $table->string('entity_type', 80)->nullable();
            $table->string('entity_ref', 100)->nullable();
            $table->json('payload')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('correlation_id', 100)->nullable();

            $table->index(['tenant_id', 'occurred_at']);
            $table->index(['tenant_id', 'actor_id']);
            $table->index(['tenant_id', 'entity_ref']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
    }
};