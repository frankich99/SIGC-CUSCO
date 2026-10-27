<?php

namespace Database\Seeders;

use App\Enums\CourseStatus;
use App\Enums\MunicipalUnit;
use App\Enums\UserRole;
use App\Models\AttendanceRecord;
use App\Models\Course;
use App\Models\CourseMaterial;
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
                'organizational_unit' => MunicipalUnit::OTI->value,
                'position' => 'Líder Técnico OTI',
                'employment_regime' => 'CAS',
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
                'organizational_unit' => MunicipalUnit::OTI->value,
                'position' => 'Especialista en Infraestructura OTI',
                'employment_regime' => 'CAS',
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
                'organizational_unit' => MunicipalUnit::SOMA->value,
                'position' => 'Coordinadora SOMA',
                'employment_regime' => 'D.L. 728',
                'email_verified_at' => now(),
                'password' => 'password',
            ]
        );

        // 4. Participante / Alumno
        $alumno = User::firstOrCreate(
            ['email' => 'alumno@sigc.unsaac.edu.pe'],
            [
                'name' => 'Juan',
                'paterno' => 'Perez',
                'materno' => 'Condori',
                'dni' => '70123456',
                'role' => UserRole::Participante,
                'organizational_unit' => MunicipalUnit::RRHH->value,
                'position' => 'Asistente de Recursos Humanos',
                'employment_regime' => 'CAS',
                'email_verified_at' => now(),
                'password' => 'password',
            ]
        );

        // 5. Cursos de ejemplo adaptados al PDP de San Jerónimo
        $course1 = Course::firstOrCreate(
            ['code' => 'SIGC-2026-001'],
            [
                'pdp_code' => 'PDP-2026-001',
                'pdp_year' => 2026,
                'pdp_category' => 'Competencias Digitales y Gestión Documental',
                'modality' => 'presencial',
                'course_type' => 'interno',
                'title' => 'Desarrollo Web Moderno con Laravel 13 e Inertia Vue',
                'institution' => 'Municipalidad Distrital de San Jerónimo',
                'description' => 'Aprende a construir aplicaciones web completas utilizando la arquitectura monolítica moderna con Laravel 13, Inertia v3 y Vue 3.',
                'instructor_id' => $docente1->id,
                'start_date' => now()->addDays(5)->format('Y-m-d'),
                'end_date' => now()->addDays(25)->format('Y-m-d'),
                'hours' => 40,
                'total_sessions' => 4,
                'capacity' => 30,
                'budget_allocated' => 3500.00,
                'budget_executed' => 2800.00,
                'status' => CourseStatus::Abierto,
            ]
        );

        $course2 = Course::firstOrCreate(
            ['code' => 'SIGC-2026-002'],
            [
                'pdp_code' => 'PDP-2026-002',
                'pdp_year' => 2026,
                'pdp_category' => 'Seguridad y Salud en el Trabajo (SOMA)',
                'modality' => 'presencial',
                'course_type' => 'interno',
                'title' => 'Seguridad en el Trabajo, Primeros Auxilios y Ergonomía Municipal',
                'institution' => 'Municipalidad Distrital de San Jerónimo',
                'description' => 'Taller obligatorio SOMA de prevención de riesgos laborales y ergonomía para servidores de todas las áreas municipales.',
                'instructor_id' => $docente2->id,
                'start_date' => now()->addDays(10)->format('Y-m-d'),
                'end_date' => now()->addDays(30)->format('Y-m-d'),
                'hours' => 30,
                'total_sessions' => 4,
                'capacity' => 45,
                'budget_allocated' => 2500.00,
                'budget_executed' => 2000.00,
                'status' => CourseStatus::Abierto,
            ]
        );

        $course3 = Course::firstOrCreate(
            ['code' => 'SIGC-2026-003'],
            [
                'pdp_code' => 'PDP-2026-003',
                'pdp_year' => 2026,
                'pdp_category' => 'Gestión Pública y Cero Papel',
                'modality' => 'virtual',
                'course_type' => 'interno',
                'virtual_meeting_url' => 'https://meet.google.com/mdsj-pdp-2026',
                'title' => 'Gestión Pública Digital, Cero Papel y Sistema de Trámite SGD',
                'institution' => 'Municipalidad Distrital de San Jerónimo',
                'description' => 'Capacitación oficial sobre interoperabilidad, firma digital y Sistema de Gestión Documental para servidores públicos.',
                'instructor_id' => $admin->id,
                'start_date' => now()->subDays(10)->format('Y-m-d'),
                'end_date' => now()->addDays(10)->format('Y-m-d'),
                'hours' => 20,
                'total_sessions' => 4,
                'capacity' => 60,
                'budget_allocated' => 1800.00,
                'budget_executed' => 1800.00,
                'status' => CourseStatus::EnCurso,
            ]
        );

        // 6. Matrículas de ejemplo y certificados oficiales
        $course1 = Course::where('code', 'SIGC-2026-001')->first();

        // 6.1 Franki Choquenaira (Admin matriculado y aprobado con 20)
        $enr1 = Enrollment::firstOrCreate(
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
                'organizational_unit' => MunicipalUnit::OTI->value,
                'position' => 'Líder Técnico OTI',
                'employment_regime' => 'CAS',
                'status' => 'aprobado',
                'attended_sessions' => 4,
                'final_grade' => 20.00,
                'certificate_code' => 'CERT-2026-13396200',
            ]
        );

        // 6.2 Juan Carlos Pérez (Participante aprobado con 18)
        $enr2 = Enrollment::firstOrCreate(
            [
                'course_id' => $course1->id,
                'dni' => '70123456',
            ],
            [
                'user_id' => $alumno->id,
                'nombres' => 'JUAN CARLOS',
                'paterno' => 'PEREZ',
                'materno' => 'CONDORI',
                'email' => 'alumno@sigc.unsaac.edu.pe',
                'phone' => '984000111',
                'organizational_unit' => MunicipalUnit::RRHH->value,
                'position' => 'Asistente de Recursos Humanos',
                'employment_regime' => 'CAS',
                'status' => 'aprobado',
                'attended_sessions' => 4,
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
                'organizational_unit' => MunicipalUnit::SOMA->value,
                'position' => 'Supervisora Ambiental',
                'employment_regime' => 'D.L. 728',
                'password' => 'password',
                'email_verified_at' => now(),
            ]
        );

        $enr3 = Enrollment::firstOrCreate(
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
                'organizational_unit' => MunicipalUnit::SOMA->value,
                'position' => 'Supervisora Ambiental',
                'employment_regime' => 'D.L. 728',
                'status' => 'aprobado',
                'attended_sessions' => 4,
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

        // 7. Materiales y Recursos Didácticos / Grabaciones
        CourseMaterial::firstOrCreate(
            [
                'course_id' => $course1->id,
                'title' => 'Manual de Arquitectura Limpia con Laravel 13 (MDSJ)',
            ],
            [
                'type' => 'documento',
                'url' => 'https://transparencia.munisanjeronimo.gob.pe/pdp/manual-laravel-mdsj.pdf',
                'description' => 'Guía técnica institucional de buenas prácticas de desarrollo para el área de TI.',
            ]
        );

        CourseMaterial::firstOrCreate(
            [
                'course_id' => $course1->id,
                'title' => 'Diapositivas Sesión 1: Inyección de Dependencias y Servicios Atómicos',
            ],
            [
                'type' => 'diapositiva',
                'url' => 'https://transparencia.munisanjeronimo.gob.pe/pdp/presentacion-modulo1.pdf',
                'description' => 'Presentación oficial proyectada en el auditorio municipal.',
            ]
        );

        CourseMaterial::firstOrCreate(
            [
                'course_id' => $course3->id,
                'title' => 'Grabación de Sesión Virtual: Cero Papel y Sistema de Trámite SGD',
            ],
            [
                'type' => 'video',
                'url' => 'https://youtube.com/watch?v=mdsj-pdp-demo',
                'description' => 'Grabación completa de la capacitación sincrónica transmitida por Google Meet.',
            ]
        );

        // 8. Control de Asistencia y Permanencia en Auditorio (Check-In y Check-Out)
        AttendanceRecord::firstOrCreate(
            [
                'course_id' => $course1->id,
                'enrollment_id' => $enr1->id,
                'session_number' => 1,
            ],
            [
                'status' => 'presente',
                'method' => 'scan_dni_auditorio',
                'check_in_at' => now()->setTime(8, 5, 0),
                'check_out_at' => now()->setTime(12, 10, 0),
                'minutes_attended' => 245,
                'recorded_at' => now(),
                'recorded_by' => $admin->id,
            ]
        );

        AttendanceRecord::firstOrCreate(
            [
                'course_id' => $course1->id,
                'enrollment_id' => $enr2->id,
                'session_number' => 1,
            ],
            [
                'status' => 'presente',
                'method' => 'scan_dni_auditorio',
                'check_in_at' => now()->setTime(8, 12, 0),
                'check_out_at' => now()->setTime(12, 5, 0),
                'minutes_attended' => 233,
                'recorded_at' => now(),
                'recorded_by' => $admin->id,
            ]
        );
    }
}
