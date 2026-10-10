<?php

namespace App\Http\Controllers;

use App\Enums\CourseStatus;
use App\Enums\UserRole;
use App\Http\Requests\StoreCourseRequest;
use App\Http\Requests\UpdateCourseRequest;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\CertificateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CourseController extends Controller
{
    /**
     * Generar un QR estándar con el enlace público de la capacitación.
     */
    public function courseQr(Course $course): JsonResponse
    {
        $url = route('courses.show', $course);

        return response()->json([
            'url' => $url,
            'svg' => CertificateService::generateQrSvg($url, 260),
        ]);
    }

    /**
     * Display a listing of courses.
     */
    public function index(Request $request): Response
    {
        $filters = [
            'search' => $request->query('search'),
            'status' => $request->query('status', 'all'),
        ];

        $courses = Course::query()
            ->with(['instructor:id,name,paterno,materno,email,role'])
            ->withCount(['enrollments' => fn ($q) => $q->where('status', '!=', 'cancelado')])
            ->filter($filters)
            ->latest('id')
            ->paginate(9)
            ->withQueryString();

        $statuses = array_map(fn (CourseStatus $status) => [
            'value' => $status->value,
            'label' => $status->label(),
        ], CourseStatus::cases());

        $user = $request->user();
        $myEnrolledCourseIds = [];
        if ($user) {
            $myEnrolledCourseIds = Enrollment::where('user_id', $user->id)
                ->when($user->dni, fn ($q) => $q->orWhere('dni', $user->dni))
                ->where('status', '!=', 'cancelado')
                ->pluck('course_id')
                ->all();
        }

        return Inertia::render('courses/Index', [
            'courses' => $courses,
            'filters' => $filters,
            'statuses' => $statuses,
            'myEnrolledCourseIds' => $myEnrolledCourseIds,
            'can' => [
                'create' => $user ? ($user->isAdmin() || $user->isDocente()) : false,
            ],
        ]);
    }

    /**
     * Show the form for creating a new course.
     */
    public function create(Request $request): Response|RedirectResponse
    {
        if (! $request->user()) {
            return redirect()->route('login')->with('warning', 'Inicie sesión como Administrador o Docente para registrar una capacitación.');
        }

        Gate::authorize('create', Course::class);

        $instructors = User::query()
            ->whereIn('role', [UserRole::Admin, UserRole::Docente])
            ->orderBy('name')
            ->get(['id', 'name', 'paterno', 'materno', 'email', 'role']);

        $statuses = array_map(fn (CourseStatus $status) => [
            'value' => $status->value,
            'label' => $status->label(),
        ], CourseStatus::cases());

        $year = date('Y');
        $prefix = "SIGC-{$year}-";
        $latest = Course::where('code', 'like', "{$prefix}%")
            ->orderByDesc('id')
            ->value('code');

        if ($latest && preg_match('/SIGC-\d{4}-(\d+)/', $latest, $matches)) {
            $nextNumber = ((int) $matches[1]) + 1;
        } else {
            $nextNumber = (Course::max('id') ?: 0) + 1;
        }

        $suggestedCode = $prefix.str_pad((string) $nextNumber, 3, '0', STR_PAD_LEFT);

        return Inertia::render('courses/Create', [
            'instructors' => $instructors,
            'statuses' => $statuses,
            'suggestedCode' => $suggestedCode,
        ]);
    }

    /**
     * Store a newly created course in database.
     */
    public function store(StoreCourseRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        if (empty($validated['code'])) {
            $year = date('Y');
            $prefix = "SIGC-{$year}-";
            $latest = Course::where('code', 'like', "{$prefix}%")
                ->orderByDesc('id')
                ->value('code');

            if ($latest && preg_match('/SIGC-\d{4}-(\d+)/', $latest, $matches)) {
                $nextNumber = ((int) $matches[1]) + 1;
            } else {
                $nextNumber = (Course::max('id') ?: 0) + 1;
            }

            $validated['code'] = $prefix.str_pad((string) $nextNumber, 3, '0', STR_PAD_LEFT);
        }

        if (empty($validated['instructor_id'])) {
            $validated['instructor_id'] = null;
        } elseif (empty($validated['instructor_name'])) {
            $user = User::find($validated['instructor_id']);
            if ($user instanceof User) {
                $validated['instructor_name'] = trim("{$user->name} {$user->paterno} {$user->materno}") ?: $user->name;
            }
        }

        Course::create($validated);

        return redirect()->route('courses.index')->with('success', 'Capacitación creada exitosamente.');
    }

    /**
     * Display the specified course.
     */
    public function show(Request $request, Course $course): Response
    {
        $user = $request->user();
        $isStaff = $user && ($user->isAdmin() || ($user->isDocente() && $course->instructor_id === $user->id));

        $course->loadCount(['enrollments' => fn ($q) => $q->where('status', '!=', 'cancelado')]);

        $instructorFields = $isStaff
            ? 'id,name,paterno,materno,email,role,dni'
            : 'id,name,paterno,materno,email,role';

        $course->load([
            'instructor:'.$instructorFields,
        ]);

        $myEnrollment = null;

        if ($isStaff) {
            // SOLO personal docente responsable o administrador ve la lista completa de matriculados
            $course->load([
                'enrollments' => function ($query) {
                    $query->with('attendanceRecords')->orderBy('paterno')->orderBy('nombres');
                },
                'actaCloser:id,name,paterno',
            ]);
        } else {
            // SEGURIDAD Y PRIVACIDAD ACADÉMICA:
            // Para el público general y visitantes: NUNCA enviar la lista nominal ni datos de otros participantes
            $course->setRelation('enrollments', collect([]));

            if ($user) {
                $myEnrollmentModel = Enrollment::with('attendanceRecords')
                    ->where('course_id', $course->id)
                    ->where(function ($q) use ($user) {
                        $q->where('user_id', $user->id);
                        if ($user->dni) {
                            $q->orWhere('dni', $user->dni);
                        }
                    })
                    ->first();

                if ($myEnrollmentModel) {
                    $totalSessions = max(1, $course->total_sessions);
                    $attendedCount = $myEnrollmentModel->attendanceRecords->whereIn('status', ['presente', 'tardanza'])->count();
                    $myEnrollmentModel->attendance_percentage = round(($attendedCount / $totalSessions) * 100);

                    $certificatePayload = null;
                    if ($myEnrollmentModel->status === 'aprobado' || $myEnrollmentModel->certificate_code) {
                        $certificatePayload = CertificateService::getCertificatePayload($myEnrollmentModel);
                    }

                    $myEnrollment = [
                        'id' => $myEnrollmentModel->id,
                        'dni' => $myEnrollmentModel->dni,
                        'nombres' => $myEnrollmentModel->nombres,
                        'paterno' => $myEnrollmentModel->paterno,
                        'materno' => $myEnrollmentModel->materno,
                        'full_name' => $myEnrollmentModel->full_name,
                        'email' => $myEnrollmentModel->email,
                        'phone' => $myEnrollmentModel->phone,
                        'status' => $myEnrollmentModel->status,
                        'attended_sessions' => $myEnrollmentModel->attended_sessions,
                        'attendance_percentage' => $myEnrollmentModel->attendance_percentage,
                        'final_grade' => $myEnrollmentModel->final_grade,
                        'credential_code' => $myEnrollmentModel->credential_code,
                        'credential_qr_svg' => CertificateService::generateQrSvg($myEnrollmentModel->credential_code, 260),
                        'certificate_code' => $myEnrollmentModel->certificate_code,
                        'certificate_hash' => $myEnrollmentModel->certificate_hash,
                        'certificate_issued_at' => $myEnrollmentModel->certificate_issued_at?->format('d/m/Y'),
                        'certificate' => $certificatePayload,
                    ];
                }
            }
        }

        $modules = CertificateService::getCourseModules($course);

        return Inertia::render('courses/Show', [
            'course' => $course,
            'isStaff' => $isStaff,
            'myEnrollment' => $myEnrollment,
            'modules' => $modules,
            'can' => [
                'update' => $user?->isAdmin() ?? false,
                'delete' => $user?->isAdmin() ?? false,
                'manage_enrollments' => $isStaff,
                'close_acta' => $isStaff,
                'issue_certificates' => $isStaff,
                'reopen_acta' => $user?->isAdmin() ?? false,
            ],
        ]);
    }

    /**
     * Show the form for editing the course.
     */
    public function edit(Course $course): Response
    {
        Gate::authorize('update', $course);

        $instructors = User::query()
            ->whereIn('role', [UserRole::Admin, UserRole::Docente])
            ->orderBy('name')
            ->get(['id', 'name', 'paterno', 'materno', 'email', 'role']);

        $statuses = array_map(fn (CourseStatus $status) => [
            'value' => $status->value,
            'label' => $status->label(),
        ], CourseStatus::cases());

        return Inertia::render('courses/Edit', [
            'course' => $course,
            'instructors' => $instructors,
            'statuses' => $statuses,
        ]);
    }

    /**
     * Update the specified course in database.
     */
    public function update(UpdateCourseRequest $request, Course $course): RedirectResponse
    {
        $validated = $request->validated();

        if (empty($validated['instructor_id'])) {
            $validated['instructor_id'] = null;
        } elseif (empty($validated['instructor_name'])) {
            $user = User::find($validated['instructor_id']);
            if ($user instanceof User) {
                $validated['instructor_name'] = trim("{$user->name} {$user->paterno} {$user->materno}") ?: $user->name;
            }
        }

        $course->update($validated);

        return redirect()->route('courses.show', $course)->with('success', 'Capacitación actualizada exitosamente.');
    }

    /**
     * Remove the specified course from database.
     */
    public function destroy(Course $course): RedirectResponse
    {
        Gate::authorize('delete', $course);

        if ($course->isActaClosed()) {
            throw ValidationException::withMessages([
                'course' => 'No se puede eliminar una capacitación que ya cuenta con acta oficial cerrada. Anule o archive el curso en su lugar.',
            ]);
        }

        $hasIssuedCertificates = $course->enrollments()
            ->where(function ($query): void {
                $query->whereNotNull('certificate_code')
                    ->orWhereNotNull('certificate_issued_at');
            })
            ->exists();

        if ($hasIssuedCertificates) {
            throw ValidationException::withMessages([
                'course' => 'No se puede eliminar una capacitación con certificados oficiales emitidos. Anule o archive el curso en su lugar.',
            ]);
        }

        $course->delete();

        return redirect()->route('courses.index')->with('success', 'Capacitación eliminada exitosamente.');
    }
}
