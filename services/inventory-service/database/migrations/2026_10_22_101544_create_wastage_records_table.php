<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wastage_records', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('tenant_id', 36)->index();
            $table->char('batch_id', 36)->index();
            $table->char('product_id', 36);
            $table->char('location_id', 36);
            $table->decimal('quantity', 14, 3);
            $table->enum('reason', ['SPOILAGE', 'BRUISING', 'SHRINKAGE', 'HANDLING_DAMAGE', 'THEFT', 'OTHER']);
            $table->text('notes')->nullable();
            $table->enum('status', ['SUBMITTED', 'POSTED', 'REJECTED', 'CANCELLED'])->default('SUBMITTED');
            $table->decimal('unit_cost', 18, 6);              // batch cost snapshot
            $table->char('reported_by', 36);                  // Identity user_id (FK nae)
            $table->char('decided_by', 36)->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_notes')->nullable();
            $table->char('movement_id', 36)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->foreign('batch_id')->references('id')->on('batches');
            $table->foreign('product_id')->references('id')->on('products');
            $table->foreign('location_id')->references('id')->on('locations');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wastage_records');
    }
};