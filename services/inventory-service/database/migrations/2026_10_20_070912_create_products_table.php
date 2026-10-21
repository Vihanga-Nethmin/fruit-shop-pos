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
       Schema::create('products', function (Blueprint $table) {
    $table->char('id', 36)->primary();
    $table->char('tenant_id', 36)->index();
    $table->string('sku', 50);
    $table->string('name');
    $table->char('category_id', 36);
    $table->string('variety')->nullable();
    $table->string('grade', 20)->nullable();
    $table->enum('base_unit', ['KG', 'EACH']);
    $table->string('sale_unit', 20);
    $table->decimal('selling_price', 12, 2);
    $table->decimal('tax_rate', 5, 2)->default(0);
    $table->boolean('is_active')->default(true);
    $table->decimal('low_stock_threshold', 14, 3)->default(0);
    $table->decimal('reorder_target', 14, 3)->default(0);
    $table->boolean('batch_tracked')->default(true);
    // optional
    $table->string('plu', 30)->nullable();
    $table->text('description')->nullable();
    $table->string('origin')->nullable();
    $table->char('default_supplier_id', 36)->nullable();
    $table->text('storage_guidance')->nullable();
    $table->unsignedSmallInteger('reorder_lead_time_days')->nullable();
    $table->unsignedSmallInteger('shelf_life_days')->nullable();
    $table->unsignedInteger('version')->default(1);
    $table->timestamps();

    $table->unique(['tenant_id', 'sku']);
    $table->unique(['tenant_id', 'plu']);
    $table->foreign('category_id')->references('id')->on('categories');
    $table->foreign('default_supplier_id')->references('id')->on('suppliers');
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
