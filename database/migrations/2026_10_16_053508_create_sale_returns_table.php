<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_returns', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no', 30)->nullable()->unique(); // e.g. SRN-00001
            $table->foreignId('sale_id')->constrained('sales')->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->restrictOnDelete();
            $table->string('customer_name')->nullable();  // snapshot from the sale
            $table->string('customer_phone', 30)->nullable();
            $table->date('return_date');
            $table->decimal('subtotal', 12, 2)->default(0);       // returned items at invoice prices
            $table->decimal('discount', 12, 2)->default(0);       // share of the invoice discount
            $table->decimal('total', 12, 2)->default(0);          // total value returned
            $table->decimal('due_adjustment', 12, 2)->default(0); // reduces the customer's due
            $table->decimal('cash_refund', 12, 2)->default(0);    // cash paid back to the customer
            $table->string('refund_method', 15)->default('cash'); // cash, adjust_due
            $table->string('reason')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 10)->default('completed');   // completed, cancelled
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('return_date');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_returns');
    }
};