<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Referencia del modelo según el protocolo de artículos (p. ej. "AV-02"): identifica el producto dentro de su tienda.
        Schema::table('products', function (Blueprint $table) {
            $table->string('reference', 40)->nullable()->after('store_id');
            $table->unique(['store_id', 'reference']);
        });

        // Una foto puede pertenecer a un color concreto del producto. Vacío = sirve para todos los colores.
        Schema::table('product_images', function (Blueprint $table) {
            $table->string('color', 40)->nullable()->after('product_id');
        });
    }

    public function down(): void
    {
        Schema::table('product_images', fn (Blueprint $table) => $table->dropColumn('color'));
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['store_id', 'reference']);
            $table->dropColumn('reference');
        });
    }
};
