<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Enrollment;
use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Carbon;

class CertificateService
{
    /**
     * Convierte la nota numérica a su representación oficial en texto académico.
     */
    public static function formatGradeText(?float $grade): string
    {
        if ($grade === null) {
            return 'Acreditado por Participación';
        }

        $rounded = (int) round($grade);

        $gradeTexts = [
            20 => 'Veinte (20) - Sobresaliente con Excelencia',
            19 => 'Diecinueve (19) - Sobresaliente',
            18 => 'Dieciocho (18) - Distinguido',
            17 => 'Diecisiete (17) - Muy Bueno',
            16 => 'Dieciséis (16) - Bueno',
            15 => 'Quince (15) - Aprobado',
            14 => 'Catorce (14) - Aprobado',
            13 => 'Trece (13) - Regular',
            12 => 'Doce (12) - Regular',
            11 => 'Once (11) - Regular',
            10 => 'Diez (10) - Desaprobado',
        ];

        return $gradeTexts[$rounded] ?? number_format($grade, 2);
    }

    /**
     * Genera el SVG del código QR permanente para verificación.
     */
    public static function generateQrSvg(string $url, int $size = 200): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size, 1, null, null, Fill::uniformColor(new Rgb(255, 255, 255), new Rgb(15, 23, 42))),
            new SvgImageBackEnd
        );

        $writer = new Writer($renderer);
        $svg = $writer->writeString($url);

        // Limpiar encabezado XML si está presente para inserción directa en HTML
        return (string) preg_replace('/<\?xml.*?\?>/i', '', $svg);
    }

    /**
     * Obtiene los módulos curriculares estructurados para el reverso del certificado.
     *
     * @return array<int, array{number: string, title: string, hours: string, topics: string}>
     */
    public static function getCourseModules(Course $course): array
    {
        $title = $course->title;
        $hoursPerModule = max(4, (int) round(($course->hours ?: 40) / 4));

        if (stripos($title, 'Laravel') !== false || stripos($title, 'Web') !== false) {
            return [
                [
                    'number' => 'MÓDULO I',
                    'title' => 'Arquitectura de Software y Fundamentos de Laravel 13',
                    'hours' => "{$hoursPerModule} hrs.",
                    'topics' => 'Ecosistema moderno PHP 8.3, Inyección de dependencias, Service Providers, Enrutamiento tipado y Migraciones robustas.',
                ],
                [
                    'number' => 'MÓDULO II',
                    'title' => 'Inertia.js v3 y Reactividad Moderna con Vue 3',
                    'hours' => "{$hoursPerModule} hrs.",
                    'topics' => 'Single Page Applications sin API REST intermedia, Composition API, Gestión de estados reactivos y Optimistic UI.',
                ],
                [
                    'number' => 'MÓDULO III',
                    'title' => 'Seguridad Corporativa, Autenticación Fortify y Políticas',
                    'hours' => "{$hoursPerModule} hrs.",
                    'topics' => 'Two-Factor Authentication (2FA), Protección CSRF/XSS, Laravel Fortify headless, Gates, Policies y Roles RBAC.',
                ],
                [
                    'number' => 'MÓDULO IV',
                    'title' => 'Testing Automatizado con Pest, Rendimiento y Despliegue',
                    'hours' => "{$hoursPerModule} hrs.",
                    'topics' => 'Pruebas unitarias e integrales en Pest, Optimización de consultas Eloquent, Caché distribuido y Despliegue continuo.',
                ],
            ];
        }

        if (stripos($title, 'Ciberseguridad') !== false || stripos($title, 'Hacking') !== false) {
            return [
                [
                    'number' => 'MÓDULO I',
                    'title' => 'Fundamentos de Ciberseguridad y Gestión de Vulnerabilidades',
                    'hours' => "{$hoursPerModule} hrs.",
                    'topics' => 'Normativas ISO 27001, Modelo MITRE ATT&CK, Superficie de ataque y Análisis de riesgos en infraestructura.',
                ],
                [
                    'number' => 'MÓDULO II',
                    'title' => 'Reconocimiento, Escaneo y Técnicas de Ethical Hacking',
                    'hours' => "{$hoursPerModule} hrs.",
                    'topics' => 'Fase de reconocimiento OSINT, Auditoría de puertos con Nmap, Detección de servicios y Escáneres automatizados.',
                ],
                [
                    'number' => 'MÓDULO III',
                    'title' => 'Seguridad Perimetral, Redes Corporativas y Firewalls',
                    'hours' => "{$hoursPerModule} hrs.",
                    'topics' => 'Segmentación VLAN, IDS/IPS, Reglas de filtrado iptables/pfSense y Mitigación de ataques DDoS y Spoofing.',
                ],
                [
                    'number' => 'MÓDULO IV',
                    'title' => 'Respuesta a Incidentes y Criptografía Aplicada',
                    'hours' => "{$hoursPerModule} hrs.",
                    'topics' => 'Preservación de evidencia digital, Criptografía simétrica/asimétrica, Certificados TLS/SSL y Hardening de servidores.',
                ],
            ];
        }

        if (stripos($title, 'Scrum') !== false || stripos($title, 'Ágil') !== false || stripos($title, 'Proyectos') !== false) {
            return [
                [
                    'number' => 'MÓDULO I',
                    'title' => 'Marcos de Trabajo Ágiles y Manifiesto de Software',
                    'hours' => "{$hoursPerModule} hrs.",
                    'topics' => 'Principios ágiles, Roles en Scrum (Product Owner, Scrum Master, Developers) y Fundamentos de Kanban y XP.',
                ],
                [
                    'number' => 'MÓDULO II',
                    'title' => 'Gestión de Product Backlog y Estimación de Historias',
                    'hours' => "{$hoursPerModule} hrs.",
                    'topics' => 'User Stories, Criterios de Aceptación (DoD), Planning Poker, Priorización MoSCoW y Refinamiento continuo.',
                ],
                [
                    'number' => 'MÓDULO III',
                    'title' => 'Ejecución del Sprint, Ceremonias y Métricas Ágiles',
                    'hours' => "{$hoursPerModule} hrs.",
                    'topics' => 'Sprint Planning, Daily Standups, Gráficos Burndown/Burnup, Cálculo de Velocidad y Gestión visual del flujo WIP.',
                ],
                [
                    'number' => 'MÓDULO IV',
                    'title' => 'Sprint Review, Retrospectivas y Escalabilidad Organizacional',
                    'hours' => "{$hoursPerModule} hrs.",
                    'topics' => 'Demostración de incrementos con valor, Técnicas de retrospectiva efectiva, Dinámicas de equipo y Marcos escalados (SAFe/Nexus).',
                ],
            ];
        }

        if (stripos($title, 'Python') !== false || stripos($title, 'Datos') !== false) {
            return [
                [
                    'number' => 'MÓDULO I',
                    'title' => 'Fundamentos de Python y Manipulación Numérica',
                    'hours' => "{$hoursPerModule} hrs.",
                    'topics' => 'Estructuras de datos nativas, Operaciones vectorizadas con NumPy y Procesamiento tabular con Pandas.',
                ],
                [
                    'number' => 'MÓDULO II',
                    'title' => 'Limpieza, Transformación y Extracción de Datos (ETL)',
                    'hours' => "{$hoursPerModule} hrs.",
                    'topics' => 'Tratamiento de valores nulos, Normalización, Cruce de datasets complejos y Consultas optimizadas.',
                ],
                [
                    'number' => 'MÓDULO III',
                    'title' => 'Análisis Exploratorio y Visualización Interactiva',
                    'hours' => "{$hoursPerModule} hrs.",
                    'topics' => 'Gráficos estadísticos con Matplotlib y Seaborn, Dashboards analíticos y Análisis de correlaciones.',
                ],
                [
                    'number' => 'MÓDULO IV',
                    'title' => 'Modelado Predictivo y Análisis Estadístico Avanzado',
                    'hours' => "{$hoursPerModule} hrs.",
                    'topics' => 'Regresiones, Clasificadores básicos con Scikit-Learn, Métricas de precisión y Generación de reportes ejecutivos.',
                ],
            ];
        }

        // Módulos genéricos estructurados de alta calidad para cualquier otro curso universitario
        return [
            [
                'number' => 'MÓDULO I',
                'title' => 'Fundamentos Conceptuales y Marco Normativo Institucional',
                'hours' => "{$hoursPerModule} hrs.",
                'topics' => 'Introducción temática, Principios metodológicos, Estándares de calidad y Contexto aplicativo.',
            ],
            [
                'number' => 'MÓDULO II',
                'title' => 'Herramientas Técnicas y Desarrollo Práctico Especializado',
                'hours' => "{$hoursPerModule} hrs.",
                'topics' => 'Casos prácticos de estudio, Aplicación de metodologías especializadas y Talleres guiados.',
            ],
            [
                'number' => 'MÓDULO III',
                'title' => 'Implementación Avanzada y Resolución de Problemas',
                'hours' => "{$hoursPerModule} hrs.",
                'topics' => 'Evaluación de escenarios complejos, Buenas prácticas profesionales y Optimización de procesos.',
            ],
            [
                'number' => 'MÓDULO IV',
                'title' => 'Evaluación Integral, Síntesis y Acreditación de Competencias',
                'hours' => "{$hoursPerModule} hrs.",
                'topics' => 'Presentación de proyecto final, Evaluación de competencias adquiridas y Conclusiones del programa.',
            ],
        ];
    }

    /**
     * Construye el payload completo y enriquecido para un certificado oficial.
     *
     * @return array<string, mixed>
     */
    public static function getCertificatePayload(Enrollment $enrollment): array
    {
        $course = $enrollment->course;
        $formattedStart = $course?->start_date ? Carbon::parse($course->start_date)->format('d/m/Y') : null;
        $formattedEnd = $course?->end_date ? Carbon::parse($course->end_date)->format('d/m/Y') : null;

        // Asegurar código de certificado
        $certCode = $enrollment->certificate_code;
        if (empty($certCode)) {
            $year = date('Y');
            $certCode = "CERT-{$year}-UNSAAC-".str_pad((string) $enrollment->course_id, 3, '0', STR_PAD_LEFT).'-'.str_pad((string) $enrollment->id, 4, '0', STR_PAD_LEFT);
            $enrollment->certificate_code = $certCode;
        }

        // Asegurar hash criptográfico SHA-256
        $certHash = $enrollment->certificate_hash;
        if (empty($certHash)) {
            $courseCode = $course?->code ?? 'SIGC-001';
            $hours = $course?->hours ?? 40;
            $grade = $enrollment->final_grade ?? '20.00';
            $hashPayload = "SIGC-UNSAAC|{$certCode}|{$enrollment->dni}|{$enrollment->full_name}|{$courseCode}|{$hours}|{$grade}";
            $certHash = hash('sha256', $hashPayload);
            $enrollment->certificate_hash = $certHash;
        }

        if (empty($enrollment->certificate_issued_at)) {
            $enrollment->certificate_issued_at = now();
        }

        // Guardar si hubo cambios sin disparar eventos innecesarios
        if ($enrollment->isDirty(['certificate_code', 'certificate_hash', 'certificate_issued_at'])) {
            $enrollment->saveQuietly();
        }

        $issuedAt = $enrollment->certificate_issued_at
            ? $enrollment->certificate_issued_at->format('d/m/Y')
            : ($formattedEnd ?? date('d/m/Y'));

        // URL permanente que nunca expira para validación online directa
        $verificationUrl = url("/certificates?dni={$enrollment->dni}&code={$certCode}");

        // SVG QR nítido permanente
        $qrSvg = self::generateQrSvg($verificationUrl, 220);

        // Módulos
        $modules = $course ? self::getCourseModules($course) : [];

        $gradeNumeric = $enrollment->final_grade !== null ? (float) $enrollment->final_grade : 20.0;
        $gradeFormatted = number_format($gradeNumeric, 2);
        $gradeText = self::formatGradeText($gradeNumeric);

        return [
            'id' => $enrollment->id,
            'dni' => $enrollment->dni,
            'student_name' => $enrollment->full_name,
            'course_code' => $course?->code ?? 'SIGC-2026-001',
            'course_title' => $course?->title ?? 'Capacitación Oficial UNSAAC',
            'institution' => $course?->institution ?? 'UNSAAC - SIGC CUSCO',
            'hours' => $course?->hours ?? 40,
            'start_date' => $formattedStart ?? '01/01/2026',
            'end_date' => $formattedEnd ?? '31/01/2026',
            'instructor_name' => $course?->instructor_display_name ?? 'Docente Especialista Asignado',
            'instructor_title' => 'Docente Principal e Investigador',
            'status' => $enrollment->status,
            'final_grade' => $gradeFormatted,
            'final_grade_text' => $gradeText,
            'certificate_code' => $certCode,
            'certificate_hash' => $certHash,
            'certificate_issued_at' => $issuedAt,
            'verification_url' => $verificationUrl,
            'qr_svg' => $qrSvg,
            'modules' => $modules,
        ];
    }
}
