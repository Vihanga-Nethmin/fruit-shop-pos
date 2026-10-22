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
        Schema::create('stock_movements', function (Blueprint $table) {
    $table->char('id', 36)->primary();
    $table->char('tenant_id', 36)->index();
    $table->char('product_id', 36)->index();
    $table->char('batch_id', 36)->index();
    $table->char('location_id', 36);
    $table->decimal('signed_qty', 14, 3);               
    $table->enum('type', [
        'OPENING', 'RECEIPT', 'SALE', 'SALE_RETURN',
        'TRANSFER_OUT', 'TRANSFER_IN', 'WASTAGE',
        'SUPPLIER_RETURN', 'COUNT_ADJUSTMENT', 'REPACK',
    ]);
    $table->string('source_type', 40)->nullable();      
    $table->char('source_id', 36)->nullable();
    $table->char('source_line_id', 36)->nullable();
    $table->string('reason')->nullable();
    $table->char('actor_id', 36);                       
    $table->char('approved_by', 36)->nullable();
    $table->decimal('unit_cost', 18, 6);                
    $table->char('reverses_movement_id', 36)->nullable();
    $table->timestamp('occurred_at');
    $table->timestamp('created_at')->nullable();

    $table->index(['tenant_id', 'batch_id', 'location_id']);
    $table->foreign('product_id')->references('id')->on('products');
    $table->foreign('batch_id')->references('id')->on('batches');
    $table->foreign('location_id')->references('id')->on('locations');
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
