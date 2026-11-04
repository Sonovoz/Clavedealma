<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cada tienda tiene sus propias prendas base: el código es único dentro de la tienda.
 * Las prendas que ya existían pasan a la tienda de sus artículos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('base_garments', function (Blueprint $table) {
            $table->foreignId('store_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        foreach (DB::table('base_garments')->pluck('id') as $id) {
            $store = DB::table('products')->where('base_garment_id', $id)->value('store_id');
            if ($store !== null) {
                DB::table('base_garments')->where('id', $id)->update(['store_id' => $store]);
            }
        }

        Schema::table('base_garments', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->unique(['store_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::table('base_garments', function (Blueprint $table) {
            $table->dropUnique(['store_id', 'code']);
            $table->unique(['code']);
            $table->dropConstrainedForeignId('store_id');
        });
    }
};
