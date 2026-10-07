<?php

use App\Http\Controllers\CourseAcademicController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\DniController;
use App\Http\Controllers\EnrollmentController;
use App\Models\Course;
use App\Models\Enrollment;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    $courses = Course::with('instructor:id,name,paterno,materno')
        ->withCount(['enrollments' => function ($query) {
            $query->where('status', '!=', 'cancelado');
        }])
        ->orderByRaw("FIELD(status, 'abierto', 'en_curso', 'concluido', 'cancelado')")
        ->latest('id')
        ->get();

    $stats = [
        'totalCourses' => Course::count(),
        'openCourses' => Course::where('status', 'abierto')->count(),
        'totalEnrolled' => Enrollment::where('status', '!=', 'cancelado')->count(),
    ];

    return Inertia::render('Welcome', [
        'courses' => $courses,
        'stats' => $stats,
    ]);
})->name('home');

Route::post('courses/{course}/enroll', [EnrollmentController::class, 'store'])->name('courses.enroll');

Route::resource('courses', CourseController::class);

Route::get('certificates', function (Request $request) {
    return Inertia::render('certificates/Index', [
        'initialDni' => (string) $request->query('dni', ''),
    ]);
})->name('certificates.index');

Route::get('certificados', function (Request $request) {
    return redirect()->route('certificates.index', array_filter(['dni' => $request->query('dni')]));
});

Route::get('api/dni/{dni}', [DniController::class, 'lookup'])->name('dni.lookup')->middleware('throttle:60,1');

Route::get('api/certificates/lookup', function (Request $request) {
    $dni = trim((string) $request->query('dni', ''));

    if (! preg_match('/^\d{8}$/', $dni)) {
        return response()->json([
            'success' => false,
            'message' => 'Ingrese un número de DNI válido de 8 dígitos.',
            'records' => [],
        ], 422);
    }

    // Solo certificaciones aprobadas o con código de certificado emitido para consulta pública
    $enrollments = Enrollment::with(['course.instructor:id,name,paterno,materno'])
        ->where('dni', $dni)
        ->where('status', '!=', 'cancelado')
        ->where(function ($q) {
            $q->where('status', 'aprobado')
                ->orWhereNotNull('certificate_code')
                ->orWhereNotNull('certificate_issued_at');
        })
        ->latest('id')
        ->get();

    return response()->json([
        'success' => true,
        'dni' => $dni,
        'records' => $enrollments->map(function ($e) {
            $formattedStart = $e->course?->start_date ? Carbon::parse($e->course->start_date)->format('d/m/Y') : null;
            $formattedEnd = $e->course?->end_date ? Carbon::parse($e->course->end_date)->format('d/m/Y') : null;

            return [
                'id' => $e->id,
                'course_code' => $e->course?->code,
                'course_title' => $e->course?->title,
                'institution' => $e->course?->institution ?? 'SIGC-CUSCO',
                'hours' => $e->course?->hours,
                'start_date' => $formattedStart,
                'end_date' => $formattedEnd,
                'instructor_name' => $e->course?->instructor_display_name ?? 'Ponente / Docente Asignado',
                'student_name' => $e->full_name,
                'status' => $e->status,
                'certificate_code' => $e->certificate_code ?? ('CERT-2026-UNSAAC-'.str_pad((string) $e->course_id, 3, '0', STR_PAD_LEFT).'-'.str_pad((string) $e->id, 4, '0', STR_PAD_LEFT)),
                'certificate_hash' => $e->certificate_hash,
                'certificate_issued_at' => $e->certificate_issued_at ? $e->certificate_issued_at->format('d/m/Y') : ($formattedEnd ?? date('d/m/Y')),
            ];
        }),
    ]);
})->name('certificates.lookup')->middleware('throttle:60,1');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        $user = Auth::user();

        $taughtCourses = [];
        $studentEnrollments = [];

        // Si es admin o docente (persona que gestiona/imparte capacitación)
        if ($user->isAdmin() || $user->isDocente()) {
            $query = Course::with('instructor:id,name,paterno,materno')
                ->withCount(['enrollments' => fn ($q) => $q->where('status', '!=', 'cancelado')]);

            if ($user->isDocente()) {
                $query->where('instructor_id', $user->id);
            }

            $taughtCourses = $query->latest('id')->take(6)->get();
        }

        // Si es participante o admin (persona que recibe capacitación)
        if ($user->isParticipante() || $user->isAdmin()) {
            $studentEnrollments = Enrollment::with(['course.instructor:id,name,paterno,materno'])
                ->where(function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                    if ($user->dni) {
                        $query->orWhere('dni', $user->dni);
                    }
                })
                ->latest('id')
                ->get();
        }

        $openCourses = Course::with('instructor:id,name,paterno,materno')
            ->withCount(['enrollments' => fn ($q) => $q->where('status', '!=', 'cancelado')])
            ->where('status', 'abierto')
            ->latest('id')
            ->take(6)
            ->get();

        return Inertia::render('Dashboard', [
            'metrics' => [
                'totalCourses' => Course::count(),
                'openCourses' => Course::where('status', 'abierto')->count(),
                'taughtCount' => $user->isDocente() ? Course::where('instructor_id', $user->id)->count() : Course::count(),
                'enrolledCount' => count($studentEnrollments),
            ],
            'taughtCourses' => $taughtCourses,
            'studentEnrollments' => $studentEnrollments,
            'openCourses' => $openCourses,
        ]);
    })->name('dashboard');

    Route::put('enrollments/{enrollment}', [EnrollmentController::class, 'update'])->name('enrollments.update');
    Route::delete('enrollments/{enrollment}', [EnrollmentController::class, 'destroy'])->name('enrollments.destroy');
    Route::post('enrollments/{enrollment}/attendance', [EnrollmentController::class, 'recordAttendance'])->name('enrollments.attendance');
    Route::post('enrollments/{enrollment}/certificate', [EnrollmentController::class, 'generateCertificate'])->name('enrollments.certificate');

    // Módulo Académico Oficial SIGC (Asistencia, Actas, Certificados en Lote, Reportes)
    Route::post('courses/{course}/sessions/{session}/attendance', [CourseAcademicController::class, 'recordSessionAttendance'])->name('courses.sessions.attendance');
    Route::post('courses/{course}/attendance/sync', [CourseAcademicController::class, 'syncOfflineAttendance'])->name('courses.attendance.sync');
    Route::post('courses/{course}/acta/close', [CourseAcademicController::class, 'closeActa'])->name('courses.acta.close');
    Route::post('courses/{course}/certificates/bulk-issue', [CourseAcademicController::class, 'bulkIssueCertificates'])->name('courses.certificates.bulk-issue');
    Route::get('courses/{course}/reports/attendance-csv', [CourseAcademicController::class, 'exportAttendanceCsv'])->name('courses.reports.attendance-csv');
    Route::get('courses/{course}/reports/acta-csv', [CourseAcademicController::class, 'exportActaCsv'])->name('courses.reports.acta-csv');
});

require __DIR__.'/settings.php';
