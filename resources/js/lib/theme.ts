/**
 * Centralized Design System & Theme Utilities for SIGC-MDSJ.
 * Municipalidad Distrital de San Jerónimo - Cusco.
 * Palette: Azul San Jerónimo (#3484A5), Verde Turquesa (#2CA792) & Sol Dorado (#F0C84F).
 * Standardized for responsive modals, unified buttons, badges, and accessible contrast.
 */

export const MDSJ_PALETTE = {
    blue: {
        primary: '#3484A5',
        deep: '#0b1e27',
        imperial: '#153542',
        vibrant: '#296d88',
        dark: '#1b4556',
        border: '#badbe9',
        light: '#f0f7fa',
        badgeBg: 'bg-[#3484a5]',
        badgeText: 'text-white',
    },
    green: {
        primary: '#2CA792',
        light: '#effaf7',
        dark: '#1c6d5f',
        border: '#b0e7db',
        badgeBg: 'bg-[#2ca792]',
        badgeText: 'text-white',
    },
    gold: {
        primary: '#F0C84F',
        light: '#fef9e7',
        dark: '#b45309',
        border: '#fde68a',
    },
    slate: {
        text: 'text-slate-900 dark:text-white',
        muted: 'text-slate-700 dark:text-slate-300',
        subtle: 'text-slate-600 dark:text-slate-400',
        border: 'border-slate-300 dark:border-slate-800',
    },
} as const;

/** Alias de compatibilidad hacia la paleta institucional MDSJ */
export const CUSCO_PALETTE = {
    granate: MDSJ_PALETTE.blue,
    gold: MDSJ_PALETTE.gold,
    slate: MDSJ_PALETTE.slate,
} as const;

/**
 * Reusable Unified Button Styles
 */
export const THEME_BUTTONS = {
    primary:
        'bg-emerald-600 hover:bg-emerald-700 text-white font-bold shadow-sm shadow-emerald-600/25 transition-all focus-visible:ring-2 focus-visible:ring-emerald-500/40 active:scale-[0.98]',
    secondary:
        'bg-slate-100 hover:bg-slate-200 text-slate-900 border border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 dark:hover:bg-slate-700/80 font-bold shadow-2xs transition-all',
    institutional:
        'bg-[#3484a5] hover:bg-[#296d88] text-white font-bold shadow-sm transition-all focus-visible:ring-2 focus-visible:ring-[#3484a5]/40',
    gold: 'bg-amber-500 hover:bg-amber-600 text-slate-950 font-bold shadow-sm transition-all',
    green: 'bg-emerald-600 hover:bg-emerald-700 text-white font-bold shadow-sm transition-all',
    outline:
        'border-2 border-slate-300 dark:border-slate-700 hover:border-emerald-600 text-slate-900 dark:text-slate-100 hover:text-emerald-700 dark:hover:text-emerald-400 font-bold transition-all',
    ghost: 'text-slate-800 dark:text-slate-200 hover:text-emerald-700 dark:hover:text-emerald-400 hover:bg-emerald-50/60 dark:hover:bg-slate-800/60 font-semibold transition-all',
    danger: 'bg-red-600 hover:bg-red-700 text-white font-bold shadow-sm transition-all',
} as const;

/**
 * Reusable Status & Category Badges
 */
export const THEME_BADGES = {
    abierto:
        'bg-emerald-100 text-emerald-950 border border-emerald-300 dark:bg-emerald-950/80 dark:text-emerald-300 dark:border-emerald-600 font-bold',
    en_curso:
        'bg-sky-100 text-[#0c2d3d] border border-sky-300 dark:bg-[#153542] dark:text-sky-200 dark:border-[#296d88] font-bold',
    concluido:
        'bg-rose-100 text-rose-950 border border-rose-300 dark:bg-rose-950/80 dark:text-rose-200 dark:border-rose-600 font-bold',
    cerrado:
        'bg-rose-100 text-rose-950 border border-rose-300 dark:bg-rose-950/80 dark:text-rose-200 dark:border-rose-600 font-bold',
    agotado:
        'bg-amber-100 text-amber-950 border border-amber-300 dark:bg-amber-950/80 dark:text-amber-200 dark:border-amber-600 font-bold',
    cancelado:
        'bg-red-100 text-red-950 border border-red-300 dark:bg-red-950/90 dark:text-red-300 dark:border-red-600 font-extrabold',
    inscrito:
        'bg-sky-100 text-[#0c2d3d] border border-sky-300 dark:bg-[#153542] dark:text-sky-200 dark:border-[#296d88] font-bold',
    aprobado:
        'bg-teal-100 text-teal-950 border border-teal-300 dark:bg-teal-950/80 dark:text-teal-200 dark:border-teal-600 font-bold',
    reprobado:
        'bg-red-100 text-red-950 border border-red-300 dark:bg-red-950/90 dark:text-red-300 dark:border-red-600 font-bold',
    urgent:
        'bg-red-100 text-red-950 border border-red-300 dark:bg-red-950/90 dark:text-red-200 dark:border-red-600 font-extrabold',
    official:
        'bg-slate-100 text-[#0c2d3d] border border-slate-300 dark:bg-sky-950/60 dark:text-sky-200 dark:border-sky-800 font-bold',
} as const;

/**
 * Unificación tipada de badges y etiquetas para el estado de capacitaciones/cursos.
 */
export function getCourseStatusBadge(status?: string | null): {
    label: string;
    class: string;
} {
    switch (status) {
        case 'abierto':
            return {
                label: 'Inscripción Abierta',
                class: THEME_BADGES.abierto,
            };
        case 'en_curso':
            return {
                label: 'En Curso',
                class: THEME_BADGES.en_curso,
            };
        case 'cerrado':
            return {
                label: 'Cerrado',
                class: THEME_BADGES.cerrado,
            };
        case 'concluido':
            return {
                label: 'Concluido',
                class: THEME_BADGES.concluido,
            };
        case 'agotado':
            return {
                label: 'Agotado',
                class: THEME_BADGES.agotado,
            };
        case 'cancelado':
            return {
                label: 'Cancelado',
                class: THEME_BADGES.cancelado,
            };
        default:
            return {
                label: (status || 'Desconocido')
                    .replace('_', ' ')
                    .toUpperCase(),
                class: THEME_BADGES.concluido,
            };
    }
}

/**
 * Unificación tipada de badges y etiquetas para el estado académico de un participante.
 */
export function getEnrollmentStatusBadge(status?: string | null): {
    label: string;
    class: string;
} {
    switch (status) {
        case 'aprobado':
            return {
                label: 'Aprobado',
                class: THEME_BADGES.aprobado,
            };
        case 'en_curso':
            return {
                label: 'En Curso',
                class: THEME_BADGES.en_curso,
            };
        case 'inscrito':
            return {
                label: 'Inscrito',
                class: THEME_BADGES.inscrito,
            };
        case 'reprobado':
        case 'desaprobado':
            return {
                label: 'Reprobado',
                class: THEME_BADGES.reprobado,
            };
        case 'cancelado':
            return {
                label: 'Cancelado',
                class: THEME_BADGES.cancelado,
            };
        default:
            return {
                label: (status || 'Registrado').replace('_', ' ').toUpperCase(),
                class: THEME_BADGES.concluido,
            };
    }
}

/**
 * Standardized Slim & Responsive Modal Layout Configurations
 */
export const THEME_MODAL = {
    // Modal armónico para formularios de autenticación (Login / Register)
    authDialog:
        'w-[94vw] sm:max-w-md max-h-[92vh] flex flex-col p-0 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 overflow-hidden',
    // Modal mediano estándar para credenciales, códigos QR y confirmaciones
    mediumDialog:
        'w-[94vw] sm:max-w-lg max-h-[90vh] flex flex-col p-0 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 overflow-hidden',
    // Modal amplio, espacioso y estructurado para formularios complejos (Inscripción / Matrícula)
    enrollmentDialog:
        'w-[96vw] sm:max-w-3xl md:max-w-4xl lg:max-w-5xl max-h-[90vh] flex flex-col p-0 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 overflow-hidden',
    // Fallback genérico para formularios
    formDialog:
        'w-[96vw] sm:max-w-3xl md:max-w-4xl lg:max-w-5xl max-h-[90vh] flex flex-col p-0 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 overflow-hidden',
} as const;

/**
 * Reusable Form Control Classes
 */
export const THEME_INPUT = {
    focusRing:
        'focus:border-[#3484a5] focus:ring-[#3484a5]/20 focus:outline-hidden',
} as const;
