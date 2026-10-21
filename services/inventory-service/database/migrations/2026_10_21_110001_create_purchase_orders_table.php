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
        Schema::create('purchase_orders', function (Blueprint $table) {
    $table->char('id', 36)->primary();
    $table->char('tenant_id', 36)->index();
    $table->char('supplier_id', 36);
    $table->string('reference', 60)->nullable();
    $table->date('order_date');
    $table->date('expected_date')->nullable();
    $table->enum('status', ['DRAFT', 'APPROVED', 'PARTIALLY_RECEIVED', 'RECEIVED', 'CLOSED', 'CANCELLED'])->default('DRAFT');
    $table->text('notes')->nullable();
    $table->char('created_by', 36);
    $table->char('approved_by', 36)->nullable();
    $table->unsignedInteger('version')->default(1);
    $table->timestamps();

    $table->foreign('supplier_id')->references('id')->on('suppliers');
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
