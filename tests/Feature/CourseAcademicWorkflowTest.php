<?php

use App\Enums\CourseStatus;
use App\Enums\UserRole;
use App\Models\AttendanceRecord;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;

test('CP-06 session attendance records successfully and updates count', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $course = Course::create([
        'code' => 'SIGC-TEST-001',
        'title' => 'Curso de Prueba Asistencia',
        'start_date' => now()->format('Y-m-d'),
        'end_date' => now()->addDays(5)->format('Y-m-d'),
        'hours' => 20,
        'total_sessions' => 4,
        'capacity' => 20,
        'status' => CourseStatus::EnCurso,
    ]);

    $enrollment = Enrollment::create([
        'course_id' => $course->id,
        'dni' => '12345678',
        'nombres' => 'MARIO',
        'paterno' => 'VARGAS',
        'email' => 'mario@unsaac.edu.pe',
        'status' => 'inscrito',
        'attended_sessions' => 0,
    ]);

    $response = $this->actingAs($admin)->post(route('courses.sessions.attendance', [
        'course' => $course->id,
        'session' => 1,
    ]), [
        'identifier' => '12345678',
        'status' => 'presente',
        'method' => 'manual',
    ]);

    $response->assertSessionHas('success');

    expect(AttendanceRecord::where('enrollment_id', $enrollment->id)->where('session_number', 1)->exists())->toBeTrue();

    $enrollment->refresh();
    expect($enrollment->attended_sessions)->toBe(1);
    expect($enrollment->status)->toBe('en_curso');
});

test('CP-07 duplicate session attendance is rejected with error message', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $course = Course::create([
        'code' => 'SIGC-TEST-002',
        'title' => 'Curso Duplicados Asistencia',
        'start_date' => now()->format('Y-m-d'),
        'end_date' => now()->addDays(5)->format('Y-m-d'),
        'hours' => 20,
        'total_sessions' => 4,
        'capacity' => 20,
        'status' => CourseStatus::EnCurso,
    ]);

    $enrollment = Enrollment::create([
        'course_id' => $course->id,
        'dni' => '87654321',
        'nombres' => 'LUCIA',
        'paterno' => 'FLORES',
        'email' => 'lucia@unsaac.edu.pe',
        'status' => 'inscrito',
        'attended_sessions' => 0,
    ]);

    // Primer registro
    $this->actingAs($admin)->post(route('courses.sessions.attendance', [
        'course' => $course->id,
        'session' => 1,
    ]), [
        'identifier' => '87654321',
    ]);

    // Segundo registro idéntico (debe ser rechazado)
    $response = $this->actingAs($admin)->post(route('courses.sessions.attendance', [
        'course' => $course->id,
        'session' => 1,
    ]), [
        'identifier' => '87654321',
    ]);

    $response->assertSessionHas('error');
    expect(AttendanceRecord::where('enrollment_id', $enrollment->id)->where('session_number', 1)->count())->toBe(1);
});

test('CP-08 close acta applies strict UNSAAC rule (grade >= 11 and attendance >= 75%)', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $course = Course::create([
        'code' => 'SIGC-TEST-003',
        'title' => 'Curso Calificaciones y Acta',
        'start_date' => now()->format('Y-m-d'),
        'end_date' => now()->addDays(5)->format('Y-m-d'),
        'hours' => 40,
        'total_sessions' => 4,
        'min_attendance_percentage' => 75,
        'capacity' => 20,
        'status' => CourseStatus::EnCurso,
    ]);

    // Alumno 1: Cumple ambos requisitos (3 de 4 sesiones = 75%, nota 16) -> Aprobado
    $alumnoAprobado = Enrollment::create([
        'course_id' => $course->id,
        'dni' => '11112222',
        'nombres' => 'CARLOS',
        'paterno' => 'MAMANI',
        'email' => 'carlos@unsaac.edu.pe',
        'attended_sessions' => 3,
        'final_grade' => 16.0,
        'status' => 'en_curso',
    ]);

    // Alumno 2: Buena nota (18) pero baja asistencia (1 de 4 = 25%) -> Reprobado
    $alumnoFaltaAsistencia = Enrollment::create([
        'course_id' => $course->id,
        'dni' => '33334444',
        'nombres' => 'ANA',
        'paterno' => 'QUISPE',
        'email' => 'ana@unsaac.edu.pe',
        'attended_sessions' => 1,
        'final_grade' => 18.0,
        'status' => 'en_curso',
    ]);

    // Alumno 3: Asistencia perfecta (4 de 4 = 100%) pero nota baja (9.0) -> Reprobado
    $alumnoJalado = Enrollment::create([
        'course_id' => $course->id,
        'dni' => '55556666',
        'nombres' => 'PEDRO',
        'paterno' => 'HUAMAN',
        'email' => 'pedro@unsaac.edu.pe',
        'attended_sessions' => 4,
        'final_grade' => 9.0,
        'status' => 'en_curso',
    ]);

    $response = $this->actingAs($admin)->post(route('courses.acta.close', $course->id));
    $response->assertSessionHas('success');

    $course->refresh();
    expect($course->isActaClosed())->toBeTrue();

    $alumnoAprobado->refresh();
    $alumnoFaltaAsistencia->refresh();
    $alumnoJalado->refresh();

    expect($alumnoAprobado->status)->toBe('aprobado');
    expect($alumnoFaltaAsistencia->status)->toBe('reprobado');
    expect($alumnoJalado->status)->toBe('reprobado');
});

test('CP-09 bulk certificate generation assigns codes and SHA-256 digital hashes', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $course = Course::create([
        'code' => 'SIGC-TEST-004',
        'title' => 'Curso de Certificación Masiva',
        'start_date' => now()->format('Y-m-d'),
        'end_date' => now()->addDays(5)->format('Y-m-d'),
        'hours' => 30,
        'total_sessions' => 4,
        'capacity' => 20,
        'status' => CourseStatus::Concluido,
        'acta_closed_at' => now(),
        'acta_closed_by' => $admin->id,
    ]);

    $aprobado = Enrollment::create([
        'course_id' => $course->id,
        'dni' => '77778888',
        'nombres' => 'ROSA',
        'paterno' => 'CONDORI',
        'email' => 'rosa@unsaac.edu.pe',
        'attended_sessions' => 4,
        'final_grade' => 19.5,
        'status' => 'aprobado',
    ]);

    $response = $this->actingAs($admin)->post(route('courses.certificates.bulk-issue', $course->id));
    $response->assertSessionHas('success');

    $aprobado->refresh();
    expect($aprobado->certificate_code)->not->toBeEmpty();
    expect($aprobado->certificate_hash)->not->toBeEmpty();
    expect(strlen($aprobado->certificate_hash))->toBe(64); // SHA-256 hex string length
    expect($aprobado->certificate_issued_at)->not->toBeNull();
});

test('CP-10 offline attendance sync processes multiple batch records', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $course = Course::create([
        'code' => 'SIGC-TEST-005',
        'title' => 'Curso Sincronización Offline',
        'start_date' => now()->format('Y-m-d'),
        'end_date' => now()->addDays(5)->format('Y-m-d'),
        'hours' => 20,
        'total_sessions' => 4,
        'capacity' => 20,
        'status' => CourseStatus::EnCurso,
    ]);

    $e1 = Enrollment::create([
        'course_id' => $course->id,
        'dni' => '10101010',
        'nombres' => 'MARCOS',
        'paterno' => 'SOLIS',
        'email' => 'marcos@unsaac.edu.pe',
        'status' => 'inscrito',
        'attended_sessions' => 0,
    ]);

    $e2 = Enrollment::create([
        'course_id' => $course->id,
        'dni' => '20202020',
        'nombres' => 'ELENA',
        'paterno' => 'TORRES',
        'email' => 'elena@unsaac.edu.pe',
        'status' => 'inscrito',
        'attended_sessions' => 0,
    ]);

    $response = $this->actingAs($admin)->postJson(route('courses.attendance.sync', $course->id), [
        'items' => [
            [
                'identifier' => '10101010',
                'session_number' => 1,
                'status' => 'presente',
                'recorded_at' => now()->toIso8601String(),
            ],
            [
                'identifier' => '20202020',
                'session_number' => 1,
                'status' => 'tardanza',
                'recorded_at' => now()->toIso8601String(),
            ],
        ],
    ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'synced' => 2,
            'skipped' => 0,
        ]);

    expect(AttendanceRecord::where('course_id', $course->id)->count())->toBe(2);
});

test('CP-11 certificate lookup API returns enriched official payload with qr and modules', function () {
    $course = Course::create([
        'code' => 'SIGC-TEST-006',
        'title' => 'Desarrollo Web Moderno con Laravel 13 e Inertia Vue',
        'start_date' => now()->format('Y-m-d'),
        'end_date' => now()->addDays(5)->format('Y-m-d'),
        'hours' => 40,
        'total_sessions' => 4,
        'capacity' => 20,
        'status' => CourseStatus::Concluido,
    ]);

    Enrollment::create([
        'course_id' => $course->id,
        'dni' => '99887766',
        'nombres' => 'CARLOS',
        'paterno' => 'MAMANI',
        'email' => 'carlos@unsaac.edu.pe',
        'status' => 'aprobado',
        'final_grade' => 20.0,
        'certificate_code' => 'CERT-2026-UNSAAC-006-9988',
        'certificate_hash' => hash('sha256', 'test-hash-payload'),
        'certificate_issued_at' => now(),
    ]);

    $response = $this->getJson('/api/certificates/lookup?dni=99887766');

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'dni' => '99887766',
        ]);

    $data = $response->json('records.0');
    expect($data)->not->toBeNull();
    expect($data['dni'])->toBe('99887766');
    expect($data['student_name'])->toBe('CARLOS MAMANI');
    expect($data['final_grade'])->toBe('20.00');
    expect($data['final_grade_text'])->toContain('Veinte');
    expect($data['verification_url'])->toContain('99887766');
    expect($data['qr_svg'])->toContain('<svg');
    expect($data['modules'])->toBeArray()->and(count($data['modules']))->toBeGreaterThanOrEqual(1);
});

test('CP-12 permanent verification route redirects to certificate page with dni and code', function () {
    $course = Course::create([
        'code' => 'SIGC-TEST-007',
        'title' => 'Ciberseguridad y Ethical Hacking',
        'start_date' => now()->format('Y-m-d'),
        'end_date' => now()->addDays(5)->format('Y-m-d'),
        'hours' => 30,
        'total_sessions' => 4,
        'capacity' => 20,
        'status' => CourseStatus::Concluido,
    ]);

    Enrollment::create([
        'course_id' => $course->id,
        'dni' => '33445566',
        'nombres' => 'ANA',
        'paterno' => 'QUISPE',
        'email' => 'ana@unsaac.edu.pe',
        'status' => 'aprobado',
        'certificate_code' => 'CERT-2026-UNSAAC-007-3344',
    ]);

    $response = $this->get(route('certificates.verify', 'CERT-2026-UNSAAC-007-3344'));
    $response->assertRedirect(route('certificates.index', [
        'dni' => '33445566',
        'code' => 'CERT-2026-UNSAAC-007-3344',
    ]));
});
