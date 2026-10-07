<?php

namespace App\Enums;

enum CourseStatus: string
{
    case Abierto = 'abierto';
    case EnCurso = 'en_curso';
    case Concluido = 'concluido';
    case Cancelado = 'cancelado';

    /**
     * Human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Abierto => 'Abierto / Convocatoria',
            self::EnCurso => 'En curso',
            self::Concluido => 'Concluido',
            self::Cancelado => 'Cancelado',
        };
    }

    /**
     * Tailwind badge styling classes.
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Abierto => 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800',
            self::EnCurso => 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950/40 dark:text-blue-300 dark:border-blue-800',
            self::Concluido => 'bg-gray-50 text-gray-700 border-gray-200 dark:bg-gray-800/40 dark:text-gray-300 dark:border-gray-700',
            self::Cancelado => 'bg-red-50 text-red-700 border-red-200 dark:bg-red-950/40 dark:text-red-300 dark:border-red-800',
        };
    }
}
