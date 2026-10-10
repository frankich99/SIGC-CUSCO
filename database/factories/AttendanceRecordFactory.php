<?php

namespace Database\Factories;

use App\Models\AttendanceRecord;
use App\Models\Course;
use App\Models\Enrollment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceRecord>
 */
class AttendanceRecordFactory extends Factory
{
    protected $model = AttendanceRecord::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'enrollment_id' => Enrollment::factory(),
            'session_number' => 1,
            'status' => 'presente',
            'method' => 'manual',
            'recorded_at' => now(),
            'recorded_by' => null,
        ];
    }
}
