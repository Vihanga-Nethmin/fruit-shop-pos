<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sell_by_reviews', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('tenant_id', 36)->index();
            $table->char('batch_id', 36)->unique();            // batch ekakata dan thiyena decision eka
            $table->enum('decision', ['SALE_APPROVED', 'NOT_FOR_SALE']);
            $table->date('valid_until')->nullable();           // SALE_APPROVED walata witharai
            $table->string('reason');
            $table->char('decided_by', 36);                    // Identity user_id (FK nae)
            $table->timestamp('decided_at');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->foreign('batch_id')->references('id')->on('batches');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sell_by_reviews');
    }
};