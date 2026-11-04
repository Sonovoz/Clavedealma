<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Textos legales generados (ADR 0020): una versión por cambio, para Clave de Alma
| (store_id nulo) y para cada tienda. Inmutables salvo su revisión por la asesoría.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_documents', function (Blueprint $table) {
            $table->id();
            $table->string('page', 40);
            $table->unsignedBigInteger('store_id')->nullable();
            $table->unsignedInteger('version');
            $table->longText('content');
            $table->json('missing')->nullable();
            $table->timestamp('generated_at');
            $table->timestamp('approved_at')->nullable();
            $table->string('approved_by')->nullable();
            $table->timestamps();

            $table->unique(['page', 'store_id', 'version']);
            $table->index(['page', 'store_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_documents');
    }
};
