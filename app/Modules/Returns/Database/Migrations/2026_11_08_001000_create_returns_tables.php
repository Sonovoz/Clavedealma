<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Devoluciones (ADR 0016). Las líneas e importes no cambian; el estado avanza.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->nullable()->unique();
            $table->unsignedBigInteger('order_id')->index();
            $table->string('order_reference', 20);
            $table->string('status', 20)->index();
            $table->string('reason', 30);
            $table->string('comment', 500)->nullable();
            $table->unsignedInteger('refund_cents');
            $table->boolean('includes_shipping')->default(false);
            $table->text('instructions')->nullable();
            $table->string('rejection_reason', 500)->nullable();
            $table->boolean('restocked')->default(false);
            $table->string('refund_error', 500)->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();
        });

        Schema::create('return_request_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_request_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('order_line_id');
            $table->unsignedBigInteger('product_variant_id');
            $table->string('sku', 80);
            $table->string('product_name');
            $table->string('option', 120)->nullable();
            $table->unsignedSmallInteger('quantity');
            $table->unsignedInteger('unit_price_cents');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_request_lines');
        Schema::dropIfExists('return_requests');
    }
};
