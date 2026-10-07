<?php

namespace App\Http\Controllers;

use App\Enums\CourseStatus;
use App\Enums\UserRole;
use App\Http\Requests\StoreCourseRequest;
use App\Http\Requests\UpdateCourseRequest;
use App\Models\Course;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CourseController extends Controller
{
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
            ->filter($filters)
            ->latest('id')
            ->paginate(9)
            ->withQueryString();

        $statuses = array_map(fn (CourseStatus $status) => [
            'value' => $status->value,
            'label' => $status->label(),
        ], CourseStatus::cases());

        return Inertia::render('courses/Index', [
            'courses' => $courses,
            'filters' => $filters,
            'statuses' => $statuses,
            'can' => [
                'create' => $request->user() ? ($request->user()->isAdmin() || $request->user()->isDocente()) : true,
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

        $suggestedCode = 'SIGC-'.date('Y').'-'.str_pad((string) (Course::max('id') + 1), 3, '0', STR_PAD_LEFT);

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
            $validated['code'] = 'SIGC-'.date('Y').'-'.str_pad((string) (Course::max('id') + 1), 3, '0', STR_PAD_LEFT);
        }

        if (empty($validated['instructor_id'])) {
            $validated['instructor_id'] = null;
        } elseif (empty($validated['instructor_name'])) {
            $user = User::find($validated['instructor_id']);
            if ($user) {
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
        $course->load([
            'instructor:id,name,paterno,materno,email,role,dni',
            'enrollments' => function ($query) {
                $query->orderBy('paterno')->orderBy('nombres');
            },
        ]);

        $course->loadCount(['enrollments' => fn ($q) => $q->where('status', '!=', 'cancelado')]);

        $user = $request->user();
        $isStaff = $user && ($user->isAdmin() || ($user->isDocente() && $course->instructor_id === $user->id));

        return Inertia::render('courses/Show', [
            'course' => $course,
            'can' => [
                'update' => $user?->isAdmin() ?? false,
                'delete' => $user?->isAdmin() ?? false,
                'manage_enrollments' => $isStaff,
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
            if ($user) {
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

        $course->delete();

        return redirect()->route('courses.index')->with('success', 'Capacitación eliminada exitosamente.');
    }
}
