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
            $table->string('pdp_code', 50)->nullable()->index()->after('code');
            $table->unsignedSmallInteger('pdp_year')->default(2026)->after('pdp_code');
            $table->string('pdp_category', 100)->default('Desarrollo de Competencias')->after('pdp_year');
            $table->string('modality', 30)->default('presencial')->after('pdp_category'); // presencial, virtual, hibrido
            $table->string('course_type', 30)->default('interno')->after('modality'); // interno, externo
            $table->decimal('budget_allocated', 10, 2)->default(0.00)->after('capacity');
            $table->decimal('budget_executed', 10, 2)->default(0.00)->after('budget_allocated');
            $table->string('virtual_meeting_url', 500)->nullable()->after('budget_executed');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('organizational_unit', 100)->nullable()->index()->after('role'); // OTI, SOMA, Recursos Humanos, etc.
            $table->string('position', 120)->nullable()->after('organizational_unit'); // Cargo del servidor
            $table->string('employment_regime', 50)->nullable()->after('position'); // CAS, 276, 728, Locación
        });

        Schema::table('enrollments', function (Blueprint $table) {
            $table->string('organizational_unit', 100)->nullable()->index()->after('phone');
            $table->string('position', 120)->nullable()->after('organizational_unit');
            $table->string('employment_regime', 50)->nullable()->after('position');
        });

        Schema::table('attendance_records', function (Blueprint $table) {
            $table->timestamp('check_in_at')->nullable()->after('method');
            $table->timestamp('check_out_at')->nullable()->after('check_in_at');
            $table->unsignedInteger('minutes_attended')->default(0)->after('check_out_at');
        });

        Schema::create('course_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->string('title', 150);
            $table->string('type', 40)->default('documento'); // documento, video, diapositiva, enlace
            $table->text('url');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_materials');

        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropColumn(['check_in_at', 'check_out_at', 'minutes_attended']);
        });

        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropColumn(['organizational_unit', 'position', 'employment_regime']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['organizational_unit', 'position', 'employment_regime']);
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn([
                'pdp_code',
                'pdp_year',
                'pdp_category',
                'modality',
                'course_type',
                'budget_allocated',
                'budget_executed',
                'virtual_meeting_url',
            ]);
        });
    }
};
