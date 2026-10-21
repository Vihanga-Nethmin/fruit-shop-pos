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
       Schema::create('batches', function (Blueprint $table) {
    $table->char('id', 36)->primary();
    $table->char('tenant_id', 36)->index();
    $table->char('product_id', 36)->index();
    $table->char('supplier_id', 36)->nullable();
    $table->char('receipt_line_id', 36)->nullable();   
    $table->enum('source', ['RECEIPT', 'OPENING', 'RETURN'])->default('RECEIPT');
    $table->timestamp('received_at');
    $table->string('supplier_lot_ref', 60)->nullable();
    $table->decimal('accepted_qty', 14, 3);
    $table->decimal('unit_cost', 18, 6);
    $table->string('origin', 100)->nullable();
    $table->text('inspection_notes')->nullable();
    $table->date('harvest_date')->nullable();
    $table->enum('ripeness', ['UNRIPE', 'READY', 'OVERRIPE'])->default('READY');
    $table->date('sell_by_date')->nullable();
    $table->date('use_by_date')->nullable();
    $table->enum('quality_state', ['SALEABLE', 'QUARANTINED', 'SPOILED'])->default('SALEABLE');
    $table->unsignedInteger('version')->default(1);
    $table->timestamps();

    $table->foreign('product_id')->references('id')->on('products');
    $table->foreign('supplier_id')->references('id')->on('suppliers');
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('batches');
    }
};
