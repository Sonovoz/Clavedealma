<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Inventario por producto, como en WooCommerce (ADR 0022). Clave de Alma fabrica bajo
| pedido: cada producto tiene un estado (Hay existencias / Agotado) y, si se quiere, una
| cantidad máxima para todas sus tallas y colores. Sustituye al stock por variante.
|
| Paso de los datos: un producto con variantes «limitadas (se agota)» pasa a gestionar
| inventario con la suma de sus unidades; «bajo pedido» y «stock y después bajo pedido»
| quedan sin límite. El historial por variante no se traslada: se anota un alta inicial
| con la cantidad resultante (el proyecto aún no vendía).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('in_stock')->index();
            $table->boolean('managed')->default(false);
            $table->integer('quantity')->default(0);
            $table->unsignedInteger('low_stock_threshold')->nullable();
            $table->timestamps();
        });

        // Registro inmutable: cada entrada o salida de unidades de un producto.
        Schema::create('product_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->integer('delta');
            $table->integer('quantity_after');
            $table->string('reason', 30);
            $table->string('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['product_id', 'created_at']);
        });

        if (Schema::hasTable('stock_levels')) {
            $limited = DB::table('stock_levels')
                ->join('product_variants', 'product_variants.id', '=', 'stock_levels.product_variant_id')
                ->where('stock_levels.mode', 'limited_sold_out')
                ->groupBy('product_variants.product_id')
                ->selectRaw('product_variants.product_id as product_id, SUM(stock_levels.quantity) as quantity, MIN(stock_levels.low_stock_threshold) as threshold')
                ->get();

            foreach ($limited as $row) {
                $quantity = max(0, (int) $row->quantity);
                DB::table('product_stocks')->insert([
                    'product_id' => $row->product_id, 'status' => 'in_stock', 'managed' => true, 'quantity' => $quantity,
                    'low_stock_threshold' => $row->threshold, 'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('product_stock_movements')->insert([
                    'product_id' => $row->product_id, 'delta' => $quantity, 'quantity_after' => $quantity, 'reason' => 'initial',
                    'note' => 'Paso a inventario por producto: suma de las tallas', 'created_at' => now(),
                ]);
            }
        }

        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_levels');
    }

    public function down(): void
    {
        Schema::dropIfExists('product_stock_movements');
        Schema::dropIfExists('product_stocks');
    }
};
