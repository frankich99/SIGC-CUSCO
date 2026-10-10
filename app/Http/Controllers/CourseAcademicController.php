<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Enrollment;
use App\Services\AttendanceQrService;
use App\Services\AttendanceService;
use App\Services\CertificateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CourseAcademicController extends Controller
{
    /**
     * Registrar asistencia de una sesión individual (escaneo QR o ingreso manual).
     */
    public function recordSessionAttendance(Request $request, Course $course, int $session, AttendanceService $attendanceService): RedirectResponse|JsonResponse
    {
        $this->authorizeStaff($course);

        $validated = $request->validate([
            'identifier' => ['required', 'string', 'max:50'],
            'status' => ['nullable', 'string', 'in:presente,tardanza,falta'],
            'method' => ['nullable', 'string', 'in:qr_proyeccion,manual,offline_sync'],
        ], [
            'identifier.required' => 'Debe ingresar el DNI o código de credencial del participante.',
        ]);

        $totalSessions = max(1, (int) ($course->total_sessions ?: 4));
        if ($session < 1 || $session > $totalSessions) {
            throw ValidationException::withMessages([
                'session' => "El número de sesión debe estar entre 1 y {$totalSessions}.",
            ]);
        }

        $identifier = trim($validated['identifier']);
        $enrollment = Enrollment::query()
            ->where('course_id', $course->id)
            ->where(function ($query) use ($identifier): void {
                $query->where('dni', $identifier)
                    ->orWhere('credential_code', $identifier);
            })
            ->first();

        if (! $enrollment) {
            $message = "No se encontró ningún participante inscrito con DNI o credencial '{$identifier}' en este curso.";

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $message,
                ], 404);
            }

            throw ValidationException::withMessages(['identifier' => $message]);
        }

        $record = $attendanceService->record(
            $course,
            $enrollment,
            $session,
            $validated['status'] ?? 'presente',
            $validated['method'] ?? 'manual',
            Auth::id(),
        );

        if ($record === null) {
            $message = "Tu asistencia a la sesión {$session} ya fue registrada previamente para {$enrollment->full_name}.";

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'already_recorded' => true,
                    'message' => $message,
                ], 422);
            }

            return back()->with('error', $message);
        }

        $enrollment->refresh();
        $successMsg = "Asistencia registrada: sesión {$session} para {$enrollment->full_name}.";

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $successMsg,
                'participant' => [
                    'id' => $enrollment->id,
                    'full_name' => $enrollment->full_name,
                    'dni' => $enrollment->dni,
                    'attended_sessions' => $enrollment->attended_sessions,
                ],
            ]);
        }

        return back()->with('success', $successMsg);
    }

    /**
     * Generate a short-lived, signed QR URL for participant self-attendance.
     */
    public function sessionQr(Course $course, int $session, AttendanceQrService $attendanceQrService): JsonResponse
    {
        $this->authorizeStaff($course);

        $totalSessions = max(1, (int) ($course->total_sessions ?: 4));
        if ($session < 1 || $session > $totalSessions) {
            throw ValidationException::withMessages([
                'session' => "El número de sesión debe estar entre 1 y {$totalSessions}.",
            ]);
        }

        return response()->json($attendanceQrService->forSession($course, $session));
    }

    public function syncOfflineAttendance(Request $request, Course $course, AttendanceService $attendanceService): JsonResponse
    {
        $this->authorizeStaff($course);
        $attendanceService->assertCourseOpen($course);

        $totalSessions = max(1, (int) ($course->total_sessions ?: 4));
        $validated = $request->validate([
            'items' => ['required', 'array', 'max:500'],
            'items.*.identifier' => ['required', 'string', 'max:50'],
            'items.*.session_number' => ['required', 'integer', 'min:1', 'max:'.$totalSessions],
            'items.*.status' => ['nullable', 'string', 'in:presente,tardanza,falta'],
            'items.*.recorded_at' => ['nullable', 'date'],
        ]);

        $syncedCount = 0;
        $skippedCount = 0;

        foreach ($validated['items'] as $item) {
            $identifier = trim($item['identifier']);
            $session = (int) $item['session_number'];

            $enrollment = Enrollment::query()
                ->where('course_id', $course->id)
                ->where(function ($query) use ($identifier): void {
                    $query->where('dni', $identifier)
                        ->orWhere('credential_code', $identifier);
                })
                ->first();

            if (! $enrollment) {
                $skippedCount++;

                continue;
            }

            $record = $attendanceService->record(
                $course,
                $enrollment,
                $session,
                $item['status'] ?? 'presente',
                'offline_sync',
                Auth::id(),
                now(),
            );

            if ($record === null) {
                $skippedCount++;

                continue;
            }

            $syncedCount++;
        }

        return response()->json([
            'success' => true,
            'synced' => $syncedCount,
            'skipped' => $skippedCount,
            'message' => "Sincronización finalizada: {$syncedCount} asistencias registradas con éxito.",
        ]);
    }

    /**
     * Cierre oficial e irreversible del acta de notas y calificaciones (SIGC-5).
     * Aplica la regla académica UNSAAC: Asistencia >= 75% Y Nota >= 11.
     */
    public function closeActa(Course $course): RedirectResponse
    {
        $this->authorizeStaff($course);

        $closed = DB::transaction(function () use ($course): bool {
            $lockedCourse = Course::query()
                ->whereKey($course->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedCourse->isActaClosed()) {
                return false;
            }

            $minAttendance = $lockedCourse->min_attendance_percentage ?: 75;
            $totalSessions = max(1, (int) ($lockedCourse->total_sessions ?: 4));
            $enrollments = $lockedCourse->enrollments()
                ->with('attendanceRecords')
                ->where('status', '!=', 'cancelado')
                ->lockForUpdate()
                ->get();

            foreach ($enrollments as $enrollment) {
                $attendedCount = $enrollment->attendanceRecords
                    ->whereIn('status', ['presente', 'tardanza'])
                    ->count();
                $attendancePercentage = round(($attendedCount / $totalSessions) * 100, 1);
                $grade = $enrollment->final_grade !== null ? (float) $enrollment->final_grade : 0.0;

                $enrollment->update([
                    'attended_sessions' => $attendedCount,
                    'status' => $attendancePercentage >= $minAttendance && $grade >= 11.0
                        ? 'aprobado'
                        : 'reprobado',
                ]);
            }

            $lockedCourse->update([
                'acta_closed_at' => now(),
                'acta_closed_by' => Auth::id(),
                'status' => 'concluido',
            ]);

            return true;
        });

        if (! $closed) {
            return back()->with('error', 'El acta oficial de este curso ya fue cerrada anteriormente.');
        }

        return back()->with('success', '¡Acta oficial del curso cerrada exitosamente! Se recalcularon las asistencias y condiciones finales de todos los participantes.');
    }

    /**
     * Reabrir o reactivar acta oficial de notas para correcciones o modificaciones.
     */
    public function reopenActa(Course $course): RedirectResponse
    {
        $this->authorizeAdmin($course);

        if (! $course->isActaClosed()) {
            return back()->with('error', 'El acta oficial del curso ya se encuentra abierta para edición.');
        }

        $course->update([
            'acta_closed_at' => null,
            'acta_closed_by' => null,
            'status' => 'en_curso',
        ]);

        return back()->with('success', '¡Acta oficial reactivada exitosamente! Ahora se encuentra en modo edición para realizar correcciones autorizadas.');
    }

    /**
     * Emisión en lote de certificados digitales oficiales para aprobados (SIGC-6).
     * Genera código oficial y firma criptográfica SHA-256 única e inalterable.
     */
    public function bulkIssueCertificates(Course $course): RedirectResponse
    {
        $this->authorizeStaff($course);

        $issuedCount = DB::transaction(function () use ($course): int {
            $lockedCourse = Course::query()
                ->whereKey($course->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedCourse->isActaClosed()) {
                throw ValidationException::withMessages([
                    'certificate' => 'El acta debe estar cerrada oficialmente antes de emitir los certificados.',
                ]);
            }

            $approvedEnrollments = $lockedCourse->enrollments()
                ->where('status', 'aprobado')
                ->with('attendanceRecords')
                ->lockForUpdate()
                ->get();
            $minAttendance = $lockedCourse->min_attendance_percentage ?: 75;
            $totalSessions = max(1, (int) ($lockedCourse->total_sessions ?: 4));

            foreach ($approvedEnrollments as $enrollment) {
                $attendedCount = $enrollment->attendanceRecords
                    ->whereIn('status', ['presente', 'tardanza'])
                    ->count();
                $attendancePercentage = round(($attendedCount / $totalSessions) * 100, 1);
                $grade = $enrollment->final_grade !== null ? (float) $enrollment->final_grade : 0.0;

                if ($attendancePercentage < $minAttendance || $grade < 11.0) {
                    throw ValidationException::withMessages([
                        'certificate' => "La matrícula {$enrollment->full_name} no cumple los requisitos para certificación.",
                    ]);
                }
            }

            foreach ($approvedEnrollments as $enrollment) {
                $enrollment->attended_sessions = $enrollment->attendanceRecords
                    ->whereIn('status', ['presente', 'tardanza'])
                    ->count();
                $enrollment->certificate_code = $enrollment->certificate_code ?: CertificateService::makeCertificateCode($enrollment);
                $enrollment->certificate_hash = CertificateService::sign($enrollment);
                $enrollment->certificate_issued_at = $enrollment->certificate_issued_at ?: now();
                $enrollment->save();
            }

            return $approvedEnrollments->count();
        });

        return back()->with('success', "Se emitieron exitosamente {$issuedCount} certificados oficiales con sello HMAC-SHA-256.");
    }

    /**
     * Exportar matriz de asistencias a formato CSV para Excel (SIGC-10).
     */
    public function exportAttendanceCsv(Course $course): Response
    {
        $this->authorizeStaff($course);

        $enrollments = $course->enrollments()
            ->with('attendanceRecords')
            ->orderBy('paterno')
            ->orderBy('nombres')
            ->get();

        $totalSessions = $course->total_sessions ?: 4;

        // Armar encabezados dinámicos S1..SN
        $headers = ['Nro', 'DNI', 'Apellidos y Nombres', 'Correo Electrónico', 'Teléfono'];
        for ($i = 1; $i <= $totalSessions; $i++) {
            $headers[] = "Sesión {$i}";
        }
        $headers[] = 'Total Asistencias';
        $headers[] = '% Asistencia';
        $headers[] = 'Estado';

        $output = fopen('php://temp', 'r+');
        // UTF-8 BOM para que Excel en Windows reconozca tildes y caracteres peruanos
        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, $headers, ';');

        foreach ($enrollments as $index => $enrollment) {
            $row = [
                $index + 1,
                $enrollment->dni,
                $enrollment->full_name,
                $enrollment->email,
                $enrollment->phone ?: '-',
            ];

            $recordsBySession = $enrollment->attendanceRecords->keyBy('session_number');

            for ($i = 1; $i <= $totalSessions; $i++) {
                $rec = $recordsBySession->get($i);
                $row[] = $rec ? mb_strtoupper($rec->status) : 'FALTA';
            }

            $row[] = $enrollment->attended_sessions;
            $row[] = "{$enrollment->attendance_percentage}%";
            $row[] = mb_strtoupper($enrollment->status);

            fputcsv($output, $row, ';');
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        $fileName = 'Asistencia_'.preg_replace('/[^A-Za-z0-9_-]/', '_', $course->code).'.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ]);
    }

    /**
     * Exportar acta oficial de calificaciones a formato CSV para Excel (SIGC-10).
     */
    public function exportActaCsv(Course $course): Response
    {
        $this->authorizeStaff($course);

        $enrollments = $course->enrollments()
            ->orderBy('paterno')
            ->orderBy('nombres')
            ->get();

        $headers = [
            'Nro',
            'DNI',
            'Apellidos y Nombres',
            'Correo',
            'Sesiones Asistidas',
            '% Asistencia',
            'Nota Vigesimal (0-20)',
            'Condición Final',
            'Código Certificado',
            'Firma Hash SHA-256',
        ];

        $output = fopen('php://temp', 'r+');
        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, $headers, ';');

        foreach ($enrollments as $index => $enrollment) {
            $row = [
                $index + 1,
                $enrollment->dni,
                $enrollment->full_name,
                $enrollment->email,
                $enrollment->attended_sessions,
                "{$enrollment->attendance_percentage}%",
                $enrollment->final_grade !== null ? number_format((float) $enrollment->final_grade, 1) : '-',
                mb_strtoupper($enrollment->status),
                $enrollment->certificate_code ?: '-',
                $enrollment->certificate_hash ?: '-',
            ];

            fputcsv($output, $row, ';');
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        $fileName = 'Acta_Oficial_'.preg_replace('/[^A-Za-z0-9_-]/', '_', $course->code).'.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ]);
    }

    /**
     * Verifica que el usuario autenticado sea administrador.
     */
    private function authorizeAdmin(Course $course): void
    {
        $user = Auth::user();

        if (! $user || ! $user->isAdmin()) {
            abort(403, 'Solo un administrador puede reabrir un acta oficial.');
        }

        $this->authorizeStaff($course);
    }

    /**
     * Verifica que el usuario autenticado sea administrador o el docente a cargo.
     */
    protected function authorizeStaff(Course $course): void
    {
        $user = Auth::user();

        if (! $user) {
            abort(403, 'Acceso denegado. Se requiere autenticación.');
        }

        $isStaff = $user->isAdmin() || ($user->isDocente() && $course->instructor_id === $user->id);

        if (! $isStaff) {
            abort(403, 'No tienes privilegios para gestionar la actividad académica de esta capacitación.');
        }
    }
}
