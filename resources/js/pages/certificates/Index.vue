<script setup lang="ts">
import { ref, computed, onMounted, nextTick } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    Award,
    Search,
    Loader2,
    Calendar,
    Clock,
    CheckCircle2,
    Download,
    Building2,
    AlertCircle,
    Printer,
    FileCheck,
    Copy,
    Check,
    ShieldCheck,
    ArrowLeft,
    Eye,
    QrCode,
    ExternalLink,
    BookOpen,
    Share2,
    ZoomIn,
    ZoomOut,
    RotateCcw,
    Layers,
    X,
    FileSearch,
    BadgeCheck,
    GraduationCap,
    Lock,
    HelpCircle,
    Info,
} from '@lucide/vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogTitle,
} from '@/components/ui/dialog';
import PdfIntegrityVerifier from '@/components/PdfIntegrityVerifier.vue';
import { notify } from '@/lib/notify';
import { formatDateRange, formatHours } from '@/lib/formatters';
import type { BreadcrumbItem } from '@/types';
import {
    type CertificateRecord,
    printCertificate,
    DOCENTE_STAMP_SVG,
    DIRECCION_STAMP_SVG,
    formatSpanishDate,
    formatSpanishDateRange,
} from '@/lib/certificateTemplate';

export type { CertificateRecord };

const props = defineProps<{
    initialDni?: string;
    initialCode?: string;
    showModalOnLoad?: boolean;
}>();

const page = usePage();
const authUser = computed(() => (page.props.auth as any)?.user);

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    {
        title: authUser.value ? 'Panel Principal' : 'Portal Principal',
        href: authUser.value ? '/dashboard' : '/',
    },
    { title: 'Validación de Certificados', href: '/certificates' },
]);

// Pestaña principal: 'search' para consulta de certificados | 'pdf' para validación de archivo PDF
const activeTab = ref<'search' | 'pdf'>('search');

// Modalidad de consulta: 'dni' (Documento 8 dígitos) o 'code' (Código alfanumérico de certificado)
const searchMode = ref<'dni' | 'code'>(props.initialCode ? 'code' : 'dni');
const queryInput = ref(props.initialCode || props.initialDni || '');

const loading = ref(false);
const searched = ref(false);
const error = ref<string | null>(null);
const studentName = ref<string | null>(null);
const records = ref<CertificateRecord[]>([]);

// Modal de Vista Previa del Diploma de Estudio
const selectedCert = ref<CertificateRecord | null>(null);
const isCertModalOpen = ref(false);
const previewTab = ref<'anverso' | 'reverso' | 'completa'>('anverso');
const zoomScale = ref<number>(100);
const isVerifiedByQr = ref(false);
const copiedCodeMap = ref<Record<string, boolean>>({});
const copiedUrl = ref(false);

function zoomIn() {
    if (zoomScale.value < 130) zoomScale.value += 15;
}

function zoomOut() {
    if (zoomScale.value > 65) zoomScale.value -= 15;
}

function resetZoom() {
    zoomScale.value = 100;
}

function setSearchMode(mode: 'dni' | 'code') {
    if (searchMode.value === mode) return;
    searchMode.value = mode;
    error.value = null;
    queryInput.value = '';
    searched.value = false;
    records.value = [];
    studentName.value = null;
}

function allowOnlyNumbers(e: KeyboardEvent) {
    if (searchMode.value !== 'dni') return;
    if (
        [
            'Backspace',
            'Delete',
            'Tab',
            'ArrowLeft',
            'ArrowRight',
            'Home',
            'End',
            'Enter',
        ].includes(e.key)
    ) {
        return;
    }
    if (e.ctrlKey || e.metaKey) return;
    if (!/^[0-9]$/.test(e.key)) {
        e.preventDefault();
    }
}

function onQueryInput(e: Event) {
    const target = e.target as HTMLInputElement;
    if (searchMode.value === 'dni') {
        const sanitized = target.value.replace(/\D/g, '').slice(0, 8);
        if (target.value !== sanitized) {
            target.value = sanitized;
        }
        queryInput.value = sanitized;
    } else {
        queryInput.value = target.value.trim();
    }
    if (error.value) error.value = null;
}

async function performSearch(overrideQuery?: string, overrideMode?: 'dni' | 'code') {
    const mode = overrideMode || searchMode.value;
    const rawVal = (overrideQuery !== undefined ? overrideQuery : queryInput.value).trim();

    if (!rawVal) {
        error.value = mode === 'dni'
            ? 'Ingrese un número de DNI de 8 dígitos para consultar.'
            : 'Ingrese el código de verificación del certificado (ej. CERT-2026-XXXX).';
        return;
    }

    if (mode === 'dni' && !/^\d{8}$/.test(rawVal)) {
        error.value = 'El DNI debe contener exactamente 8 dígitos numéricos.';
        return;
    }

    loading.value = true;
    error.value = null;
    records.value = [];
    searched.value = true;

    try {
        const queryParams = new URLSearchParams();
        if (mode === 'dni') {
            queryParams.set('dni', rawVal);
        } else {
            queryParams.set('code', rawVal);
        }

        const response = await fetch(`/api/certificates/lookup?${queryParams.toString()}`, {
            headers: {
                Accept: 'application/json',
            },
        });

        const data = await response.json();

        if (response.ok && data.success) {
            records.value = data.records || [];
            studentName.value = data.student_name || (records.value[0]?.student_name ?? null);

            if (records.value.length > 0) {
                if (mode === 'code') {
                    const matched = records.value.find(
                        (r) => r.certificate_code.toLowerCase() === rawVal.toLowerCase(),
                    ) || records.value[0];
                    selectedCert.value = matched;
                    isVerifiedByQr.value = true;
                    previewTab.value = 'anverso';
                    isCertModalOpen.value = true;
                    notify.success(
                        'Certificado Verificado con Éxito',
                        `Acreditado a nombre de ${matched.student_name}`,
                        3000,
                    );
                } else {
                    notify.success(
                        'Certificados Encontrados',
                        `Se encontraron ${records.value.length} diploma(s) oficial(es)`,
                        2000,
                    );
                }
            } else {
                studentName.value = null;
                notify.warning(
                    'Sin registros oficiales',
                    mode === 'dni'
                        ? 'No se encontraron certificados para el DNI consultado.'
                        : 'No se encontró ningún certificado con el código ingresado.',
                    3000,
                );
            }
        } else {
            error.value = data.message || 'No se encontraron registros para la consulta solicitada.';
            notify.error('Búsqueda sin resultados', error.value || 'Intente nuevamente.', 3000);
        }
    } catch {
        error.value = 'Error al conectarse con el servidor de validación. Intente nuevamente.';
        notify.error('Error de conexión', error.value || 'Intente nuevamente.', 3000);
    } finally {
        loading.value = false;
    }
}

function resetSearch(): void {
    queryInput.value = '';
    error.value = null;
    records.value = [];
    searched.value = false;
    studentName.value = null;
    nextTick(() => {
        const input = document.getElementById('cert-search-input') as HTMLInputElement | null;
        if (input) input.focus();
    });
}

function openCertificatePreview(record: CertificateRecord) {
    selectedCert.value = record;
    isVerifiedByQr.value = false;
    previewTab.value = 'anverso';
    isCertModalOpen.value = true;
}

async function copyVerificationCode(code: string) {
    try {
        await navigator.clipboard.writeText(code);
        copiedCodeMap.value[code] = true;
        notify.success('Código copiado al portapapeles', code, 1500);
        setTimeout(() => {
            copiedCodeMap.value[code] = false;
        }, 2000);
    } catch {
        // Fallback
    }
}

async function copyVerificationUrl(url: string) {
    try {
        await navigator.clipboard.writeText(url);
        copiedUrl.value = true;
        notify.success('Enlace de validación copiado', 'Puedes compartir este enlace directo', 1500);
        setTimeout(() => {
            copiedUrl.value = false;
        }, 2000);
    } catch {
        // Fallback
    }
}

onMounted(() => {
    const params = new URLSearchParams(window.location.search);
    const codeParam = params.get('code') || props.initialCode;
    const dniParam = params.get('dni') || props.initialDni;

    if (codeParam && codeParam.trim()) {
        searchMode.value = 'code';
        queryInput.value = codeParam.trim();
        performSearch(codeParam.trim(), 'code');
    } else if (dniParam && /^\d{8}$/.test(dniParam.trim())) {
        searchMode.value = 'dni';
        queryInput.value = dniParam.trim();
        performSearch(dniParam.trim(), 'dni');
    }
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Validación Oficial de Certificados - SIGC-MDSJ" />

        <div class="mx-auto w-full max-w-6xl min-w-0 space-y-8 overflow-x-hidden px-4 py-6 sm:px-6 sm:py-8 lg:px-8">
            <!-- HEADER DE SECCIÓN ACADÉMICA INSTITUCIONAL -->
            <div class="flex flex-col justify-between gap-4 border-b border-slate-200/90 pb-5 sm:flex-row sm:items-center dark:border-slate-800">
                <div class="space-y-1">
                    <div class="flex items-center gap-3">
                        <div class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-[#1a6b8a] text-white shadow-md ring-1 ring-[#1a6b8a]/30 dark:bg-[#1a6b8a]">
                            <Award class="size-6 text-amber-300" />
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h1 class="text-xl font-black tracking-tight text-slate-950 sm:text-2xl dark:text-white">
                                    Consulta y Validación de Certificados
                                </h1>
                                <Badge class="hidden sm:inline-flex border-amber-300 bg-amber-50 text-[10px] font-black text-amber-900 dark:border-amber-800/60 dark:bg-amber-950/40 dark:text-amber-200">
                                    <ShieldCheck class="mr-1 size-3 text-amber-600" />
                                    Fe Pública Oficial
                                </Badge>
                            </div>
                            <p class="text-xs font-medium text-slate-600 sm:text-sm dark:text-slate-400">
                                Acreditación oficial institucional, diplomas con firma digital y verificación criptográfica SHA-256.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <Button
                        as-child
                        variant="outline"
                        size="sm"
                        class="cursor-pointer border-slate-300 text-xs font-bold hover:border-[#1a6b8a] hover:text-[#1a6b8a] dark:border-slate-700 dark:hover:text-sky-300"
                    >
                        <Link :href="authUser ? '/dashboard' : '/'">
                            <ArrowLeft class="mr-1 size-3.5 text-slate-600 dark:text-slate-400" />
                            {{ authUser ? 'Volver al Panel' : 'Portal Principal' }}
                        </Link>
                    </Button>
                </div>
            </div>

            <!-- SELECTOR DE MODALIDAD (TABS RESPONSIVE) -->
            <div class="mx-auto flex w-full max-w-xl items-center justify-center px-1">
                <div class="grid w-full grid-cols-2 gap-1 rounded-xl border border-slate-200 bg-slate-100 p-1 shadow-xs dark:border-slate-800 dark:bg-slate-900">
                    <button
                        type="button"
                        @click="activeTab = 'search'"
                        class="flex cursor-pointer items-center justify-center gap-2 rounded-lg px-3 py-2 text-center text-xs font-black transition-all sm:px-5"
                        :class="
                            activeTab === 'search'
                                ? 'bg-[#1a6b8a] text-white shadow-sm'
                                : 'text-slate-700 hover:text-[#1a6b8a] dark:text-slate-300 dark:hover:text-sky-300'
                        "
                    >
                        <Search class="size-3.5 shrink-0" />
                        <span class="truncate">Búsqueda de Certificados</span>
                    </button>
                    <button
                        type="button"
                        @click="activeTab = 'pdf'"
                        class="flex cursor-pointer items-center justify-center gap-2 rounded-lg px-3 py-2 text-center text-xs font-black transition-all sm:px-5"
                        :class="
                            activeTab === 'pdf'
                                ? 'bg-[#1a6b8a] text-white shadow-sm'
                                : 'text-slate-700 hover:text-[#1a6b8a] dark:text-slate-300 dark:hover:text-sky-300'
                        "
                    >
                        <FileCheck
                            class="size-3.5 shrink-0"
                            :class="activeTab === 'pdf' ? 'text-amber-300' : 'text-amber-500'"
                        />
                        <span class="truncate">Comprobador PDF (SHA-256)</span>
                    </button>
                </div>
            </div>

            <!-- PESTAÑA 1: CONSULTA DE CERTIFICADOS OFICIALES -->
            <div v-if="activeTab === 'search'" class="space-y-8">
                <!-- FORMULARIO EN LAYOUT DE 2 COLUMNAS BALANCEADO (SI NO HA BUSCADO O PARA NUEVA CONSULTA) -->
                <div v-if="!searched" class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                    <!-- COLUMNA PRINCIPAL (7 cols): Búsqueda por DNI / Código Oficial -->
                    <div class="lg:col-span-7 space-y-5">
                        <Card class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                            <CardHeader class="border-b border-slate-100 bg-slate-50/70 p-5 pb-4 dark:border-slate-800 dark:bg-slate-900/90 sm:p-6 sm:pb-4">
                                <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                                    <div>
                                        <div class="flex items-center gap-1.5 text-[11px] font-black tracking-wider text-[#1a6b8a] uppercase dark:text-sky-400">
                                            <BadgeCheck class="size-4 text-[#1a6b8a] dark:text-sky-400" />
                                            <span>Padrón Oficial de Fe Pública • SIGC-MDSJ</span>
                                        </div>
                                        <CardTitle class="mt-1 text-base font-black text-slate-950 sm:text-lg dark:text-white">
                                            Consultar Certificados y Diplomas Oficiales
                                        </CardTitle>
                                        <CardDescription class="text-xs font-medium text-slate-600 dark:text-slate-400">
                                            {{
                                                searchMode === 'dni'
                                                    ? 'Ingrese el número de DNI para consultar las acreditaciones emitidas al participante.'
                                                    : 'Ingrese el código de verificación único para validar la autenticidad e inmutabilidad del diploma.'
                                            }}
                                        </CardDescription>
                                    </div>

                                    <!-- Selector de Modo de Búsqueda (Pills Limpias) -->
                                    <div class="inline-flex shrink-0 rounded-xl border border-slate-200 bg-slate-100 p-1 shadow-2xs dark:border-slate-700 dark:bg-slate-800">
                                        <button
                                            type="button"
                                            @click="setSearchMode('dni')"
                                            class="cursor-pointer rounded-lg px-3 py-1.5 text-xs font-bold transition-all"
                                            :class="
                                                searchMode === 'dni'
                                                    ? 'bg-white text-slate-950 shadow-xs dark:bg-slate-900 dark:text-white font-black'
                                                    : 'text-slate-600 hover:text-slate-950 dark:text-slate-400 dark:hover:text-white'
                                            "
                                        >
                                            Por DNI (8 dígitos)
                                        </button>
                                        <button
                                            type="button"
                                            @click="setSearchMode('code')"
                                            class="cursor-pointer rounded-lg px-3 py-1.5 text-xs font-bold transition-all"
                                            :class="
                                                searchMode === 'code'
                                                    ? 'bg-white text-slate-950 shadow-xs dark:bg-slate-900 dark:text-white font-black'
                                                    : 'text-slate-600 hover:text-slate-950 dark:text-slate-400 dark:hover:text-white'
                                            "
                                        >
                                            Por Código Oficial
                                        </button>
                                    </div>
                                </div>
                            </CardHeader>

                            <CardContent class="space-y-4 p-5 sm:p-6">
                                <form @submit.prevent="performSearch()" class="flex flex-col gap-3 sm:flex-row">
                                    <div class="relative flex-1">
                                        <Search class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-slate-400" />
                                        <input
                                            id="cert-search-input"
                                            v-model="queryInput"
                                            @input="onQueryInput"
                                            @keypress="allowOnlyNumbers"
                                            type="text"
                                            :inputmode="searchMode === 'dni' ? 'numeric' : 'text'"
                                            :pattern="searchMode === 'dni' ? '[0-9]*' : undefined"
                                            :maxlength="searchMode === 'dni' ? 8 : 40"
                                            :placeholder="
                                                searchMode === 'dni'
                                                    ? 'Ingrese número de DNI (8 dígitos)'
                                                    : 'Ej: CERT-2026-13396200'
                                            "
                                            class="h-12 w-full rounded-xl border border-slate-300 bg-white pr-14 pl-10 text-sm font-bold tracking-normal text-slate-950 shadow-2xs transition-all placeholder:font-normal placeholder:text-slate-400 focus:border-[#1a6b8a] focus:ring-2 focus:ring-[#1a6b8a]/20 focus:outline-hidden disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                                            :class="searchMode === 'dni' ? 'font-mono tracking-widest' : ''"
                                            :disabled="loading"
                                            autofocus
                                        />
                                        <div
                                            v-if="searchMode === 'dni'"
                                            class="pointer-events-none absolute top-1/2 right-3.5 -translate-y-1/2 font-mono text-[11px] font-bold text-slate-400"
                                        >
                                            {{ queryInput.length }}/8
                                        </div>
                                    </div>

                                    <Button
                                        type="submit"
                                        :disabled="loading || !queryInput.trim() || (searchMode === 'dni' && queryInput.length !== 8)"
                                        class="h-12 shrink-0 cursor-pointer bg-[#059669] px-6 text-xs font-bold text-white shadow-md shadow-emerald-700/20 hover:bg-[#047857] disabled:cursor-not-allowed disabled:bg-slate-200 disabled:text-slate-400 dark:bg-[#059669] dark:hover:bg-[#047857] dark:disabled:bg-slate-800 dark:disabled:text-slate-600 sm:text-sm transition-all"
                                    >
                                        <Loader2 v-if="loading" class="mr-2 size-4 animate-spin" />
                                        <ShieldCheck v-else class="mr-2 size-4 text-emerald-200" />
                                        <span>{{ searchMode === 'dni' ? 'Consultar Certificados' : 'Validar Código' }}</span>
                                    </Button>
                                </form>

                                <div
                                    v-if="error"
                                    class="flex items-center gap-2 rounded-xl border border-rose-200 bg-rose-50 p-3 text-xs font-bold text-rose-950 dark:border-rose-900/60 dark:bg-rose-950/40 dark:text-rose-200"
                                >
                                    <AlertCircle class="size-4 shrink-0 text-rose-600 dark:text-rose-400" />
                                    <span>{{ error }}</span>
                                </div>

                                <div class="rounded-xl border border-dashed border-slate-200 bg-slate-50/60 p-3.5 text-xs text-slate-600 dark:border-slate-800 dark:bg-slate-950/40 dark:text-slate-400">
                                    <div class="flex items-center gap-2 font-bold text-slate-800 dark:text-slate-200">
                                        <Info class="size-4 text-[#1a6b8a] dark:text-sky-400" />
                                        <span>Verificación Directa mediante QR</span>
                                    </div>
                                    <p class="mt-1 text-[11px] leading-relaxed text-slate-500 dark:text-slate-400">
                                        Cada diploma emitido por la Municipalidad Distrital de San Jerónimo cuenta con un código QR único para validación inmediata sin intermediarios.
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- COLUMNA LATERAL (5 cols): Respaldo y Garantías Institucionales MDSJ -->
                    <div class="lg:col-span-5 space-y-4">
                        <Card class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xs dark:border-slate-800 dark:bg-slate-900">
                            <CardHeader class="border-b border-slate-100 pb-3 dark:border-slate-800">
                                <div class="flex items-center gap-2.5">
                                    <div class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-sky-100 text-[#1a6b8a] dark:bg-sky-950/80 dark:text-sky-300">
                                        <ShieldCheck class="size-4.5" />
                                    </div>
                                    <div>
                                        <CardTitle class="text-sm font-bold text-slate-950 dark:text-white">
                                            Garantía de Autenticidad MDSJ
                                        </CardTitle>
                                        <p class="text-[11px] text-slate-500 dark:text-slate-400">
                                            Fe Pública y Validez Institucional
                                        </p>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent class="p-4 space-y-3.5 text-xs">
                                <div class="flex items-start gap-3">
                                    <div class="flex size-7 shrink-0 items-center justify-center rounded-md bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 mt-0.5">
                                        <CheckCircle2 class="size-4" />
                                    </div>
                                    <div>
                                        <div class="font-bold text-slate-900 dark:text-slate-100">Validación RENIEC en Tiempo Real</div>
                                        <p class="text-[11px] leading-relaxed text-slate-600 dark:text-slate-400">
                                            Se cotejan los datos de identidad con el padrón oficial civil.
                                        </p>
                                    </div>
                                </div>

                                <div class="flex items-start gap-3">
                                    <div class="flex size-7 shrink-0 items-center justify-center rounded-md bg-sky-100 text-[#1a6b8a] dark:bg-sky-950/60 dark:text-sky-300 mt-0.5">
                                        <Lock class="size-4" />
                                    </div>
                                    <div>
                                        <div class="font-bold text-slate-900 dark:text-slate-100">Criptografía SHA-256 Inmutable</div>
                                        <p class="text-[11px] leading-relaxed text-slate-600 dark:text-slate-400">
                                            Cada diploma posee un hash criptográfico único que impide su adulteración.
                                        </p>
                                    </div>
                                </div>

                                <div class="flex items-start gap-3">
                                    <div class="flex size-7 shrink-0 items-center justify-center rounded-md bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 mt-0.5">
                                        <QrCode class="size-4" />
                                    </div>
                                    <div>
                                        <div class="font-bold text-slate-900 dark:text-slate-100">Código QR de Fe Pública</div>
                                        <p class="text-[11px] leading-relaxed text-slate-600 dark:text-slate-400">
                                            Escaneable desde smartphones para verificar nota, horas y docente responsable.
                                        </p>
                                    </div>
                                </div>

                                <div class="flex items-start gap-3">
                                    <div class="flex size-7 shrink-0 items-center justify-center rounded-md bg-indigo-100 text-indigo-800 dark:bg-indigo-950/60 dark:text-indigo-300 mt-0.5">
                                        <Building2 class="size-4" />
                                    </div>
                                    <div>
                                        <div class="font-bold text-slate-900 dark:text-slate-100">Reconocimiento Municipal y Legal</div>
                                        <p class="text-[11px] leading-relaxed text-slate-600 dark:text-slate-400">
                                            Expedido por la Municipalidad Distrital de San Jerónimo con valor curricular oficial.
                                        </p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-2xs dark:border-slate-800 dark:bg-slate-900">
                            <div class="flex items-center gap-2 text-xs font-bold text-slate-800 dark:text-slate-200">
                                <HelpCircle class="size-4 text-slate-400" />
                                <span>¿Necesitas asistencia con tu certificado?</span>
                            </div>
                            <p class="mt-1 text-[11px] leading-relaxed text-slate-500 dark:text-slate-400">
                                Comunícate con la Gerencia de Desarrollo Social o acércate a la sede de la Municipalidad Distrital de San Jerónimo.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- ESTADO DE CARGA -->
                <div v-if="loading" class="mx-auto max-w-3xl space-y-3 py-10 text-center">
                    <Loader2 class="mx-auto size-9 animate-spin text-[#1a6b8a] dark:text-sky-400" />
                    <p class="text-xs font-bold text-slate-700 sm:text-sm dark:text-slate-300">
                        Consultando registro oficial de acreditación institucional...
                    </p>
                </div>

                <!-- RESULTADOS DE CONSULTA -->
                <div v-else-if="searched" class="w-full space-y-6">
                    <template v-if="records.length > 0">
                        <!-- TARJETA DEL TITULAR ACREDITADO (PROPORCIONES PERFECTAS A ANCHO COMPLETO) -->
                        <div class="flex flex-col items-start justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:flex-row sm:items-center sm:p-6 dark:border-slate-800 dark:bg-slate-900">
                            <div class="flex items-center gap-3.5">
                                <div class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-[#1a6b8a] text-white shadow-sm ring-1 ring-[#1a6b8a]/30 dark:bg-[#1a6b8a]">
                                    <GraduationCap class="size-6 text-amber-300" />
                                </div>
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h2 class="text-base font-black tracking-tight text-slate-950 uppercase sm:text-lg dark:text-white">
                                            {{ studentName }}
                                        </h2>
                                        <Badge class="border-emerald-200 bg-emerald-50 text-[10px] font-black text-emerald-900 dark:border-emerald-800/60 dark:bg-emerald-950/40 dark:text-emerald-300">
                                            <CheckCircle2 class="mr-1 size-3 text-emerald-600" />
                                            Titular Oficial Acreditado
                                        </Badge>
                                    </div>
                                    <div class="mt-1 flex flex-wrap items-center gap-3 text-xs text-slate-600 dark:text-slate-400">
                                        <span v-if="records[0]?.dni" class="font-mono">
                                            DNI: <strong class="text-slate-900 dark:text-white">{{ records[0].dni }}</strong>
                                        </span>
                                        <span>•</span>
                                        <span class="text-slate-600 dark:text-slate-400">Municipalidad Distrital de San Jerónimo</span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <Badge
                                    variant="outline"
                                    class="border-[#1a6b8a]/30 bg-sky-50 px-3 py-1 text-xs font-bold text-[#1a6b8a] dark:border-sky-800/60 dark:bg-sky-950/40 dark:text-sky-200"
                                >
                                    <Award class="mr-1 size-3.5 text-[#1a6b8a] dark:text-sky-300" />
                                    {{ records.length }}
                                    {{ records.length === 1 ? 'Diploma Oficial' : 'Diplomas Oficiales' }}
                                </Badge>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    class="cursor-pointer border-slate-300 text-xs font-bold hover:border-[#1a6b8a] hover:text-[#1a6b8a] dark:border-slate-700 dark:hover:text-sky-300"
                                    @click="resetSearch"
                                >
                                    <RotateCcw class="mr-1 size-3.5 text-slate-600 dark:text-slate-400" />
                                    <span>Nueva Consulta</span>
                                </Button>
                            </div>
                        </div>

                        <!-- LISTADO DE CERTIFICADOS EN GRILLA DE 2 COLUMNAS RESPONSIVA -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <Card
                                v-for="record in records"
                                :key="record.id"
                                class="flex flex-col justify-between overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xs transition-all hover:border-[#1a6b8a]/40 hover:shadow-md dark:border-slate-800 dark:bg-slate-900 dark:hover:border-slate-700"
                            >
                                <CardHeader class="flex flex-col justify-between gap-3 border-b border-slate-100 bg-slate-50/70 p-4 pb-3 dark:border-slate-800 dark:bg-slate-900/80">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <span class="rounded-lg bg-[#1a6b8a] px-2.5 py-0.5 font-mono text-xs font-black text-white">
                                            {{ record.course_code }}
                                        </span>
                                        <Badge class="border border-sky-200 bg-sky-50 text-[11px] font-bold text-[#1a6b8a] dark:border-sky-900/60 dark:bg-sky-950/40 dark:text-sky-300">
                                            <Building2 class="mr-1 size-3 text-[#1a6b8a] dark:text-sky-300" />
                                            {{ record.institution || 'MDSJ' }}
                                        </Badge>
                                    </div>
                                    <Badge class="w-fit border-emerald-200 bg-emerald-50 text-[11px] font-black text-emerald-900 dark:border-emerald-800/60 dark:bg-emerald-950/40 dark:text-emerald-300">
                                        <CheckCircle2 class="mr-1 size-3 text-emerald-600" />
                                        Acreditado Oficialmente • Fe Pública
                                    </Badge>
                                </CardHeader>

                                <CardContent class="flex-1 space-y-4 p-4 sm:p-5">
                                    <h3 class="text-base font-black leading-snug tracking-tight text-slate-950 dark:text-white line-clamp-2">
                                        {{ record.course_title }}
                                    </h3>

                                    <!-- Calificación Oficial Acreditada -->
                                    <div class="flex flex-wrap items-center gap-2">
                                        <div class="inline-flex items-center gap-1.5 rounded-full border border-emerald-300 bg-emerald-50/90 px-3.5 py-1 text-xs font-black text-emerald-950 dark:border-emerald-800/70 dark:bg-emerald-950/40 dark:text-emerald-200">
                                            <CheckCircle2 class="size-3.5 text-emerald-600" />
                                            <span>Calificación Oficial:</span>
                                            <span class="text-sm text-emerald-800 dark:text-emerald-300">{{ record.final_grade }} / 20.00</span>
                                            <span class="text-[11px] font-semibold text-emerald-700 dark:text-emerald-400">
                                                ({{ record.final_grade_text || 'Aprobado con Excelencia' }})
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Ficha Técnica Proporcionada en 3 Bloques -->
                                    <div class="grid grid-cols-1 gap-2.5 text-xs sm:grid-cols-3">
                                        <!-- Ponente / Docente Responsable -->
                                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-2.5 dark:border-slate-800 dark:bg-slate-800/50">
                                            <div class="mb-0.5 text-[10px] font-black tracking-wider text-slate-500 uppercase dark:text-slate-400">
                                                Docente
                                            </div>
                                            <div class="truncate font-bold text-slate-900 dark:text-white">
                                                {{ record.instructor_name }}
                                            </div>
                                        </div>

                                        <!-- Carga Lectiva Oficial -->
                                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-2.5 dark:border-slate-800 dark:bg-slate-800/50">
                                            <div class="mb-0.5 text-[10px] font-black tracking-wider text-slate-500 uppercase dark:text-slate-400">
                                                Carga Lectiva
                                            </div>
                                            <div class="flex items-center gap-1 font-bold text-slate-900 dark:text-white">
                                                <Clock class="size-3 text-amber-600" />
                                                {{ formatHours(record.hours) }}
                                            </div>
                                        </div>

                                        <!-- Período de Capacitación -->
                                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-2.5 dark:border-slate-800 dark:bg-slate-800/50">
                                            <div class="mb-0.5 text-[10px] font-black tracking-wider text-slate-500 uppercase dark:text-slate-400">
                                                Período
                                            </div>
                                            <div class="flex items-center gap-1 font-bold text-slate-900 dark:text-white">
                                                <Calendar class="size-3 text-slate-500" />
                                                {{ formatDateRange(record.start_date, record.end_date, 'medium') }}
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Código Oficial y Acciones -->
                                    <div class="flex flex-col justify-between gap-3 border-t border-slate-200 pt-3.5 dark:border-slate-800">
                                        <div class="flex flex-wrap items-center justify-between gap-2 font-mono text-xs text-slate-600 dark:text-slate-400">
                                            <div class="flex items-center gap-1.5">
                                                <span class="font-bold text-slate-900 dark:text-slate-200">Cód:</span>
                                                <span class="rounded-md border border-slate-200 bg-slate-100 px-2 py-0.5 font-black text-[#1a6b8a] dark:border-slate-700 dark:bg-slate-800 dark:text-sky-300">
                                                    {{ record.certificate_code }}
                                                </span>
                                                <button
                                                    type="button"
                                                    @click="copyVerificationCode(record.certificate_code)"
                                                    class="cursor-pointer text-slate-400 hover:text-slate-700 dark:hover:text-white"
                                                    title="Copiar código"
                                                >
                                                    <Check v-if="copiedCodeMap[record.certificate_code]" class="size-3.5 text-emerald-600" />
                                                    <Copy v-else class="size-3.5" />
                                                </button>
                                            </div>
                                            <div class="text-[10px] text-slate-500">
                                                {{ record.certificate_issued_at || 'Oficial' }}
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 pt-1">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                class="h-9 cursor-pointer justify-center border-slate-300 px-3 text-xs font-bold hover:border-[#1a6b8a] hover:text-[#1a6b8a] dark:border-slate-700 dark:hover:text-sky-300"
                                                @click="openCertificatePreview(record)"
                                            >
                                                <Eye class="mr-1.5 size-3.5" />
                                                Ver Diploma
                                            </Button>
                                            <Button
                                                type="button"
                                                size="sm"
                                                class="h-9 cursor-pointer justify-center bg-[#059669] px-3 text-xs font-bold text-white shadow-xs hover:bg-[#047857] transition-all"
                                                @click="printCertificate(record)"
                                            >
                                                <Download class="mr-1.5 size-3.5 text-emerald-200" />
                                                Descargar PDF
                                            </Button>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </template>

                    <!-- ESTADO VACÍO (SIN REGISTROS) -->
                    <div v-else class="space-y-3 rounded-2xl border border-slate-200 bg-white p-8 text-center shadow-xs dark:border-slate-800 dark:bg-slate-900">
                        <div class="mx-auto flex size-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400 dark:bg-slate-800">
                            <FileSearch class="size-6 text-slate-500" />
                        </div>
                        <h3 class="text-base font-bold text-slate-950 dark:text-white">
                            No se encontraron certificados oficiales
                        </h3>
                        <p class="mx-auto max-w-md text-xs leading-relaxed text-slate-600 dark:text-slate-400">
                            No se registran diplomas emitidos con los datos consultados. Verifique que el número de DNI o código sea correcto, o consulte tras el cierre de actas académicas.
                        </p>
                        <div class="pt-2">
                            <Button
                                variant="outline"
                                size="sm"
                                class="border-slate-300 font-semibold dark:border-slate-700 hover:border-[#1a6b8a] hover:text-[#1a6b8a]"
                                @click="resetSearch"
                            >
                                <RotateCcw class="mr-1.5 size-3.5" />
                                Intentar otra búsqueda
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- PESTAÑA 2: COMPROBADOR CRIPTOGRÁFICO DE ARCHIVO PDF -->
            <div v-else class="mx-auto max-w-4xl space-y-6">
                <PdfIntegrityVerifier
                    :expected-hash="selectedCert?.certificate_hash || records[0]?.certificate_hash"
                />
            </div>
        </div>

        <!-- MODAL DE VISTA PREVIA DEL DIPLOMA OFICIAL (VISOR ESTUDIO A4 LANDSCAPE) -->
        <Dialog v-model:open="isCertModalOpen">
            <DialogContent
                :show-close-button="false"
                class="flex h-[92vh] max-h-[96vh] w-[96vw] max-w-7xl flex-col overflow-hidden rounded-2xl border border-slate-800 bg-slate-950 p-0 text-slate-100 shadow-2xl sm:max-w-none"
            >
                <DialogTitle class="sr-only">Diploma Oficial de Capacitación - SIGC-MDSJ</DialogTitle>

                <!-- TOP TOOLBAR INSTITUCIONAL / BARRA DE HERRAMIENTAS ESTILO ESTUDIO -->
                <div class="flex shrink-0 flex-wrap items-center justify-between gap-3 border-b border-slate-800/90 bg-slate-900/95 px-4 py-3 backdrop-blur-md select-none sm:px-6">
                    <!-- Info del Certificado -->
                    <div class="flex min-w-0 items-center gap-3">
                        <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-[#1a6b8a] text-white shadow-xs ring-1 ring-[#1a6b8a]/30">
                            <Award class="size-5 text-amber-300" />
                        </div>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-sm font-black tracking-tight text-white">
                                    Diploma Oficial Acreditado
                                </span>
                                <Badge class="border border-emerald-700/80 bg-emerald-950 px-2 py-0.5 text-[10px] font-bold text-emerald-200">
                                    <CheckCircle2 class="mr-1 size-3 text-emerald-400" />
                                    Aprobado • Fe Pública
                                </Badge>
                            </div>
                            <div class="mt-0.5 flex items-center gap-2 text-[11px] text-slate-400">
                                <span class="truncate">MDSJ • Sistema Integral de Gestión de Capacitaciones</span>
                                <span class="text-slate-600">•</span>
                                <span class="font-mono font-bold text-amber-400">{{ selectedCert?.certificate_code }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Selector de Modos de Vista: Anverso / Reverso / Vista Completa -->
                    <div class="inline-flex rounded-xl border border-slate-800 bg-slate-950 p-1 shadow-inner">
                        <button
                            type="button"
                            @click="previewTab = 'anverso'"
                            class="flex cursor-pointer items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-bold transition-all sm:px-4"
                            :class="previewTab === 'anverso' ? 'bg-[#1a6b8a] text-white shadow-xs' : 'text-slate-400 hover:text-white'"
                        >
                            <Award class="size-3.5 text-amber-300" />
                            <span class="hidden sm:inline">Cara 1:</span>
                            <span>Anverso</span>
                        </button>
                        <button
                            type="button"
                            @click="previewTab = 'reverso'"
                            class="flex cursor-pointer items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-bold transition-all sm:px-4"
                            :class="previewTab === 'reverso' ? 'bg-[#1a6b8a] text-white shadow-xs' : 'text-slate-400 hover:text-white'"
                        >
                            <QrCode class="size-3.5 text-amber-300" />
                            <span class="hidden sm:inline">Cara 2:</span>
                            <span>Reverso</span>
                        </button>
                        <button
                            type="button"
                            @click="previewTab = 'completa'"
                            class="flex cursor-pointer items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-bold transition-all sm:px-4"
                            :class="previewTab === 'completa' ? 'bg-[#1a6b8a] text-white shadow-xs' : 'text-slate-400 hover:text-white'"
                        >
                            <Layers class="size-3.5 text-amber-300" />
                            <span>Vista Completa (2 Páginas)</span>
                        </button>
                    </div>

                    <!-- Controles de Zoom y Acciones Rápidas -->
                    <div class="flex items-center gap-2">
                        <!-- Zoom Controls -->
                        <div class="hidden items-center rounded-lg border border-slate-800 bg-slate-950 p-0.5 text-xs text-slate-300 md:inline-flex">
                            <button
                                type="button"
                                @click="zoomOut"
                                class="cursor-pointer rounded p-1.5 text-slate-400 transition-colors hover:bg-slate-800 hover:text-white"
                                title="Reducir zoom"
                            >
                                <ZoomOut class="size-3.5" />
                            </button>
                            <span class="px-2 font-mono text-[11px] font-bold text-amber-400">{{ zoomScale }}%</span>
                            <button
                                type="button"
                                @click="zoomIn"
                                class="cursor-pointer rounded p-1.5 text-slate-400 transition-colors hover:bg-slate-800 hover:text-white"
                                title="Aumentar zoom"
                            >
                                <ZoomIn class="size-3.5" />
                            </button>
                            <button
                                type="button"
                                @click="resetZoom"
                                class="ml-0.5 cursor-pointer rounded border-l border-slate-800 p-1.5 text-slate-400 transition-colors hover:bg-slate-800 hover:text-white"
                                title="Ajuste original 100%"
                            >
                                <RotateCcw class="size-3.5" />
                            </button>
                        </div>

                        <!-- Botón Principal: Imprimir / Descargar PDF Oficial -->
                        <Button
                            size="sm"
                            class="cursor-pointer rounded-xl bg-[#059669] px-3.5 py-2 text-xs font-black text-white shadow-md hover:bg-[#047857] transition-all active:scale-95 sm:px-4"
                            @click="selectedCert && printCertificate(selectedCert)"
                        >
                            <Printer class="mr-1.5 size-3.5 text-amber-200" />
                            <span>Descargar / Imprimir PDF</span>
                        </Button>

                        <!-- Botón Cerrar (X) con Icono Limpio -->
                        <button
                            type="button"
                            @click="isCertModalOpen = false"
                            class="flex size-9 cursor-pointer items-center justify-center rounded-xl border border-slate-700 bg-slate-800 text-slate-300 transition-all hover:border-slate-600 hover:bg-slate-700 hover:text-white"
                            title="Cerrar visor de diploma"
                        >
                            <X class="size-4" />
                        </button>
                    </div>
                </div>

                <!-- LIENZO / VISOR DEL DOCUMENTO A4 LANDSCAPE -->
                <div
                    v-if="selectedCert"
                    class="relative flex min-h-0 flex-1 flex-col items-center justify-start space-y-8 overflow-y-auto bg-zinc-950 p-4 select-text sm:p-8"
                    style="overscroll-behavior-y: contain"
                >
                    <!-- Banner Flotante de Verificación QR si corresponde -->
                    <div
                        v-if="isVerifiedByQr"
                        class="flex w-full max-w-5xl shrink-0 items-center justify-between rounded-xl border border-emerald-700 bg-emerald-950/80 p-3 text-xs font-bold text-emerald-200 shadow-lg"
                    >
                        <div class="flex items-center gap-2">
                            <ShieldCheck class="size-4.5 shrink-0 text-emerald-400" />
                            <span>Verificación Criptográfica Exitosa: El certificado digital coincide con los registros oficiales inmutables de la MDSJ.</span>
                        </div>
                        <Badge variant="outline" class="border-emerald-600 bg-emerald-900/40 font-mono text-emerald-300">
                            VALIDADO
                        </Badge>
                    </div>

                    <!-- CONTENEDOR CON ESCALADO DE ZOOM -->
                    <div
                        class="flex w-full origin-top flex-col items-center space-y-8 transition-transform duration-150"
                        :style="{
                            transform: `scale(${zoomScale / 100})`,
                            transformOrigin: 'top center',
                        }"
                    >
                        <!-- CARA 1: ANVERSO (DIPLOMA DE HONOR INSTITUCIONAL) -->
                        <div
                            v-if="previewTab === 'anverso' || previewTab === 'completa'"
                            class="flex w-full max-w-5xl flex-col items-center"
                        >
                            <div
                                v-if="previewTab === 'completa'"
                                class="mb-2 flex w-full items-center justify-between px-2 font-mono text-xs font-bold text-slate-400"
                            >
                                <span class="flex items-center gap-1.5 text-amber-400">
                                    <Award class="size-3.5" />
                                    PÁGINA 1 DE 2: DIPLOMA DE HONOR INSTITUCIONAL
                                </span>
                                <span class="font-sans text-slate-500">FORMATO OFICIAL A4 LANDSCAPE (297mm × 210mm)</span>
                            </div>

                            <!-- LIENZO DE PAPEL A4 LANDSCAPE (ANVERSO) - DOBLE MARCO INSTITUCIONAL -->
                            <div class="relative flex aspect-[297/210] min-h-[580px] w-full flex-col justify-between overflow-hidden rounded-xl border-[3px] border-[#1a6b8a] bg-[#fefefc] p-6 pb-8 text-slate-950 shadow-[0_25px_60px_-15px_rgba(0,0,0,0.7)] select-text sm:min-h-[660px] sm:p-9 sm:pb-10 md:min-h-[720px] md:p-11 md:pb-11">
                                <!-- Filete Interior Dorado -->
                                <div class="pointer-events-none absolute inset-2 z-10 rounded-lg border border-[#b45309] sm:inset-3"></div>

                                <!-- Marca de Agua Institucional de Fondo -->
                                <div class="pointer-events-none absolute inset-0 z-0 flex items-center justify-center opacity-[0.04] select-none">
                                    <img
                                        src="/images/mdsj/escudo_sm.webp"
                                        alt="Marca de Agua MDSJ"
                                        class="size-80 object-contain sm:size-96"
                                    />
                                </div>

                                <!-- Contenido del Diploma Anverso -->
                                <div class="relative z-20 flex h-full flex-col justify-between text-center">
                                    <!-- Cabecera Institucional Oficial con Logos de Alta Resolución -->
                                    <div class="mb-2 flex items-center justify-between border-b-2 border-[#1a6b8a] pb-3">
                                        <div class="flex w-20 shrink-0 justify-center sm:w-28">
                                            <img
                                                src="/images/mdsj/escudo_sm.webp"
                                                alt="MDSJ Escudo"
                                                class="h-16 object-contain drop-shadow-xs filter sm:h-20 md:h-24"
                                            />
                                        </div>
                                        <div class="min-w-0 flex-1 px-3 text-center">
                                            <div class="text-[10px] font-black tracking-[0.25em] text-[#1a6b8a] uppercase sm:text-xs">
                                                REPÚBLICA DEL PERÚ • REGIÓN CUSCO
                                            </div>
                                            <h1 class="mt-1 font-serif text-base leading-tight font-black tracking-tight text-slate-950 uppercase sm:text-2xl md:text-[26px]">
                                                Municipalidad Distrital de San Jerónimo
                                            </h1>
                                            <div class="mt-0.5 text-[9px] font-bold tracking-wider text-slate-700 uppercase sm:text-xs">
                                                Gerencia de Desarrollo Social y Humano
                                            </div>
                                            <div class="mt-1.5 inline-block rounded bg-[#1a6b8a] px-3.5 py-0.5 text-[8px] font-black tracking-widest text-amber-200 uppercase shadow-xs sm:text-[10px]">
                                                Sistema Integral de Gestión de Capacitaciones (SIGC-MDSJ)
                                            </div>
                                        </div>
                                        <div class="flex w-20 shrink-0 justify-center sm:w-28">
                                            <img
                                                src="/images/mdsj/logo_mdsj_sm.webp"
                                                alt="Logo Municipalidad San Jerónimo"
                                                class="h-16 object-contain drop-shadow-xs filter sm:h-20 md:h-24"
                                            />
                                        </div>
                                    </div>

                                    <!-- Divisor Decorativo Dorado con Diamante Central -->
                                    <div class="my-1 flex w-full items-center justify-center">
                                        <div class="h-0.5 w-full bg-gradient-to-r from-transparent via-[#b45309] to-transparent"></div>
                                        <div class="shrink-0 px-2">
                                            <span class="inline-block size-2 rotate-45 bg-[#b45309]"></span>
                                        </div>
                                        <div class="h-0.5 w-full bg-gradient-to-r from-transparent via-[#b45309] to-transparent"></div>
                                    </div>

                                    <!-- Título Principal del Certificado -->
                                    <div class="my-1 space-y-1">
                                        <h2 class="font-serif text-3xl leading-none font-black tracking-[0.25em] text-[#1a6b8a] uppercase sm:text-5xl md:text-[54px]">
                                            CERTIFICADO
                                        </h2>
                                        <p class="font-serif text-[11px] tracking-wide text-slate-600 italic sm:text-xs">
                                            Por culminación académica satisfactoria y acreditación de competencias profesionales
                                        </p>
                                    </div>

                                    <!-- Otorgado a: Nombre del Participante -->
                                    <div class="my-2 sm:my-3">
                                        <p class="font-serif text-[11px] font-bold tracking-widest text-slate-600 uppercase sm:text-xs">
                                            Conferido en testimonio de honor y mérito académico a:
                                        </p>
                                        <div class="mt-0.5 inline-block border-b-2 border-[#1a6b8a] px-8 py-1 font-serif text-xl font-black tracking-tight text-slate-950 uppercase sm:text-3xl md:text-[34px]">
                                            {{ selectedCert.student_name }}
                                        </div>
                                        <div class="mt-1.5">
                                            <span class="inline-block rounded-full border border-sky-200 bg-sky-50 px-4 py-0.5 font-mono text-xs font-black text-[#1a6b8a] shadow-2xs sm:text-sm">
                                                D.N.I. N° {{ selectedCert.dni }}
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Descripción y Datos del Curso -->
                                    <div class="mx-auto my-1 max-w-3xl font-serif text-xs leading-relaxed text-slate-700 sm:text-sm">
                                        Por haber aprobado satisfactoriamente con alto rendimiento académico el programa de capacitación en:
                                        <strong class="my-1.5 block font-serif text-base leading-snug font-black tracking-tight text-[#1a6b8a] sm:text-xl md:text-2xl">
                                            "{{ selectedCert.course_title }}"
                                        </strong>
                                        desarrollado
                                        <strong>{{
                                            selectedCert.date_range_formal ||
                                            formatSpanishDateRange(selectedCert.start_date, selectedCert.end_date)
                                        }}</strong>, con una carga lectiva de
                                        <strong>{{ formatHours(selectedCert.hours) }}</strong>, en cumplimiento de los estándares de acreditación institucional.
                                    </div>

                                    <!-- Calificación Oficial Obtenida -->
                                    <div class="mx-auto my-1.5 inline-flex items-center gap-2 rounded-full border border-emerald-400 bg-gradient-to-r from-amber-50 to-emerald-50 px-4 py-1 text-xs font-black text-slate-900 shadow-2xs sm:text-sm">
                                        <span class="font-serif text-slate-600">Calificación Obtenida:</span>
                                        <span class="text-sm font-black text-emerald-700 sm:text-base">{{ selectedCert.final_grade }} / 20.00</span>
                                        <span class="font-serif font-semibold text-slate-600">({{ selectedCert.final_grade_text || 'Sobresaliente' }})</span>
                                    </div>

                                    <!-- Lugar y Fecha de Emisión Formal -->
                                    <div class="my-1 pr-6 text-right font-serif text-[11px] font-bold text-slate-600 italic sm:pr-10 sm:text-xs">
                                        {{
                                            selectedCert.city_issued_formal ||
                                            'Cusco, ' + formatSpanishDate(selectedCert.certificate_issued_at || selectedCert.end_date)
                                        }}
                                    </div>

                                    <!-- Firmas Digitales con Sellos Redondos Oficiales -->
                                    <div class="mt-2 grid grid-cols-2 gap-8 border-t border-slate-300 pt-3 text-xs">
                                        <!-- Firma Docente Responsable -->
                                        <div class="relative flex flex-col items-center text-center">
                                            <div class="-mb-2 flex size-20 items-center justify-center" v-html="DOCENTE_STAMP_SVG"></div>
                                            <div class="w-48 border-t border-slate-800 pt-1 sm:w-60">
                                                <div class="font-serif text-xs font-black text-slate-900 uppercase sm:text-sm">
                                                    {{ selectedCert.instructor_name }}
                                                </div>
                                                <div class="font-serif text-[10px] text-slate-500 uppercase">
                                                    {{ selectedCert.instructor_title || 'Docente Responsable / Ponente' }}
                                                </div>
                                            </div>
                                        </div>
                                        <!-- Firma Dirección Académica -->
                                        <div class="relative flex flex-col items-center text-center">
                                            <div class="-mb-2 flex size-20 items-center justify-center" v-html="DIRECCION_STAMP_SVG"></div>
                                            <div class="w-48 border-t border-slate-800 pt-1 sm:w-60">
                                                <div class="font-serif text-xs font-black text-slate-900 uppercase sm:text-sm">
                                                    Dirección Académica
                                                </div>
                                                <div class="font-serif text-[10px] text-slate-500 uppercase">
                                                    Coordinación General SIGC • MDSJ
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Pie de Página con Código de Verificación Oficial -->
                                    <div class="mt-2 flex items-center justify-between border-t border-dashed border-slate-300 pt-2 font-mono text-[10px] text-slate-500">
                                        <span>Cód. Verificación: <strong class="text-slate-900">{{ selectedCert.certificate_code }}</strong></span>
                                        <span>Fecha de Emisión: <strong>{{ selectedCert.issued_date_formal || formatSpanishDate(selectedCert.certificate_issued_at || selectedCert.end_date) }}</strong></span>
                                        <span class="font-black text-[#1a6b8a]">REGISTRO OFICIAL DE FE PÚBLICA • CUSCO, PERÚ</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- CARA 2: REVERSO (PLAN CURRICULAR, EVALUACIÓN Y QR OFICIAL) -->
                        <div
                            v-if="previewTab === 'reverso' || previewTab === 'completa'"
                            class="flex w-full max-w-5xl flex-col items-center"
                        >
                            <div
                                v-if="previewTab === 'completa'"
                                class="mb-2 flex w-full items-center justify-between px-2 font-mono text-xs font-bold text-slate-400"
                            >
                                <span class="flex items-center gap-1.5 text-amber-400">
                                    <QrCode class="size-3.5" />
                                    PÁGINA 2 DE 2: PLAN CURRICULAR, CALIFICACIÓN Y VERIFICACIÓN QR
                                </span>
                                <span class="font-sans text-slate-500">FORMATO OFICIAL A4 LANDSCAPE (297mm × 210mm)</span>
                            </div>

                            <!-- LIENZO DE PAPEL A4 LANDSCAPE (REVERSO) - DOBLE MARCO INSTITUCIONAL -->
                            <div class="relative flex aspect-[297/210] min-h-[580px] w-full flex-col justify-between overflow-hidden rounded-xl border-[3px] border-[#1a6b8a] bg-[#fefefc] p-6 pb-8 text-slate-950 shadow-[0_25px_60px_-15px_rgba(0,0,0,0.7)] select-text sm:min-h-[660px] sm:p-9 sm:pb-10 md:min-h-[720px] md:p-11 md:pb-11">
                                <!-- Filete Interior Dorado -->
                                <div class="pointer-events-none absolute inset-2 z-10 rounded-lg border border-[#b45309] sm:inset-3"></div>

                                <!-- Contenido del Diploma Reverso -->
                                <div class="relative z-20 flex h-full flex-col justify-between text-left">
                                    <!-- Cabecera del Reverso -->
                                    <div class="mb-3 flex items-center justify-between border-b-2 border-[#1a6b8a] pb-2.5">
                                        <div>
                                            <div class="text-[10px] font-black tracking-wider text-[#1a6b8a] uppercase">
                                                MUNICIPALIDAD DISTRITAL DE SAN JERÓNIMO • SIGC
                                            </div>
                                            <h3 class="mt-0.5 font-serif text-base font-black text-slate-950 uppercase sm:text-xl">
                                                Plan Curricular y Registro Oficial de Calificación
                                            </h3>
                                            <p class="text-xs text-slate-600">
                                                Programa: <strong class="text-slate-900">{{ selectedCert.course_title }}</strong>
                                                (Código: {{ selectedCert.course_code }})
                                            </p>
                                        </div>
                                        <div class="hidden text-right font-mono text-[11px] text-slate-500 sm:block">
                                            <div>Cód: <strong>{{ selectedCert.certificate_code }}</strong></div>
                                            <div>Horas: <strong>{{ selectedCert.hours }} hrs. lectivas</strong></div>
                                        </div>
                                    </div>

                                    <!-- Estructura en 2 Columnas -->
                                    <div class="grid min-h-0 flex-1 grid-cols-1 gap-5 md:grid-cols-12">
                                        <!-- Columna Izquierda: Temario y Módulos Curriculares (7 cols) -->
                                        <div class="flex flex-col justify-between space-y-2 md:col-span-7">
                                            <div class="flex items-center justify-between border-b border-slate-200 pb-1 text-xs font-black tracking-wider text-[#1a6b8a] uppercase">
                                                <span class="flex items-center gap-1.5">
                                                    <BookOpen class="size-3.5" />
                                                    Módulos Académicos Acreditados
                                                </span>
                                                <span class="font-mono text-[10px] text-slate-500">{{ selectedCert.hours }} Horas Lectivas</span>
                                            </div>

                                            <div class="max-h-[300px] space-y-2 overflow-y-auto pr-1">
                                                <div
                                                    v-for="(module, index) in selectedCert.modules"
                                                    :key="index"
                                                    class="rounded-lg border border-l-4 border-slate-200 border-l-[#1a6b8a] bg-slate-50 p-2.5 dark:border-slate-800 dark:bg-slate-900/60"
                                                >
                                                    <div class="flex items-center justify-between text-xs font-bold text-slate-950 dark:text-slate-100">
                                                        <span class="mr-2 text-[11px] font-black text-[#1a6b8a]">{{ module.number }}</span>
                                                        <span class="flex-1 truncate font-serif">{{ module.title }}</span>
                                                        <span class="rounded border border-slate-200 bg-white px-2 py-0.5 font-mono text-[10px] text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                                            {{ module.hours }}
                                                        </span>
                                                    </div>
                                                    <p class="mt-1 text-[11px] leading-relaxed text-slate-600 dark:text-slate-400">
                                                        {{ module.topics }}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Columna Derecha: Calificación, Docente, QR y Criptografía (5 cols) -->
                                        <div class="flex flex-col justify-between space-y-3 md:col-span-5">
                                            <!-- Cuadro de Calificación Oficial -->
                                            <div class="flex items-center justify-between rounded-xl border border-emerald-300 bg-emerald-50 p-3.5 dark:border-emerald-800/60 dark:bg-emerald-950/30">
                                                <div>
                                                    <div class="text-[10px] font-bold text-emerald-800 uppercase dark:text-emerald-300">
                                                        Registro de Calificación
                                                    </div>
                                                    <div class="text-2xl font-black text-emerald-950 dark:text-emerald-100">
                                                        {{ selectedCert.final_grade }} / 20.00
                                                    </div>
                                                    <div class="text-[10px] font-bold text-emerald-700 dark:text-emerald-400">
                                                        {{ selectedCert.final_grade_text || 'Sobresaliente' }}
                                                    </div>
                                                </div>
                                                <Badge class="bg-emerald-700 px-2.5 py-1 text-xs font-black text-white">
                                                    ✓ APROBADO
                                                </Badge>
                                            </div>

                                            <!-- Docente Responsable -->
                                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-900/60">
                                                <div class="text-[10px] font-bold text-slate-500 uppercase dark:text-slate-400">
                                                    Docente / Ponente Responsable:
                                                </div>
                                                <div class="mt-0.5 text-xs font-black text-slate-950 uppercase dark:text-white">
                                                    {{ selectedCert.instructor_name }}
                                                </div>
                                                <div class="text-[10px] text-slate-600 dark:text-slate-300">
                                                    {{ selectedCert.instructor_title || 'Docente Responsable' }}
                                                </div>
                                                <div class="mt-0.5 text-[9px] text-slate-500 dark:text-slate-400">
                                                    {{ selectedCert.institution || 'Municipalidad Distrital de San Jerónimo' }}
                                                </div>
                                            </div>

                                            <!-- Código QR Permanente de Validación -->
                                            <div class="flex items-center gap-3 rounded-xl border-2 border-[#1a6b8a] bg-white p-3 dark:border-[#1a6b8a] dark:bg-slate-900/80">
                                                <div class="flex size-20 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-white p-1 dark:border-slate-700">
                                                    <div v-html="selectedCert.qr_svg" class="size-full [&>svg]:size-full"></div>
                                                </div>
                                                <div class="min-w-0 flex-1 space-y-0.5">
                                                    <div class="flex items-center gap-1 text-xs font-black text-[#1a6b8a] uppercase">
                                                        <QrCode class="size-3.5 shrink-0" />
                                                        <span>Validación QR en Línea</span>
                                                    </div>
                                                    <p class="text-[10px] leading-tight text-slate-600 dark:text-slate-400">
                                                        Escanee el código QR para verificar la autenticidad e integridad del certificado en tiempo real.
                                                    </p>
                                                    <a
                                                        :href="selectedCert.verification_url"
                                                        target="_blank"
                                                        class="inline-flex max-w-full items-center gap-1 truncate font-mono text-[9px] font-bold text-blue-700 hover:underline dark:text-blue-400"
                                                    >
                                                        <ExternalLink class="size-2.5 shrink-0" />
                                                        <span class="truncate">{{ selectedCert.verification_url }}</span>
                                                    </a>
                                                </div>
                                            </div>

                                            <!-- Huella Criptográfica SHA-256 -->
                                            <div
                                                v-if="selectedCert.certificate_hash"
                                                class="rounded-lg border border-slate-200 bg-slate-50 p-2 font-mono text-[9px] dark:border-slate-800 dark:bg-slate-900/60"
                                            >
                                                <div class="mb-0.5 flex items-center justify-between font-bold text-slate-600 dark:text-slate-400">
                                                    <span class="flex items-center gap-1 text-[#1a6b8a]">
                                                        <ShieldCheck class="size-3" />
                                                        Huella SHA-256:
                                                    </span>
                                                    <span class="text-[8px] text-slate-400 dark:text-slate-500">Inmutable</span>
                                                </div>
                                                <div class="leading-tight font-semibold break-all text-slate-800 select-all dark:text-slate-200">
                                                    {{ selectedCert.certificate_hash }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Pie del Reverso -->
                                    <div class="mt-2 flex items-center justify-between border-t border-dashed border-slate-300 pt-2 font-mono text-[10px] text-slate-500">
                                        <span>Certificado: <strong>{{ selectedCert.certificate_code }}</strong></span>
                                        <span>Registro en Actas: <strong>{{ selectedCert.issued_date_formal || formatSpanishDate(selectedCert.certificate_issued_at || selectedCert.end_date) }}</strong></span>
                                        <span class="font-black text-[#1a6b8a]">CUSCO, REPÚBLICA DEL PERÚ • CERTIFICACIÓN OFICIAL</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- FOOTER FIJO CON ACCIONES ESTILO ESTUDIO -->
                <div class="flex shrink-0 flex-col items-center justify-between gap-3 border-t border-slate-800 bg-slate-900/95 px-4 py-3 select-none sm:flex-row sm:px-6">
                    <div class="flex w-full flex-wrap items-center gap-2 sm:w-auto">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="flex-1 cursor-pointer justify-center rounded-xl border-slate-700 bg-slate-800/80 text-xs font-bold text-slate-200 hover:bg-slate-700 sm:flex-initial"
                            @click="selectedCert && copyVerificationCode(selectedCert.certificate_code)"
                        >
                            <Check v-if="copiedCodeMap[selectedCert?.certificate_code || '']" class="mr-1 size-3.5 text-emerald-400" />
                            <Copy v-else class="mr-1 size-3.5 text-slate-400" />
                            <span>{{ copiedCodeMap[selectedCert?.certificate_code || ''] ? '¡Código Copiado!' : 'Copiar Código' }}</span>
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="flex-1 cursor-pointer justify-center rounded-xl border-slate-700 bg-slate-800/80 text-xs font-bold text-slate-200 hover:bg-slate-700 sm:flex-initial"
                            @click="selectedCert && copyVerificationUrl(selectedCert.verification_url)"
                        >
                            <Check v-if="copiedUrl" class="mr-1 size-3.5 text-emerald-400" />
                            <Share2 v-else class="mr-1 size-3.5 text-slate-400" />
                            <span>{{ copiedUrl ? '¡Enlace Copiado!' : 'Copiar QR' }}</span>
                        </Button>
                    </div>

                    <div class="flex w-full flex-wrap items-center justify-end gap-2.5 sm:w-auto">
                        <Button
                            variant="ghost"
                            size="sm"
                            class="cursor-pointer rounded-xl px-3 text-xs text-slate-400 hover:bg-slate-800 hover:text-white"
                            @click="isCertModalOpen = false"
                        >
                            Cerrar
                        </Button>
                        <Button
                            size="sm"
                            class="w-full cursor-pointer justify-center rounded-xl bg-[#059669] px-4 py-2 text-xs font-black text-white shadow-md hover:bg-[#047857] transition-all sm:w-auto"
                            @click="selectedCert && printCertificate(selectedCert)"
                        >
                            <Printer class="mr-1.5 size-3.5 text-emerald-200" />
                            <span>Imprimir / PDF (2 Páginas)</span>
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>