<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Título y descripción para buscadores (y redes sociales). Vacíos = se generan solos
 * a partir del nombre y la descripción (docs/seo).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['products', 'collections'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->string('seo_title', 70)->nullable();
                $t->string('seo_description', 170)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['products', 'collections'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn(['seo_title', 'seo_description']));
        }
    }
};
