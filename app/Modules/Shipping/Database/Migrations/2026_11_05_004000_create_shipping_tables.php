<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Empresas de transporte con las que trabaja Clave de Alma.
        Schema::create('carriers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            // Enlace de seguimiento con {codigo} donde va el número del envío.
            $table->string('tracking_url')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Un destino = el transportista elegido para él, su plazo y su envío gratis.
        Schema::create('shipping_zone_rates', function (Blueprint $table) {
            $table->id();
            $table->string('zone', 20)->unique();
            $table->foreignId('carrier_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('free_from_cents')->nullable();
            $table->unsignedTinyInteger('min_days')->default(1);
            $table->unsignedTinyInteger('max_days')->default(3);
            $table->boolean('active')->default(false);
            $table->timestamps();
        });

        // Baremo por peso del destino: hasta X gramos, precio con IVA.
        Schema::create('shipping_rate_bands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipping_zone_rate_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('max_weight_grams');
            $table->unsignedInteger('price_cents');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        // Destinos y transportistas de partida. El baremo NO se inventa: se rellena en el
        // panel (Envíos → Tarifas); cada destino nace desactivado hasta tenerlo.
        $now = now();
        foreach (['NACEX', 'GLS'] as $name) {
            DB::table('carriers')->insert(['name' => $name, 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
        foreach (['peninsula', 'baleares', 'canarias', 'ceuta_melilla', 'portugal', 'europa'] as $zone) {
            DB::table('shipping_zone_rates')->insert(['zone' => $zone, 'active' => false, 'min_days' => 1, 'max_days' => 3, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_rate_bands');
        Schema::dropIfExists('shipping_zone_rates');
        Schema::dropIfExists('carriers');
    }
};
