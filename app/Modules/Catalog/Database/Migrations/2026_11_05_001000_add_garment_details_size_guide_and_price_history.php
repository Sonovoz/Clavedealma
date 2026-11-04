<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ficha textil de la prenda base: la heredan todos sus artículos.
        Schema::table('base_garments', function (Blueprint $table) {
            // Obligatoria para vender textil (Reglamento UE 1007/2011), p. ej. "100 % algodón".
            $table->string('composition')->nullable()->after('material');
            $table->string('fit', 40)->nullable()->after('composition');
            $table->text('care')->nullable()->after('fit');
            $table->string('origin_country', 60)->nullable()->after('care');
            $table->string('size_guide_note')->nullable()->after('origin_country');
        });

        // Guía de tallas: medidas de la prenda en cm, una fila por talla.
        Schema::create('garment_sizes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('base_garment_id')->constrained()->cascadeOnDelete();
            $table->string('size', 20);
            $table->unsignedSmallInteger('position')->default(0);
            $table->decimal('chest_cm', 5, 1)->nullable();
            $table->decimal('length_cm', 5, 1)->nullable();
            $table->decimal('sleeve_cm', 5, 1)->nullable();
            $table->timestamps();

            $table->unique(['base_garment_id', 'size']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('model_info')->nullable()->after('description'); // "El modelo mide 1,80 y lleva M"
        });

        Schema::table('product_images', function (Blueprint $table) {
            $table->boolean('is_swatch')->default(false)->after('color'); // foto del tejido para elegir color
        });

        // Historial de precios de cada variante (inmutable): calcula el precio anterior de las rebajas.
        Schema::create('variant_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('price_cents');
            $table->timestamp('started_at');
            $table->index(['product_variant_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('variant_prices');
        Schema::table('product_images', fn (Blueprint $table) => $table->dropColumn('is_swatch'));
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('model_info'));
        Schema::dropIfExists('garment_sizes');
        Schema::table('base_garments', fn (Blueprint $table) => $table->dropColumn(['composition', 'fit', 'care', 'origin_country', 'size_guide_note']));
    }
};
