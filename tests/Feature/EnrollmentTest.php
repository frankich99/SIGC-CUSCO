<?php

use App\Enums\CourseStatus;
use App\Enums\UserRole;
use App\Models\Course;
use App\Models\Enrollment;

test('ciudadano puede matricularse en curso abierto con DNI valido', function () {
    $course = Course::factory()->create([
        'status' => CourseStatus::Abierto,
        'capacity' => 10,
    ]);

    $response = $this->post(route('courses.enroll', $course), [
        'dni' => '72345678',
        'nombres' => 'MARIA ISABEL',
        'paterno' => 'JIMENEZ',
        'materno' => 'DIAZ',
        'email' => 'maria.jimenez@gmail.com',
        'phone' => '984123456',
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('enrollments', [
        'course_id' => $course->id,
        'dni' => '72345678',
        'nombres' => 'MARIA ISABEL',
        'paterno' => 'JIMENEZ',
        'status' => 'inscrito',
    ]);

    $this->assertDatabaseHas('users', [
        'email' => 'maria.jimenez@gmail.com',
        'dni' => '72345678',
        'role' => UserRole::Participante,
    ]);
});

test('rechaza matricula si DNI no tiene 8 digitos numericos', function () {
    $course = Course::factory()->create([
        'status' => CourseStatus::Abierto,
        'capacity' => 10,
    ]);

    $response = $this->post(route('courses.enroll', $course), [
        'dni' => '123',
        'nombres' => 'CARLOS',
        'paterno' => 'QUISPE',
        'email' => 'carlos@gmail.com',
    ]);

    $response->assertSessionHasErrors(['dni']);
});

test('no permite matricula en curso cerrado o concluido', function () {
    $course = Course::factory()->create([
        'status' => CourseStatus::Concluido,
        'capacity' => 10,
    ]);

    $response = $this->post(route('courses.enroll', $course), [
        'dni' => '72345679',
        'nombres' => 'LUIS',
        'paterno' => 'MAMANI',
        'email' => 'luis@gmail.com',
    ]);

    $response->assertSessionHasErrors(['dni']);
});

test('no permite matricula si se agoto el aforo de vacantes', function () {
    $course = Course::factory()->create([
        'status' => CourseStatus::Abierto,
        'capacity' => 1,
    ]);

    // Ocupamos la única vacante
    Enrollment::create([
        'course_id' => $course->id,
        'dni' => '70000001',
        'nombres' => 'JUAN',
        'paterno' => 'PEREZ',
        'email' => 'juan@gmail.com',
        'status' => 'inscrito',
    ]);

    // Segundo intento con otra persona
    $response = $this->post(route('courses.enroll', $course), [
        'dni' => '70000002',
        'nombres' => 'ANA',
        'paterno' => 'FLORES',
        'email' => 'ana@gmail.com',
    ]);

    $response->assertSessionHasErrors(['dni']);
});

test('no permite matricula duplicada con el mismo DNI en el mismo curso', function () {
    $course = Course::factory()->create([
        'status' => CourseStatus::Abierto,
        'capacity' => 20,
    ]);

    Enrollment::create([
        'course_id' => $course->id,
        'dni' => '71112223',
        'nombres' => 'ROBERTO',
        'paterno' => 'GOMEZ',
        'email' => 'roberto@gmail.com',
        'status' => 'inscrito',
    ]);

    $response = $this->post(route('courses.enroll', $course), [
        'dni' => '71112223',
        'nombres' => 'ROBERTO',
        'paterno' => 'GOMEZ',
        'email' => 'roberto2@gmail.com',
    ]);

    $response->assertSessionHasErrors(['dni']);
});
