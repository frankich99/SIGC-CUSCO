/**
 * Live session roster returned by GET /courses/{course}/sessions/{session}/roster (SC-32, SC-33).
 */
export type RosterState =
    | 'presente'
    | 'tardanza'
    | 'parcial'
    | 'sin_salida'
    | 'falta'
    | 'en_sala'
    | 'ausente';

export type MarkType = 'entrada' | 'salida';

export type MarkOrigin = 'dni_lector' | 'qr' | 'manual';

export interface RosterParticipant {
    id: number;
    dni: string;
    full_name: string;
    state: RosterState;
    recorded_at: string | null;
    method: string | null;
    minutes_present: number | null;
    justification: string | null;
    justified_at: string | null;
    entry_at: string | null;
    entry_origin: MarkOrigin | null;
    exit_at: string | null;
    exit_origin: MarkOrigin | null;
}

export interface RosterUnenrolledScan {
    id: number;
    dni: string;
    type: MarkType;
    reason: string;
    scanned_at: string;
}

export interface SessionRoster {
    session: number;
    duration_minutes: number;
    required_percent: number;
    is_acta_closed: boolean;
    summary: {
        total: number;
        presentes: number;
        en_sala: number;
        parciales: number;
        sin_salida: number;
        ausentes: number;
        no_inscritos: number;
    };
    participants: RosterParticipant[];
    unenrolled: RosterUnenrolledScan[];
}

export interface MarkResult {
    result:
        | 'ingreso_registrado'
        | 'asistida'
        | 'parcial'
        | 'duplicada'
        | 'sin_ingreso'
        | 'no_inscrito'
        | 'dni_invalido'
        /** Solo al sincronizar: la hora del dispositivo está fuera de rango (SC-52). */
        | 'hora_invalida';
    message: string;
    first_mark: boolean;
    participant: { id: number; dni: string; full_name: string } | null;
    marked_at: string | null;
    permanence: {
        minutes: number;
        percent: number;
        required_percent: number;
        duration_minutes: number;
    } | null;
}

export const ROSTER_STATE_LABELS: Record<RosterState, string> = {
    presente: 'Asistida',
    tardanza: 'Tardanza',
    parcial: 'Asistencia parcial',
    sin_salida: 'Sin salida',
    falta: 'Falta',
    en_sala: 'En sala',
    ausente: 'Ausente',
};

export const ROSTER_STATE_CLASSES: Record<RosterState, string> = {
    presente:
        'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300',
    tardanza:
        'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-300',
    parcial:
        'border-orange-200 bg-orange-50 text-orange-900 dark:border-orange-900 dark:bg-orange-950/40 dark:text-orange-300',
    sin_salida:
        'border-rose-200 bg-rose-50 text-rose-900 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-300',
    falta: 'border-slate-200 bg-slate-100 text-slate-600 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400',
    en_sala:
        'border-sky-200 bg-sky-50 text-sky-900 dark:border-sky-900 dark:bg-sky-950/40 dark:text-sky-300',
    ausente:
        'border-slate-200 bg-slate-100 text-slate-600 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400',
};

export const ORIGIN_LABELS: Record<string, string> = {
    dni_lector: 'Lector DNI',
    qr: 'QR',
    qr_proyeccion: 'QR',
    manual: 'Manual',
    offline_sync: 'Offline',
    scan: 'Credencial',
};

export function formatLimaTime(iso: string | null): string {
    if (!iso) return '—';

    return new Intl.DateTimeFormat('es-PE', {
        timeZone: 'America/Lima',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false,
    }).format(new Date(iso));
}
