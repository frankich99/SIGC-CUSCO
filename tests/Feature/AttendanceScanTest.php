<?php

use App\Enums\CourseStatus;
use App\Enums\UserRole;
use App\Models\AttendanceRecord;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Support\Facades\URL;

function makeSignedAttendanceUrl(Course $course, int $session): string
{
    return URL::temporarySignedRoute('attendance.scan', now()->addMinutes(5), [
        'course' => $course,
        'session' => $session,
    ]);
}

function makeScanCourse(User $user): Course
{
    return Course::create([
        'code' => 'SIGC-SCAN-001',
        'title' => 'Curso de Asistencia QR',
        'start_date' => now()->format('Y-m-d'),
        'end_date' => now()->addDays(5)->format('Y-m-d'),
        'hours' => 20,
        'total_sessions' => 4,
        'capacity' => 20,
        'status' => CourseStatus::EnCurso,
        'instructor_id' => $user->id,
    ]);
}

test('muestra la pagina de confirmacion para un participante inscrito', function () {
    $user = User::factory()->create([
        'dni' => '55123456',
        'role' => UserRole::Participante,
    ]);

    $course = makeScanCourse(User::factory()->create(['role' => UserRole::Docente]));

    Enrollment::create([
        'course_id' => $course->id,
        'user_id' => $user->id,
        'dni' => '55123456',
        'nombres' => 'LUZ',
        'paterno' => 'MAMANI',
        'email' => 'luz@unsaac.edu.pe',
        'status' => 'inscrito',
        'attended_sessions' => 0,
    ]);

    $response = $this->actingAs($user)->get(makeSignedAttendanceUrl($course, 1));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('attendance/Scan'));
});

test('registra asistencia desde el enlace firmado', function () {
    $user = User::factory()->create([
        'dni' => '55123457',
        'role' => UserRole::Participante,
    ]);

    $course = makeScanCourse(User::factory()->create(['role' => UserRole::Docente]));

    $enrollment = Enrollment::create([
        'course_id' => $course->id,
        'user_id' => $user->id,
        'dni' => '55123457',
        'nombres' => 'MARIO',
        'paterno' => 'QUISPE',
        'email' => 'mario.q@unsaac.edu.pe',
        'status' => 'inscrito',
        'attended_sessions' => 0,
    ]);

    $response = $this->actingAs($user)->post(makeSignedAttendanceUrl($course, 1));

    $response->assertSessionHas('success');
    $enrollment->refresh();
    expect($enrollment->attended_sessions)->toBe(1);
    expect(AttendanceRecord::where('enrollment_id', $enrollment->id)->where('session_number', 1)->exists())->toBeTrue();
});

test('rechaza el enlace firmado para un usuario sin matricula en el curso', function () {
    $user = User::factory()->create([
        'dni' => '55123458',
        'role' => UserRole::Participante,
    ]);

    $course = makeScanCourse(User::factory()->create(['role' => UserRole::Docente]));

    $response = $this->actingAs($user)->get(makeSignedAttendanceUrl($course, 1));

    $response->assertForbidden();
});

test('rechaza un enlace no firmado', function () {
    $user = User::factory()->create([
        'dni' => '55123459',
        'role' => UserRole::Participante,
    ]);

    $course = makeScanCourse(User::factory()->create(['role' => UserRole::Docente]));

    $response = $this->actingAs($user)->get(route('attendance.scan', [
        'course' => $course->id,
        'session' => 1,
    ]));

    $response->assertForbidden();
});
