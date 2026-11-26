<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->text('summary')->nullable();
            $table->timestamp('summary_generated_at')->nullable();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->enum('subscription_status', ['free', 'premium'])->default('free');
        });

        Schema::table('borrows', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('user_name')->constrained()->nullOnDelete();
            $table->enum('payment_status', ['pending', 'paid', 'free'])->default('pending');
        });
    }

    public function down(): void
    {
        Schema::table('borrows', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn(['user_id', 'payment_status']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('subscription_status');
        });

        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn(['summary', 'summary_generated_at']);
        });
    }
};