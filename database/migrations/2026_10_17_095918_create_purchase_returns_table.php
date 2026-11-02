<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_returns', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no', 30)->nullable()->unique(); // e.g. PRN-00001
            $table->foreignId('purchase_id')->constrained('purchases')->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->restrictOnDelete();
            $table->string('supplier_name')->nullable();  // snapshot from the purchase
            $table->string('supplier_phone', 30)->nullable();
            $table->date('return_date');
            $table->decimal('subtotal', 12, 2)->default(0);       // returned items at purchase cost
            $table->decimal('discount', 12, 2)->default(0);       // share of the purchase discount
            $table->decimal('total', 12, 2)->default(0);          // total value returned
            $table->decimal('due_adjustment', 12, 2)->default(0); // reduces what we owe the supplier
            $table->decimal('cash_received', 12, 2)->default(0);  // cash the supplier paid back to us
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
        Schema::dropIfExists('purchase_returns');
    }
};