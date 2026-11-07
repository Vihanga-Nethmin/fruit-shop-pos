
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('margin_projection', function (Blueprint $table) {
            $table->id();

            $table->uuid('tenant_id');
            $table->date('report_date');

            $table->decimal('total_revenue', 15, 2)->default(0);
            $table->decimal('total_cost', 15, 2)->default(0);
            $table->decimal('gross_profit', 15, 2)->default(0);

            $table->decimal('gross_margin_percentage', 8, 2)->default(0);

            $table->timestamp('data_as_of')->nullable();

            $table->timestamps();

            $table->unique(
                ['tenant_id', 'report_date'],
                'margin_projection_tenant_date_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('margin_projection');
    }
};
