<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\Course;
use App\Models\Enrollment;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceService
{
    /**
     * Record one attendance event while serializing concurrent writes.
     */
    public function record(
        Course $course,
        Enrollment $enrollment,
        int $session,
        string $status,
        string $method,
        int|string|null $recordedBy = null,
        ?CarbonInterface $recordedAt = null,
    ): ?AttendanceRecord {
        return DB::transaction(function () use ($course, $enrollment, $session, $status, $method, $recordedBy, $recordedAt): ?AttendanceRecord {
            $lockedCourse = Course::query()
                ->whereKey($course->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $lockedEnrollment = Enrollment::query()
                ->whereKey($enrollment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertRecordable($lockedCourse, $lockedEnrollment, $session, $status);

            return $this->createRecord(
                $lockedCourse,
                $lockedEnrollment,
                $session,
                $status,
                $method,
                $recordedBy,
                $recordedAt,
            );
        });
    }

    /**
     * Record the next available session for a participant atomically.
     */
    public function recordNext(
        Enrollment $enrollment,
        string $status = 'presente',
        string $method = 'manual',
        int|string|null $recordedBy = null,
    ): ?AttendanceRecord {
        return DB::transaction(function () use ($enrollment, $status, $method, $recordedBy): ?AttendanceRecord {
            $lockedEnrollment = Enrollment::query()
                ->whereKey($enrollment->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $lockedCourse = Course::query()
                ->whereKey($lockedEnrollment->course_id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertCourseOpen($lockedCourse);

            $totalSessions = $this->totalSessions($lockedCourse);
            $recordedSessions = AttendanceRecord::query()
                ->where('enrollment_id', $lockedEnrollment->getKey())
                ->pluck('session_number')
                ->all();
            $nextSession = collect(range(1, $totalSessions))
                ->first(fn (int $session): bool => ! in_array($session, $recordedSessions, true));

            if ($nextSession === null) {
                return null;
            }

            $this->assertRecordable($lockedCourse, $lockedEnrollment, $nextSession, $status);

            return $this->createRecord(
                $lockedCourse,
                $lockedEnrollment,
                $nextSession,
                $status,
                $method,
                $recordedBy,
                now(),
            );
        });
    }

    /**
     * Recalculate the denormalized attendance counter from the attendance ledger.
     */
    public function recalculate(Enrollment $enrollment): int
    {
        return AttendanceRecord::query()
            ->where('enrollment_id', $enrollment->getKey())
            ->whereIn('status', ['presente', 'tardanza'])
            ->count();
    }

    /**
     * Ensure no academic attendance changes are made after acta closure.
     */
    public function assertCourseOpen(Course $course): void
    {
        if ($course->isActaClosed()) {
            throw ValidationException::withMessages([
                'course' => 'El acta oficial está cerrada. Reabra el acta antes de modificar asistencias.',
            ]);
        }
    }

    private function assertRecordable(Course $course, Enrollment $enrollment, int $session, string $status): void
    {
        $this->assertCourseOpen($course);

        if ($enrollment->course_id !== $course->getKey()) {
            throw ValidationException::withMessages([
                'enrollment' => 'La matrícula no pertenece a esta capacitación.',
            ]);
        }

        if ($session < 1 || $session > $this->totalSessions($course)) {
            throw ValidationException::withMessages([
                'session' => "El número de sesión debe estar entre 1 y {$this->totalSessions($course)}.",
            ]);
        }

        if (! in_array($status, ['presente', 'tardanza', 'falta'], true)) {
            throw ValidationException::withMessages([
                'status' => 'El estado de asistencia no es válido.',
            ]);
        }
    }

    private function createRecord(
        Course $course,
        Enrollment $enrollment,
        int $session,
        string $status,
        string $method,
        int|string|null $recordedBy,
        ?CarbonInterface $recordedAt,
    ): ?AttendanceRecord {
        $alreadyRecorded = AttendanceRecord::query()
            ->where('enrollment_id', $enrollment->getKey())
            ->where('session_number', $session)
            ->lockForUpdate()
            ->exists();

        if ($alreadyRecorded) {
            return null;
        }

        $record = AttendanceRecord::create([
            'course_id' => $course->getKey(),
            'enrollment_id' => $enrollment->getKey(),
            'session_number' => $session,
            'status' => $status,
            'method' => $method,
            'recorded_at' => $recordedAt ?? now(),
            'recorded_by' => $recordedBy !== null ? (int) $recordedBy : null,
        ]);

        $enrollment->update([
            'attended_sessions' => $this->recalculate($enrollment),
            'status' => $enrollment->status === 'inscrito' ? 'en_curso' : $enrollment->status,
        ]);

        return $record;
    }

    private function totalSessions(Course $course): int
    {
        return max(1, (int) ($course->total_sessions ?: 4));
    }
}
