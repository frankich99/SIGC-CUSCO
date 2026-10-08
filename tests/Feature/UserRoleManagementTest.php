<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('invitados son redirigidos al login al intentar acceder a usuarios', function () {
    $response = $this->get(route('users.index'));
    $response->assertRedirect(route('login'));
});

test('participantes no pueden acceder a la gestión de usuarios y roles', function () {
    $user = User::factory()->create(['role' => 'participante']);
    $this->actingAs($user);

    $response = $this->get(route('users.index'));
    $response->assertForbidden();
});

test('docentes no pueden acceder a la gestión de usuarios y roles', function () {
    $docente = User::factory()->create(['role' => 'docente']);
    $this->actingAs($docente);

    $response = $this->get(route('users.index'));
    $response->assertForbidden();
});

test('administradores pueden ver el directorio de usuarios, estadísticas y matriz de permisos', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    User::factory()->create(['role' => 'docente']);
    User::factory()->create(['role' => 'participante']);

    $this->actingAs($admin);

    $response = $this->get(route('users.index'));
    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('users/Index')
        ->has('users.data')
        ->has('stats')
        ->has('permissionsMatrix')
    );
});

test('administrador puede reasignar el rol de un participante a docente', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $student = User::factory()->create(['role' => 'participante']);

    $this->actingAs($admin);

    $response = $this->put(route('users.update-role', $student), [
        'role' => 'docente',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('users', [
        'id' => $student->id,
        'role' => 'docente',
    ]);
});

test('administrador no puede despojarse a sí mismo si es el único administrador', function () {
    // Asegurar que solo hay 1 admin
    User::where('role', 'admin')->delete();
    $soleAdmin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($soleAdmin);

    $response = $this->put(route('users.update-role', $soleAdmin), [
        'role' => 'participante',
    ]);

    $response->assertSessionHas('error');
    $this->assertEquals('admin', $soleAdmin->fresh()->role->value);
});

test('la validación rechaza roles inexistentes o no autorizados', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create(['role' => 'participante']);

    $this->actingAs($admin);

    $response = $this->put(route('users.update-role', $user), [
        'role' => 'super_hacker',
    ]);

    $response->assertSessionHasErrors('role');
});
