<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Cupones como WooCommerce (ADR 0024): Marketing → Cupones. Los usos se anotan cuando
| se paga el pedido y se liberan si se cancela.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('description', 255)->nullable();
            $table->string('type', 20)->default('percent');
            // Puntos básicos (porcentaje) o céntimos (importe fijo).
            $table->unsignedInteger('amount')->default(0);
            $table->boolean('free_shipping')->default(false);
            $table->timestamp('starts_at')->nullable();
            $table->date('expires_on')->nullable();
            $table->unsignedInteger('minimum_cents')->nullable();
            // Vacía = todas las tiendas.
            $table->unsignedBigInteger('store_id')->nullable()->index();
            $table->boolean('exclude_sale_items')->default(false);
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('usage_limit_per_customer')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('coupon_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('order_id')->unique();
            $table->string('email');
            $table->unsignedInteger('discount_cents');
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
            $table->index(['coupon_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_redemptions');
        Schema::dropIfExists('coupons');
    }
};
