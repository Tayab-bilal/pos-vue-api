<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('type', 30);                      // purchase, sale, sale_return, adjustment, ...
            $table->decimal('quantity', 12, 2);              // signed: + adds stock, - removes stock
            $table->decimal('balance_after', 12, 2);         // product stock right after this movement
            $table->string('reference_type', 30)->nullable(); // purchase, sale, sale_return, purchase_return, stock_adjustment, product
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reference_no', 50)->nullable();  // e.g. INV-00001
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'id']);
            $table->index(['reference_type', 'reference_id']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};