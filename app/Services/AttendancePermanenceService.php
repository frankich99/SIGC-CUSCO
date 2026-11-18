<?php

namespace App\Services;

use App\Models\AttendanceMark;
use App\Models\AttendanceRecord;
use App\Models\AttendanceUnenrolledScan;
use App\Models\Course;
use App\Models\Enrollment;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Control de permanencia por marcas de ingreso y salida (SC-32 / HU-03.2).
 *
 * Una sesión queda «Asistida» si la permanencia es ≥ 80 % de la duración
 * programada, «Asistencia parcial» si es menor y «Sin salida» si al cerrar la
 * sesión solo hay marca de ingreso. Solo «Asistida» suma al porcentaje de
 * asistencia (y por ende a la aptitud al certificado, SC-34).
 */
class AttendancePermanenceService
{
    public const MIN_PERMANENCE_PERCENT = 80;

    public const RESULT_ENTRY = 'ingreso_registrado';

    public const RESULT_ATTENDED = 'asistida';

    public const RESULT_PARTIAL = 'parcial';

    public const RESULT_DUPLICATE = 'duplicada';

    public const RESULT_NO_ENTRY = 'sin_ingreso';

    public const RESULT_NOT_ENROLLED = 'no_inscrito';

    public const RESULT_INVALID_DNI = 'dni_invalido';

    /** Marcación sincronizada con una hora de dispositivo fuera de rango (SC-52). */
    public const RESULT_INVALID_TIME = 'hora_invalida';

    /** Máximo de marcaciones por lote de sincronización. */
    public const SYNC_MAX_BATCH = 50;

    /** Desfase permitido del reloj del dispositivo hacia el futuro. */
    public const SYNC_CLOCK_TOLERANCE_MINUTES = 2;

    /** Antigüedad máxima de una marcación guardada sin conexión. */
    public const SYNC_MAX_AGE_HOURS = 24;

    public function __construct(private readonly AttendanceService $attendanceService) {}

    /**
     * Duración programada de una sesión en minutos (horas del curso / número de sesiones).
     */
    public function sessionDurationMinutes(Course $course): int
    {
        $totalSessions = max(1, (int) ($course->total_sessions ?: 4));
        $hours = max(0, (int) $course->hours);

        return max(1, (int) round(($hours * 60) / $totalSessions));
    }

    /**
     * Extrae el número de DNI (8 dígitos) de la lectura del lector de código de barras.
     * El lector USB funciona en modo teclado y puede añadir el dígito verificador u otros caracteres.
     */
    public function extractDni(string $raw): ?string
    {
        return preg_match('/(\d{8})/', $raw, $matches) === 1 ? $matches[1] : null;
    }

    /**
     * Registra una marca de ingreso o salida.
     *
     * @return array{
     *     result: string,
     *     message: string,
     *     first_mark: bool,
     *     participant: array{id: int, dni: string, full_name: string}|null,
     *     marked_at: string|null,
     *     permanence: array{minutes: int, percent: float, required_percent: int, duration_minutes: int}|null
     * }
     */
    public function registerMark(
        Course $course,
        int $session,
        string $rawIdentifier,
        string $type,
        string $origin,
        ?int $recordedBy = null,
        ?CarbonInterface $at = null,
        ?string $clientUuid = null,
    ): array {
        $this->assertSession($course, $session);
        $this->attendanceService->assertCourseOpen($course);

        $at ??= now();
        $dni = $this->extractDni($rawIdentifier);

        if ($dni === null) {
            return $this->response(self::RESULT_INVALID_DNI, 'La lectura no contiene un DNI válido de 8 dígitos. Vuelva a escanear.');
        }

        return DB::transaction(function () use ($course, $session, $dni, $type, $origin, $recordedBy, $at, $clientUuid): array {
            // Same lock order as AttendanceService (course, then enrollment) to avoid deadlocks.
            Course::query()->whereKey($course->getKey())->lockForUpdate()->firstOrFail();

            // SC-52: una marcación del kiosco sin conexión que ya se recibió no se registra otra vez.
            if ($clientUuid !== null) {
                $synced = AttendanceMark::query()->where('client_uuid', $clientUuid)->with('enrollment')->first();

                if ($synced) {
                    return $this->response(self::RESULT_DUPLICATE, 'Marcación ya sincronizada.', [
                        'id' => $synced->enrollment->id,
                        'dni' => $synced->enrollment->dni,
                        'full_name' => $synced->enrollment->full_name,
                    ], $synced->marked_at);
                }

                if (AttendanceUnenrolledScan::query()->where('client_uuid', $clientUuid)->exists()) {
                    return $this->response(self::RESULT_NOT_ENROLLED, 'Esta lectura ya está en la lista «No inscritos».');
                }
            }

            $enrollment = Enrollment::query()
                ->where('course_id', $course->id)
                ->where('dni', $dni)
                ->where('status', '!=', 'cancelado')
                ->lockForUpdate()
                ->first();

            if (! $enrollment) {
                $reason = Enrollment::query()->where('dni', $dni)->where('status', '!=', 'cancelado')->exists()
                    ? 'Inscrito en otra capacitación, no en esta sesión.'
                    : 'DNI no inscrito en esta capacitación.';

                AttendanceUnenrolledScan::create([
                    'course_id' => $course->id,
                    'session_number' => $session,
                    'dni' => $dni,
                    'type' => $type,
                    'reason' => $reason,
                    'scanned_at' => $at,
                    'recorded_by' => $recordedBy,
                    'client_uuid' => $clientUuid,
                ]);

                return $this->response(self::RESULT_NOT_ENROLLED, "{$reason} Se agregó a la lista «No inscritos» para que RR.HH. decida.");
            }

            $participant = [
                'id' => $enrollment->id,
                'dni' => $enrollment->dni,
                'full_name' => $enrollment->full_name,
            ];

            $marks = AttendanceMark::query()
                ->where('enrollment_id', $enrollment->id)
                ->where('session_number', $session)
                ->get()
                ->keyBy('type');

            if ($marks->has($type)) {
                return $this->response(self::RESULT_DUPLICATE, 'Marca ya registrada.', $participant, $marks->get($type)?->marked_at);
            }

            $entry = $marks->get(AttendanceMark::TYPE_ENTRY);

            if ($type === AttendanceMark::TYPE_EXIT && ! $entry) {
                return $this->response(self::RESULT_NO_ENTRY, 'No registra marca de ingreso en esta sesión. Registre primero el ingreso o use el modo alterno.', $participant);
            }

            $firstMark = ! AttendanceMark::query()->where('enrollment_id', $enrollment->id)->exists();

            AttendanceMark::create([
                'course_id' => $course->id,
                'enrollment_id' => $enrollment->id,
                'session_number' => $session,
                'type' => $type,
                'origin' => $origin,
                'marked_at' => $at,
                'recorded_by' => $recordedBy,
                'client_uuid' => $clientUuid,
            ]);

            if ($type === AttendanceMark::TYPE_ENTRY) {
                return $this->response(self::RESULT_ENTRY, "Ingreso registrado: {$enrollment->full_name}.", $participant, $at, null, $firstMark);
            }

            /** @var AttendanceMark $entry */
            $duration = $this->sessionDurationMinutes($course);
            $minutes = (int) max(0, floor($entry->marked_at->diffInSeconds($at, false) / 60));
            $percent = round(min(100, ($minutes / $duration) * 100), 1);
            $attended = $percent >= self::MIN_PERMANENCE_PERCENT;
            $permanence = [
                'minutes' => $minutes,
                'percent' => $percent,
                'required_percent' => self::MIN_PERMANENCE_PERCENT,
                'duration_minutes' => $duration,
            ];

            $record = $this->attendanceService->record(
                $course,
                $enrollment,
                $session,
                $attended ? 'presente' : 'parcial',
                $this->methodForOrigin($origin),
                $recordedBy,
                $at,
                ['minutes_present' => $minutes],
            );

            $message = $attended
                ? "Salida registrada: {$enrollment->full_name}. Sesión asistida ({$minutes} min, {$percent} %)."
                : "Salida registrada: {$enrollment->full_name}. Asistencia parcial: {$minutes} min ({$percent} %), se exige ".self::MIN_PERMANENCE_PERCENT.' %.';

            if ($record === null) {
                $message .= ' La sesión ya tenía un registro de asistencia y no se modificó.';
            }

            return $this->response(
                $attended ? self::RESULT_ATTENDED : self::RESULT_PARTIAL,
                $message,
                $participant,
                $at,
                $permanence,
                $firstMark,
            );
        });
    }

    /**
     * Registra un lote de marcaciones que el kiosco guardó sin conexión (SC-52, ADR 0001).
     *
     * Usa la hora del dispositivo al escanear, las procesa en orden cronológico
     * (el ingreso antes que la salida) y es idempotente por `client_uuid`:
     * reenviar el mismo lote no duplica nada.
     *
     * @param  list<array{client_uuid: string, identifier: string, type: string, origin?: string|null, captured_at: string}>  $items
     * @return list<array<string, mixed>>
     */
    public function syncMarks(Course $course, int $session, array $items, ?int $recordedBy = null): array
    {
        $this->assertSession($course, $session);
        $this->attendanceService->assertCourseOpen($course);

        $now = now();
        $latestAllowed = $now->copy()->addMinutes(self::SYNC_CLOCK_TOLERANCE_MINUTES);
        $earliestAllowed = $now->copy()->subHours(self::SYNC_MAX_AGE_HOURS);

        $queue = array_map(fn (array $item): array => [
            'item' => $item,
            'at' => Carbon::parse($item['captured_at'])->setTimezone($now->getTimezone()),
        ], $items);

        // Orden cronológico; a igual hora, el ingreso antes que la salida.
        usort($queue, fn (array $a, array $b): int => [$a['at']->getTimestamp(), $a['item']['type'] === AttendanceMark::TYPE_ENTRY ? 0 : 1]
            <=> [$b['at']->getTimestamp(), $b['item']['type'] === AttendanceMark::TYPE_ENTRY ? 0 : 1]);

        $results = [];

        foreach ($queue as ['item' => $item, 'at' => $at]) {
            if ($at->greaterThan($latestAllowed) || $at->lessThan($earliestAllowed)) {
                $results[] = ['client_uuid' => $item['client_uuid']] + $this->response(
                    self::RESULT_INVALID_TIME,
                    'La hora de la marcación ('.$at->format('d/m/Y H:i').') no es válida: está en el futuro o tiene más de '.self::SYNC_MAX_AGE_HOURS.' horas. Revise el reloj del equipo del kiosco.',
                );

                continue;
            }

            $results[] = ['client_uuid' => $item['client_uuid']] + $this->registerMark(
                $course,
                $session,
                $item['identifier'],
                $item['type'],
                $item['origin'] ?? AttendanceMark::ORIGIN_DNI_READER,
                $recordedBy,
                $at,
                $item['client_uuid'],
            );
        }

        return $results;
    }

    /**
     * Cierra el control de permanencia de una sesión: quien solo tiene marca de ingreso queda «Sin salida».
     *
     * @return int Cantidad de participantes marcados como «Sin salida».
     */
    public function closeSession(Course $course, int $session, ?int $closedBy = null): int
    {
        $this->assertSession($course, $session);
        $this->attendanceService->assertCourseOpen($course);

        $entriesWithoutExit = AttendanceMark::query()
            ->where('course_id', $course->id)
            ->where('session_number', $session)
            ->where('type', AttendanceMark::TYPE_ENTRY)
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('attendance_marks as exits')
                    ->whereColumn('exits.enrollment_id', 'attendance_marks.enrollment_id')
                    ->whereColumn('exits.session_number', 'attendance_marks.session_number')
                    ->where('exits.type', AttendanceMark::TYPE_EXIT);
            })
            ->with('enrollment')
            ->get();

        $count = 0;

        foreach ($entriesWithoutExit as $entry) {
            if ($entry->enrollment->status === 'cancelado') {
                continue;
            }

            $record = $this->attendanceService->record(
                $course,
                $entry->enrollment,
                $session,
                'sin_salida',
                $this->methodForOrigin($entry->origin),
                $closedBy,
                now(),
                ['minutes_present' => 0],
            );

            if ($record !== null) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Justifica una sesión «Sin salida» con motivo; queda en la bitácora con usuario y hora.
     */
    public function justify(Course $course, Enrollment $enrollment, int $session, string $reason, int $justifiedBy): AttendanceRecord
    {
        $this->assertSession($course, $session);
        $this->attendanceService->assertCourseOpen($course);

        if ($enrollment->course_id !== $course->id) {
            throw ValidationException::withMessages([
                'enrollment' => 'La matrícula no pertenece a esta capacitación.',
            ]);
        }

        return DB::transaction(function () use ($course, $enrollment, $session, $reason, $justifiedBy): AttendanceRecord {
            $record = AttendanceRecord::query()
                ->where('enrollment_id', $enrollment->id)
                ->where('session_number', $session)
                ->lockForUpdate()
                ->first();

            if (! $record || $record->status !== 'sin_salida') {
                throw ValidationException::withMessages([
                    'reason' => 'Solo se puede justificar una sesión en estado «Sin salida».',
                ]);
            }

            $record->update([
                'status' => 'presente',
                'justification' => $reason,
                'justified_by' => $justifiedBy,
                'justified_at' => now(),
            ]);

            $enrollment->update([
                'attended_sessions' => $this->attendanceService->recalculate($enrollment),
            ]);

            Log::info('Bitácora de asistencia: justificación de sesión sin salida', [
                'course_id' => $course->id,
                'enrollment_id' => $enrollment->id,
                'session_number' => $session,
                'reason' => $reason,
                'justified_by' => $justifiedBy,
                'justified_at' => $record->justified_at?->toIso8601String(),
            ]);

            return $record;
        });
    }

    /**
     * Padrón en vivo de una sesión: presentes, en sala, parciales, sin salida y ausentes (SC-32 / SC-33).
     *
     * @return array<string, mixed>
     */
    public function roster(Course $course, int $session): array
    {
        $this->assertSession($course, $session);

        $enrollments = $course->enrollments()
            ->where('status', '!=', 'cancelado')
            ->with([
                'attendanceRecords' => fn ($query) => $query->where('session_number', $session),
                'attendanceMarks' => fn ($query) => $query->where('session_number', $session),
            ])
            ->orderBy('paterno')
            ->orderBy('materno')
            ->orderBy('nombres')
            ->get();

        $participants = $enrollments->map(function (Enrollment $enrollment): array {
            /** @var AttendanceRecord|null $record */
            $record = $enrollment->attendanceRecords->first();
            $entry = $enrollment->attendanceMarks->firstWhere('type', AttendanceMark::TYPE_ENTRY);
            $exit = $enrollment->attendanceMarks->firstWhere('type', AttendanceMark::TYPE_EXIT);

            $state = match (true) {
                $record !== null => $record->status,
                $entry !== null => 'en_sala',
                default => 'ausente',
            };

            return [
                'id' => $enrollment->id,
                'dni' => $enrollment->dni,
                'full_name' => $enrollment->full_name,
                'state' => $state,
                'recorded_at' => $record?->recorded_at?->toIso8601String(),
                'method' => $record?->method,
                'minutes_present' => $record?->minutes_present,
                'justification' => $record?->justification,
                'justified_at' => $record?->justified_at?->toIso8601String(),
                'entry_at' => $entry?->marked_at->toIso8601String(),
                'entry_origin' => $entry?->origin,
                'exit_at' => $exit?->marked_at->toIso8601String(),
                'exit_origin' => $exit?->origin,
            ];
        })->values();

        $unenrolled = AttendanceUnenrolledScan::query()
            ->where('course_id', $course->id)
            ->where('session_number', $session)
            ->latest('scanned_at')
            ->get()
            ->map(fn (AttendanceUnenrolledScan $scan): array => [
                'id' => $scan->id,
                'dni' => $scan->dni,
                'type' => $scan->type,
                'reason' => $scan->reason,
                'scanned_at' => $scan->scanned_at->toIso8601String(),
            ])
            ->values();

        $countBy = fn (array $states): int => $participants->whereIn('state', $states)->count();

        return [
            'session' => $session,
            'duration_minutes' => $this->sessionDurationMinutes($course),
            'required_percent' => self::MIN_PERMANENCE_PERCENT,
            'is_acta_closed' => $course->isActaClosed(),
            'summary' => [
                'total' => $participants->count(),
                'presentes' => $countBy(AttendanceRecord::COUNTED_STATUSES),
                'en_sala' => $countBy(['en_sala']),
                'parciales' => $countBy(['parcial']),
                'sin_salida' => $countBy(['sin_salida']),
                'ausentes' => $countBy(['ausente', 'falta']),
                'no_inscritos' => $unenrolled->count(),
            ],
            'participants' => $participants,
            'unenrolled' => $unenrolled,
        ];
    }

    private function methodForOrigin(string $origin): string
    {
        return match ($origin) {
            AttendanceMark::ORIGIN_QR => 'qr_proyeccion',
            AttendanceMark::ORIGIN_MANUAL => 'manual',
            default => 'dni_lector',
        };
    }

    private function assertSession(Course $course, int $session): void
    {
        $totalSessions = max(1, (int) ($course->total_sessions ?: 4));

        if ($session < 1 || $session > $totalSessions) {
            throw ValidationException::withMessages([
                'session' => "El número de sesión debe estar entre 1 y {$totalSessions}.",
            ]);
        }
    }

    /**
     * @param  array{id: int, dni: string, full_name: string}|null  $participant
     * @param  array{minutes: int, percent: float, required_percent: int, duration_minutes: int}|null  $permanence
     * @return array{
     *     result: string,
     *     message: string,
     *     first_mark: bool,
     *     participant: array{id: int, dni: string, full_name: string}|null,
     *     marked_at: string|null,
     *     permanence: array{minutes: int, percent: float, required_percent: int, duration_minutes: int}|null
     * }
     */
    private function response(
        string $result,
        string $message,
        ?array $participant = null,
        ?CarbonInterface $markedAt = null,
        ?array $permanence = null,
        bool $firstMark = false,
    ): array {
        return [
            'result' => $result,
            'message' => $message,
            'first_mark' => $firstMark,
            'participant' => $participant,
            'marked_at' => $markedAt?->toIso8601String(),
            'permanence' => $permanence,
        ];
    }
}
