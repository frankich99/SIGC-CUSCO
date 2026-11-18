<?php

use App\Enums\CourseStatus;
use App\Enums\UserRole;
use App\Models\AttendanceMark;
use App\Models\AttendanceRecord;
use App\Models\AttendanceUnenrolledScan;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * SC-39 (HU-11.3): sincronizar las marcaciones guardadas sin conexión, sin duplicados.
 * SC-52: endpoint por lote · SC-54: pruebas.
 *
 * Curso de 10 horas en 5 sesiones → 120 minutos por sesión; 80 % = 96 minutos.
 */
function syncCourse(User $instructor, array $overrides = []): Course
{
    return Course::create(array_merge([
        'code' => 'SIGC-SYNC-'.fake()->unique()->numerify('###'),
        'title' => 'Capacitación en Gestión Pública MDSJ',
        'start_date' => now()->format('Y-m-d'),
        'end_date' => now()->addDays(10)->format('Y-m-d'),
        'hours' => 10,
        'total_sessions' => 5,
        'capacity' => 40,
        'status' => CourseStatus::EnCurso,
        'instructor_id' => $instructor->id,
    ], $overrides));
}

function syncEnroll(Course $course, string $dni): Enrollment
{
    return Enrollment::create([
        'course_id' => $course->id,
        'dni' => $dni,
        'nombres' => 'ROSA',
        'paterno' => 'HUAMAN',
        'materno' => 'QUISPE',
        'email' => "{$dni}@munisanjeronimo.gob.pe",
        'status' => 'inscrito',
        'attended_sessions' => 0,
    ]);
}

/**
 * @return array{client_uuid: string, identifier: string, type: string, origin: string, captured_at: string}
 */
function offlineMark(string $dni, string $type, string $capturedAt, ?string $uuid = null): array
{
    return [
        'client_uuid' => $uuid ?? (string) Str::uuid(),
        'identifier' => $dni,
        'type' => $type,
        'origin' => 'dni_lector',
        // Como lo envía el navegador: ISO 8601 en UTC.
        'captured_at' => Carbon::parse($capturedAt, 'America/Lima')->utc()->toIso8601ZuluString(),
    ];
}

function syncMarks($test, User $user, Course $course, int $session, array $marks)
{
    return $test->actingAs($user)->postJson(
        route('courses.sessions.marks.sync', [$course, $session]),
        ['marks' => $marks],
    );
}

beforeEach(function () {
    $this->docente = User::factory()->create(['role' => UserRole::Docente]);
    $this->course = syncCourse($this->docente);
    Carbon::setTestNow(Carbon::parse('2026-11-16 11:00:00', 'America/Lima'));
});

afterEach(function () {
    Carbon::setTestNow();
});

test('registra ingreso y salida con la hora del dispositivo y calcula la permanencia', function () {
    $enrollment = syncEnroll($this->course, '45678912');
    $entry = offlineMark('45678912', 'entrada', '2026-11-16 09:00:00');
    $exit = offlineMark('45678912', 'salida', '2026-11-16 10:50:00');

    syncMarks($this, $this->docente, $this->course, 1, [$entry, $exit])
        ->assertOk()
        ->assertJsonCount(2, 'results')
        ->assertJsonPath('results.0.client_uuid', $entry['client_uuid'])
        ->assertJsonPath('results.0.result', 'ingreso_registrado')
        ->assertJsonPath('results.1.client_uuid', $exit['client_uuid'])
        ->assertJsonPath('results.1.result', 'asistida')
        ->assertJsonPath('results.1.permanence.minutes', 110);

    $marks = AttendanceMark::query()->orderBy('marked_at')->get();
    expect($marks)->toHaveCount(2)
        ->and($marks[0]->client_uuid)->toBe($entry['client_uuid'])
        ->and($marks[0]->marked_at->equalTo(Carbon::parse('2026-11-16 09:00:00', 'America/Lima')))->toBeTrue()
        ->and($marks[1]->marked_at->equalTo(Carbon::parse('2026-11-16 10:50:00', 'America/Lima')))->toBeTrue();

    expect(AttendanceRecord::query()->where('enrollment_id', $enrollment->id)->value('status'))->toBe('presente');
});

test('reenviar el mismo lote no duplica las marcaciones', function () {
    syncEnroll($this->course, '45678912');
    $batch = [
        offlineMark('45678912', 'entrada', '2026-11-16 09:00:00'),
        offlineMark('45678912', 'salida', '2026-11-16 10:50:00'),
    ];

    syncMarks($this, $this->docente, $this->course, 1, $batch)->assertOk();

    syncMarks($this, $this->docente, $this->course, 1, $batch)
        ->assertOk()
        ->assertJsonPath('results.0.result', 'duplicada')
        ->assertJsonPath('results.0.message', 'Marcación ya sincronizada.')
        ->assertJsonPath('results.0.participant.dni', '45678912')
        ->assertJsonPath('results.1.result', 'duplicada');

    expect(AttendanceMark::count())->toBe(2)
        ->and(AttendanceRecord::count())->toBe(1);
});

test('procesa en orden cronológico aunque el lote llegue desordenado', function () {
    syncEnroll($this->course, '45678912');
    $exit = offlineMark('45678912', 'salida', '2026-11-16 10:50:00');
    $entry = offlineMark('45678912', 'entrada', '2026-11-16 09:00:00');

    syncMarks($this, $this->docente, $this->course, 1, [$exit, $entry])
        ->assertOk()
        ->assertJsonPath('results.0.client_uuid', $entry['client_uuid'])
        ->assertJsonPath('results.0.result', 'ingreso_registrado')
        ->assertJsonPath('results.1.client_uuid', $exit['client_uuid'])
        ->assertJsonPath('results.1.result', 'asistida');
});

test('un DNI no inscrito queda una sola vez en «No inscritos» aunque se reenvíe', function () {
    $mark = offlineMark('87654321', 'entrada', '2026-11-16 09:05:00');

    syncMarks($this, $this->docente, $this->course, 1, [$mark])
        ->assertOk()
        ->assertJsonPath('results.0.result', 'no_inscrito');

    syncMarks($this, $this->docente, $this->course, 1, [$mark])
        ->assertOk()
        ->assertJsonPath('results.0.result', 'no_inscrito');

    $scan = AttendanceUnenrolledScan::sole();
    expect($scan->client_uuid)->toBe($mark['client_uuid'])
        ->and($scan->scanned_at->equalTo(Carbon::parse('2026-11-16 09:05:00', 'America/Lima')))->toBeTrue();
});

test('una marcación que ya se hizo en línea vuelve como duplicada', function () {
    syncEnroll($this->course, '45678912');

    $this->actingAs($this->docente)
        ->postJson(route('courses.sessions.marks', [$this->course, 1]), [
            'identifier' => '45678912',
            'type' => 'entrada',
        ])
        ->assertCreated();

    syncMarks($this, $this->docente, $this->course, 1, [offlineMark('45678912', 'entrada', '2026-11-16 09:30:00')])
        ->assertOk()
        ->assertJsonPath('results.0.result', 'duplicada');

    expect(AttendanceMark::count())->toBe(1);
});

test('rechaza horas del dispositivo en el futuro o de hace más de 24 horas', function () {
    syncEnroll($this->course, '45678912');
    $future = offlineMark('45678912', 'entrada', '2026-11-16 11:05:00');
    $old = offlineMark('45678912', 'entrada', '2026-11-15 10:00:00');
    $withinTolerance = offlineMark('45678912', 'entrada', '2026-11-16 11:01:30');

    syncMarks($this, $this->docente, $this->course, 1, [$future, $old])
        ->assertOk()
        ->assertJsonPath('results.0.result', 'hora_invalida')
        ->assertJsonPath('results.1.result', 'hora_invalida');

    expect(AttendanceMark::count())->toBe(0);

    // Un desfase de reloj de hasta 2 minutos se acepta.
    syncMarks($this, $this->docente, $this->course, 1, [$withinTolerance])
        ->assertOk()
        ->assertJsonPath('results.0.result', 'ingreso_registrado');
});

test('una lectura sin DNI válido se rechaza sin registrar nada', function () {
    syncMarks($this, $this->docente, $this->course, 1, [offlineMark('1234', 'entrada', '2026-11-16 09:00:00')])
        ->assertOk()
        ->assertJsonPath('results.0.result', 'dni_invalido');

    expect(AttendanceMark::count())->toBe(0)
        ->and(AttendanceUnenrolledScan::count())->toBe(0);
});

test('valida el lote', function () {
    $mark = offlineMark('45678912', 'entrada', '2026-11-16 09:00:00');

    syncMarks($this, $this->docente, $this->course, 1, [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('marks');

    syncMarks($this, $this->docente, $this->course, 1, [$mark, $mark])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('marks.1.client_uuid');

    syncMarks($this, $this->docente, $this->course, 1, [array_merge($mark, ['client_uuid' => 'no-es-uuid'])])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('marks.0.client_uuid');

    syncMarks($this, $this->docente, $this->course, 1, [array_merge($mark, ['type' => 'otro'])])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('marks.0.type');

    $tooMany = array_map(
        fn () => offlineMark('45678912', 'entrada', '2026-11-16 09:00:00'),
        range(1, 51),
    );
    syncMarks($this, $this->docente, $this->course, 1, $tooMany)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('marks');
});

test('no sincroniza con el acta cerrada ni en una sesión inexistente', function () {
    syncEnroll($this->course, '45678912');
    $mark = offlineMark('45678912', 'entrada', '2026-11-16 09:00:00');

    syncMarks($this, $this->docente, $this->course, 9, [$mark])->assertUnprocessable();

    $this->course->update(['acta_closed_at' => now()]);
    syncMarks($this, $this->docente, $this->course, 1, [$mark])->assertUnprocessable();

    expect(AttendanceMark::count())->toBe(0);
});

test('solo quien gestiona la asistencia del curso puede sincronizar', function () {
    $mark = offlineMark('45678912', 'entrada', '2026-11-16 09:00:00');
    $otroDocente = User::factory()->create(['role' => UserRole::Docente]);
    $participante = User::factory()->create(['role' => UserRole::Participante]);

    syncMarks($this, $otroDocente, $this->course, 1, [$mark])->assertForbidden();
    syncMarks($this, $participante, $this->course, 1, [$mark])->assertForbidden();
});
