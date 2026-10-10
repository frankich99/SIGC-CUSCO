<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Enrollment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Enrollment>
 */
class EnrollmentFactory extends Factory
{
    protected $model = Enrollment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'user_id' => null,
            'credential_code' => 'INS-'.fake()->numerify('####-####'),
            'dni' => fake()->unique()->numerify('########'),
            'nombres' => mb_strtoupper(fake()->firstName()),
            'paterno' => mb_strtoupper(fake()->lastName()),
            'materno' => mb_strtoupper(fake()->lastName()),
            'email' => fake()->safeEmail(),
            'phone' => '9'.fake()->numerify('########'),
            'status' => 'inscrito',
            'attended_sessions' => 0,
            'final_grade' => null,
            'certificate_code' => null,
            'certificate_hash' => null,
            'certificate_issued_at' => null,
        ];
    }
}
