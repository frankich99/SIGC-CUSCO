<?php

namespace Database\Factories;

use App\Enums\CourseStatus;
use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
{
    protected $model = Course::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('+1 week', '+1 month');
        $endDate = (clone $startDate)->modify('+2 weeks');

        return [
            'code' => 'SIGC-'.fake()->unique()->numerify('2026-###'),
            'title' => fake()->randomElement([
                'Desarrollo de Aplicaciones Web con Laravel 13',
                'Arquitectura de Software y Patrones de Diseño',
                'Seguridad Informática y Ethical Hacking',
                'Inteligencia Artificial Aplicada a la Industria',
                'Gestión Ágil de Proyectos con Scrum y Kanban',
            ]),
            'description' => fake()->paragraph(),
            'instructor_id' => User::factory(),
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'hours' => fake()->randomElement([20, 30, 40, 60, 80]),
            'capacity' => fake()->numberBetween(15, 50),
            'status' => CourseStatus::Abierto,
        ];
    }
}
