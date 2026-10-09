<?php

use App\Enums\CourseStatus;
use App\Models\Course;
use App\Models\Enrollment;
use Inertia\Testing\AssertableInertia as Assert;

test('guest can access public landing page with course catalog and stats', function () {
    $course = Course::factory()->create([
        'title' => 'Capacitación en Inteligencia Artificial y Datos',
        'status' => 'abierto',
    ]);

    $response = $this->get(route('home'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Welcome')
        ->has('courses')
        ->has('stats')
        ->where('stats.totalCourses', fn ($count) => $count >= 1)
        ->where('stats.openCourses', fn ($count) => $count >= 1)
    );
});

test('guest can access public course catalog at /courses', function () {
    Course::factory()->count(3)->create([
        'status' => 'abierto',
    ]);

    $response = $this->get(route('courses.index'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('courses/Index')
        ->has('courses.data')
        ->has('statuses')
    );
});

test('guest can access course detail page without leaking confidential enrollment lists', function () {
    $course = Course::factory()->create([
        'title' => 'Taller de Ciberseguridad y Redes',
        'status' => 'abierto',
    ]);

    // Crear inscritos en el curso
    Enrollment::create([
        'course_id' => $course->id,
        'dni' => '70000001',
        'nombres' => 'JUAN',
        'paterno' => 'QUISPE',
        'email' => 'juan@example.com',
        'status' => 'inscrito',
        'attended_sessions' => 0,
    ]);

    $response = $this->get(route('courses.show', $course));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('courses/Show')
        ->where('isStaff', false)
        ->where('myEnrollment', null)
        ->where('course.id', $course->id)
        ->where('course.title', 'Taller de Ciberseguridad y Redes')
        ->where('course.enrollments', []) // Protección de privacidad
    );
});

test('guest can access official certificate verification portal', function () {
    $response = $this->get(route('certificates.index'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('certificates/Index')
        ->where('initialDni', '')
        ->where('initialCode', '')
    );
});

test('guest is redirected to certificate portal when scanning verification code URL', function () {
    $course = Course::factory()->create([
        'status' => CourseStatus::Concluido,
    ]);

    $enrollment = Enrollment::create([
        'course_id' => $course->id,
        'dni' => '88888888',
        'nombres' => 'ANA',
        'paterno' => 'MAMANI',
        'email' => 'ana@example.com',
        'status' => 'aprobado',
        'final_grade' => 18,
        'certificate_code' => 'SIGC-2026-TESTCODE',
        'certificate_hash' => hash('sha256', 'mock_hash'),
        'certificate_issued_at' => now(),
    ]);

    $response = $this->get(route('certificates.verify', 'SIGC-2026-TESTCODE'));

    $response->assertRedirect(route('certificates.index', [
        'dni' => '88888888',
        'code' => 'SIGC-2026-TESTCODE',
    ]));
});

test('guest can query certificates lookup api with valid dni', function () {
    $course = Course::factory()->create([
        'title' => 'Gestión Pública y Modernización',
        'status' => CourseStatus::Concluido,
    ]);

    Enrollment::create([
        'course_id' => $course->id,
        'dni' => '77777777',
        'nombres' => 'CARLOS',
        'paterno' => 'CONDORI',
        'email' => 'carlos@example.com',
        'status' => 'aprobado',
        'final_grade' => 19,
        'certificate_code' => 'SIGC-2026-VALID01',
        'certificate_hash' => hash('sha256', 'mock_hash_2'),
        'certificate_issued_at' => now(),
    ]);

    $response = $this->getJson('/api/certificates/lookup?dni=77777777');

    $response->assertOk();
    $response->assertJson([
        'success' => true,
        'dni' => '77777777',
    ]);
    $response->assertJsonStructure([
        'success',
        'dni',
        'records' => [
            '*' => [
                'certificate_code',
                'course_title',
                'student_name',
            ],
        ],
    ]);
});

test('guest receives 422 error when querying lookup api with invalid dni format', function () {
    $response = $this->getJson('/api/certificates/lookup?dni=123');

    $response->assertStatus(422);
    $response->assertJson([
        'success' => false,
        'message' => 'Ingrese un número de DNI válido de 8 dígitos.',
        'records' => [],
    ]);
});

test('guest can query lookup api with check_only flag to verify student existence', function () {
    $course = Course::factory()->create([
        'title' => 'Gestión Pública y Modernización',
        'status' => CourseStatus::Concluido,
    ]);

    Enrollment::create([
        'course_id' => $course->id,
        'dni' => '77777777',
        'nombres' => 'CARLOS',
        'paterno' => 'CONDORI',
        'email' => 'carlos@example.com',
        'status' => 'aprobado',
        'final_grade' => 19,
        'certificate_code' => 'SIGC-2026-VALID01',
        'certificate_hash' => hash('sha256', 'mock_hash_2'),
        'certificate_issued_at' => now(),
    ]);

    $response = $this->getJson('/api/certificates/lookup?dni=77777777&check_only=1');

    $response->assertOk();
    $response->assertJson([
        'success' => true,
        'dni' => '77777777',
        'has_records' => true,
        'student_name' => 'CARLOS CONDORI',
    ]);
});
