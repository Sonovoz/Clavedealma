<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Si la factura se emitió con Veri*Factu activado (QR, leyenda y envío a la AEAT).
| Mientras no sea obligatorio queda desactivado; la huella encadenada se guarda igual.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->boolean('verifactu')->default(false)->after('currency');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('verifactu');
        });
    }
};
