<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Docente = 'docente';
    case Participante = 'participante';

    /**
     * Get user-friendly label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Docente => 'Docente / Instructor',
            self::Participante => 'Participante / Alumno',
        };
    }
}
