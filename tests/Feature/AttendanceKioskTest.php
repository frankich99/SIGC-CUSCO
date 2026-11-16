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
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * SC-32 (HU-03.2): ingreso y salida con lector de DNI para controlar la permanencia.
 * SC-33 (HU-03.3): evitar marcaciones duplicadas y ver la lista de la sesión.
 *
 * Curso de 10 horas en 5 sesiones → 120 minutos por sesión; 80 % = 96 minutos.
 */
function kioskCourse(User $instructor, array $overrides = []): Course
{
    return Course::create(array_merge([
        'code' => 'SIGC-KIOSK-'.fake()->unique()->numerify('###'),
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

function kioskEnroll(Course $course, string $dni, array $overrides = []): Enrollment
{
    return Enrollment::create(array_merge([
        'course_id' => $course->id,
        'dni' => $dni,
        'nombres' => 'ROSA',
        'paterno' => 'HUAMAN',
        'materno' => 'QUISPE',
        'email' => "{$dni}@munisanjeronimo.gob.pe",
        'status' => 'inscrito',
        'attended_sessions' => 0,
    ], $overrides));
}

function kioskMark($test, User $user, Course $course, int $session, string $identifier, string $type, ?string $origin = null)
{
    return $test->actingAs($user)->postJson(route('courses.sessions.marks', [$course, $session]), array_filter([
        'identifier' => $identifier,
        'type' => $type,
        'origin' => $origin,
    ]));
}

beforeEach(function () {
    $this->docente = User::factory()->create(['role' => UserRole::Docente]);
    $this->course = kioskCourse($this->docente);
    Carbon::setTestNow(Carbon::parse('2026-11-16 09:00:00', 'America/Lima'));
});

afterEach(function () {
    Carbon::setTestNow();
});

test('CA1: registra el ingreso con el DNI leído y muestra el nombre del servidor', function () {
    $enrollment = kioskEnroll($this->course, '45678912');

    $response = kioskMark($this, $this->docente, $this->course, 1, '45678912', 'entrada');

    $response->assertCreated()
        ->assertJsonPath('result', 'ingreso_registrado')
        ->assertJsonPath('participant.full_name', 'ROSA HUAMAN QUISPE')
        ->assertJsonPath('participant.dni', '45678912');

    $mark = AttendanceMark::sole();
    expect($mark->enrollment_id)->toBe($enrollment->id)
        ->and($mark->type)->toBe('entrada')
        ->and($mark->origin)->toBe('dni_lector')
        ->and($mark->marked_at->equalTo(now()))->toBeTrue();
});

test('CA1: extrae el DNI aunque el lector envíe caracteres extra o el dígito verificador', function () {
    kioskEnroll($this->course, '45678912');

    kioskMark($this, $this->docente, $this->course, 1, "*456789123*\n", 'entrada')
        ->assertCreated()
        ->assertJsonPath('participant.dni', '45678912');
});

test('CA2: con permanencia de 80 % o más la sesión queda Asistida y suma asistencia', function () {
    $enrollment = kioskEnroll($this->course, '45678912');

    kioskMark($this, $this->docente, $this->course, 1, '45678912', 'entrada')->assertCreated();
    Carbon::setTestNow(now()->addMinutes(96));

    kioskMark($this, $this->docente, $this->course, 1, '45678912', 'salida')
        ->assertOk()
        ->assertJsonPath('result', 'asistida')
        ->assertJsonPath('permanence.minutes', 96)
        ->assertJsonPath('permanence.duration_minutes', 120);

    $record = AttendanceRecord::sole();
    expect($record->status)->toBe('presente')
        ->and($record->method)->toBe('dni_lector')
        ->and($record->minutes_present)->toBe(96)
        ->and($enrollment->fresh()->attended_sessions)->toBe(1);
});

test('CA2: con permanencia menor al 80 % queda Asistencia parcial con los minutos y no suma', function () {
    $enrollment = kioskEnroll($this->course, '45678912');

    kioskMark($this, $this->docente, $this->course, 1, '45678912', 'entrada');
    Carbon::setTestNow(now()->addMinutes(95));

    kioskMark($this, $this->docente, $this->course, 1, '45678912', 'salida')
        ->assertOk()
        ->assertJsonPath('result', 'parcial')
        ->assertJsonPath('permanence.minutes', 95);

    $record = AttendanceRecord::sole();
    expect($record->status)->toBe('parcial')
        ->and($record->minutes_present)->toBe(95)
        ->and($enrollment->fresh()->attended_sessions)->toBe(0);
});

test('CA3: al cerrar la sesión quien solo tiene ingreso queda Sin salida y no suma horas', function () {
    $conSalida = kioskEnroll($this->course, '45678912');
    $sinSalida = kioskEnroll($this->course, '41234567', ['nombres' => 'JUAN']);

    kioskMark($this, $this->docente, $this->course, 1, '45678912', 'entrada');
    kioskMark($this, $this->docente, $this->course, 1, '41234567', 'entrada');
    Carbon::setTestNow(now()->addMinutes(110));
    kioskMark($this, $this->docente, $this->course, 1, '45678912', 'salida');

    $this->actingAs($this->docente)
        ->postJson(route('courses.sessions.close', [$this->course, 1]))
        ->assertOk()
        ->assertJsonPath('without_exit', 1);

    $record = AttendanceRecord::where('enrollment_id', $sinSalida->id)->sole();
    expect($record->status)->toBe('sin_salida')
        ->and($record->minutes_present)->toBe(0)
        ->and($sinSalida->fresh()->attended_sessions)->toBe(0)
        ->and($conSalida->fresh()->attended_sessions)->toBe(1);

    // Cerrar dos veces no duplica registros.
    $this->actingAs($this->docente)
        ->postJson(route('courses.sessions.close', [$this->course, 1]))
        ->assertJsonPath('without_exit', 0);
});

test('CA3: la justificación con motivo queda en bitácora con usuario y hora y suma la sesión', function () {
    $enrollment = kioskEnroll($this->course, '41234567');

    kioskMark($this, $this->docente, $this->course, 1, '41234567', 'entrada');
    $this->actingAs($this->docente)->postJson(route('courses.sessions.close', [$this->course, 1]));

    $this->actingAs($this->docente)
        ->postJson(route('courses.sessions.justify', [$this->course, 1, $enrollment]), [
            'reason' => 'Salió por comisión de servicio autorizada por su jefe.',
        ])
        ->assertOk();

    $record = AttendanceRecord::where('enrollment_id', $enrollment->id)->sole();
    expect($record->status)->toBe('presente')
        ->and($record->justification)->toBe('Salió por comisión de servicio autorizada por su jefe.')
        ->and($record->justified_by)->toBe($this->docente->id)
        ->and($record->justified_at)->not->toBeNull()
        ->and($enrollment->fresh()->attended_sessions)->toBe(1);
});

test('CA3: no se puede justificar una sesión que no está Sin salida', function () {
    $enrollment = kioskEnroll($this->course, '41234567');

    $this->actingAs($this->docente)
        ->postJson(route('courses.sessions.justify', [$this->course, 1, $enrollment]), [
            'reason' => 'Motivo cualquiera para probar.',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('reason');
});

test('CA4: un DNI no inscrito se rechaza y queda en la lista No inscritos', function () {
    $otherCourse = kioskCourse($this->docente);
    kioskEnroll($otherCourse, '47777777');

    kioskMark($this, $this->docente, $this->course, 2, '40000000', 'entrada')
        ->assertUnprocessable()
        ->assertJsonPath('result', 'no_inscrito');

    kioskMark($this, $this->docente, $this->course, 2, '47777777', 'entrada')
        ->assertUnprocessable()
        ->assertJsonPath('result', 'no_inscrito');

    expect(AttendanceMark::count())->toBe(0)
        ->and(AttendanceUnenrolledScan::count())->toBe(2);

    $this->actingAs($this->docente)
        ->getJson(route('courses.sessions.roster', [$this->course, 2]))
        ->assertOk()
        ->assertJsonPath('summary.no_inscritos', 2)
        ->assertJsonFragment(['dni' => '47777777', 'reason' => 'Inscrito en otra capacitación, no en esta sesión.'])
        ->assertJsonFragment(['dni' => '40000000', 'reason' => 'DNI no inscrito en esta capacitación.']);
});

test('CA4: una lectura sin DNI válido se rechaza sin registrar nada', function () {
    kioskMark($this, $this->docente, $this->course, 1, 'ABC-123', 'entrada')
        ->assertUnprocessable()
        ->assertJsonPath('result', 'dni_invalido');

    expect(AttendanceUnenrolledScan::count())->toBe(0);
});

test('CA5: escanear dos veces el mismo tipo de marca no crea duplicados', function () {
    kioskEnroll($this->course, '45678912');

    kioskMark($this, $this->docente, $this->course, 1, '45678912', 'entrada')->assertCreated();
    kioskMark($this, $this->docente, $this->course, 1, '45678912', 'entrada')
        ->assertStatus(409)
        ->assertJsonPath('result', 'duplicada')
        ->assertJsonPath('message', 'Marca ya registrada.');

    Carbon::setTestNow(now()->addMinutes(100));
    kioskMark($this, $this->docente, $this->course, 1, '45678912', 'salida')->assertOk();
    kioskMark($this, $this->docente, $this->course, 1, '45678912', 'salida')->assertStatus(409);

    expect(AttendanceMark::count())->toBe(2)
        ->and(AttendanceRecord::count())->toBe(1);
});

test('una salida sin ingreso previo se rechaza', function () {
    kioskEnroll($this->course, '45678912');

    kioskMark($this, $this->docente, $this->course, 1, '45678912', 'salida')
        ->assertUnprocessable()
        ->assertJsonPath('result', 'sin_ingreso');

    expect(AttendanceMark::count())->toBe(0);
});

test('CA6: en modo alterno se marca por DNI digitado con origen manual visible en el padrón', function () {
    kioskEnroll($this->course, '45678912');

    kioskMark($this, $this->docente, $this->course, 1, '45678912', 'entrada', 'manual')->assertCreated();
    Carbon::setTestNow(now()->addMinutes(120));
    kioskMark($this, $this->docente, $this->course, 1, '45678912', 'salida', 'manual')->assertOk();

    $this->actingAs($this->docente)
        ->getJson(route('courses.sessions.roster', [$this->course, 1]))
        ->assertJsonPath('participants.0.entry_origin', 'manual')
        ->assertJsonPath('participants.0.exit_origin', 'manual')
        ->assertJsonPath('participants.0.method', 'manual')
        ->assertJsonPath('participants.0.state', 'presente');
});

test('CA6: el origen solo puede ser lector o manual desde el kiosco', function () {
    kioskEnroll($this->course, '45678912');

    kioskMark($this, $this->docente, $this->course, 1, '45678912', 'entrada', 'gps')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('origin');
});

test('CA7: la primera marca del servidor activa el aviso de datos personales y solo se guardan los datos mínimos', function () {
    kioskEnroll($this->course, '45678912');

    kioskMark($this, $this->docente, $this->course, 1, '45678912', 'entrada')->assertJsonPath('first_mark', true);
    Carbon::setTestNow(now()->addMinutes(100));
    kioskMark($this, $this->docente, $this->course, 1, '45678912', 'salida')->assertJsonPath('first_mark', false);

    $columns = Schema::getColumnListing('attendance_marks');
    sort($columns);

    expect($columns)->toBe([
        'course_id', 'created_at', 'enrollment_id', 'id', 'marked_at', 'origin',
        'recorded_by', 'session_number', 'type', 'updated_at',
    ]);
});

test('no permite marcar cuando el acta está cerrada', function () {
    kioskEnroll($this->course, '45678912');
    $this->course->update(['acta_closed_at' => now()]);

    kioskMark($this, $this->docente, $this->course, 1, '45678912', 'entrada')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('course');
});

test('rechaza sesiones fuera de rango', function () {
    kioskEnroll($this->course, '45678912');

    kioskMark($this, $this->docente, $this->course, 6, '45678912', 'entrada')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('session');
});

test('solo el docente asignado o un administrador pueden usar el kiosco', function () {
    kioskEnroll($this->course, '45678912');
    $otroDocente = User::factory()->create(['role' => UserRole::Docente]);
    $participante = User::factory()->create(['role' => UserRole::Participante]);
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    kioskMark($this, $otroDocente, $this->course, 1, '45678912', 'entrada')->assertForbidden();
    kioskMark($this, $participante, $this->course, 1, '45678912', 'entrada')->assertForbidden();
    $this->actingAs($participante)->getJson(route('courses.sessions.roster', [$this->course, 1]))->assertForbidden();
    $this->actingAs($participante)->get(route('courses.sessions.kiosk', [$this->course, 1]))->assertForbidden();

    kioskMark($this, $admin, $this->course, 1, '45678912', 'entrada')->assertCreated();
});

test('la pantalla de kiosco carga con el padrón de la sesión y el modo indicado', function () {
    kioskEnroll($this->course, '45678912');

    $this->actingAs($this->docente)
        ->get(route('courses.sessions.kiosk', [$this->course, 2]).'?modo=salida')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('attendance/Kiosk')
            ->where('initialMode', 'salida')
            ->where('roster.session', 2)
            ->where('roster.summary.total', 1)
            ->where('course.total_sessions', 5));
});

test('SC-33: el padrón de la sesión muestra presentes y ausentes con su hora de marcación', function () {
    $presente = kioskEnroll($this->course, '45678912', ['paterno' => 'APAZA']);
    kioskEnroll($this->course, '41234567', ['paterno' => 'ZAPATA']);
    $enSala = kioskEnroll($this->course, '43333333', ['paterno' => 'MENDOZA']);

    $this->actingAs($this->docente)->post(route('courses.sessions.attendance', [$this->course, 3]), [
        'identifier' => '45678912',
        'method' => 'manual',
    ]);
    kioskMark($this, $this->docente, $this->course, 3, '43333333', 'entrada');

    $response = $this->actingAs($this->docente)
        ->getJson(route('courses.sessions.roster', [$this->course, 3]))
        ->assertOk()
        ->assertJsonPath('summary.total', 3)
        ->assertJsonPath('summary.presentes', 1)
        ->assertJsonPath('summary.en_sala', 1)
        ->assertJsonPath('summary.ausentes', 1);

    $participants = collect($response->json('participants'))->keyBy('id');

    expect($participants[$presente->id]['state'])->toBe('presente')
        ->and(Carbon::parse($participants[$presente->id]['recorded_at'])->equalTo(now()))->toBeTrue()
        ->and($participants[$enSala->id]['state'])->toBe('en_sala')
        ->and($participants[$enSala->id]['entry_at'])->not->toBeNull()
        ->and($response->json('participants.2.state'))->toBe('ausente');
});

test('SC-33: el registro por credencial avisa si el participante ya marcó en la sesión', function () {
    kioskEnroll($this->course, '45678912');

    $this->actingAs($this->docente)
        ->postJson(route('courses.attendance.by-credential', $this->course), ['code' => '45678912', 'session_number' => 1])
        ->assertOk();

    $this->actingAs($this->docente)
        ->postJson(route('courses.attendance.by-credential', $this->course), ['code' => '45678912', 'session_number' => 1])
        ->assertUnprocessable()
        ->assertJsonPath('already_recorded', true);

    expect(AttendanceRecord::count())->toBe(1);
});

test('el padrón CSV incluye el origen de la marca y las horas efectivas', function () {
    kioskEnroll($this->course, '45678912');

    kioskMark($this, $this->docente, $this->course, 1, '45678912', 'entrada');
    Carbon::setTestNow(now()->addMinutes(90));
    kioskMark($this, $this->docente, $this->course, 1, '45678912', 'salida');

    $csv = $this->actingAs($this->docente)
        ->get(route('courses.reports.attendance-csv', $this->course))
        ->assertOk()
        ->getContent();

    expect($csv)->toContain('Horas Efectivas (control de permanencia)')
        ->and($csv)->toContain('PARCIAL (lector DNI) 90 min')
        ->and($csv)->toContain(';1.50;');
});

test('SC-31: el QR de la sesión informa su vencimiento y cada renovación genera un enlace nuevo', function () {
    $first = $this->actingAs($this->docente)
        ->getJson(route('courses.sessions.qr', [$this->course, 1]))
        ->assertOk()
        ->assertJsonStructure(['url', 'svg', 'expires_at']);

    expect(Carbon::parse($first->json('expires_at'))->isAfter(now()))->toBeTrue();

    Carbon::setTestNow(now()->addMinutes(5)->subSeconds(10));

    $second = $this->actingAs($this->docente)
        ->getJson(route('courses.sessions.qr', [$this->course, 1]))
        ->assertOk();

    expect($second->json('url'))->not->toBe($first->json('url'))
        ->and(Carbon::parse($second->json('expires_at'))->isAfter(Carbon::parse($first->json('expires_at'))))->toBeTrue();
});
