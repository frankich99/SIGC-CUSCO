<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Enrollment;
use App\Services\AttendanceService;
use App\Services\CertificateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EnrollmentController extends Controller
{
    /**
     * Registrar una nueva matrícula en la capacitación.
     */
    public function store(Request $request, Course $course): RedirectResponse
    {
        $validated = $request->validate([
            'dni' => ['required', 'string', 'size:8', 'regex:/^[0-9]{8}$/'],
            'nombres' => ['required', 'string', 'max:150'],
            'paterno' => ['required', 'string', 'max:100'],
            'materno' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'regex:/^9[0-9]{8}$/'],
        ], [
            'dni.required' => 'El número de DNI es obligatorio.',
            'dni.size' => 'El DNI debe contener exactamente 8 dígitos.',
            'dni.regex' => 'El DNI solo debe contener caracteres numéricos.',
            'nombres.required' => 'Los nombres del participante son obligatorios.',
            'paterno.required' => 'El apellido paterno del participante es obligatorio.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'El correo electrónico ingresado no tiene un formato válido.',
            'phone.regex' => 'El número de celular debe contener exactamente 9 dígitos e iniciar con 9 (ej. 9XXXXXXXX).',
        ]);

        $user = $request->user();
        $isStaff = $user && ($user->isAdmin() || ($user->isDocente() && $course->instructor_id === $user->id));

        if ($user && ! $isStaff && $user->dni && $user->dni !== $validated['dni']) {
            throw ValidationException::withMessages([
                'dni' => 'El DNI de la matrícula debe coincidir con el DNI de tu cuenta.',
            ]);
        }

        DB::transaction(function () use ($course, $validated, $user, $isStaff): void {
            $lockedCourse = Course::query()
                ->whereKey($course->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $isStaff) {
                $statusValue = is_string($lockedCourse->status)
                    ? $lockedCourse->status
                    : $lockedCourse->status->value;

                if ($statusValue !== 'abierto') {
                    throw ValidationException::withMessages([
                        'dni' => 'Esta capacitación no se encuentra abierta para inscripciones actualmente.',
                    ]);
                }

                $occupiedSpots = $lockedCourse->enrollments()
                    ->where('status', '!=', 'cancelado')
                    ->count();

                if ($occupiedSpots >= $lockedCourse->capacity) {
                    throw ValidationException::withMessages([
                        'dni' => 'Las vacantes para esta capacitación se han agotado (aforo completo).',
                    ]);
                }
            }

            $exists = Enrollment::query()
                ->where('course_id', $lockedCourse->getKey())
                ->where('dni', $validated['dni'])
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'dni' => 'Ya existe una matrícula registrada con este DNI para esta capacitación.',
                ]);
            }

            $userId = null;
            $participantData = $validated;

            // Solo se vincula una matrícula cuando el usuario autenticado demuestra
            // que el DNI enviado es el suyo. Nunca se busca o crea otra cuenta por DNI/correo.
            if ($user && $user->dni && $user->dni === $validated['dni']) {
                $userId = $user->id;
                $participantData['nombres'] = $user->name;
                $participantData['paterno'] = $user->paterno ?? $validated['paterno'];
                $participantData['materno'] = $user->materno ?? $validated['materno'] ?? null;
                $participantData['email'] = $user->email;
                $participantData['phone'] = $user->phone ?? $validated['phone'] ?? null;
            }

            Enrollment::create([
                'course_id' => $lockedCourse->id,
                'user_id' => $userId,
                'dni' => $participantData['dni'],
                'nombres' => mb_strtoupper($participantData['nombres']),
                'paterno' => mb_strtoupper($participantData['paterno']),
                'materno' => ! empty($participantData['materno']) ? mb_strtoupper($participantData['materno']) : null,
                'email' => $participantData['email'],
                'phone' => $participantData['phone'] ?? null,
                'status' => 'inscrito',
                'attended_sessions' => 0,
            ]);
        });

        return back()->with('success', '¡Inscripción confirmada exitosamente! Tu vacante para "'.$course->title.'" ha sido reservada.');
    }

    /**
     * Actualizar datos administrativos de una matrícula mientras el acta esté abierta.
     */
    public function update(Request $request, Enrollment $enrollment): RedirectResponse
    {
        $this->authorizeStaff($enrollment);
        $this->assertActaOpen($enrollment);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:inscrito,en_curso,cancelado'],
            'final_grade' => ['nullable', 'numeric', 'min:0', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'regex:/^9[0-9]{8}$/'],
        ], [
            'phone.regex' => 'El número de celular debe contener exactamente 9 dígitos e iniciar con 9 (ej. 9XXXXXXXX).',
        ]);

        $enrollment->update($validated);

        return back()->with('success', "Matrícula de {$enrollment->full_name} actualizada correctamente.");
    }

    /**
     * Incrementar asistencia rápida de una sesión de clase de forma atómica.
     */
    public function recordAttendance(Enrollment $enrollment, AttendanceService $attendanceService): RedirectResponse
    {
        $this->authorizeStaff($enrollment);

        $record = $attendanceService->recordNext(
            $enrollment,
            'presente',
            'manual',
            Auth::id(),
        );

        if ($record === null) {
            return back()->with('error', 'Todas las sesiones de esta matrícula ya tienen un registro de asistencia.');
        }

        $enrollment->refresh();

        return back()->with('success', "Asistencia registrada para {$enrollment->full_name} (sesión {$record->session_number}, total {$enrollment->attended_sessions} sesiones).");
    }

    /**
     * Emitir certificado oficial solo después del cierre del acta y de validar elegibilidad.
     */
    public function generateCertificate(Enrollment $enrollment, AttendanceService $attendanceService): RedirectResponse
    {
        $this->authorizeStaff($enrollment);

        $course = $enrollment->course;
        if (! $course->isActaClosed()) {
            throw ValidationException::withMessages([
                'certificate' => 'El acta debe estar cerrada oficialmente antes de emitir certificados.',
            ]);
        }

        $enrollment->update([
            'attended_sessions' => $attendanceService->recalculate($enrollment),
        ]);
        $enrollment->refresh();

        if ($enrollment->final_grade === null || ! $enrollment->meetsPassingCriteria()) {
            throw ValidationException::withMessages([
                'certificate' => 'El participante no cumple los requisitos de asistencia y nota para recibir un certificado.',
            ]);
        }

        $enrollment->update([
            'status' => 'aprobado',
            'certificate_code' => $enrollment->certificate_code ?: CertificateService::makeCertificateCode($enrollment),
            'certificate_hash' => CertificateService::sign($enrollment),
            'certificate_issued_at' => $enrollment->certificate_issued_at ?: now(),
        ]);

        $payload = CertificateService::getCertificatePayload($enrollment->refresh());

        return back()->with('success', "Certificado emitido exitosamente con código {$payload['certificate_code']}.");
    }

    /**
     * Devolver un QR estándar con el código de credencial del participante.
     */
    public function credentialQr(Enrollment $enrollment): JsonResponse
    {
        $user = Auth::user();

        if (! $user || $enrollment->user_id !== $user->id) {
            $this->authorizeStaff($enrollment);
        }

        $code = $enrollment->credential_code;

        return response()->json([
            'code' => $code,
            'svg' => CertificateService::generateQrSvg($code, 260),
        ]);
    }

    /**
     * Eliminar o desmatricular un participante solo mientras no exista un certificado emitido.
     */
    public function destroy(Enrollment $enrollment): RedirectResponse
    {
        $this->authorizeStaff($enrollment);
        $this->assertActaOpen($enrollment);

        if ($enrollment->certificate_issued_at || $enrollment->certificate_code) {
            throw ValidationException::withMessages([
                'enrollment' => 'No se puede eliminar una matrícula con certificado emitido.',
            ]);
        }

        $name = $enrollment->full_name;
        $enrollment->delete();

        return back()->with('success', "La matrícula de {$name} ha sido eliminada del curso.");
    }

    /**
     * Verificar que el usuario tenga permisos de administración o sea el docente asignado.
     */
    protected function authorizeStaff(Enrollment $enrollment): void
    {
        $user = Auth::user();

        if (! $user) {
            abort(403, 'Acceso no autorizado.');
        }

        $isStaff = $user->isAdmin() || ($user->isDocente() && $enrollment->course->instructor_id === $user->id);

        if (! $isStaff) {
            abort(403, 'No tienes permisos para gestionar matrículas en este curso.');
        }
    }

    private function assertActaOpen(Enrollment $enrollment): void
    {
        if ($enrollment->course->isActaClosed()) {
            throw ValidationException::withMessages([
                'course' => 'El acta oficial está cerrada. Reabra el acta antes de modificar la matrícula.',
            ]);
        }
    }
}
