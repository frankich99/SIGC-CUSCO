<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SC-51 (HU-11.3): identificador único que genera el kiosco al escanear sin
 * conexión. Permite reenviar el mismo lote de marcaciones sin duplicarlas
 * (ver docs/adr/0001-modo-offline-kiosco.md).
 *
 * Es nulo para las marcaciones registradas en línea.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_marks', function (Blueprint $table) {
            $table->uuid('client_uuid')->nullable()->unique()->after('recorded_by');
        });

        Schema::table('attendance_unenrolled_scans', function (Blueprint $table) {
            $table->uuid('client_uuid')->nullable()->unique()->after('recorded_by');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_unenrolled_scans', function (Blueprint $table) {
            $table->dropUnique(['client_uuid']);
            $table->dropColumn('client_uuid');
        });

        Schema::table('attendance_marks', function (Blueprint $table) {
            $table->dropUnique(['client_uuid']);
            $table->dropColumn('client_uuid');
        });
    }
};
