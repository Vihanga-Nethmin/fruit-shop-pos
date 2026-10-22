<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_return_receipt_lines', function (Blueprint $table) {
            $table->timestamp('released_at')->nullable();
            $table->char('released_by', 36)->nullable();
            $table->string('release_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sale_return_receipt_lines', function (Blueprint $table) {
            $table->dropColumn(['released_at', 'released_by', 'release_reason']);
        });
    }
};