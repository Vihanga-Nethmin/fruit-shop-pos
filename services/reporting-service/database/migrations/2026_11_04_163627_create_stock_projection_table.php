
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_projection', function (Blueprint $table) {
            $table->id();

            $table->uuid('tenant_id');
            $table->uuid('product_id');

            $table->string('product_name');
            $table->string('category')->nullable();

            $table->decimal('quantity_on_hand', 15, 3)->default(0);
            $table->decimal('reorder_level', 15, 3)->default(0);

            $table->boolean('is_low_stock')->default(false);
            $table->boolean('is_out_of_stock')->default(false);

            $table->timestamp('data_as_of')->nullable();

            $table->timestamps();

            $table->unique(
                ['tenant_id', 'product_id'],
                'stock_projection_tenant_product_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_projection');
    }
};
