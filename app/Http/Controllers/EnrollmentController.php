<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
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
            'phone' => ['nullable', 'string', 'max:20'],
        ], [
            'dni.required' => 'El número de DNI es obligatorio.',
            'dni.size' => 'El DNI debe contener exactamente 8 dígitos.',
            'dni.regex' => 'El DNI solo debe contener caracteres numéricos.',
            'nombres.required' => 'Los nombres del participante son obligatorios.',
            'paterno.required' => 'El apellido paterno es obligatorio.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'El correo electrónico ingresado no tiene un formato válido.',
        ]);

        // Validar que el curso esté abierto
        if ($course->status->value !== 'abierto' && $course->status !== 'abierto') {
            throw ValidationException::withMessages([
                'dni' => 'Esta capacitación no se encuentra abierta para inscripciones actualmente.',
            ]);
        }

        // Validar disponibilidad de vacantes
        if (! $course->hasAvailableSpots()) {
            throw ValidationException::withMessages([
                'dni' => 'Las vacantes para esta capacitación se han agotado (aforo completo).',
            ]);
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
            'nombres' => $validated['nombres'],
            'paterno' => $validated['paterno'],
            'materno' => $validated['materno'] ?? null,
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'status' => 'inscrito',
            'attended_sessions' => 0,
        ]);

        return back()->with('success', '¡Inscripción confirmada exitosamente! Tu vacante para "'.$course->title.'" ha sido reservada.');
    }
}
