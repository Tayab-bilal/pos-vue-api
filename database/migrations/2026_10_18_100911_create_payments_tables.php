<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no', 30)->nullable()->unique(); // RCT-00001 or PAY-00001
            $table->string('type', 10);                               // receipt (from a customer), payment (to a supplier)
            $table->foreignId('customer_id')->nullable()->constrained('customers')->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->restrictOnDelete();
            $table->string('party_name')->nullable();                 // snapshot, so old documents never change
            $table->string('party_phone', 30)->nullable();
            $table->date('payment_date');
            $table->decimal('amount', 12, 2);                         // total = opening balance part + invoice parts
            $table->decimal('opening_amount', 12, 2)->default(0);     // part that settles the opening balance
            $table->string('method', 20);                             // cash, bank_transfer, cheque, mobile_wallet, other
            $table->string('transaction_ref', 100)->nullable();       // cheque number, transfer id, etc.
            $table->text('notes')->nullable();
            $table->string('status', 10)->default('completed');       // completed, cancelled
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('type');
            $table->index('payment_date');
            $table->index('status');
        });

        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained('sales')->restrictOnDelete();
            $table->foreignId('purchase_id')->nullable()->constrained('purchases')->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('payments');
    }
};