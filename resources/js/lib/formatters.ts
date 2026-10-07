/**
 * Centralized formatting utilities for dates, hours, and values in SIGC-CUSCO.
 * Handles UTC strings cleanly without timezone day-shifting errors.
 */

const MONTHS_ES_SHORT = [
    'ene.', 'feb.', 'mar.', 'abr.', 'may.', 'jun.',
    'jul.', 'ago.', 'set.', 'oct.', 'nov.', 'dic.',
];

const MONTHS_ES_FULL = [
    'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
    'julio', 'agosto', 'setiembre', 'octubre', 'noviembre', 'diciembre',
];

/**
 * Extracts { year, month, day, hours, minutes } safely from ISO or YYYY-MM-DD strings.
 * Avoids browser timezone offset issues where UTC midnight rolls back one day in Peru (UTC-5).
 */
function parseDateParts(dateInput: string | Date | null | undefined): {
    year: number;
    month: number; // 1-12
    day: number;
    hours: number;
    minutes: number;
} | null {
    if (!dateInput) return null;

    if (dateInput instanceof Date) {
        if (isNaN(dateInput.getTime())) return null;
        return {
            year: dateInput.getFullYear(),
            month: dateInput.getMonth() + 1,
            day: dateInput.getDate(),
            hours: dateInput.getHours(),
            minutes: dateInput.getMinutes(),
        };
    }

    const str = String(dateInput).trim();
    if (!str) return null;

    // Matches YYYY-MM-DD or YYYY-MM-DDTHH:mm:ss...
    const match = str.match(/^(\d{4})-(\d{2})-(\d{2})(?:[T\s](\d{2}):(\d{2}))?/);
    if (match) {
        return {
            year: parseInt(match[1], 10),
            month: parseInt(match[2], 10),
            day: parseInt(match[3], 10),
            hours: match[4] ? parseInt(match[4], 10) : 0,
            minutes: match[5] ? parseInt(match[5], 10) : 0,
        };
    }

    const parsed = new Date(str);
    if (isNaN(parsed.getTime())) return null;

    return {
        year: parsed.getFullYear(),
        month: parsed.getMonth() + 1,
        day: parsed.getDate(),
        hours: parsed.getHours(),
        minutes: parsed.getMinutes(),
    };
}

/**
 * Formats a date into a clean, human-readable Spanish string.
 * Default style: 'compact' -> '17/10/2026'
 * 'medium' -> '17 oct. 2026'
 * 'full' -> '17 de octubre de 2026'
 */
export function formatDate(
    dateInput: string | Date | null | undefined,
    style: 'compact' | 'medium' | 'full' = 'compact'
): string {
    const parts = parseDateParts(dateInput);
    if (!parts) return 'Por definir';

    const dd = String(parts.day).padStart(2, '0');
    const mm = String(parts.month).padStart(2, '0');
    const yyyy = parts.year;

    if (style === 'compact') {
        return `${dd}/${mm}/${yyyy}`;
    }

    if (style === 'medium') {
        const monthShort = MONTHS_ES_SHORT[parts.month - 1] || mm;
        return `${parts.day} ${monthShort} ${yyyy}`;
    }

    const monthFull = MONTHS_ES_FULL[parts.month - 1] || mm;
    return `${parts.day} de ${monthFull} de ${yyyy}`;
}

/**
 * Formats a date and time string: '17/10/2026 • 08:30 hrs'
 */
export function formatDateTime(dateInput: string | Date | null | undefined): string {
    const parts = parseDateParts(dateInput);
    if (!parts) return 'Por definir';

    const dd = String(parts.day).padStart(2, '0');
    const mm = String(parts.month).padStart(2, '0');
    const yyyy = parts.year;
    const hh = String(parts.hours).padStart(2, '0');
    const min = String(parts.minutes).padStart(2, '0');

    return `${dd}/${mm}/${yyyy} • ${hh}:${min} hrs`;
}

/**
 * Formats a date range: '17/10/2026 al 25/11/2026' or '17 oct. — 25 nov. 2026'
 */
export function formatDateRange(
    startInput?: string | null,
    endInput?: string | null,
    style: 'compact' | 'medium' = 'compact'
): string {
    if (!startInput && !endInput) return 'Fechas por definir';
    if (startInput && !endInput) return `Desde ${formatDate(startInput, style)}`;
    if (!startInput && endInput) return `Hasta ${formatDate(endInput, style)}`;

    if (style === 'compact') {
        return `${formatDate(startInput, 'compact')} al ${formatDate(endInput, 'compact')}`;
    }

    return `${formatDate(startInput, 'medium')} — ${formatDate(endInput, 'medium')}`;
}

/**
 * Formats academic hours cleanly: '40 hrs'
 */
export function formatHours(hours?: number | null): string {
    if (!hours || hours <= 0) return '0 hrs';
    return `${hours} hrs académicas`;
}
