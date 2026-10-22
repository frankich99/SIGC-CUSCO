import { ref, computed } from 'vue';

export interface BrandingPreset {
    key: string;
    label: string;
    description: string;
    color: string;
    institutionName: string;
    systemName: string;
    systemSubtitle: string;
    logoUrl: string;
}

export const BRANDING_PRESETS: BrandingPreset[] = [
    {
        key: 'mdsj',
        label: 'MDSJ San Jerónimo',
        description: 'Municipalidad Distrital de San Jerónimo (Azul Institucional)',
        color: '#3484a5',
        institutionName: 'Municipalidad Distrital de San Jerónimo',
        systemName: 'SIGC-MDSJ',
        systemSubtitle: 'Sistema Integral de Gestión de Capacitaciones',
        logoUrl: '/images/mdsj/escudo_sm.webp',
    },
    {
        key: 'unsaac',
        label: 'UNSAAC Cusco',
        description: 'Universidad Nacional de San Antonio Abad del Cusco (Granate)',
        color: '#800020',
        institutionName: 'Universidad Nacional de San Antonio Abad del Cusco',
        systemName: 'SIGC-UNSAAC',
        systemSubtitle: 'Sistema Integral de Gestión de Capacitaciones',
        logoUrl: '/images/unsaac-logo.png',
    },
    {
        key: 'emerald',
        label: 'Verde Esmeralda',
        description: 'Dirección Regional / Entidad Ecológica y de Desarrollo',
        color: '#059669',
        institutionName: 'Dirección Regional de Educación Cusco',
        systemName: 'SIGC-DREC',
        systemSubtitle: 'Plataforma Oficial de Capacitación Docente y Administrativa',
        logoUrl: '/images/escudo.png',
    },
    {
        key: 'navy',
        label: 'Azul Marino Imperial',
        description: 'Gobierno Regional / Colegios Profesionales',
        color: '#1e40af',
        institutionName: 'Gobierno Regional del Cusco',
        systemName: 'SIGC-GORE',
        systemSubtitle: 'Sistema Integrado de Formación y Certificación Continua',
        logoUrl: '/images/escudo.png',
    },
    {
        key: 'purple',
        label: 'Púrpura Tecnológico',
        description: 'Institutos Tecnológicos y Centros de Innovación',
        color: '#7c3aed',
        institutionName: 'Instituto de Educación Superior Tecnológico',
        systemName: 'SIGC-TECH',
        systemSubtitle: 'Centro de Formación Tecnológica y Competencias Digitales',
        logoUrl: '/images/escudo.png',
    },
    {
        key: 'custom',
        label: 'Color Personalizado',
        description: 'Definido manualmente por el usuario',
        color: '#2ca792',
        institutionName: 'Entidad Personalizada',
        systemName: 'SIGC-APP',
        systemSubtitle: 'Sistema Integral de Gestión de Capacitaciones',
        logoUrl: '/images/escudo.png',
    },
];

const DEFAULT_STATE = {
    institutionName: 'Municipalidad Distrital de San Jerónimo',
    systemName: 'SIGC-MDSJ',
    systemSubtitle: 'Sistema Integral de Gestión de Capacitaciones',
    logoUrl: '/images/mdsj/escudo_sm.webp',
    primaryColor: '#3484a5',
    presetKey: 'mdsj',
};

const STORAGE_KEY = 'sigc_branding';

// Global reactive singletons so all components share the same instance
const institutionName = ref(DEFAULT_STATE.institutionName);
const systemName = ref(DEFAULT_STATE.systemName);
const systemSubtitle = ref(DEFAULT_STATE.systemSubtitle);
const logoUrl = ref(DEFAULT_STATE.logoUrl);
const primaryColor = ref(DEFAULT_STATE.primaryColor);
const presetKey = ref(DEFAULT_STATE.presetKey);
const isInitialized = ref(false);

/**
 * Adjust hex color brightness (positive percent = lighten, negative = darken)
 */
export function adjustHex(hex: string, percent: number): string {
    const cleanHex = hex.replace('#', '');
    let num = parseInt(cleanHex, 16);
    if (isNaN(num)) return hex;

    let r = (num >> 16) + Math.round(255 * (percent / 100));
    let g = ((num >> 8) & 0x00ff) + Math.round(255 * (percent / 100));
    let b = (num & 0x0000ff) + Math.round(255 * (percent / 100));

    r = Math.min(255, Math.max(0, r));
    g = Math.min(255, Math.max(0, g));
    b = Math.min(255, Math.max(0, b));

    return '#' + ((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1);
}

/**
 * Convert Hex color to HSL string
 */
export function hexToHsl(hex: string): { h: number; s: number; l: number } {
    let cleanHex = hex.replace('#', '');
    if (cleanHex.length === 3) {
        cleanHex = cleanHex.split('').map((c) => c + c).join('');
    }
    const num = parseInt(cleanHex, 16);
    if (isNaN(num)) return { h: 197, s: 52, l: 43 };

    let r = (num >> 16) / 255;
    let g = ((num >> 8) & 0x00ff) / 255;
    let b = (num & 0x0000ff) / 255;

    const max = Math.max(r, g, b);
    const min = Math.min(r, g, b);
    let h = 0;
    let s = 0;
    let l = (max + min) / 2;

    if (max !== min) {
        const d = max - min;
        s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
        switch (max) {
            case r:
                h = (g - b) / d + (g < b ? 6 : 0);
                break;
            case g:
                h = (b - r) / d + 2;
                break;
            case b:
                h = (r - g) / d + 4;
                break;
        }
        h /= 6;
    }

    return {
        h: Math.round(h * 360),
        s: Math.round(s * 100),
        l: Math.round(l * 100),
    };
}

/**
 * Apply all branding CSS variables directly to :root / documentElement
 */
export function applyBrandingCssVariables(color: string) {
    if (typeof document === 'undefined') return;

    const root = document.documentElement;
    const hsl = hexToHsl(color);

    // Core institutional tokens
    root.style.setProperty('--institutional', color);
    root.style.setProperty('--institutional-hover', adjustHex(color, -12));
    root.style.setProperty('--institutional-dark', adjustHex(color, -35));
    root.style.setProperty('--institutional-deep', adjustHex(color, -60));
    root.style.setProperty('--institutional-light', adjustHex(color, 82));
    root.style.setProperty('--institutional-border', adjustHex(color, 55));

    // Tailwind compatibility mapping
    root.style.setProperty('--color-institutional', color);
    root.style.setProperty('--color-institutional-hover', adjustHex(color, -12));
    root.style.setProperty('--color-institutional-dark', adjustHex(color, -35));
    root.style.setProperty('--color-institutional-deep', adjustHex(color, -60));
    root.style.setProperty('--color-institutional-light', adjustHex(color, 82));
    root.style.setProperty('--color-institutional-border', adjustHex(color, 55));

    // Update primary token dynamically to match branding
    root.style.setProperty('--primary', `hsl(${hsl.h} ${hsl.s}% ${hsl.l}%)`);
    root.style.setProperty('--ring', `hsl(${hsl.h} ${hsl.s}% ${hsl.l}%)`);
    root.style.setProperty('--color-primary', color);
    root.style.setProperty('--color-ring', color);
}

/**
 * Load branding configuration from localStorage
 */
export function loadBranding() {
    if (typeof window === 'undefined') return;

    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        if (raw) {
            const parsed = JSON.parse(raw);
            if (parsed.institutionName) institutionName.value = parsed.institutionName;
            if (parsed.systemName) systemName.value = parsed.systemName;
            if (parsed.systemSubtitle) systemSubtitle.value = parsed.systemSubtitle;
            if (parsed.logoUrl) logoUrl.value = parsed.logoUrl;
            if (parsed.primaryColor) primaryColor.value = parsed.primaryColor;
            if (parsed.presetKey) presetKey.value = parsed.presetKey;
        }
    } catch {
        // Fallback to defaults
    }

    applyBrandingCssVariables(primaryColor.value);
    isInitialized.value = true;
}

/**
 * Save current branding state to localStorage and update CSS variables
 */
export function saveBranding() {
    if (typeof window === 'undefined') return;

    const data = {
        institutionName: institutionName.value,
        systemName: systemName.value,
        systemSubtitle: systemSubtitle.value,
        logoUrl: logoUrl.value,
        primaryColor: primaryColor.value,
        presetKey: presetKey.value,
    };

    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
        window.dispatchEvent(new CustomEvent('sigc-branding-changed', { detail: data }));
    } catch {
        // Ignored
    }

    applyBrandingCssVariables(primaryColor.value);
}

/**
 * Composable hook
 */
export function useBranding() {
    if (!isInitialized.value) {
        loadBranding();
    }

    function applyPreset(preset: BrandingPreset) {
        presetKey.value = preset.key;
        primaryColor.value = preset.color;
        if (preset.key !== 'custom') {
            institutionName.value = preset.institutionName;
            systemName.value = preset.systemName;
            systemSubtitle.value = preset.systemSubtitle;
            logoUrl.value = preset.logoUrl;
        }
        saveBranding();
    }

    function setManualColor(hex: string) {
        presetKey.value = 'custom';
        primaryColor.value = hex;
        saveBranding();
    }

    function setLogoUrl(url: string) {
        logoUrl.value = url;
        saveBranding();
    }

    function setEntityNames(names: {
        institutionName?: string;
        systemName?: string;
        systemSubtitle?: string;
    }) {
        if (names.institutionName) institutionName.value = names.institutionName;
        if (names.systemName) systemName.value = names.systemName;
        if (names.systemSubtitle) systemSubtitle.value = names.systemSubtitle;
        saveBranding();
    }

    function resetToMdsj() {
        const mdsj = BRANDING_PRESETS[0];
        applyPreset(mdsj);
    }

    return {
        institutionName,
        systemName,
        systemSubtitle,
        logoUrl,
        primaryColor,
        presetKey,
        presets: BRANDING_PRESETS,
        applyPreset,
        setManualColor,
        setLogoUrl,
        setEntityNames,
        resetToMdsj,
        saveBranding,
    };
}
