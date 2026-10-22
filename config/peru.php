<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Datos Oficiales de Perú y Geolocalización Institucional
    |--------------------------------------------------------------------------
    | Configuración geográfica, zona horaria y datos de localización para SIGC.
    | Garantiza que las marcas de tiempo, asistencias, sesiones y fechas
    | operen sin desfases bajo la zona horaria oficial del Perú (UTC-05:00).
    */

    'country' => 'Perú',
    'country_code' => 'PE',
    'country_code_alpha3' => 'PER',
    'phone_prefix' => '+51',
    'timezone' => env('APP_TIMEZONE', 'America/Lima'),
    'locale' => env('APP_LOCALE', 'es'),
    'full_locale' => 'es_PE',

    'currency' => [
        'code' => 'PEN',
        'name' => 'Sol Peruano',
        'symbol' => 'S/',
    ],

    'institution' => [
        'name' => 'Municipalidad Distrital de San Jerónimo',
        'acronym' => 'MDSJ',
        'campus' => 'Sede Central - Palacio Municipal',
        'address' => 'Plaza de Armas s/n, San Jerónimo, Cusco 08005',
        'city' => 'Cusco',
        'department' => 'Cusco',
        'province' => 'Cusco',
        'district' => 'San Jerónimo',
        'postal_code' => '08005',
        'ubigeo' => '080104',
        'coordinates' => [
            'latitude' => -13.55167,
            'longitude' => -71.88417,
        ],
        'google_maps_url' => 'https://maps.google.com/?q=-13.55167,-71.88417',
    ],

    /*
    |--------------------------------------------------------------------------
    | Departamentos del Perú (25 regiones)
    |--------------------------------------------------------------------------
    */
    'departments' => [
        'Amazonas',
        'Áncash',
        'Apurímac',
        'Arequipa',
        'Ayacucho',
        'Cajamarca',
        'Callao',
        'Cusco',
        'Huancavelica',
        'Huánuco',
        'Ica',
        'Junín',
        'La Libertad',
        'Lambayeque',
        'Lima',
        'Loreto',
        'Madre de Dios',
        'Moquegua',
        'Pasco',
        'Piura',
        'Puno',
        'San Martín',
        'Tacna',
        'Tumbes',
        'Ucayali',
    ],
];
