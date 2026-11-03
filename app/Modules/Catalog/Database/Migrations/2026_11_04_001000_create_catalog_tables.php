<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Prenda lisa con la que se fabrica (p. ej. "Sudadera unisex algodón 320 g").
        Schema::create('base_garments', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('material')->nullable();
            $table->unsignedInteger('weight_grams')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->restrictOnDelete();
            $table->foreignId('base_garment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();

            // Precio de venta con IVA incluido, en céntimos.
            $table->unsignedInteger('price_cents');
            $table->unsignedInteger('vat_rate_bps')->default(2100);
            // Si se rellena, sustituye a la comisión de la tienda para este producto.
            $table->unsignedInteger('commission_bps')->nullable();

            $table->unsignedTinyInteger('lead_time_min_days')->default(5);
            $table->unsignedTinyInteger('lead_time_max_days')->default(7);
            // Personalizado a petición del cliente: excluido del desistimiento de 14 días.
            $table->boolean('personalized')->default(false);
            // Archivo del diseño para producción (disco privado).
            $table->string('design_path')->nullable();

            $table->string('status', 20)->default('draft')->index();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['store_id', 'slug']);
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('sku', 64)->unique();
            $table->string('size', 20)->nullable();
            $table->string('color', 40)->nullable();
            $table->string('color_hex', 7)->nullable();
            // Precio propio de la variante (p. ej. tallas grandes). Vacío = precio del producto.
            $table->unsignedInteger('price_cents')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('alt')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['store_id', 'slug']);
        });

        Schema::create('collection_product', function (Blueprint $table) {
            $table->foreignId('collection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->primary(['collection_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_product');
        Schema::dropIfExists('collections');
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
        Schema::dropIfExists('base_garments');
    }
};
