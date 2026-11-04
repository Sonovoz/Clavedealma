<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Diseño de la tienda pública (config/storefront.php). La pasarela de productos es común a todos.
        Schema::table('stores', function (Blueprint $table) {
            $table->string('design', 40)->default('base')->after('theme');
        });
    }

    public function down(): void
    {
        Schema::table('stores', fn (Blueprint $table) => $table->dropColumn('design'));
    }
};
