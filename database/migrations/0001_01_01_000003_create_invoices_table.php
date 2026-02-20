<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('original_filename');
            $table->string('stored_path');
            $table->string('status')->default('pending');
            $table->string('invoice_number')->nullable();
            $table->date('invoice_date')->nullable();
            $table->date('due_date')->nullable();
            $table->string('currency', 3)->nullable();
            $table->string('vendor_name')->nullable();
            $table->text('vendor_address')->nullable();
            $table->string('vendor_tax_id')->nullable();
            $table->string('customer_name')->nullable();
            $table->text('customer_address')->nullable();
            $table->string('customer_tax_id')->nullable();
            $table->decimal('subtotal', 12, 2)->nullable();
            $table->decimal('tax_amount', 12, 2)->nullable();
            $table->decimal('total', 12, 2)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
