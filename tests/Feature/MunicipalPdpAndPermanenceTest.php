<?php

use App\Enums\CourseStatus;
use App\Enums\MunicipalUnit;
use App\Enums\UserRole;
use App\Models\AttendanceRecord;
use App\Models\Course;
use App\Models\CourseMaterial;
use App\Models\Enrollment;
use App\Models\User;

test('course can be created with PDP SERVIR attributes, modality and budget', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $response = $this->actingAs($admin)->post(route('courses.store'), [
        'code' => 'SIGC-2026-MDSJ-01',
        'pdp_code' => 'PDP-2026-045',
        'pdp_year' => 2026,
        'pdp_category' => 'Formación Laboral y Gestión Documental',
        'modality' => 'presencial',
        'course_type' => 'interno',
        'budget_allocated' => 4500.50,
        'budget_executed' => 1200.00,
        'virtual_meeting_url' => 'https://meet.google.com/test-pdp',
        'title' => 'Gestión Documental Cero Papel para Servidores MDSJ',
        'institution' => 'Municipalidad Distrital de San Jerónimo',
        'description' => 'Curso de digitalización y gobierno electrónico.',
        'instructor_name' => 'Ing. Adrian Valer',
        'start_date' => now()->format('Y-m-d'),
        'end_date' => now()->addDays(15)->format('Y-m-d'),
        'hours' => 30,
        'total_sessions' => 4,
        'min_attendance_percentage' => 75,
        'capacity' => 50,
        'status' => CourseStatus::Abierto->value,
    ]);

    $response->assertRedirect(route('courses.index'));

    $this->assertDatabaseHas('courses', [
        'code' => 'SIGC-2026-MDSJ-01',
        'pdp_code' => 'PDP-2026-045',
        'modality' => 'presencial',
        'budget_allocated' => 4500.50,
        'budget_executed' => 1200.00,
        'virtual_meeting_url' => 'https://meet.google.com/test-pdp',
    ]);
});

test('enrollment records municipal servant organizational unit and position', function () {
    $course = Course::create([
        'code' => 'SIGC-2026-TEST-ENR',
        'title' => 'Curso Seguridad SOMA',
        'start_date' => now()->format('Y-m-d'),
        'end_date' => now()->addDays(10)->format('Y-m-d'),
        'hours' => 20,
        'total_sessions' => 4,
        'min_attendance_percentage' => 75,
        'capacity' => 20,
        'status' => CourseStatus::Abierto,
    ]);

    $response = $this->post(route('courses.enroll', $course), [
        'dni' => '71829304',
        'nombres' => 'MARCO',
        'paterno' => 'CHAVEZ',
        'materno' => 'QUISPE',
        'email' => 'mchavez@munisanjeronimo.gob.pe',
        'phone' => '984112233',
        'organizational_unit' => MunicipalUnit::OTI->value,
        'position' => 'Especialista en Redes',
        'employment_regime' => 'CAS',
    ]);

    $response->assertSessionHas('success');

    $this->assertDatabaseHas('enrollments', [
        'course_id' => $course->id,
        'dni' => '71829304',
        'organizational_unit' => MunicipalUnit::OTI->value,
        'position' => 'Especialista en Redes',
        'employment_regime' => 'CAS',
    ]);
});

test('permanence attendance registers check-in and check-out with minutes calculation', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $course = Course::create([
        'code' => 'SIGC-2026-TEST-PERM',
        'title' => 'Capacitación en Auditorio Municipal',
        'start_date' => now()->format('Y-m-d'),
        'end_date' => now()->addDays(5)->format('Y-m-d'),
        'hours' => 20,
        'total_sessions' => 4,
        'capacity' => 30,
        'status' => CourseStatus::EnCurso,
    ]);

    $enrollment = Enrollment::create([
        'course_id' => $course->id,
        'dni' => '80123456',
        'nombres' => 'CARLOS',
        'paterno' => 'CONDORI',
        'email' => 'ccondori@munisanjeronimo.gob.pe',
        'organizational_unit' => MunicipalUnit::SOMA->value,
        'position' => 'Inspector SOMA',
        'status' => 'inscrito',
    ]);

    // 1. Check-In (Entrada)
    $responseCheckIn = $this->actingAs($admin)->postJson(route('courses.attendance.permanence', $course), [
        'code' => '80123456',
        'session_number' => 1,
        'action' => 'check_in',
    ]);

    $responseCheckIn->assertOk()
        ->assertJson([
            'success' => true,
            'action_performed' => 'check_in',
            'participant' => [
                'dni' => '80123456',
                'organizational_unit' => MunicipalUnit::SOMA->value,
            ],
        ]);

    $record = AttendanceRecord::where('enrollment_id', $enrollment->id)->first();
    expect($record)->not->toBeNull();
    expect($record->check_in_at)->not->toBeNull();
    expect($record->check_out_at)->toBeNull();

    // 2. Check-Out (Salida)
    $responseCheckOut = $this->actingAs($admin)->postJson(route('courses.attendance.permanence', $course), [
        'code' => '80123456',
        'session_number' => 1,
        'action' => 'check_out',
    ]);

    $responseCheckOut->assertOk()
        ->assertJson([
            'success' => true,
            'action_performed' => 'check_out',
        ]);

    $record->refresh();
    expect($record->check_out_at)->not->toBeNull();
    expect($record->minutes_attended)->toBeGreaterThanOrEqual(0);
});

test('staff can add and delete course materials and video links', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $course = Course::create([
        'code' => 'SIGC-2026-TEST-MAT',
        'title' => 'Curso con Materiales',
        'start_date' => now()->format('Y-m-d'),
        'end_date' => now()->addDays(5)->format('Y-m-d'),
        'hours' => 20,
        'total_sessions' => 4,
        'capacity' => 30,
        'status' => CourseStatus::EnCurso,
    ]);

    // Agregar material
    $response = $this->actingAs($admin)->post(route('courses.materials.store', $course), [
        'title' => 'Directiva de Cero Papel MDSJ 2026',
        'type' => 'documento',
        'url' => 'https://munisanjeronimo.gob.pe/directiva-001.pdf',
        'description' => 'Documento oficial normativo.',
    ]);

    $response->assertSessionHas('success');

    $material = CourseMaterial::where('course_id', $course->id)->first();
    expect($material)->not->toBeNull();
    expect($material->title)->toBe('Directiva de Cero Papel MDSJ 2026');

    // Eliminar material
    $deleteResponse = $this->actingAs($admin)->delete(route('courses.materials.destroy', [
        'course' => $course->id,
        'material' => $material->id,
    ]));

    $deleteResponse->assertSessionHas('success');
    expect(CourseMaterial::where('id', $material->id)->exists())->toBeFalse();
});

test('sgd interoperability endpoint returns accredited records of public servant by DNI', function () {
    $course = Course::create([
        'code' => 'SIGC-2026-SGD',
        'pdp_code' => 'PDP-2026-001',
        'title' => 'Capacitación Acreditada SGD',
        'start_date' => now()->subDays(10)->format('Y-m-d'),
        'end_date' => now()->subDays(2)->format('Y-m-d'),
        'hours' => 40,
        'total_sessions' => 4,
        'capacity' => 30,
        'status' => CourseStatus::Concluido,
    ]);

    Enrollment::create([
        'course_id' => $course->id,
        'dni' => '12344321',
        'nombres' => 'ELENA',
        'paterno' => 'VALLE',
        'email' => 'evalle@munisanjeronimo.gob.pe',
        'organizational_unit' => MunicipalUnit::RRHH->value,
        'position' => 'Jefa de Capacitación',
        'status' => 'aprobado',
        'attended_sessions' => 4,
        'final_grade' => 19.5,
        'certificate_code' => 'CERT-2026-UNSAAC-MDSJ-001',
        'certificate_hash' => hash_hmac('sha256', 'test-data', 'test-key'),
        'certificate_issued_at' => now(),
    ]);

    $response = $this->getJson(route('api.sgd.servants', ['dni' => '12344321']));

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'institution' => 'Municipalidad Distrital de San Jerónimo',
            'dni' => '12344321',
            'student_name' => 'ELENA VALLE',
            'organizational_unit' => MunicipalUnit::RRHH->value,
            'total_accredited_hours' => 40,
            'certificates_count' => 1,
        ]);
});
