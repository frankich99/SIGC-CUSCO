/**
 * Centralized Design System & Theme Utilities for SIGC-CUSCO.
 * Palette: Granate Imperial (#800020 / Rose 900-950) & Sol Dorado (#d97706 / Amber 600-700).
 * Standardized for responsive modals, unified buttons, badges, and accessible contrast.
 */

export const CUSCO_PALETTE = {
    granate: {
        primary: '#800020',
        deep: '#4c0519',
        imperial: '#701a31',
        vibrant: '#9f1239',
        dark: '#881337',
        border: '#fecdd3',
        light: '#fff1f2',
        badgeBg: 'bg-rose-900',
        badgeText: 'text-white',
    },
    gold: {
        primary: '#d97706',
        light: '#fef3c7',
        dark: '#92400e',
        border: '#fde68a',
    },
    slate: {
        text: 'text-slate-900 dark:text-white',
        muted: 'text-slate-700 dark:text-slate-300',
        subtle: 'text-slate-600 dark:text-slate-400',
        border: 'border-slate-300 dark:border-slate-800',
    },
} as const;

/**
 * Reusable Unified Button Styles
 */
export const THEME_BUTTONS = {
    primary:
        'bg-rose-900 hover:bg-rose-950 text-white font-bold shadow-sm transition-all focus-visible:ring-2 focus-visible:ring-rose-900/40',
    secondary:
        'bg-white hover:bg-rose-50 text-rose-950 border border-rose-200 dark:border-rose-900 dark:bg-slate-900 dark:text-rose-200 font-bold shadow-2xs transition-all',
    gold: 'bg-amber-600 hover:bg-amber-700 text-white font-bold shadow-sm transition-all',
    outline:
        'border-slate-300 dark:border-slate-700 hover:border-rose-800 text-slate-800 dark:text-slate-200 hover:text-rose-900 dark:hover:text-rose-300 font-semibold transition-all',
    ghost: 'text-slate-700 dark:text-slate-300 hover:text-rose-900 dark:hover:text-rose-300 hover:bg-rose-50 dark:hover:bg-rose-950/40 font-semibold transition-all',
    danger: 'bg-rose-600 hover:bg-rose-700 text-white font-bold shadow-sm transition-all',
} as const;

/**
 * Reusable Status & Category Badges
 */
export const THEME_BADGES = {
    abierto:
        'bg-rose-100 text-rose-950 border border-rose-300 dark:bg-rose-950 dark:text-rose-200 dark:border-rose-800 font-bold',
    en_curso:
        'bg-sky-100 text-sky-950 border border-sky-300 dark:bg-sky-950 dark:text-sky-200 dark:border-sky-800 font-bold',
    concluido:
        'bg-slate-100 text-slate-800 border border-slate-300 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700 font-bold',
    cancelado:
        'bg-rose-50 text-rose-700 border border-rose-200 dark:bg-rose-950/50 dark:text-rose-400 dark:border-rose-900 font-semibold',
    aprobado:
        'bg-rose-100 text-rose-950 border border-rose-300 dark:bg-rose-950 dark:text-rose-200 dark:border-rose-800 font-bold',
    official:
        'bg-rose-50 text-rose-900 border border-rose-200 dark:bg-rose-950/60 dark:text-rose-200 dark:border-rose-800 font-bold',
} as const;

/**
 * Standardized Slim & Responsive Modal Layout Configurations
 */
export const THEME_MODAL = {
    // Narrow & slim auth modal dialog (Login / Register permanecen compactos)
    authDialog:
        'w-[92vw] sm:max-w-sm max-h-[92vh] flex flex-col p-0 rounded-2xl shadow-2xl border border-rose-200 dark:border-rose-900 bg-white dark:bg-slate-950 overflow-hidden',
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
        'focus:border-rose-900 focus:ring-rose-900/20 focus:outline-hidden',
} as const;
