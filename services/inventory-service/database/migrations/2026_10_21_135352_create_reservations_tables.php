<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('tenant_id', 36)->index();
            $table->string('request_id', 100);          // Sales eke checkout request id (idempotency)
            $table->enum('status', ['ACTIVE', 'COMMITTED', 'RELEASED', 'EXPIRED'])->default('ACTIVE');
            $table->timestamp('expires_at');
            $table->timestamp('committed_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique(['tenant_id', 'request_id']);
            $table->index(['status', 'expires_at']);
        });

        Schema::create('reservation_lines', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('tenant_id', 36)->index();
            $table->char('reservation_id', 36)->index();
            $table->char('product_id', 36);
            $table->char('batch_id', 36);
            $table->char('location_id', 36);
            $table->decimal('qty', 14, 3);
            $table->decimal('unit_cost', 18, 6);        // batch cost snapshot (Sales allocations walata)
            $table->timestamps();

            $table->foreign('reservation_id')->references('id')->on('reservations');
            $table->foreign('product_id')->references('id')->on('products');
            $table->foreign('batch_id')->references('id')->on('batches');
            $table->foreign('location_id')->references('id')->on('locations');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_lines');
        Schema::dropIfExists('reservations');
    }
};