/**
 * Centralized Theme Palette for SIGC-CUSCO
 * Institutional Granate Imperial & Dorado Sol de Echenique (UNSAAC / Cusco)
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

export const THEME_BUTTONS = {
    primary: 'bg-rose-900 hover:bg-rose-950 text-white font-bold shadow-sm transition-all',
    secondary: 'bg-white hover:bg-rose-50 text-rose-950 border border-rose-200 font-bold shadow-2xs',
    gold: 'bg-amber-600 hover:bg-amber-700 text-white font-bold shadow-sm',
    outline: 'border-slate-300 hover:border-rose-800 text-slate-800 hover:text-rose-900 font-semibold',
} as const;
