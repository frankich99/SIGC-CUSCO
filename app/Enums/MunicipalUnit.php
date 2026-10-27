<?php

namespace App\Enums;

enum MunicipalUnit: string
{
    case OTI = 'OTI';
    case RRHH = 'RRHH';
    case SOMA = 'SOMA';
    case GDUR = 'GDUR';
    case GDSH = 'GDSH';
    case GAF = 'GAF';
    case GSMA = 'GSMA';
    case GDE = 'GDE';
    case GAT = 'GAT';
    case PPM = 'PPM';
    case ALC = 'ALC';
    case Externo = 'EXTERNO';

    /**
     * Nombre formal y completo de la unidad orgánica municipal.
     */
    public function label(): string
    {
        return match ($this) {
            self::OTI => 'Oficina de Tecnologías de la Información (OTI)',
            self::RRHH => 'Subgerencia de Recursos Humanos',
            self::SOMA => 'Seguridad, Salud en el Trabajo y Medio Ambiente (SOMA)',
            self::GDUR => 'Gerencia de Desarrollo Urbano y Rural',
            self::GDSH => 'Gerencia de Desarrollo Social y Humano',
            self::GAF => 'Gerencia de Administración y Finanzas',
            self::GSMA => 'Gerencia de Servicios Municipales y Medio Ambiente',
            self::GDE => 'Gerencia de Desarrollo Económico',
            self::GAT => 'Gerencia de Administración Tributaria (Rentas)',
            self::PPM => 'Procuraduría Pública Municipal',
            self::ALC => 'Alcaldía y Secretaría General',
            self::Externo => 'Participante Externo / Comunidad San Jerónimo',
        };
    }

    /**
     * Lista plana formateada para selects o formularios.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $unit) => ['value' => $unit->value, 'label' => $unit->label()],
            self::cases()
        );
    }
}
