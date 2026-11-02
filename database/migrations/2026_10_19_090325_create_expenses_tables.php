<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no', 30)->nullable()->unique(); // e.g. EXP-00001
            $table->foreignId('expense_category_id')->constrained('expense_categories')->restrictOnDelete();
            $table->date('expense_date');
            $table->decimal('amount', 12, 2);
            $table->string('method', 20);                              // cash, bank_transfer, cheque, mobile_wallet, other
            $table->string('transaction_ref', 100)->nullable();        // cheque number, transfer id, etc.
            $table->string('paid_to')->nullable();                     // who the money went to
            $table->string('description')->nullable();                 // short line, e.g. "October electricity bill"
            $table->text('notes')->nullable();
            $table->string('status', 10)->default('completed');        // completed, cancelled
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('expense_date');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');
    }
};