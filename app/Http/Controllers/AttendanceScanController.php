<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Enrollment;
use App\Services\AttendanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceScanController extends Controller
{
    /**
     * Show the authenticated participant's signed attendance action.
     */
    public function show(Request $request, Course $course, int $session): Response
    {
        $this->assertSession($course, $session);
        $enrollment = $this->findParticipantEnrollment($request, $course);

        abort_if(! $enrollment, 403, 'No tienes una matrícula activa en esta capacitación.');

        return Inertia::render('attendance/Scan', [
            'course' => [
                'id' => $course->id,
                'code' => $course->code,
                'title' => $course->title,
            ],
            'session' => $session,
            'enrollment' => [
                'id' => $enrollment->id,
                'full_name' => $enrollment->full_name,
                'attended_sessions' => $enrollment->attended_sessions,
            ],
        ]);
    }

    /**
     * Register attendance for the authenticated participant from a signed QR URL.
     */
    public function store(Request $request, Course $course, int $session, AttendanceService $attendanceService): RedirectResponse
    {
        $this->assertSession($course, $session);
        $enrollment = $this->findParticipantEnrollment($request, $course);

        abort_if(! $enrollment, 403, 'No tienes una matrícula activa en esta capacitación.');

        $record = $attendanceService->record(
            $course,
            $enrollment,
            $session,
            'presente',
            'qr_proyeccion',
            $request->user()?->id,
        );

        if ($record === null) {
            return back()->with('warning', "Tu asistencia a la sesión {$session} ya estaba registrada.");
        }

        return back()->with('success', "Asistencia registrada para la sesión {$session}.");
    }

    private function findParticipantEnrollment(Request $request, Course $course): ?Enrollment
    {
        $user = $request->user();

        return Enrollment::query()
            ->where('course_id', $course->id)
            ->where('status', '!=', 'cancelado')
            ->where(function ($query) use ($user): void {
                $query->where('user_id', $user->id);

                if ($user->dni) {
                    $query->orWhere('dni', $user->dni);
                }
            })
            ->first();
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
}
