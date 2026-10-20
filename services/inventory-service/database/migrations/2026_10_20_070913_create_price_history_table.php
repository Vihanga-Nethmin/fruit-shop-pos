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
       Schema::create('price_history', function (Blueprint $table) {
    $table->char('id', 36)->primary();
    $table->char('tenant_id', 36)->index();
    $table->char('product_id', 36)->index();
    $table->decimal('old_price', 12, 2)->nullable();
    $table->decimal('new_price', 12, 2);
    $table->timestamp('effective_at');
    $table->char('changed_by', 36);   // Identity service user_id (UUID, FK nae)
    $table->string('reason');
    $table->timestamps();

    $table->foreign('product_id')->references('id')->on('products');
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('price_history');
    }
};
