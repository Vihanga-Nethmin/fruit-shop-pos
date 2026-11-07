
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_projection', function (Blueprint $table) {
            $table->id();

            $table->uuid('tenant_id');
            $table->date('sale_date');

            $table->unsignedInteger('total_sales')->default(0);
            $table->decimal('total_revenue', 15, 2)->default(0);
            $table->decimal('total_refunds', 15, 2)->default(0);
            $table->decimal('net_revenue', 15, 2)->default(0);

            $table->timestamp('data_as_of')->nullable();

            $table->timestamps();

            $table->unique(
                ['tenant_id', 'sale_date'],
                'sales_projection_tenant_date_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_projection');
    }
};
