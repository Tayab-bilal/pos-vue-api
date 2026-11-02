<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no', 30)->nullable()->unique(); // e.g. ADJ-00001
            $table->date('adjustment_date');
            $table->string('type', 20);                               // damage, loss, expired, count_correction, other
            $table->string('reason')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('value_change', 12, 2)->default(0);       // net value of the change at cost (negative = loss)
            $table->string('status', 10)->default('completed');       // completed, cancelled
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('adjustment_date');
            $table->index('type');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_adjustments');
    }
};