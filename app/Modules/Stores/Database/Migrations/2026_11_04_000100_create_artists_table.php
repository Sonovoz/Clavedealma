<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('artists', function (Blueprint $table) {
            $table->id();
            // Usuario con rol "artista" que verá su ventana de ventas (opcional).
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();

            $table->string('display_name');
            $table->string('legal_name')->nullable();
            $table->string('tax_id', 20)->nullable();
            $table->string('tax_regime', 20)->nullable();
            // Tipos para la autofacturación, en puntos básicos (21 % = 2100). Los fija el gestor.
            $table->unsignedInteger('vat_rate_bps')->nullable();
            $table->unsignedInteger('irpf_rate_bps')->nullable();

            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('address_line')->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->char('country', 2)->default('ES');

            $table->text('iban')->nullable(); // cifrado en el modelo
            $table->date('self_billing_agreement_signed_at')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('artists');
    }
};
