<?php

use App\Enums\UserRole;
use App\Models\User;

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

test('authenticated user can query dni endpoint', function () {
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
        ]);
});
