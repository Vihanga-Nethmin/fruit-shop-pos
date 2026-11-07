<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wastage_projection', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id');
            $table->uuid('product_id');
            $table->string('product_name');
            $table->decimal('wasted_quantity', 15, 3)->default(0);
            $table->decimal('wastage_cost', 15, 2)->default(0);
            $table->string('reason')->nullable();
            $table->date('wastage_date');
            $table->timestamp('data_as_of')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'wastage_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wastage_projection');
    }
};