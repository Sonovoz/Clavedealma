<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| «Notas del pedido» como WooCommerce: quién escribió cada nota (ADR 0025). Las notas son
| apuntes del historial del pedido: inmutables, como el resto.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_events', function (Blueprint $table) {
            $table->string('author', 120)->nullable()->after('note');
        });
    }

    public function down(): void
    {
        Schema::table('order_events', fn (Blueprint $table) => $table->dropColumn('author'));
    }
};
