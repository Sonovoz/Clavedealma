<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| «Mi cuenta» (ADR 0021): la dirección guardada del cliente, como la dirección de
| facturación de WooCommerce. Rellena «Finalizar compra» y se actualiza con cada pedido.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('phone', 30)->nullable();
            $table->string('address_line1', 200);
            $table->string('address_line2', 200)->nullable();
            $table->string('postal_code', 20);
            $table->string('city', 120);
            $table->string('province', 120)->nullable();
            $table->string('country', 2)->default('ES');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_addresses');
    }
};
