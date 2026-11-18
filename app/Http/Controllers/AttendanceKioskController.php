<?php

namespace App\Http\Controllers;

use App\Models\AttendanceMark;
use App\Models\Course;
use App\Models\Enrollment;
use App\Services\AttendancePermanenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Kiosco de ingreso/salida con lector de DNI y padrón en vivo por sesión (SC-32, SC-33).
 */
class AttendanceKioskController extends Controller
{
    public function __construct(private readonly AttendancePermanenceService $permanence) {}

    /**
     * Pantalla de kiosco para la puerta del auditorio.
     */
    public function show(Request $request, Course $course, int $session): Response
    {
        Gate::authorize('manageAttendance', $course);

        $mode = $request->query('modo') === AttendanceMark::TYPE_EXIT
            ? AttendanceMark::TYPE_EXIT
            : AttendanceMark::TYPE_ENTRY;

        return Inertia::render('attendance/Kiosk', [
            'course' => [
                'id' => $course->id,
                'code' => $course->code,
                'title' => $course->title,
                'total_sessions' => max(1, (int) ($course->total_sessions ?: 4)),
                'is_acta_closed' => $course->isActaClosed(),
            ],
            'roster' => $this->permanence->roster($course, $session),
            'initialMode' => $mode,
        ]);
    }

    /**
     * Registrar una marca de ingreso o salida (lector de DNI o modo alterno).
     */
    public function storeMark(Request $request, Course $course, int $session): JsonResponse
    {
        Gate::authorize('manageAttendance', $course);

        $validated = $request->validate([
            'identifier' => ['required', 'string', 'max:50'],
            'type' => ['required', Rule::in([AttendanceMark::TYPE_ENTRY, AttendanceMark::TYPE_EXIT])],
            'origin' => ['nullable', Rule::in([AttendanceMark::ORIGIN_DNI_READER, AttendanceMark::ORIGIN_MANUAL])],
        ], [
            'identifier.required' => 'Escanee o digite el DNI del servidor.',
        ]);

        $result = $this->permanence->registerMark(
            $course,
            $session,
            $validated['identifier'],
            $validated['type'],
            $validated['origin'] ?? AttendanceMark::ORIGIN_DNI_READER,
            $request->user()?->id,
        );

        $status = match ($result['result']) {
            AttendancePermanenceService::RESULT_ENTRY => 201,
            AttendancePermanenceService::RESULT_ATTENDED, AttendancePermanenceService::RESULT_PARTIAL => 200,
            AttendancePermanenceService::RESULT_DUPLICATE => 409,
            default => 422,
        };

        return response()->json($result, $status);
    }

    /**
     * Sincronizar las marcaciones que el kiosco guardó sin conexión (SC-52, HU-11.3).
     *
     * Responde una línea por `client_uuid`; reenviar el mismo lote es seguro.
     */
    public function syncMarks(Request $request, Course $course, int $session): JsonResponse
    {
        Gate::authorize('manageAttendance', $course);

        $validated = $request->validate([
            'marks' => ['required', 'array', 'min:1', 'max:'.AttendancePermanenceService::SYNC_MAX_BATCH],
            'marks.*.client_uuid' => ['required', 'uuid', 'distinct'],
            'marks.*.identifier' => ['required', 'string', 'max:50'],
            'marks.*.type' => ['required', Rule::in([AttendanceMark::TYPE_ENTRY, AttendanceMark::TYPE_EXIT])],
            'marks.*.origin' => ['nullable', Rule::in([AttendanceMark::ORIGIN_DNI_READER, AttendanceMark::ORIGIN_MANUAL])],
            'marks.*.captured_at' => ['required', 'date'],
        ], [
            'marks.max' => 'Envíe como máximo '.AttendancePermanenceService::SYNC_MAX_BATCH.' marcaciones por lote.',
            'marks.*.client_uuid.distinct' => 'El lote tiene marcaciones repetidas.',
        ]);

        /** @var list<array{client_uuid: string, identifier: string, type: string, origin?: string|null, captured_at: string}> $marks */
        $marks = $validated['marks'];

        return response()->json([
            'results' => $this->permanence->syncMarks($course, $session, $marks, $request->user()?->id),
        ]);
    }

    /**
     * Padrón en vivo de la sesión (presentes, ausentes, hora de marcación, origen y «No inscritos»).
     */
    public function roster(Course $course, int $session): JsonResponse
    {
        Gate::authorize('manageAttendance', $course);

        return response()->json($this->permanence->roster($course, $session));
    }

    /**
     * Cerrar la sesión: quien solo tiene marca de ingreso queda «Sin salida».
     */
    public function close(Request $request, Course $course, int $session): JsonResponse
    {
        Gate::authorize('manageAttendance', $course);

        $count = $this->permanence->closeSession($course, $session, $request->user()?->id);

        return response()->json([
            'success' => true,
            'without_exit' => $count,
            'message' => $count > 0
                ? "Sesión {$session} cerrada. {$count} participante(s) quedaron «Sin salida»."
                : "Sesión {$session} cerrada. Todos los ingresos tienen marca de salida.",
        ]);
    }

    /**
     * Justificar una sesión «Sin salida» con motivo (queda en bitácora con usuario y hora).
     */
    public function justify(Request $request, Course $course, int $session, Enrollment $enrollment): JsonResponse
    {
        Gate::authorize('manageAttendance', $course);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'reason.required' => 'Indique el motivo de la justificación.',
            'reason.min' => 'El motivo debe tener al menos 5 caracteres.',
        ]);

        $userId = $request->user()?->id;
        abort_if($userId === null, 403);

        $record = $this->permanence->justify($course, $enrollment, $session, trim($validated['reason']), $userId);

        return response()->json([
            'success' => true,
            'message' => "Sesión {$session} justificada para {$enrollment->full_name}.",
            'justified_at' => $record->justified_at?->toIso8601String(),
        ]);
    }
}
