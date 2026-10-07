<?php

namespace App\DTOs;

readonly class DniPerson
{
    public function __construct(
        public string $dni,
        public string $nombres,
        public string $apellidoPaterno,
        public string $apellidoMaterno,
        public string $nombreCompleto,
        public ?string $genero = null,
        public ?string $fechaNacimiento = null,
        public ?string $codigoVerificacion = null,
    ) {}

    /**
     * Build DTO from PeruDevs API result array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromPeruDevs(array $data): self
    {
        return new self(
            dni: (string) ($data['id'] ?? ''),
            nombres: trim((string) ($data['nombres'] ?? '')),
            apellidoPaterno: trim((string) ($data['apellido_paterno'] ?? '')),
            apellidoMaterno: trim((string) ($data['apellido_materno'] ?? '')),
            nombreCompleto: trim((string) ($data['nombre_completo'] ?? '')),
            genero: ! empty($data['genero']) ? (string) $data['genero'] : null,
            fechaNacimiento: ! empty($data['fecha_nacimiento']) ? (string) $data['fecha_nacimiento'] : null,
            codigoVerificacion: ! empty($data['codigo_verificacion']) ? (string) $data['codigo_verificacion'] : null,
        );
    }

    /**
     * Convert DTO to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'dni' => $this->dni,
            'nombres' => $this->nombres,
            'apellido_paterno' => $this->apellidoPaterno,
            'apellido_materno' => $this->apellidoMaterno,
            'paterno' => $this->apellidoPaterno,
            'materno' => $this->apellidoMaterno,
            'nombre_completo' => $this->nombreCompleto,
            'genero' => $this->genero,
            'fecha_nacimiento' => $this->fechaNacimiento,
            'codigo_verificacion' => $this->codigoVerificacion,
        ];
    }
}
