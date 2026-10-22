<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            $table->char('original_batch_id', 36)->nullable()->after('receipt_line_id');
            $table->foreign('original_batch_id')->references('id')->on('batches');
        });

        Schema::create('sale_return_receipts', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('tenant_id', 36)->index();
            $table->string('request_id', 100);              // Sales eke idempotency id
            $table->char('sale_id', 36);                    // Sales service id (opaque, FK nae)
            $table->char('sale_return_id', 36);             // Sales service id (opaque, FK nae)
            $table->char('created_by', 36);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique(['tenant_id', 'request_id']);
        });

        Schema::create('sale_return_receipt_lines', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('tenant_id', 36)->index();
            $table->char('sale_return_receipt_id', 36)->index();
            $table->char('product_id', 36);
            $table->char('location_id', 36);
            $table->decimal('quantity', 14, 3);
            $table->decimal('unit_cost', 18, 6);
            $table->char('original_batch_id', 36)->nullable();
            $table->char('batch_id', 36)->nullable();       // aluth return batch eka
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('sale_return_receipt_id')->references('id')->on('sale_return_receipts');
            $table->foreign('product_id')->references('id')->on('products');
            $table->foreign('location_id')->references('id')->on('locations');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_return_receipt_lines');
        Schema::dropIfExists('sale_return_receipts');

        Schema::table('batches', function (Blueprint $table) {
            $table->dropForeign(['original_batch_id']);
            $table->dropColumn('original_batch_id');
        });
    }
};