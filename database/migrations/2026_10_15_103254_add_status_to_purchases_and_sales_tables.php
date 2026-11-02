<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['purchases', 'sales'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('status', 10)->default('completed')->after('payment_status'); // completed, cancelled
                $table->timestamp('cancelled_at')->nullable()->after('status');
                $table->string('cancel_reason', 500)->nullable()->after('cancelled_at');
                $table->index('status');
            });
        }
    }

    public function down(): void
    {
        foreach (['purchases', 'sales'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropIndex(['status']);
                $table->dropColumn(['status', 'cancelled_at', 'cancel_reason']);
            });
        }
    }
};