<?php

use App\Contracts\DniLookupService;
use App\DTOs\DniPerson;
use App\Services\Dni\PeruDevsDniService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Cache::flush();
    config()->set('services.perudevs.key', 'test_key');
    config()->set('services.perudevs.base_url', 'https://api.perudevs.com/api/v1');
});

test('service resolves from container', function () {
    $service = app(DniLookupService::class);
    expect($service)->toBeInstanceOf(PeruDevsDniService::class);
});

test('invalid dni format returns null without calling api', function () {
    Http::fake();

    $service = app(DniLookupService::class);

    expect($service->search('123'))->toBeNull()
        ->and($service->search('123456789'))->toBeNull()
        ->and($service->search('abcdefgh'))->toBeNull();

    Http::assertNothingSent();
});

test('successful dni query returns typed DniPerson and caches result', function () {
    Http::fake([
        'api.perudevs.com/*' => Http::response([
            'estado' => true,
            'mensaje' => 'Encontrado',
            'resultado' => [
                'id' => '12345678',
                'nombres' => 'MARIA ISABEL',
                'apellido_paterno' => 'JIMENEZ',
                'apellido_materno' => 'DIAZ',
                'nombre_completo' => 'MARIA ISABEL JIMENEZ DIAZ',
                'genero' => 'F',
                'fecha_nacimiento' => '16/11/1994',
                'codigo_verificacion' => '8',
            ],
        ], 200),
    ]);

    $service = app(DniLookupService::class);
    $person = $service->search('12345678');

    expect($person)->toBeInstanceOf(DniPerson::class)
        ->and($person->dni)->toBe('12345678')
        ->and($person->nombres)->toBe('MARIA ISABEL')
        ->and($person->apellidoPaterno)->toBe('JIMENEZ')
        ->and($person->apellidoMaterno)->toBe('DIAZ')
        ->and($person->nombreCompleto)->toBe('MARIA ISABEL JIMENEZ DIAZ');

    // Second call should hit cache, not HTTP
    Http::fake();
    $cachedPerson = $service->search('12345678');
    expect($cachedPerson)->not->toBeNull()
        ->and($cachedPerson->dni)->toBe('12345678');

    Http::assertNothingSent();
});

test('not found dni returns null', function () {
    Http::fake([
        'api.perudevs.com/*' => Http::response([
            'estado' => false,
            'mensaje' => 'No se ha encontrado a la persona',
        ], 200),
    ]);

    $service = app(DniLookupService::class);
    $person = $service->search('99999999');

    expect($person)->toBeNull();
});
