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
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained(); // කැෂියර්
            $table->decimal('opening_float', 12, 2)->default(0); // ආරම්භක මුදල්
           $table->decimal('closing_cash', 12, 2)->nullable(); // අවසානයේ ලෙසට ඇති මුදල්
            $table->decimal('expected_cash', 12, 2)->nullable(); // බලාපොරොත්තු වන මුදල්
            $table->decimal('variance', 12, 2)->nullable(); // වෙන $table->string('status')->default('open'); // open, closed
            $table->string('status')->default('open');
           $table->timestamp('opened_at')->nullable();
           $table->timestamp('closed_at')->nullable();
           $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};
