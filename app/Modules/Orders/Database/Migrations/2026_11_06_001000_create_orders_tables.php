<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Pedidos (inmutables): las líneas y los importes no se cambian nunca; el estado avanza
| y cada cambio queda en order_events. Una corrección es un registro nuevo (ADR 0014).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->nullable()->unique();
            $table->string('token', 64)->unique();
            $table->string('status', 30)->index();
            $table->string('review_reason')->nullable();
            $table->boolean('is_test')->default(false);

            // Cliente y dirección de entrega
            $table->string('email');
            $table->string('customer_name');
            $table->string('phone', 30);
            $table->string('address_line1');
            $table->string('address_line2')->nullable();
            $table->string('postal_code', 12);
            $table->string('city', 120);
            $table->string('province', 120)->nullable();
            $table->char('country', 2);
            $table->string('customer_note', 500)->nullable();

            // Envío (Clave de Alma elige el transportista)
            $table->string('shipping_zone', 30);
            $table->string('shipping_carrier', 120);
            $table->unsignedInteger('shipping_cents');
            $table->boolean('shipping_free')->default(false);
            $table->unsignedSmallInteger('delivery_min_days');
            $table->unsignedSmallInteger('delivery_max_days');
            $table->unsignedSmallInteger('lead_time_min_days');
            $table->unsignedSmallInteger('lead_time_max_days');
            $table->unsignedInteger('weight_grams');

            // Importes con IVA incluido, en céntimos
            $table->unsignedInteger('subtotal_cents');
            $table->unsignedInteger('total_cents');
            $table->unsignedInteger('vat_cents');
            $table->char('currency', 3)->default('EUR');

            $table->string('terms_version', 40);
            $table->timestamp('terms_accepted_at');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('store_id')->index();
            $table->string('store_name');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('product_variant_id')->index();
            $table->string('sku', 80);
            $table->string('product_name');
            $table->string('reference', 40)->nullable();
            $table->string('size', 20)->nullable();
            $table->string('color', 60)->nullable();
            $table->unsignedInteger('unit_price_cents');
            $table->unsignedSmallInteger('quantity');
            $table->unsignedInteger('total_cents');
            $table->unsignedSmallInteger('vat_rate_bps');
            $table->unsignedSmallInteger('commission_bps');
            $table->unsignedInteger('weight_grams');
            $table->boolean('personalized')->default(false);
            $table->string('image_path')->nullable();
            $table->timestamps();
        });

        // Historial del pedido: solo se añaden filas.
        Schema::create('order_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('note', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_events');
        Schema::dropIfExists('order_lines');
        Schema::dropIfExists('orders');
    }
};
