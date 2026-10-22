<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
      Schema::create('goods_receipt_lines', function (Blueprint $table) {
    $table->char('id', 36)->primary();
    $table->char('tenant_id', 36)->index();
    $table->char('goods_receipt_id', 36)->index();
    $table->char('product_id', 36);
    $table->char('purchase_order_line_id', 36)->nullable();
    $table->char('location_id', 36);
    $table->decimal('delivered_qty', 14, 3);
    $table->decimal('accepted_qty', 14, 3);
    $table->decimal('rejected_qty', 14, 3)->default(0);
    $table->decimal('gross_weight', 14, 3)->nullable();
    $table->decimal('tare_weight', 14, 3)->nullable();
    $table->decimal('net_weight', 14, 3)->nullable();
    $table->decimal('line_cost', 12, 2);
    $table->string('reject_reason')->nullable();
    $table->string('supplier_lot_ref', 60)->nullable();
    $table->string('origin', 100)->nullable();
    $table->enum('ripeness', ['UNRIPE', 'READY', 'OVERRIPE'])->default('READY');
    $table->date('sell_by_date')->nullable();
    $table->date('use_by_date')->nullable();
    $table->date('harvest_date')->nullable();
    $table->text('inspection_notes')->nullable();
    $table->char('batch_id', 36)->nullable();
    $table->timestamps();

    $table->foreign('goods_receipt_id')->references('id')->on('goods_receipts');
    $table->foreign('product_id')->references('id')->on('products');
    $table->foreign('location_id')->references('id')->on('locations');
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('goods_receipt_lines');
    }
};
