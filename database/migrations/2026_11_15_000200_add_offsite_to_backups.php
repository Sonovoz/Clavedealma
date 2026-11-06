<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Copia fuera del servidor (Object Storage de OVH), además de la del servidor. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('backups', function (Blueprint $table): void {
            $table->string('offsite_disk', 40)->nullable()->after('path');
            $table->string('offsite_path')->nullable()->after('offsite_disk');
            $table->timestamp('offsite_at')->nullable()->after('offsite_path');
            $table->string('offsite_error', 1000)->nullable()->after('offsite_at');
        });
    }

    public function down(): void
    {
        Schema::table('backups', function (Blueprint $table): void {
            $table->dropColumn(['offsite_disk', 'offsite_path', 'offsite_at', 'offsite_error']);
        });
    }
};
