<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Un intento de cobro por pedido e importe (se reutiliza al recargar la página de pago).
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 20);
            $table->string('provider_reference', 120)->nullable()->unique();
            $table->unsignedInteger('amount_cents');
            $table->char('currency', 3)->default('EUR');
            $table->string('status', 20)->index();
            $table->string('failure_message', 500)->nullable();
            $table->timestamp('succeeded_at')->nullable();
            $table->timestamps();
        });

        // Avisos de la pasarela ya procesados: cada uno se aplica una sola vez.
        Schema::create('payment_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 20);
            $table->string('event_id', 120);
            $table->string('type', 80);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['provider', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_events');
        Schema::dropIfExists('payments');
    }
};
