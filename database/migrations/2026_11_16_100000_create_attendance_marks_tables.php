<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SC-32 (HU-03.2): control de permanencia con marcas de ingreso y salida.
 *
 * Por la Ley N.° 29733 solo se guardan DNI (vía la matrícula), sesión, tipo de
 * marca, origen y hora. No se almacenan fotografías ni geolocalización.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_marks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('enrollment_id')->constrained('enrollments')->cascadeOnDelete();
            $table->unsignedInteger('session_number');
            $table->string('type', 10); // entrada, salida
            $table->string('origin', 20); // dni_lector, qr, manual
            $table->timestamp('marked_at');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['enrollment_id', 'session_number', 'type'], 'unique_enrollment_session_mark');
            $table->index(['course_id', 'session_number']);
        });

        Schema::create('attendance_unenrolled_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->unsignedInteger('session_number');
            $table->string('dni', 20);
            $table->string('type', 10);
            $table->string('reason', 255);
            $table->timestamp('scanned_at');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['course_id', 'session_number']);
        });

        Schema::table('attendance_records', function (Blueprint $table) {
            $table->unsignedInteger('minutes_present')->nullable()->after('method');
            $table->string('justification', 500)->nullable()->after('recorded_by');
            $table->foreignId('justified_by')->nullable()->after('justification')->constrained('users')->nullOnDelete();
            $table->timestamp('justified_at')->nullable()->after('justified_by');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropForeign(['justified_by']);
            $table->dropColumn(['minutes_present', 'justification', 'justified_by', 'justified_at']);
        });

        Schema::dropIfExists('attendance_unenrolled_scans');
        Schema::dropIfExists('attendance_marks');
    }
};
