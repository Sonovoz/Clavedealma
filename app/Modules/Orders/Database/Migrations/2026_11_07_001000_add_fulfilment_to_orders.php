<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Fabricación y envío: fechas de cada paso y datos del seguimiento (ADR 0015).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('production_started_at')->nullable()->after('paid_at');
            $table->timestamp('ready_at')->nullable()->after('production_started_at');
            $table->timestamp('shipped_at')->nullable()->after('ready_at');
            $table->timestamp('delivered_at')->nullable()->after('shipped_at');
            $table->string('tracking_carrier', 120)->nullable()->after('delivered_at');
            $table->string('tracking_number', 120)->nullable()->after('tracking_carrier');
            $table->string('tracking_url', 500)->nullable()->after('tracking_number');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['production_started_at', 'ready_at', 'shipped_at', 'delivered_at', 'tracking_carrier', 'tracking_number', 'tracking_url']);
        });
    }
};
