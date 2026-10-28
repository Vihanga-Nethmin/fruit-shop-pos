<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbox', function (Blueprint $table) {
            $table->char('id', 36)->primary();                 // event_id
            $table->char('tenant_id', 36)->index();
            $table->string('event_type', 80);
            $table->unsignedSmallInteger('schema_version')->default(1);
            $table->char('aggregate_id', 36);                  // movement id
            $table->json('payload');
            $table->string('correlation_id', 100)->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('published_at')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('last_error')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['tenant_id', 'event_type', 'aggregate_id']);   // movement ekakata event ekak
            $table->index(['published_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox');
    }
};