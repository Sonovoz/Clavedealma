<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Avisos de los artistas a Clave de Alma (ADR 0023): «Firmo camisetas en El Corte Inglés el
| día 12», «Nueva camiseta con el logo de temporada». Llegan a los administradores, que los
| marcan «En curso» o «Hecho» y pueden responder.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('artist_notices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artist_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 120);
            $table->text('message');
            $table->date('event_date')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->json('attachments')->nullable();
            $table->string('status', 20)->default('new')->index();
            $table->text('reply')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('artist_notices');
    }
};
