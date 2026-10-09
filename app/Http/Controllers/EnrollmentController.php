<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\AttendanceRecord;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\CertificateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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
            'paterno.required' => 'El apellido paterno es obligatorio.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'El correo electrónico ingresado no tiene un formato válido.',
            'phone.regex' => 'El número de celular debe contener exactamente 9 dígitos e iniciar con 9 (ej. 9XXXXXXXX).',
        ]);

        // Validar que el curso esté abierto o que sea admin/docente quien matricula
        $user = Auth::user();
        $isStaff = $user && ($user->isAdmin() || ($user->isDocente() && $course->instructor_id === $user->id));

        if (! $isStaff) {
            $statusVal = is_string($course->status) ? $course->status : $course->status->value;
            if ($statusVal !== 'abierto') {
                throw ValidationException::withMessages([
                    'dni' => 'Esta capacitación no se encuentra abierta para inscripciones actualmente.',
                ]);
            }

            if (! $course->hasAvailableSpots()) {
                throw ValidationException::withMessages([
                    'dni' => 'Las vacantes para esta capacitación se han agotado (aforo completo).',
                ]);
            }
        }

        // Validar que el participante no esté ya inscrito
        $exists = Enrollment::where('course_id', $course->id)
            ->where('dni', $validated['dni'])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'dni' => 'Ya existe una matrícula registrada con este DNI para esta capacitación.',
            ]);
        }

        // Vincular con usuario autenticado o crear cuenta de participante
        $userId = Auth::id();

        if (! $userId) {
            $user = User::where('email', $validated['email'])
                ->orWhere('dni', $validated['dni'])
                ->first();

            if (! $user) {
                $user = User::create([
                    'name' => $validated['nombres'],
                    'paterno' => $validated['paterno'],
                    'materno' => $validated['materno'] ?? null,
                    'dni' => $validated['dni'],
                    'phone' => $validated['phone'] ?? null,
                    'email' => $validated['email'],
                    'role' => UserRole::Participante,
                    'password' => Hash::make($validated['dni']),
                ]);
            }

            $userId = $user->id;
        }

        // Registrar la matrícula
        Enrollment::create([
            'course_id' => $course->id,
            'user_id' => $userId,
            'dni' => $validated['dni'],
            'nombres' => mb_strtoupper($validated['nombres']),
            'paterno' => mb_strtoupper($validated['paterno']),
            'materno' => ! empty($validated['materno']) ? mb_strtoupper($validated['materno']) : null,
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'status' => 'inscrito',
            'attended_sessions' => 0,
        ]);

        return back()->with('success', '¡Inscripción confirmada exitosamente! Tu vacante para "'.$course->title.'" ha sido reservada.');
    }

    /**
     * Actualizar estado, asistencia, nota o certificado de un participante.
     */
    public function update(Request $request, Enrollment $enrollment): RedirectResponse
    {
        $this->authorizeStaff($enrollment);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:inscrito,en_curso,aprobado,reprobado,cancelado'],
            'attended_sessions' => ['required', 'integer', 'min:0'],
            'final_grade' => ['nullable', 'numeric', 'min:0', 'max:20'],
            'certificate_code' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'regex:/^9[0-9]{8}$/'],
        ], [
            'phone.regex' => 'El número de celular debe contener exactamente 9 dígitos e iniciar con 9 (ej. 9XXXXXXXX).',
        ]);

        // Si se aprueba y no tiene código de certificado, se genera automáticamente
        if ($validated['status'] === 'aprobado' && empty($validated['certificate_code'])) {
            $year = date('Y');
            $validated['certificate_code'] = "CERT-{$year}-{$enrollment->dni}";
        }

        $enrollment->update($validated);

        return back()->with('success', "Matrícula de {$enrollment->full_name} actualizada correctamente.");
    }

    /**
     * Incrementar asistencia rápida de una sesión de clase y sincronizar matriz oficial.
     */
    public function recordAttendance(Enrollment $enrollment): RedirectResponse
    {
        $this->authorizeStaff($enrollment);

        $course = $enrollment->course;
        $totalSessions = $course->total_sessions ?: 4;

        // Determinar siguiente sesión disponible sin registro
        $recordedSessions = AttendanceRecord::where('enrollment_id', $enrollment->id)->pluck('session_number')->toArray();
        $targetSession = 1;
        for ($i = 1; $i <= $totalSessions; $i++) {
            if (! in_array($i, $recordedSessions)) {
                $targetSession = $i;
                break;
            }
        }

        AttendanceRecord::firstOrCreate(
            [
                'course_id' => $enrollment->course_id,
                'enrollment_id' => $enrollment->id,
                'session_number' => $targetSession,
            ],
            [
                'status' => 'presente',
                'method' => 'manual',
                'recorded_at' => now(),
                'recorded_by' => Auth::id(),
            ]
        );

        $attendedCount = AttendanceRecord::where('enrollment_id', $enrollment->id)
            ->whereIn('status', ['presente', 'tardanza'])
            ->count();

        $enrollment->update([
            'attended_sessions' => max($attendedCount, $enrollment->attended_sessions + 1),
            'status' => $enrollment->status === 'inscrito' ? 'en_curso' : $enrollment->status,
        ]);

        return back()->with('success', "Asistencia registrada para {$enrollment->full_name} (sesión {$targetSession}, total {$enrollment->attended_sessions} sesiones).");
    }

    /**
     * Emitir certificado oficial para un participante aprobado con firma SHA-256 institucional.
     */
    public function generateCertificate(Enrollment $enrollment): RedirectResponse
    {
        $this->authorizeStaff($enrollment);

        $enrollment->update([
            'status' => 'aprobado',
            'final_grade' => $enrollment->final_grade ?? 18.00,
        ]);

        $payload = CertificateService::getCertificatePayload($enrollment);

        return back()->with('success', "Certificado emitido exitosamente con código {$payload['certificate_code']}.");
    }

    /**
     * Eliminar o desmatricular a un participante del curso.
     */
    public function destroy(Enrollment $enrollment): RedirectResponse
    {
        $this->authorizeStaff($enrollment);

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
}
