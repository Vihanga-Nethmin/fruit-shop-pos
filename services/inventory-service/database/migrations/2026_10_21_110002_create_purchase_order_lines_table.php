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
       Schema::create('purchase_order_lines', function (Blueprint $table) {
    $table->char('id', 36)->primary();
    $table->char('tenant_id', 36)->index();
    $table->char('purchase_order_id', 36)->index();
    $table->char('product_id', 36);
    $table->decimal('ordered_qty', 14, 3);
    $table->decimal('received_qty', 14, 3)->default(0);
    $table->decimal('cancelled_qty', 14, 3)->default(0);
    $table->decimal('agreed_unit_cost', 18, 6);
    $table->unsignedInteger('container_count')->nullable();
    $table->timestamps();

    $table->foreign('purchase_order_id')->references('id')->on('purchase_orders');
    $table->foreign('product_id')->references('id')->on('products');
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_order_lines');
    }
};
