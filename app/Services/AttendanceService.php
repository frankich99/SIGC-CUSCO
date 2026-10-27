<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\Course;
use App\Models\Enrollment;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
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
     * Record or update permanence (Check-in / Check-out) for municipal auditoriums.
     *
     * @param  'check_in'|'check_out'|'auto'  $action
     * @return array{record: AttendanceRecord, action_performed: string, minutes: int}
     */
    public function recordPermanence(
        Course $course,
        Enrollment $enrollment,
        int $session,
        string $action = 'auto',
        string $method = 'scan_dni_pistola',
        int|string|null $recordedBy = null,
        ?CarbonInterface $timestamp = null,
    ): array {
        return DB::transaction(function () use ($course, $enrollment, $session, $action, $method, $recordedBy, $timestamp): array {
            $lockedCourse = Course::query()->whereKey($course->getKey())->lockForUpdate()->firstOrFail();
            $lockedEnrollment = Enrollment::query()->whereKey($enrollment->getKey())->lockForUpdate()->firstOrFail();

            $this->assertRecordable($lockedCourse, $lockedEnrollment, $session, 'presente');

            /** @var AttendanceRecord|null $record */
            $record = AttendanceRecord::query()
                ->where('enrollment_id', $lockedEnrollment->getKey())
                ->where('session_number', $session)
                ->lockForUpdate()
                ->first();

            $now = $timestamp ? Carbon::instance($timestamp) : now();
            $actualAction = $action;

            if ($action === 'auto') {
                $actualAction = (! $record || $record->check_in_at === null) ? 'check_in' : 'check_out';
            }

            if (! $record) {
                $record = AttendanceRecord::create([
                    'course_id' => $lockedCourse->getKey(),
                    'enrollment_id' => $lockedEnrollment->getKey(),
                    'session_number' => $session,
                    'status' => 'presente',
                    'method' => $method,
                    'check_in_at' => $actualAction === 'check_in' ? $now : null,
                    'check_out_at' => $actualAction === 'check_out' ? $now : null,
                    'minutes_attended' => 0,
                    'recorded_at' => $now,
                    'recorded_by' => $recordedBy !== null ? (int) $recordedBy : null,
                ]);
            } else {
                if ($actualAction === 'check_in') {
                    $record->check_in_at = $now;
                } else {
                    $record->check_out_at = $now;
                }

                if ($record->check_in_at && $record->check_out_at) {
                    $record->minutes_attended = max(0, (int) $record->check_in_at->diffInMinutes($record->check_out_at));
                }

                $record->method = $method;
                $record->save();
            }

            $lockedEnrollment->update([
                'attended_sessions' => $this->recalculate($lockedEnrollment),
                'status' => $lockedEnrollment->status === 'inscrito' ? 'en_curso' : $lockedEnrollment->status,
            ]);

            return [
                'record' => $record,
                'action_performed' => $actualAction,
                'minutes' => (int) $record->minutes_attended,
            ];
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

        $now = $recordedAt ?? now();

        $record = AttendanceRecord::create([
            'course_id' => $course->getKey(),
            'enrollment_id' => $enrollment->getKey(),
            'session_number' => $session,
            'status' => $status,
            'method' => $method,
            'check_in_at' => $now,
            'recorded_at' => $now,
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
