<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->unsignedInteger('total_sessions')->default(4)->after('hours');
            $table->unsignedInteger('min_attendance_percentage')->default(75)->after('total_sessions');
            $table->timestamp('acta_closed_at')->nullable()->after('status');
            $table->foreignId('acta_closed_by')->nullable()->after('acta_closed_at')->constrained('users')->nullOnDelete();
        });

        Schema::table('enrollments', function (Blueprint $table) {
            $table->string('credential_code', 50)->nullable()->index()->after('user_id');
            $table->string('certificate_hash', 64)->nullable()->after('certificate_code');
            $table->timestamp('certificate_issued_at')->nullable()->after('certificate_hash');
        });

        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('enrollment_id')->constrained('enrollments')->cascadeOnDelete();
            $table->unsignedInteger('session_number');
            $table->string('status', 20)->default('presente'); // presente, tardanza, falta
            $table->string('method', 30)->default('manual'); // qr_proyeccion, manual, offline_sync
            $table->timestamp('recorded_at')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['enrollment_id', 'session_number'], 'unique_enrollment_session');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_records');

        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropColumn(['credential_code', 'certificate_hash', 'certificate_issued_at']);
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->dropForeign(['acta_closed_by']);
            $table->dropColumn(['total_sessions', 'min_attendance_percentage', 'acta_closed_at', 'acta_closed_by']);
        });
    }
};
