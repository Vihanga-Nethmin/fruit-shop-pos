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
        Schema::create('goods_receipts', function (Blueprint $table) {
    $table->char('id', 36)->primary();
    $table->char('tenant_id', 36)->index();
    $table->char('supplier_id', 36);
    $table->char('purchase_order_id', 36)->nullable();
    $table->string('delivery_reference', 60)->nullable();
    $table->date('receipt_date');
    $table->enum('status', ['DRAFT', 'POSTED', 'REVERSED', 'CANCELLED'])->default('DRAFT');
    $table->char('received_by', 36);
    $table->char('posted_by', 36)->nullable();
    $table->timestamp('posted_at')->nullable();
    $table->text('notes')->nullable();
    $table->unsignedInteger('version')->default(1);
    $table->timestamps();

    $table->foreign('supplier_id')->references('id')->on('suppliers');
    $table->foreign('purchase_order_id')->references('id')->on('purchase_orders');
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('goods_receipts');
    }
};
