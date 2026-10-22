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
       Schema::create('stock_positions', function (Blueprint $table) {
    $table->char('id', 36)->primary();
    $table->char('tenant_id', 36)->index();
    $table->char('product_id', 36)->index();
    $table->char('batch_id', 36);
    $table->char('location_id', 36);
    $table->decimal('physical_qty', 14, 3)->default(0);
    $table->decimal('reserved_qty', 14, 3)->default(0);
    $table->unsignedInteger('version')->default(1);
    $table->timestamps();

    $table->unique(['tenant_id', 'batch_id', 'location_id']);
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
        Schema::dropIfExists('stock_positions');
    }
};
