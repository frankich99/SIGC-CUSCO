<?php

use App\Enums\UserRole;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('CP-01 non admin cannot access create course screen and gets 403', function () {
    $alumno = User::factory()->create([
        'role' => UserRole::Participante,
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($alumno)->get(route('courses.create'));

    $response->assertForbidden();
});

test('CP-02 course capacity must be positive', function () {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
        'email_verified_at' => now(),
    ]);

    $instructor = User::factory()->create(['role' => UserRole::Docente]);

    $response = $this->actingAs($admin)->post(route('courses.store'), [
        'title' => 'Curso con vacantes negativas',
        'instructor_id' => $instructor->id,
        'start_date' => now()->format('Y-m-d'),
        'end_date' => now()->addDays(10)->format('Y-m-d'),
        'hours' => 20,
        'capacity' => 0, // Invalido: debe ser >= 1
        'status' => 'abierto',
    ]);

    $response->assertSessionHasErrors(['capacity']);
});

test('CP-03 end date cannot be before start date', function () {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
        'email_verified_at' => now(),
    ]);

    $instructor = User::factory()->create(['role' => UserRole::Docente]);

    $response = $this->actingAs($admin)->post(route('courses.store'), [
        'title' => 'Curso con fechas incongruentes',
        'instructor_id' => $instructor->id,
        'start_date' => '2026-11-20',
        'end_date' => '2026-11-10', // Invalido: menor a start_date
        'hours' => 20,
        'capacity' => 30,
        'status' => 'abierto',
    ]);

    $response->assertSessionHasErrors(['end_date']);
});

test('CP-04 admin creates valid course and stores in mysql', function () {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
        'email_verified_at' => now(),
    ]);

    $instructor = User::factory()->create([
        'role' => UserRole::Docente,
        'name' => 'Profesor Prueba',
    ]);

    $courseData = [
        'code' => 'SIGC-TEST-001',
        'title' => 'Capacitación Oficial de Inteligencia Artificial',
        'description' => 'Curso de alto nivel para estudiantes y profesionales.',
        'instructor_id' => $instructor->id,
        'start_date' => '2026-11-01',
        'end_date' => '2026-11-30',
        'hours' => 45,
        'capacity' => 35,
        'status' => 'abierto',
    ];

    $response = $this->actingAs($admin)->post(route('courses.store'), $courseData);

    $response->assertRedirect(route('courses.index'));
    $this->assertDatabaseHas('courses', [
        'code' => 'SIGC-TEST-001',
        'title' => 'Capacitación Oficial de Inteligencia Artificial',
        'capacity' => 35,
        'instructor_id' => $instructor->id,
    ]);
});

test('CP-05 admin creates valid course with external ponente and stores in mysql', function () {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
        'email_verified_at' => now(),
    ]);

    $courseData = [
        'code' => 'SIGC-TEST-EXT-01',
        'title' => 'Seminario Internacional de Ciberdefensa',
        'description' => 'Ponencia magistral con especialista externo.',
        'instructor_name' => 'Dr. Walter Quispe Mendoza',
        'start_date' => '2026-11-05',
        'end_date' => '2026-11-15',
        'hours' => 20,
        'capacity' => 50,
        'status' => 'abierto',
    ];

    $response = $this->actingAs($admin)->post(route('courses.store'), $courseData);

    $response->assertRedirect(route('courses.index'));
    $this->assertDatabaseHas('courses', [
        'code' => 'SIGC-TEST-EXT-01',
        'title' => 'Seminario Internacional de Ciberdefensa',
        'instructor_name' => 'Dr. Walter Quispe Mendoza',
        'instructor_id' => null,
    ]);
});

test('authenticated user can query dni endpoint without receiving unnecessary PII', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    Http::fake([
        'api.perudevs.com/*' => Http::response([
            'estado' => true,
            'mensaje' => 'Encontrado',
            'resultado' => [
                'id' => '12345678',
                'nombres' => 'JUAN',
                'apellido_paterno' => 'QUISPE',
                'apellido_materno' => 'FLORES',
                'nombre_completo' => 'JUAN QUISPE FLORES',
                'genero' => 'M',
                'fecha_nacimiento' => '1990-01-01',
                'codigo_verificacion' => '7',
            ],
        ], 200),
    ]);

    $response = $this->actingAs($user)->getJson(route('dni.lookup', ['dni' => '12345678']));

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'data' => [
                'dni' => '12345678',
                'nombre_completo' => 'JUAN QUISPE FLORES',
            ],
        ])
        ->assertJsonMissingPath('data.genero')
        ->assertJsonMissingPath('data.fecha_nacimiento')
        ->assertJsonMissingPath('data.codigo_verificacion');
});

test('credential QR is available to its owner but not to another participant', function () {
    $owner = User::factory()->create([
        'role' => UserRole::Participante,
        'dni' => '71234567',
        'email_verified_at' => now(),
    ]);
    $otherParticipant = User::factory()->create([
        'role' => UserRole::Participante,
        'dni' => '82345678',
        'email_verified_at' => now(),
    ]);
    $course = Course::factory()->create(['status' => 'en_curso']);
    $enrollment = Enrollment::create([
        'course_id' => $course->id,
        'user_id' => $owner->id,
        'dni' => '71234567',
        'nombres' => 'LUZ',
        'paterno' => 'MAMANI',
        'email' => $owner->email,
        'status' => 'en_curso',
    ]);

    $ownerResponse = $this->actingAs($owner)->getJson(route('enrollments.credential-qr', $enrollment));

    $ownerResponse->assertOk()->assertJsonPath('code', $enrollment->credential_code);
    expect($ownerResponse->json('svg'))->toContain('<svg');

    $this->actingAs($otherParticipant)
        ->getJson(route('enrollments.credential-qr', $enrollment))
        ->assertForbidden();
});

test('authenticated user receives a standards-compliant course QR', function () {
    $user = User::factory()->create([
        'role' => UserRole::Participante,
        'email_verified_at' => now(),
    ]);
    $course = Course::factory()->create();

    $response = $this->actingAs($user)->getJson(route('courses.qr', $course));

    $response->assertOk()->assertJsonPath('url', route('courses.show', $course));
    expect($response->json('svg'))->toContain('<svg');
});

test('CP-06 guest user visiting course show gets 200 without exposing confidential enrollments list', function () {
    $course = Course::factory()->create(['status' => 'abierto', 'capacity' => 40]);

    // Crear dos matrículas con datos confidenciales
    Enrollment::create([
        'course_id' => $course->id,
        'dni' => '71234567',
        'nombres' => 'MARIA',
        'paterno' => 'MAMANI',
        'email' => 'maria@secreto.com',
        'phone' => '984112233',
        'status' => 'en_curso',
        'attended_sessions' => 0,
    ]);
    Enrollment::create([
        'course_id' => $course->id,
        'dni' => '82345678',
        'nombres' => 'CARLOS',
        'paterno' => 'CONDORI',
        'email' => 'carlos@secreto.com',
        'phone' => '984556677',
        'status' => 'aprobado',
        'attended_sessions' => 4,
    ]);

    // Acceder sin iniciar sesión (invitado público)
    $response = $this->get(route('courses.show', $course));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('courses/Show')
        ->where('isStaff', false)
        ->where('myEnrollment', null)
        ->has('modules')
        ->where('course.enrollments', []) // Confidencialidad: lista de alumnos NO expuesta
    );
});

test('CP-07 participante user visiting course show sees only their own enrollment data', function () {
    $studentUser = User::factory()->create([
        'role' => UserRole::Participante,
        'dni' => '71234567',
        'email_verified_at' => now(),
    ]);

    $course = Course::factory()->create(['status' => 'abierto']);

    // Matrícula del alumno autenticado
    Enrollment::create([
        'course_id' => $course->id,
        'user_id' => $studentUser->id,
        'dni' => '71234567',
        'nombres' => 'ALUMNO',
        'paterno' => 'AUTENTICADO',
        'email' => 'alumno@unsaac.pe',
        'status' => 'en_curso',
        'attended_sessions' => 1,
    ]);

    // Matrícula de un compañero ajeno
    Enrollment::create([
        'course_id' => $course->id,
        'dni' => '99999999',
        'nombres' => 'OTRO',
        'paterno' => 'COMPAÑERO',
        'email' => 'privado@gmail.com',
        'status' => 'aprobado',
        'attended_sessions' => 4,
    ]);

    $response = $this->actingAs($studentUser)->get(route('courses.show', $course));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('courses/Show')
        ->where('isStaff', false)
        ->where('course.enrollments', []) // Confidencialidad: no ve la lista de todos
        ->where('myEnrollment.dni', '71234567') // Ve su propio avance
        ->where('myEnrollment.nombres', 'ALUMNO')
    );
});

test('CP-08 admin or course docente visiting course show receives full enrollments and isStaff true', function () {
    $docente = User::factory()->create([
        'role' => UserRole::Docente,
        'email_verified_at' => now(),
    ]);

    $course = Course::factory()->create([
        'instructor_id' => $docente->id,
        'status' => 'abierto',
    ]);

    for ($i = 1; $i <= 3; $i++) {
        Enrollment::create([
            'course_id' => $course->id,
            'dni' => '1000000'.$i,
            'nombres' => 'ALUMNO'.$i,
            'paterno' => 'TEST',
            'email' => "alumno{$i}@unsaac.pe",
            'status' => 'inscrito',
            'attended_sessions' => 0,
        ]);
    }

    $response = $this->actingAs($docente)->get(route('courses.show', $course));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('courses/Show')
        ->where('isStaff', true)
        ->has('course.enrollments', 3) // El docente titular gestiona a todos los alumnos
    );
});
