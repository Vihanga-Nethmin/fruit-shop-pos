<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_counts', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('tenant_id', 36)->index();
            $table->char('location_id', 36)->nullable();       // scope
            $table->char('product_id', 36)->nullable();        // scope
            $table->enum('status', ['OPEN', 'SUBMITTED', 'POSTED', 'CANCELLED'])->default('OPEN');
            $table->text('notes')->nullable();
            $table->char('opened_by', 36);                     // Identity user_id (FK nae)
            $table->char('submitted_by', 36)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->char('decided_by', 36)->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->string('review_notes')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        Schema::create('stock_count_lines', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('tenant_id', 36)->index();
            $table->char('stock_count_id', 36)->index();
            $table->char('product_id', 36);
            $table->char('batch_id', 36);
            $table->char('location_id', 36);
            $table->decimal('snapshot_qty', 14, 3);            // system quantity at count start
            $table->decimal('counted_qty', 14, 3)->nullable();
            $table->decimal('variance', 14, 3)->nullable();    // counted - snapshot
            $table->decimal('unit_cost', 18, 6);               // batch cost snapshot
            $table->char('counted_by', 36)->nullable();
            $table->timestamp('counted_at')->nullable();
            $table->char('movement_id', 36)->nullable();       // adjustment movement
            $table->timestamps();

            $table->unique(['stock_count_id', 'batch_id', 'location_id']);
            $table->foreign('stock_count_id')->references('id')->on('stock_counts');
            $table->foreign('product_id')->references('id')->on('products');
            $table->foreign('batch_id')->references('id')->on('batches');
            $table->foreign('location_id')->references('id')->on('locations');
        });

        // Count ekak run wenakota position eka lock karana column eka
        Schema::table('stock_positions', function (Blueprint $table) {
            $table->char('locked_by_count_id', 36)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('stock_positions', function (Blueprint $table) {
            $table->dropIndex(['locked_by_count_id']);
            $table->dropColumn('locked_by_count_id');
        });

        Schema::dropIfExists('stock_count_lines');
        Schema::dropIfExists('stock_counts');
    }
};