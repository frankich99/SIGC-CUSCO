<?php

namespace App\Console\Commands;

use App\Models\AttendanceRecord;
use App\Models\Course;
use App\Models\Enrollment;
use App\Services\CertificateService;
use Illuminate\Console\Command;

class SanitizeAcademicDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'academic:sanitize';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sanear datos incoherentes, cursos de prueba toscos y alinear asistencias con actas sin tocar seeders';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Iniciando saneamiento de datos académicos...');

        // 1. Sanear Curso 4
        $c4 = Course::find(4);
        if ($c4) {
            $c4->update([
                'title' => 'Inteligencia Artificial Aplicada a la Ingeniería y Ciencia de Datos',
                'institution' => 'Facultad de Ingeniería Eléctrica, Electrónica, Informática y Mecánica - UNSAAC',
                'description' => 'Fundamentos prácticos de redes neuronales, machine learning aplicado con Python y análisis de grandes volúmenes de datos para soluciones tecnológicas de alto impacto.',
                'hours' => 40,
                'capacity' => 30,
                'total_sessions' => 4,
                'min_attendance_percentage' => 75,
                'status' => 'abierto',
            ]);
            $this->info('Curso 4 saneado: IA y Ciencia de Datos.');
        }

        // 2. Sanear Curso 5
        $c5 = Course::find(5);
        if ($c5) {
            $c5->update([
                'title' => 'Arquitectura Cloud y DevOps en Infraestructuras Corporativas',
                'institution' => 'Dirección de Tecnologías de Información y Comunicaciones - UNSAAC',
                'description' => 'Diseño e implementación de microservicios contenerizados con Docker y Kubernetes, canalizaciones CI/CD automatizadas y despliegue resiliente en la nube.',
                'hours' => 35,
                'capacity' => 25,
                'total_sessions' => 4,
                'min_attendance_percentage' => 75,
                'status' => 'abierto',
            ]);
            $this->info('Curso 5 saneado: Cloud y DevOps.');
        }

        // 3. Sanear Curso 6
        $c6 = Course::find(6);
        if ($c6) {
            $c6->update([
                'title' => 'Programación Orientada a Objetos y Estructuras de Datos Avanzadas',
                'institution' => 'Escuela Profesional de Ingeniería Informática y de Sistemas - UNSAAC',
                'description' => 'Principios de diseño de software SOLID, patrones de arquitectura, estructuras de datos no lineales y algoritmos eficientes para la ingeniería moderna.',
                'instructor_name' => 'Ing. Julio Pérez de la Sota',
                'hours' => 60,
                'capacity' => 25,
                'total_sessions' => 4,
                'min_attendance_percentage' => 75,
                'status' => 'abierto',
            ]);
            $this->info('Curso 6 saneado: POO y Estructuras de Datos.');
        }

        // 4. Actualizar estado de Curso 1 si tiene acta cerrada
        $c1 = Course::find(1);
        if ($c1 && $c1->isActaClosed()) {
            $c1->update(['status' => 'concluido']);
            $this->info('Curso 1 actualizado a "concluido" por acta cerrada.');
        }

        // 5. Normalizar sesiones asistidas de Curso 1 y sincronizar tabla attendance_records
        $course1Enrollments = [
            3 => 4,
            4 => 4,
            5 => 3,
            6 => 3,
        ];

        foreach ($course1Enrollments as $enrollmentId => $sessions) {
            $e = Enrollment::find($enrollmentId);
            if ($e) {
                $e->update(['attended_sessions' => $sessions]);
                for ($s = 1; $s <= $sessions; $s++) {
                    AttendanceRecord::firstOrCreate(
                        [
                            'course_id' => $e->course_id,
                            'enrollment_id' => $e->id,
                            'session_number' => $s,
                        ],
                        [
                            'status' => 'presente',
                            'method' => 'qr_proyeccion',
                            'recorded_at' => now(),
                            'recorded_by' => 1,
                        ]
                    );
                }
            }
        }
        $this->info('Asistencias y matriz de Curso 1 sincronizadas.');

        // 6. Armonizar certificados oficiales con CertificateService
        $approved = Enrollment::where('status', 'aprobado')->orWhereNotNull('certificate_code')->get();
        foreach ($approved as $e) {
            CertificateService::getCertificatePayload($e);
        }
        $this->info('Certificados alineados con código oficial y firma SHA-256.');

        $this->info('¡Saneamiento completado con éxito!');

        return Command::SUCCESS;
    }
}
