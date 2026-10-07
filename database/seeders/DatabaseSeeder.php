<?php

namespace Database\Seeders;

use App\Enums\CourseStatus;
use App\Enums\UserRole;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Administrador (Líder Técnico)
        $admin = User::firstOrCreate(
            ['email' => 'heall2099@gmail.com'],
            [
                'name' => 'Franki',
                'paterno' => 'Choquenaira',
                'materno' => 'Quispe',
                'dni' => '13396200',
                'role' => UserRole::Admin,
                'email_verified_at' => now(),
                'password' => 'password',
            ]
        );

        // 2. Docente 1
        $docente1 = User::firstOrCreate(
            ['email' => 'docente@sigc.unsaac.edu.pe'],
            [
                'name' => 'Aldo',
                'paterno' => 'Yaranga',
                'materno' => 'Achahui',
                'dni' => '10317900',
                'role' => UserRole::Docente,
                'email_verified_at' => now(),
                'password' => 'password',
            ]
        );

        // 3. Docente 2
        $docente2 = User::firstOrCreate(
            ['email' => 'carolay@sigc.unsaac.edu.pe'],
            [
                'name' => 'Carolay',
                'paterno' => 'Ccama',
                'materno' => 'Enriquez',
                'dni' => '21092100',
                'role' => UserRole::Docente,
                'email_verified_at' => now(),
                'password' => 'password',
            ]
        );

        // 4. Participante / Alumno
        User::firstOrCreate(
            ['email' => 'alumno@sigc.unsaac.edu.pe'],
            [
                'name' => 'Juan',
                'paterno' => 'Perez',
                'materno' => 'Condori',
                'dni' => '70123456',
                'role' => UserRole::Participante,
                'email_verified_at' => now(),
                'password' => 'password',
            ]
        );

        // 5. Cursos de ejemplo
        Course::firstOrCreate(
            ['code' => 'SIGC-2026-001'],
            [
                'title' => 'Desarrollo Web Moderno con Laravel 13 e Inertia Vue',
                'institution' => 'Colegio de Ingenieros del Perú - CD Cusco',
                'description' => 'Aprende a construir aplicaciones web completas utilizando la arquitectura monolítica moderna con Laravel 13, Inertia v3 y Vue 3.',
                'instructor_id' => $docente1->id,
                'start_date' => now()->addDays(5)->format('Y-m-d'),
                'end_date' => now()->addDays(25)->format('Y-m-d'),
                'hours' => 40,
                'capacity' => 30,
                'status' => CourseStatus::Abierto,
            ]
        );

        Course::firstOrCreate(
            ['code' => 'SIGC-2026-002'],
            [
                'title' => 'Ciberseguridad y Ethical Hacking para Redes Corporativas',
                'institution' => 'Cámara de Comercio de Cusco & Ciberdefensa',
                'description' => 'Taller intensivo sobre auditoría de seguridad, análisis de vulnerabilidades y defensa perimetral.',
                'instructor_id' => $docente2->id,
                'start_date' => now()->addDays(10)->format('Y-m-d'),
                'end_date' => now()->addDays(30)->format('Y-m-d'),
                'hours' => 30,
                'capacity' => 25,
                'status' => CourseStatus::Abierto,
            ]
        );

        $course3 = Course::firstOrCreate(
            ['code' => 'SIGC-2026-003'],
            [
                'title' => 'Gestión Ágil de Proyectos con Scrum, Kanban y XP',
                'institution' => 'Dirección Regional de Educación Cusco',
                'description' => 'Metodologías ágiles aplicadas al desarrollo de software en entornos académicos e industriales.',
                'instructor_id' => $admin->id,
                'start_date' => now()->subDays(10)->format('Y-m-d'),
                'end_date' => now()->addDays(10)->format('Y-m-d'),
                'hours' => 20,
                'capacity' => 40,
                'status' => CourseStatus::EnCurso,
            ]
        );

        // 6. Matrículas de ejemplo y certificados oficiales
        $course1 = Course::where('code', 'SIGC-2026-001')->first();

        // 6.1 Franki Choquenaira (Admin matriculado y aprobado con 20)
        Enrollment::firstOrCreate(
            [
                'course_id' => $course1->id,
                'dni' => '13396200',
            ],
            [
                'user_id' => $admin->id,
                'nombres' => 'FRANKI',
                'paterno' => 'CHOQUENAIRA',
                'materno' => 'QUISPE',
                'email' => 'heall2099@gmail.com',
                'phone' => '984123456',
                'status' => 'aprobado',
                'attended_sessions' => 12,
                'final_grade' => 20.00,
                'certificate_code' => 'CERT-2026-13396200',
            ]
        );

        // 6.2 Juan Carlos Pérez (Participante aprobado con 18)
        Enrollment::firstOrCreate(
            [
                'course_id' => $course1->id,
                'dni' => '70123456',
            ],
            [
                'user_id' => User::where('email', 'alumno@sigc.unsaac.edu.pe')->first()->id,
                'nombres' => 'JUAN CARLOS',
                'paterno' => 'PEREZ',
                'materno' => 'CONDORI',
                'email' => 'alumno@sigc.unsaac.edu.pe',
                'phone' => '984000111',
                'status' => 'aprobado',
                'attended_sessions' => 10,
                'final_grade' => 18.00,
                'certificate_code' => 'CERT-2026-70123456',
            ]
        );

        // 6.3 María Elena Quispe
        $mariaUser = User::firstOrCreate(
            ['dni' => '45678901'],
            [
                'name' => 'María Elena',
                'paterno' => 'Quispe',
                'materno' => 'Mamani',
                'email' => 'mquispe@unsaac.edu.pe',
                'role' => UserRole::Participante,
                'password' => 'password',
                'email_verified_at' => now(),
            ]
        );

        Enrollment::firstOrCreate(
            [
                'course_id' => $course1->id,
                'dni' => '45678901',
            ],
            [
                'user_id' => $mariaUser->id,
                'nombres' => 'MARIA ELENA',
                'paterno' => 'QUISPE',
                'materno' => 'MAMANI',
                'email' => 'mquispe@unsaac.edu.pe',
                'phone' => '984555888',
                'status' => 'aprobado',
                'attended_sessions' => 11,
                'final_grade' => 19.00,
                'certificate_code' => 'CERT-2026-45678901',
            ]
        );

        // 6.4 Rosa Luz Huamán
        $rosaUser = User::firstOrCreate(
            ['dni' => '48920134'],
            [
                'name' => 'Rosa Luz',
                'paterno' => 'Huamán',
                'materno' => 'Flores',
                'email' => 'rhuaman@unsaac.edu.pe',
                'role' => UserRole::Participante,
                'password' => 'password',
                'email_verified_at' => now(),
            ]
        );

        Enrollment::firstOrCreate(
            [
                'course_id' => $course1->id,
                'dni' => '48920134',
            ],
            [
                'user_id' => $rosaUser->id,
                'nombres' => 'ROSA LUZ',
                'paterno' => 'HUAMAN',
                'materno' => 'FLORES',
                'email' => 'rhuaman@unsaac.edu.pe',
                'phone' => '984333222',
                'status' => 'aprobado',
                'attended_sessions' => 10,
                'final_grade' => 17.50,
                'certificate_code' => 'CERT-2026-48920134',
            ]
        );

        // 6.5 Carlos Alberto Mendoza (Inscrito activo)
        Enrollment::firstOrCreate(
            [
                'course_id' => $course1->id,
                'dni' => '41239876',
            ],
            [
                'user_id' => null,
                'nombres' => 'CARLOS ALBERTO',
                'paterno' => 'MENDOZA',
                'materno' => 'PAREDES',
                'email' => 'cmendoza@gmail.com',
                'phone' => '984999111',
                'status' => 'inscrito',
                'attended_sessions' => 3,
                'final_grade' => null,
                'certificate_code' => null,
            ]
        );

        // 6.6 Inscripción en Curso 3
        Enrollment::firstOrCreate(
            [
                'course_id' => $course3->id,
                'dni' => '70123456',
            ],
            [
                'user_id' => User::where('email', 'alumno@sigc.unsaac.edu.pe')->first()->id,
                'nombres' => 'JUAN CARLOS',
                'paterno' => 'PEREZ',
                'materno' => 'CONDORI',
                'email' => 'alumno@sigc.unsaac.edu.pe',
                'phone' => '984000111',
                'status' => 'en_curso',
                'attended_sessions' => 5,
            ]
        );
    }
}
