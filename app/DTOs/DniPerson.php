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
            'nombre_completo' => $this->nombreCompleto,
        ];
    }
}
