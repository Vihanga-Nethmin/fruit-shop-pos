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
       Schema::create('quality_assessments', function (Blueprint $table) {
    $table->char('id', 36)->primary();
    $table->char('tenant_id', 36)->index();
    $table->char('batch_id', 36)->index();
    $table->json('before_values');
    $table->json('after_values');
    $table->string('reason')->nullable();
    $table->text('notes')->nullable();
    $table->char('actor_id', 36);               // Identity user_id (FK nae)
    $table->timestamp('created_at')->nullable();

    $table->foreign('batch_id')->references('id')->on('batches');
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quality_assessments');
    }
};
