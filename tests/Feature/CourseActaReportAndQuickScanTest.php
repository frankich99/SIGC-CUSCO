<?php

use App\Enums\CourseStatus;
use App\Enums\UserRole;
use App\Models\AttendanceRecord;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function makeReportCourse(?User $instructor = null): Course
{
    return Course::create([
        'code' => 'SIGC-REP-001',
        'title' => 'Taller de Especialización SIGC',
        'start_date' => now()->format('Y-m-d'),
        'end_date' => now()->addDays(5)->format('Y-m-d'),
        'hours' => 30,
        'total_sessions' => 3,
        'capacity' => 25,
        'status' => CourseStatus::EnCurso,
        'instructor_id' => $instructor?->id,
    ]);
}

test('docente asignado puede visualizar el acta oficial institucional', function () {
    $docente = User::factory()->create(['role' => UserRole::Docente]);
    $course = makeReportCourse($docente);

    Enrollment::create([
        'course_id' => $course->id,
        'dni' => '11223344',
        'nombres' => 'CARLOS',
        'paterno' => 'CONDORI',
        'email' => 'carlos.condori@unsaac.edu.pe',
        'status' => 'aprobado',
        'attended_sessions' => 3,
        'final_grade' => 18.0,
    ]);

    $response = $this->actingAs($docente)->get(route('courses.reports.acta', $course));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('courses/reports/ActaOfficial')
        ->has('course')
        ->has('stats')
        ->has('participants', 1)
        ->where('course.code', 'SIGC-REP-001')
        ->where('stats.total_enrolled', 1)
        ->where('stats.approved_count', 1)
    );
});

test('administrador puede visualizar el acta oficial de cualquier curso', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $course = makeReportCourse();

    $response = $this->actingAs($admin)->get(route('courses.reports.acta', $course));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page->component('courses/reports/ActaOfficial'));
});

test('docente no asignado no tiene permiso para ver el acta oficial', function () {
    $docenteA = User::factory()->create(['role' => UserRole::Docente]);
    $docenteB = User::factory()->create(['role' => UserRole::Docente]);
    $course = makeReportCourse($docenteA);

    $response = $this->actingAs($docenteB)->get(route('courses.reports.acta', $course));

    $response->assertForbidden();
});

test('participante no tiene permiso para ver el acta oficial', function () {
    $participante = User::factory()->create(['role' => UserRole::Participante]);
    $course = makeReportCourse();

    $response = $this->actingAs($participante)->get(route('courses.reports.acta', $course));

    $response->assertForbidden();
});

test('usuario invitado es redirigido al login al solicitar el acta oficial', function () {
    $course = makeReportCourse();

    $response = $this->get(route('courses.reports.acta', $course));

    $response->assertRedirect(route('login'));
});

test('staff puede registrar asistencia rápida utilizando el DNI del participante', function () {
    $docente = User::factory()->create(['role' => UserRole::Docente]);
    $course = makeReportCourse($docente);

    $enrollment = Enrollment::create([
        'course_id' => $course->id,
        'dni' => '44556677',
        'nombres' => 'ROSA',
        'paterno' => 'QUISPE',
        'email' => 'rosa.quispe@unsaac.edu.pe',
        'status' => 'inscrito',
        'attended_sessions' => 0,
    ]);

    $response = $this->actingAs($docente)->postJson(route('courses.attendance.by-credential', $course), [
        'code' => '44556677',
        'session_number' => 1,
        'status' => 'presente',
    ]);

    $response->assertOk();
    $response->assertJson([
        'success' => true,
        'participant' => [
            'id' => $enrollment->id,
            'dni' => '44556677',
            'attended_sessions' => 1,
        ],
    ]);

    expect(AttendanceRecord::where('enrollment_id', $enrollment->id)->where('session_number', 1)->exists())->toBeTrue();
});

test('staff puede registrar asistencia rápida utilizando el código de credencial', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $course = makeReportCourse();

    $enrollment = Enrollment::create([
        'course_id' => $course->id,
        'credential_code' => 'CRED-UNSAAC-8899',
        'dni' => '77889900',
        'nombres' => 'JAVIER',
        'paterno' => 'HERRERA',
        'email' => 'javier.herrera@unsaac.edu.pe',
        'status' => 'inscrito',
        'attended_sessions' => 0,
    ]);

    $response = $this->actingAs($admin)->postJson(route('courses.attendance.by-credential', $course), [
        'code' => 'CRED-UNSAAC-8899',
        'session_number' => 2,
        'status' => 'presente',
    ]);

    $response->assertOk();
    $response->assertJson([
        'success' => true,
        'participant' => [
            'id' => $enrollment->id,
            'dni' => '77889900',
            'attended_sessions' => 1,
        ],
    ]);
});

test('registro rápido rechaza código o DNI no encontrado con 404', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $course = makeReportCourse();

    $response = $this->actingAs($admin)->postJson(route('courses.attendance.by-credential', $course), [
        'code' => 'INEXISTENTE-999',
        'session_number' => 1,
    ]);

    $response->assertStatus(404);
    $response->assertJson(['success' => false]);
});

test('registro rápido rechaza marcar asistencia cuando el acta oficial está cerrada con 422', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $course = Course::create([
        'code' => 'SIGC-REP-CLOSED',
        'title' => 'Curso Cerrado',
        'start_date' => now()->format('Y-m-d'),
        'end_date' => now()->addDays(5)->format('Y-m-d'),
        'hours' => 20,
        'total_sessions' => 2,
        'capacity' => 20,
        'status' => CourseStatus::Concluido,
        'acta_closed_at' => now(),
        'acta_closed_by' => $admin->id,
    ]);

    Enrollment::create([
        'course_id' => $course->id,
        'dni' => '99887766',
        'nombres' => 'ELENA',
        'paterno' => 'TAIPE',
        'email' => 'elena.taipe@unsaac.edu.pe',
        'status' => 'aprobado',
        'attended_sessions' => 2,
    ]);

    $response = $this->actingAs($admin)->postJson(route('courses.attendance.by-credential', $course), [
        'code' => '99887766',
        'session_number' => 1,
    ]);

    $response->assertStatus(422);
    $response->assertJson([
        'success' => false,
        'message' => 'No se puede registrar asistencia porque el acta oficial del curso está cerrada.',
    ]);
});

test('registro rápido valida que el número de sesión esté dentro del rango del curso', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $course = makeReportCourse();

    $response = $this->actingAs($admin)->postJson(route('courses.attendance.by-credential', $course), [
        'code' => '12345678',
        'session_number' => 99,
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['session_number']);
});

test('participante no puede invocar el endpoint de asistencia por credencial', function () {
    $participante = User::factory()->create(['role' => UserRole::Participante]);
    $course = makeReportCourse();

    $response = $this->actingAs($participante)->postJson(route('courses.attendance.by-credential', $course), [
        'code' => '12345678',
        'session_number' => 1,
    ]);

    $response->assertForbidden();
});
