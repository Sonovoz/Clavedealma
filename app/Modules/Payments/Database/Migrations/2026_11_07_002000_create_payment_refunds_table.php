<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Devoluciones de dinero: inmutables, una fila por devolución.
        Schema::create('payment_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('order_id')->index();
            $table->unsignedInteger('amount_cents');
            $table->string('idempotency_key', 120)->unique();
            $table->string('provider_reference', 120)->nullable();
            $table->string('status', 20);
            $table->string('reason', 255);
            $table->string('failure_message', 500)->nullable();
            // Intentos fallidos: cada reintento usa una clave nueva ante la pasarela.
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_refunds');
    }
};
