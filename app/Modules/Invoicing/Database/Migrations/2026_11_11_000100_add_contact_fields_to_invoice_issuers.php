<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Datos que pide el aviso legal (LSSI, art. 10): teléfono y datos registrales.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_issuers', function (Blueprint $table) {
            $table->string('phone', 30)->nullable()->after('email');
            $table->string('registry_details', 500)->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_issuers', function (Blueprint $table) {
            $table->dropColumn(['phone', 'registry_details']);
        });
    }
};
