<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ajustes generales de la plataforma que se cambian desde el panel.
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 80)->primary();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        // Registro de copias de seguridad (el archivo vive en el disco de copias).
        Schema::create('backups', function (Blueprint $table) {
            $table->id();
            $table->string('disk', 40);
            $table->string('path')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('status', 20)->index(); // running | ok | failed
            $table->string('trigger', 20); // manual | scheduled
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('message')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backups');
        Schema::dropIfExists('settings');
    }
};
