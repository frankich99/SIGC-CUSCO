/**
 * Official Peru and Cusco Geolocation & Institutional Metadata for SIGC-CUSCO.
 * Ensures consistent timezone (America/Lima, UTC-05:00), coordinates, currency, and validation.
 */

export const PERU_CONFIG = {
    country: 'Perú',
    countryCode: 'PE',
    countryCodeAlpha3: 'PER',
    phonePrefix: '+51',
    timezone: 'America/Lima',
    locale: 'es-PE',
    currency: {
        code: 'PEN',
        name: 'Sol Peruano',
        symbol: 'S/',
    },
    institution: {
        name: 'Universidad Nacional de San Antonio Abad del Cusco',
        acronym: 'UNSAAC',
        campus: 'Ciudad Universitaria de Perayoc',
        address: 'Av. de la Cultura 733, Wanchaq, Cusco 08000',
        city: 'Cusco',
        department: 'Cusco',
        province: 'Cusco',
        district: 'Wanchaq',
        postalCode: '08000',
        ubigeo: '080101',
        coordinates: {
            latitude: -13.52264,
            longitude: -71.96734,
        },
        googleMapsUrl: 'https://maps.google.com/?q=-13.52264,-71.96734',
        openStreetMapUrl:
            'https://www.openstreetmap.org/?mlat=-13.52264&mlon=-71.96734#map=17/-13.52264/-71.96734',
    },
} as const;

export const PERU_DEPARTMENTS = [
    'Amazonas',
    'Áncash',
    'Apurímac',
    'Arequipa',
    'Ayacucho',
    'Cajamarca',
    'Callao',
    'Cusco',
    'Huancavelica',
    'Huánuco',
    'Ica',
    'Junín',
    'La Libertad',
    'Lambayeque',
    'Lima',
    'Loreto',
    'Madre de Dios',
    'Moquegua',
    'Pasco',
    'Piura',
    'Puno',
    'San Martín',
    'Tacna',
    'Tumbes',
    'Ucayali',
] as const;

/**
 * Validates a Peruvian National Identity Document (DNI).
 * Must be exactly 8 numeric digits.
 */
export function validatePeruDni(dni: string | null | undefined): boolean {
    if (!dni) return false;
    return /^[0-9]{8}$/.test(dni.trim());
}

/**
 * Validates a Peruvian mobile phone number.
 * Must be 9 numeric digits, conventionally starting with 9.
 */
export function validatePeruPhone(phone: string | null | undefined): boolean {
    if (!phone) return false;
    const clean = phone.replace(/\D/g, '');
    if (clean.length === 9 && clean.startsWith('9')) return true;
    if (clean.length === 11 && clean.startsWith('519')) return true;
    return false;
}

/**
 * Formats a phone number with the official Peru prefix (+51).
 * E.g., '984123456' -> '+51 984 123 456'
 */
export function formatPeruPhone(phone: string | null | undefined): string {
    if (!phone) return '-';
    const clean = phone.replace(/\D/g, '');
    let nationalNumber = clean;

    if (clean.startsWith('51') && clean.length === 11) {
        nationalNumber = clean.substring(2);
    }

    if (nationalNumber.length === 9) {
        return `+51 ${nationalNumber.substring(0, 3)} ${nationalNumber.substring(3, 6)} ${nationalNumber.substring(6)}`;
    }

    return phone.trim();
}

/**
 * Formats an amount in Peruvian Soles (PEN).
 * E.g., 150 -> 'S/ 150.00'
 */
export function formatCurrencyPEN(amount: number | null | undefined): string {
    if (amount == null || isNaN(amount)) return 'S/ 0.00';
    return new Intl.NumberFormat('es-PE', {
        style: 'currency',
        currency: 'PEN',
        currencyDisplay: 'narrowSymbol',
    }).format(amount);
}

/**
 * Returns current date and time formatted in official Peru Time (America/Lima, UTC-05:00).
 */
export function getCurrentPeruTime(): string {
    return new Intl.DateTimeFormat('es-PE', {
        timeZone: 'America/Lima',
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false,
    }).format(new Date());
}
