<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artist_id')->constrained()->restrictOnDelete();

            $table->string('name');
            $table->string('subdomain', 40)->unique();
            $table->string('custom_domain')->nullable()->unique();
            $table->string('status', 20)->default('draft')->index();

            // Comisión del artista en puntos básicos (25 % = 2500).
            $table->unsignedInteger('commission_bps');

            $table->string('tagline')->nullable();
            $table->text('bio')->nullable();
            // Tokens de diseño de la tienda (colores, tipografías, imágenes). Se usan en la fase 8.
            $table->json('theme')->nullable();

            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
