<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Facturación (ADR 0018). Facturas inmutables, numeración correlativa por serie y año,
| y su registro Veri*Factu con la huella encadenada.
*/
return new class extends Migration
{
    public function up(): void
    {
        // Datos fiscales de Clave de Alma (una sola fila; se rellenan en el panel).
        Schema::create('invoice_issuers', function (Blueprint $table) {
            $table->id();
            $table->string('legal_name')->nullable();
            $table->string('nif', 20)->nullable();
            $table->string('address')->nullable();
            $table->string('postal_code', 12)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('province', 120)->nullable();
            $table->char('country', 2)->default('ES');
            $table->string('email')->nullable();
            $table->string('simplified_series', 10)->default('T');
            $table->string('rectifying_series', 10)->default('R');
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('series', 10);
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('number');
            $table->string('code', 30)->unique();
            $table->string('type', 4);
            $table->unsignedBigInteger('order_id')->index();
            $table->string('order_reference', 20);
            $table->foreignId('rectifies_id')->nullable()->constrained('invoices');
            $table->unsignedBigInteger('refund_id')->nullable()->unique();
            $table->string('reason')->nullable();
            $table->timestamp('issued_at');
            $table->json('issuer');
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->integer('base_cents');
            $table->integer('vat_cents');
            $table->integer('total_cents');
            $table->char('currency', 3)->default('EUR');

            // Veri*Factu
            $table->char('hash', 64);
            $table->char('previous_hash', 64)->nullable();
            $table->string('record_generated_at', 32);
            $table->string('qr_url', 500);
            $table->string('aeat_status', 20)->index();
            $table->string('aeat_error', 1000)->nullable();
            $table->timestamp('aeat_sent_at')->nullable();
            $table->timestamps();

            $table->unique(['series', 'year', 'number']);
        });

        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->integer('quantity');
            $table->integer('unit_price_cents');
            $table->unsignedSmallInteger('vat_rate_bps');
            $table->integer('base_cents');
            $table->integer('vat_cents');
            $table->integer('total_cents');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('invoice_issuers');
    }
};
