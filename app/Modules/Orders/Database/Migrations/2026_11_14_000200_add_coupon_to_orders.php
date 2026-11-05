<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Cupones (ADR 0024): el pedido guarda el código y el descuento total, y cada línea
| su parte. El total de la línea ya es el importe con el descuento aplicado.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('coupon_code', 40)->nullable()->after('user_id');
            $table->unsignedInteger('discount_cents')->default(0)->after('subtotal_cents');
        });
        Schema::table('order_lines', function (Blueprint $table) {
            $table->unsignedInteger('discount_cents')->default(0)->after('unit_price_cents');
        });
    }

    public function down(): void
    {
        Schema::table('order_lines', fn (Blueprint $table) => $table->dropColumn('discount_cents'));
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn(['coupon_code', 'discount_cents']));
    }
};
