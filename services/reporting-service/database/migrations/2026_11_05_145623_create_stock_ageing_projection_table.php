<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_ageing_projection', function (Blueprint $table) {
            $table->id();

            $table->uuid('tenant_id');
            $table->uuid('product_id');
            $table->uuid('batch_id')->unique();

            $table->string('product_name');
            $table->decimal('quantity', 15, 3)->default(0);

            $table->date('received_date')->nullable();
            $table->date('expiry_date')->nullable();

            $table->unsignedInteger('age_in_days')->default(0);

            $table->boolean('is_near_expiry')->default(false);
            $table->boolean('is_expired')->default(false);

            $table->timestamp('data_as_of')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'received_date']);
            $table->index(['tenant_id', 'expiry_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_ageing_projection');
    }
};
