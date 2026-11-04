<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Registro de seguridad (ADR 0019): solo se añaden filas; se purgan pasados los días
| de clavedealma.security.retention_days (guarda IPs: dato personal, RGPD).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_events', function (Blueprint $table) {
            $table->id();
            $table->string('type', 40)->index();
            $table->string('level', 10)->default('info');
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('user_email')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->string('subject', 120)->nullable();
            $table->string('description', 500);
            $table->json('context')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_events');
    }
};
