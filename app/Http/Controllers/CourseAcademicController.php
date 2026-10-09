<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\Course;
use App\Models\Enrollment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CourseAcademicController extends Controller
{
    /**
     * Registrar asistencia de una sesión individual (escaneo QR o ingreso manual).
     */
    public function recordSessionAttendance(Request $request, Course $course, int $session): RedirectResponse|JsonResponse
    {
        $this->authorizeStaff($course);

        $validated = $request->validate([
            'identifier' => ['required', 'string', 'max:50'], // DNI o Código de Credencial INS-...
            'status' => ['nullable', 'string', 'in:presente,tardanza,falta'],
            'method' => ['nullable', 'string', 'in:qr_proyeccion,manual,offline_sync'],
        ], [
            'identifier.required' => 'Debe ingresar el DNI o código de credencial del participante.',
        ]);

        if ($session < 1 || $session > $course->total_sessions) {
            throw ValidationException::withMessages([
                'session' => "El número de sesión debe estar entre 1 y {$course->total_sessions}.",
            ]);
        }

        $id = trim($validated['identifier']);
        $enrollment = Enrollment::where('course_id', $course->id)
            ->where(function ($query) use ($id) {
                $query->where('dni', $id)
                    ->orWhere('credential_code', $id);
            })
            ->first();

        if (! $enrollment) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => "No se encontró ningún participante inscrito con DNI o credencial '{$id}' en este curso.",
                ], 404);
            }

            throw ValidationException::withMessages([
                'identifier' => "No se encontró ningún participante inscrito con DNI o credencial '{$id}' en este curso.",
            ]);
        }

        // Verificar si la asistencia a esta sesión ya fue registrada previamente (SIGC-9)
        $alreadyRecorded = AttendanceRecord::where('enrollment_id', $enrollment->id)
            ->where('session_number', $session)
            ->exists();

        if ($alreadyRecorded) {
            $msg = "Tu asistencia a la sesión {$session} ya fue registrada previamente para {$enrollment->full_name}.";
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'already_recorded' => true,
                    'message' => $msg,
                ], 422);
            }

            return back()->with('error', $msg);
        }

        AttendanceRecord::create([
            'course_id' => $course->id,
            'enrollment_id' => $enrollment->id,
            'session_number' => $session,
            'status' => $validated['status'] ?? 'presente',
            'method' => $validated['method'] ?? 'manual',
            'recorded_at' => now(),
            'recorded_by' => Auth::id(),
        ]);

        // Actualizar contador total de sesiones asistidas
        $attendedCount = AttendanceRecord::where('enrollment_id', $enrollment->id)
            ->whereIn('status', ['presente', 'tardanza'])
            ->count();

        $enrollment->update([
            'attended_sessions' => $attendedCount,
            'status' => $enrollment->status === 'inscrito' ? 'en_curso' : $enrollment->status,
        ]);

        $successMsg = "Asistencia registrada: sesión {$session} para {$enrollment->full_name}.";

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $successMsg,
                'participant' => [
                    'id' => $enrollment->id,
                    'full_name' => $enrollment->full_name,
                    'dni' => $enrollment->dni,
                    'attended_sessions' => $attendedCount,
                ],
            ]);
        }

        return back()->with('success', $successMsg);
    }

    /**
     * Sincronizar cola de asistencias offline almacenadas en el navegador (SIGC-12).
     */
    public function syncOfflineAttendance(Request $request, Course $course): JsonResponse
    {
        $this->authorizeStaff($course);

        $validated = $request->validate([
            'items' => ['required', 'array'],
            'items.*.identifier' => ['required', 'string'],
            'items.*.session_number' => ['required', 'integer', 'min:1'],
            'items.*.status' => ['nullable', 'string', 'in:presente,tardanza,falta'],
            'items.*.recorded_at' => ['nullable', 'string'],
        ]);

        $syncedCount = 0;
        $skippedCount = 0;

        foreach ($validated['items'] as $item) {
            $id = trim($item['identifier']);
            $session = (int) $item['session_number'];

            $enrollment = Enrollment::where('course_id', $course->id)
                ->where(function ($query) use ($id) {
                    $query->where('dni', $id)
                        ->orWhere('credential_code', $id);
                })
                ->first();

            if (! $enrollment) {
                $skippedCount++;

                continue;
            }

            $alreadyRecorded = AttendanceRecord::where('enrollment_id', $enrollment->id)
                ->where('session_number', $session)
                ->exists();

            if ($alreadyRecorded) {
                $skippedCount++;

                continue;
            }

            AttendanceRecord::create([
                'course_id' => $course->id,
                'enrollment_id' => $enrollment->id,
                'session_number' => $session,
                'status' => $item['status'] ?? 'presente',
                'method' => 'offline_sync',
                'recorded_at' => ! empty($item['recorded_at']) ? $item['recorded_at'] : now(),
                'recorded_by' => Auth::id(),
            ]);

            $attendedCount = AttendanceRecord::where('enrollment_id', $enrollment->id)
                ->whereIn('status', ['presente', 'tardanza'])
                ->count();

            $enrollment->update([
                'attended_sessions' => $attendedCount,
                'status' => $enrollment->status === 'inscrito' ? 'en_curso' : $enrollment->status,
            ]);

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

        if ($course->isActaClosed()) {
            return back()->with('error', 'El acta oficial de este curso ya fue cerrada anteriormente.');
        }

        $minAttendance = $course->min_attendance_percentage ?: 75;
        $totalSessions = $course->total_sessions ?: 4;

        $enrollments = $course->enrollments()->where('status', '!=', 'cancelado')->get();

        foreach ($enrollments as $enrollment) {
            $attendancePercentage = $totalSessions > 0
                ? round(($enrollment->attended_sessions / $totalSessions) * 100, 1)
                : 0.0;

            $grade = ! is_null($enrollment->final_grade) ? (float) $enrollment->final_grade : 0.0;

            if ($attendancePercentage >= $minAttendance && $grade >= 11.0) {
                $enrollment->status = 'aprobado';
            } else {
                $enrollment->status = 'reprobado';
            }

            $enrollment->save();
        }

        $course->update([
            'acta_closed_at' => now(),
            'acta_closed_by' => Auth::id(),
            'status' => 'concluido',
        ]);

        return back()->with('success', '¡Acta oficial del curso cerrada exitosamente! Se calcularon las condiciones finales de todos los participantes y se habilitó la emisión de certificados.');
    }

    /**
     * Reabrir o reactivar acta oficial de notas para correcciones o modificaciones.
     */
    public function reopenActa(Course $course): RedirectResponse
    {
        $this->authorizeStaff($course);

        if (! $course->isActaClosed()) {
            return back()->with('error', 'El acta oficial de este curso ya se encuentra abierta para edición.');
        }

        $course->update([
            'acta_closed_at' => null,
            'acta_closed_by' => null,
            'status' => 'en_curso',
        ]);

        return back()->with('success', '¡Acta oficial reactivada exitosamente! Ahora se encuentra en modo edición para realizar cualquier corrección o ajuste en calificaciones y asistencias.');
    }

    /**
     * Emisión en lote de certificados digitales oficiales para aprobados (SIGC-6).
     * Genera código oficial y firma criptográfica SHA-256 única e inalterable.
     */
    public function bulkIssueCertificates(Course $course): RedirectResponse
    {
        $this->authorizeStaff($course);

        if (! $course->isActaClosed()) {
            return back()->with('error', 'El acta debe estar cerrada oficialmente antes de emitir los certificados.');
        }

        $approvedEnrollments = $course->enrollments()
            ->where('status', 'aprobado')
            ->get();

        $issuedCount = 0;
        $year = date('Y');

        foreach ($approvedEnrollments as $enrollment) {
            $code = $enrollment->certificate_code;

            if (empty($code)) {
                $code = "CERT-{$year}-UNSAAC-{$course->id}-{$enrollment->id}";
                $enrollment->certificate_code = $code;
            }

            // Generar huella digital SHA-256 oficial
            $hashPayload = "SIGC-UNSAAC|{$code}|{$enrollment->dni}|{$enrollment->full_name}|{$course->code}|{$course->hours}|{$enrollment->final_grade}";
            $enrollment->certificate_hash = hash('sha256', $hashPayload);
            $enrollment->certificate_issued_at = $enrollment->certificate_issued_at ?: now();
            $enrollment->save();

            $issuedCount++;
        }

        return back()->with('success', "Se emitieron exitosamente {$issuedCount} certificados oficiales con firma criptográfica SHA-256.");
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
