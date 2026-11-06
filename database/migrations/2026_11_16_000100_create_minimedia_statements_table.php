<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cierres mensuales de la comisión de Minimedia (ADR 0029): uno por mes, inmutables.
 * Una devolución posterior se descuenta en el mes en que se hace, nunca cambia un cierre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('minimedia_statements', function (Blueprint $table): void {
            $table->id();
            $table->char('month', 7)->unique(); // 2026-10
            $table->unsignedSmallInteger('rate_bps');
            $table->unsignedInteger('orders');
            $table->bigInteger('sales_base_cents');
            $table->bigInteger('refunds_base_cents');
            $table->bigInteger('net_base_cents');
            $table->bigInteger('commission_cents');
            $table->json('stores');
            $table->string('sent_to')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('send_error', 1000)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('minimedia_statements');
    }
};
