<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
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
    GraduationCap,
    Printer,
    FileCheck,
    Copy,
    Check,
    ShieldCheck,
    User,
    ArrowLeft,
    LogIn,
    UserPlus,
    Eye,
    ChevronRight,
    Sparkles,
    FileText,
    QrCode,
    ExternalLink,
    BookOpen,
    Share2,
    ZoomIn,
    ZoomOut,
    RotateCcw,
    Layers,
    Info,
    Lock,
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
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import PdfIntegrityVerifier from '@/components/PdfIntegrityVerifier.vue';
import { notify } from '@/lib/notify';
import { formatDateRange, formatHours } from '@/lib/formatters';
import type { BreadcrumbItem } from '@/types';
import {
    type CertificateRecord,
    printCertificate,
    GOLD_MEDAL_SVG,
    EMBLEM_SVG,
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

// Pestaña activa: 'dni' para consulta por documento | 'pdf' para validación de archivo PDF
const activeTab = ref<'dni' | 'pdf'>('dni');

const dniQuery = ref(props.initialDni || '');
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
const copiedCode = ref(false);
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

// Flujo institucional en 2 pasos para consulta pública por DNI.
const searchStep = ref<1 | 2>(1); // 1 = DNI | 2 = Diplomas mostrados
const validatingDni = ref(false);

function allowOnlyNumbers(e: KeyboardEvent) {
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

function onDniInput(e: Event) {
    const target = e.target as HTMLInputElement;
    const sanitized = target.value.replace(/\D/g, '').slice(0, 8);
    if (target.value !== sanitized) {
        target.value = sanitized;
    }
    dniQuery.value = sanitized;
    if (error.value) error.value = null;
    if (searchStep.value !== 1) {
        searchStep.value = 1;
        records.value = [];
        searched.value = false;
    }
}

async function validateDniStep(): Promise<void> {
    const clean = dniQuery.value.trim();
    if (!/^\d{8}$/.test(clean)) {
        error.value =
            'Ingrese un número de DNI válido de exactamente 8 dígitos.';
        return;
    }

    validatingDni.value = true;
    error.value = null;

    try {
        // Validar identidad con la API de RENIEC; solo se usan los campos mínimos de identidad.
        const response = await fetch(`/api/dni/${clean}`, {
            headers: {
                Accept: 'application/json',
            },
        });
        const data = await response.json();

        if (response.ok && data.success && data.data) {
            const fullName =
                data.data.nombre_completo ||
                `${data.data.nombres} ${data.data.apellido_paterno || ''} ${data.data.apellido_materno || ''}`.trim();
            studentName.value = fullName;
            await searchCertificates();
            searchStep.value = 2;
            notify.success('Identidad Encontrada en RENIEC', fullName, 2000);
            return;
        }

        // Fallback con verificación en padrón local institucional.
        const localCheck = await fetch(
            `/api/certificates/lookup?dni=${clean}&check_only=1`,
            {
                headers: {
                    Accept: 'application/json',
                },
            },
        );
        const localData = await localCheck.json();

        if (localCheck.ok && localData.success && localData.has_records) {
            studentName.value =
                localData.student_name || 'Participante Institucional';
            await searchCertificates();
            searchStep.value = 2;
            notify.info(
                'Registro Académico Encontrado',
                studentName.value || clean,
                2000,
            );
        } else {
            error.value =
                data.message ||
                'No se encontró el DNI en el padrón de RENIEC ni en los registros académicos.';
            notify.warning(
                'DNI no encontrado',
                error.value || 'No se encontraron registros.',
                3000,
            );
        }
    } catch {
        error.value =
            'Error al conectarse con el servicio de validación de identidad. Intente nuevamente.';
        notify.error(
            'Error de conexión',
            error.value || 'Intente nuevamente.',
            3000,
        );
    } finally {
        validatingDni.value = false;
    }
}

function resetSearch(): void {
    searchStep.value = 1;
    dniQuery.value = '';
    error.value = null;
    records.value = [];
    searched.value = false;
    studentName.value = null;
}

async function searchCertificates(targetCode?: string) {
    const clean = dniQuery.value.trim();
    const code = targetCode?.trim() || '';

    if (!code && !/^\d{8}$/.test(clean)) {
        error.value =
            'Ingrese un número de DNI válido de exactamente 8 dígitos.';
        return;
    }

    loading.value = true;
    error.value = null;
    records.value = [];
    searched.value = true;

    try {
        const queryParams = new URLSearchParams();
        if (clean) queryParams.set('dni', clean);
        if (code) queryParams.set('code', code);

        const response = await fetch(
            `/api/certificates/lookup?${queryParams.toString()}`,
            {
                headers: {
                    Accept: 'application/json',
                },
            },
        );

        const data = await response.json();

        if (response.ok && data.success) {
            records.value = data.records || [];
            if (data.dni) {
                dniQuery.value = data.dni;
            }
            if (records.value.length > 0) {
                studentName.value = records.value[0].student_name;
                if (code) {
                    const matched = records.value.find(
                        (r) =>
                            r.certificate_code.toLowerCase() ===
                            code.toLowerCase(),
                    );
                    if (matched) {
                        selectedCert.value = matched;
                        isVerifiedByQr.value = true;
                        previewTab.value = 'anverso';
                        isCertModalOpen.value = true;
                        notify.success(
                            'Certificado Verificado con Éxito',
                            'Acreditado oficialmente por la UNSAAC.',
                            3000,
                        );
                    }
                } else {
                    notify.success(
                        'Certificados encontrados',
                        `Se encontró ${records.value.length} diploma(s) oficial(es)`,
                        2000,
                    );
                }
            } else {
                studentName.value = null;
                notify.warning(
                    'Sin registros',
                    'No se encontraron certificados para el DNI consultado.',
                    2500,
                );
            }
        } else {
            error.value =
                data.message ||
                'No se pudo consultar el registro de certificados.';
            notify.error(
                'Consulta fallida',
                error.value || 'No se pudo consultar el registro.',
                3000,
            );
        }
    } catch {
        error.value =
            'Error al conectarse con el servidor de validación. Intente nuevamente.';
        notify.error(
            'Error de conexión',
            error.value || 'Intente nuevamente.',
            3000,
        );
    } finally {
        loading.value = false;
    }
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
        copiedCode.value = true;
        notify.success('Código copiado al portapapeles', code, 1500);
        setTimeout(() => {
            copiedCode.value = false;
        }, 2000);
    } catch {
        // Fallback
    }
}

async function copyVerificationUrl(url: string) {
    try {
        await navigator.clipboard.writeText(url);
        copiedUrl.value = true;
        notify.success(
            'Enlace de validación copiado',
            'Puedes compartir este enlace directo',
            1500,
        );
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

    if (codeParam) {
        searchCertificates(codeParam.trim());
    } else if (dniParam && /^\d{8}$/.test(dniParam.trim())) {
        dniQuery.value = dniParam.trim();
        searchCertificates();
    }
});
</script>

<template>
    <!-- VISTA OFICIAL DE CONSULTA Y VALIDACIÓN DE CERTIFICADOS (UNIFICADA CON APPLAYOUT) -->
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Validación Oficial de Certificados - SIGC-CUSCO" />

        <div
            class="mx-auto w-full max-w-7xl min-w-0 space-y-8 overflow-x-hidden px-4 py-6 sm:px-6 sm:py-8 lg:px-8"
        >
            <!-- HEADER DE SECCIÓN ACADÉMICA -->
            <div
                class="flex flex-col justify-between gap-4 border-b border-slate-200/90 pb-5 sm:flex-row sm:items-center dark:border-slate-800"
            >
                <div class="space-y-1">
                    <div class="flex items-center gap-2">
                        <div
                            class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-tr from-[#701a31] to-[#800020] text-white shadow-sm"
                        >
                            <Award class="size-5 text-amber-300" />
                        </div>
                        <div>
                            <h1
                                class="text-xl font-black tracking-tight text-slate-950 sm:text-2xl dark:text-white"
                            >
                                Consulta y Validación de Certificados
                            </h1>
                            <p
                                class="text-xs font-medium text-slate-600 sm:text-sm dark:text-slate-400"
                            >
                                Acreditación oficial institucional, diplomas con
                                firma digital y verificación criptográfica
                                SHA-256.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <Button
                        as-child
                        variant="outline"
                        size="sm"
                        class="cursor-pointer border-slate-300 text-xs font-bold hover:text-rose-900"
                    >
                        <Link :href="authUser ? '/dashboard' : '/'">
                            <ArrowLeft class="mr-1 size-3.5 text-rose-800" />
                            {{
                                authUser
                                    ? 'Volver al Panel'
                                    : 'Portal Principal'
                            }}
                        </Link>
                    </Button>
                </div>
            </div>

            <!-- CONTENIDO MODULAR DE CERTIFICACIÓN -->
            <div class="space-y-6">
                <!-- SELECTOR DE MODALIDAD (TABS RESPONSIVE) -->
                <div
                    class="mx-auto flex w-full max-w-xl items-center justify-center px-1"
                >
                    <div
                        class="grid w-full grid-cols-1 gap-1 rounded-xl border border-slate-200 bg-slate-100 p-1 sm:grid-cols-2 dark:border-slate-800 dark:bg-slate-900"
                    >
                        <button
                            type="button"
                            @click="activeTab = 'dni'"
                            class="flex cursor-pointer items-center justify-center gap-2 rounded-lg px-3 py-2 text-center text-xs font-black transition-all sm:px-5"
                            :class="
                                activeTab === 'dni'
                                    ? 'bg-rose-900 text-white shadow-xs'
                                    : 'text-slate-700 hover:text-rose-900 dark:text-slate-300'
                            "
                        >
                            <Search class="size-3.5 shrink-0" />
                            <span class="truncate"
                                >Búsqueda Oficial por DNI</span
                            >
                        </button>
                        <button
                            type="button"
                            @click="activeTab = 'pdf'"
                            class="flex cursor-pointer items-center justify-center gap-2 rounded-lg px-3 py-2 text-center text-xs font-black transition-all sm:px-5"
                            :class="
                                activeTab === 'pdf'
                                    ? 'bg-rose-900 text-white shadow-xs'
                                    : 'text-slate-700 hover:text-rose-900 dark:text-slate-300'
                            "
                        >
                            <FileCheck
                                class="size-3.5 shrink-0 text-amber-500"
                            />
                            <span class="truncate"
                                >Comprobador PDF (SHA-256)</span
                            >
                        </button>
                    </div>
                </div>

                <!-- VISTA 1: CONSULTA INSTITUCIONAL POR DNI -->
                <div v-if="activeTab === 'dni'" class="space-y-6">
                    <!-- STEPPER INDICADOR INSTITUCIONAL -->
                    <div class="mx-auto w-full max-w-2xl px-2">
                        <div
                            class="grid grid-cols-2 gap-1.5 text-center text-[11px] sm:gap-2 sm:text-xs"
                        >
                            <!-- Paso 1 -->
                            <div
                                class="flex min-w-0 items-center justify-center gap-1 rounded-xl border px-1.5 py-2 font-bold transition-all sm:gap-1.5 sm:px-3"
                                :class="
                                    searchStep === 1
                                        ? 'border-rose-900 bg-rose-900 text-white shadow-xs'
                                        : searchStep > 1
                                          ? 'border-emerald-300 bg-emerald-50 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300'
                                          : 'border-slate-200 bg-slate-100 text-slate-500 dark:border-slate-700 dark:bg-slate-800'
                                "
                            >
                                <CheckCircle2
                                    v-if="searchStep > 1"
                                    class="size-3.5 shrink-0 text-emerald-600"
                                />
                                <span
                                    v-else
                                    class="flex size-4 shrink-0 items-center justify-center rounded-full bg-white/20 text-[10px]"
                                    >1</span
                                >
                                <span class="hidden md:inline">Paso 1:</span>
                                <span class="truncate">DNI</span>
                            </div>

                            <!-- Paso 2 -->
                            <div
                                class="flex min-w-0 items-center justify-center gap-1 rounded-xl border px-1.5 py-2 font-bold transition-all sm:gap-1.5 sm:px-3"
                                :class="
                                    searchStep === 2
                                        ? 'border-rose-900 bg-rose-900 text-white shadow-xs'
                                        : 'border-slate-200 bg-slate-100 text-slate-500 dark:border-slate-700 dark:bg-slate-800'
                                "
                            >
                                <span
                                    class="flex size-4 shrink-0 items-center justify-center rounded-full bg-white/20 text-[10px]"
                                    >2</span
                                >
                                <span class="truncate">Diplomas</span>
                            </div>
                        </div>
                    </div>

                    <!-- PASO 1: INGRESO DE DNI -->
                    <Card
                        v-if="searchStep === 1"
                        class="mx-auto max-w-2xl overflow-hidden border border-rose-900/20 bg-white shadow-sm dark:border-rose-900/40 dark:bg-slate-900"
                    >
                        <CardHeader
                            class="border-b bg-gradient-to-r from-rose-50/80 via-white to-amber-50/20 pb-3.5 dark:from-rose-950/30 dark:to-slate-900"
                        >
                            <div
                                class="flex items-center gap-2 text-xs font-black tracking-wider text-rose-900 uppercase dark:text-rose-300"
                            >
                                <Search class="size-4 text-rose-800" />
                                <span>Paso 1: Validación de Identidad</span>
                            </div>
                            <CardTitle
                                class="text-base font-bold text-slate-950 sm:text-lg dark:text-white"
                            >
                                Ingrese el Número de DNI
                            </CardTitle>
                            <CardDescription
                                class="text-xs text-slate-600 dark:text-slate-400"
                            >
                                Se validará su identidad en el padrón oficial de
                                RENIEC antes de mostrar los certificados
                                emitidos.
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4 p-5 sm:p-6">
                            <form
                                @submit.prevent="validateDniStep"
                                class="flex flex-col gap-3 sm:flex-row"
                            >
                                <div class="relative flex-1">
                                    <Search
                                        class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-slate-400"
                                    />
                                    <input
                                        id="dni-input"
                                        v-model="dniQuery"
                                        @input="onDniInput"
                                        @keypress="allowOnlyNumbers"
                                        type="text"
                                        inputmode="numeric"
                                        pattern="[0-9]*"
                                        maxlength="8"
                                        placeholder="Ingresa DNI (8 dígitos)"
                                        class="h-11 w-full rounded-xl border border-slate-300 bg-white pr-14 pl-10 font-mono text-base font-bold tracking-widest text-slate-950 shadow-2xs transition-all placeholder:font-normal placeholder:tracking-normal placeholder:text-slate-400 focus:border-rose-900 focus:ring-2 focus:ring-rose-900/30 focus:outline-hidden disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                                        :disabled="validatingDni"
                                        autofocus
                                    />
                                    <div
                                        class="pointer-events-none absolute top-1/2 right-3.5 -translate-y-1/2 font-mono text-[11px] font-bold text-slate-400"
                                    >
                                        {{ dniQuery.length }}/8
                                    </div>
                                </div>
                                <Button
                                    type="submit"
                                    :disabled="
                                        validatingDni || dniQuery.length !== 8
                                    "
                                    class="h-11 shrink-0 cursor-pointer bg-rose-900 px-6 text-xs font-bold text-white shadow-xs hover:bg-rose-950 sm:text-sm"
                                >
                                    <Loader2
                                        v-if="validatingDni"
                                        class="mr-2 size-4 animate-spin"
                                    />
                                    <ShieldCheck
                                        v-else
                                        class="mr-2 size-4 text-amber-300"
                                    />
                                    <span>Validar DNI en RENIEC</span>
                                </Button>
                            </form>

                            <div
                                v-if="error"
                                class="flex items-center gap-2 rounded-xl border border-rose-200 bg-rose-50 p-3 text-xs font-bold text-rose-900 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-200"
                            >
                                <AlertCircle
                                    class="size-4 shrink-0 text-rose-700"
                                />
                                <span>{{ error }}</span>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Resultados de Búsqueda -->
                    <div
                        v-if="loading"
                        class="mx-auto max-w-3xl space-y-3 py-10 text-center"
                    >
                        <Loader2
                            class="mx-auto size-8 animate-spin text-rose-900"
                        />
                        <p
                            class="text-xs font-bold text-slate-700 sm:text-sm dark:text-slate-300"
                        >
                            Consultando certificados oficiales emitidos para el
                            DNI {{ dniQuery }}...
                        </p>
                    </div>

                    <div
                        v-else-if="searched"
                        class="mx-auto max-w-3xl space-y-5"
                    >
                        <template v-if="records.length > 0">
                            <!-- Titular Acreditado -->
                            <div
                                class="flex flex-col items-start justify-between gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-xs sm:flex-row sm:items-center sm:p-5 dark:border-slate-800 dark:bg-slate-900"
                            >
                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-rose-900 font-black text-white shadow-xs"
                                    >
                                        <User class="size-5 text-amber-300" />
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <h2
                                                class="text-base font-black tracking-tight text-slate-950 uppercase sm:text-lg dark:text-white"
                                            >
                                                {{ studentName }}
                                            </h2>
                                            <Badge
                                                class="border-emerald-300 bg-emerald-100 text-[10px] font-black text-emerald-900 dark:bg-emerald-950 dark:text-emerald-200"
                                            >
                                                <CheckCircle2
                                                    class="mr-1 size-3 text-emerald-700"
                                                />
                                                Titular Oficial
                                            </Badge>
                                        </div>
                                        <div
                                            class="mt-0.5 font-mono text-xs text-slate-600 dark:text-slate-400"
                                        >
                                            DNI:
                                            <strong
                                                class="text-slate-900 dark:text-white"
                                                >{{ dniQuery }}</strong
                                            >
                                        </div>
                                    </div>
                                </div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <Badge
                                        variant="outline"
                                        class="border-rose-300 bg-rose-50 px-3 py-1 text-xs font-bold text-rose-900 dark:border-rose-800 dark:bg-rose-950 dark:text-rose-300"
                                    >
                                        <Award
                                            class="mr-1 size-3.5 text-rose-800"
                                        />
                                        {{ records.length }}
                                        {{
                                            records.length === 1
                                                ? 'Certificado Oficial'
                                                : 'Certificados Oficiales'
                                        }}
                                    </Badge>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        class="cursor-pointer border-slate-300 text-xs font-bold hover:text-rose-900"
                                        @click="resetSearch"
                                    >
                                        <RotateCcw
                                            class="mr-1 size-3.5 text-rose-800"
                                        />
                                        <span>Consultar otro DNI</span>
                                    </Button>
                                </div>
                            </div>

                            <!-- Lista de Certificados Oficiales -->
                            <div class="space-y-4">
                                <Card
                                    v-for="record in records"
                                    :key="record.id"
                                    class="overflow-hidden border border-slate-200 bg-white shadow-xs transition-all hover:border-rose-300 dark:border-slate-800 dark:bg-slate-900"
                                >
                                    <CardHeader
                                        class="flex flex-col justify-between gap-2 border-b bg-slate-50/70 p-4 pb-3 sm:flex-row sm:items-center sm:p-5 dark:bg-slate-900/80"
                                    >
                                        <div class="flex items-center gap-2">
                                            <span
                                                class="rounded bg-rose-900 px-2 py-0.5 font-mono text-xs font-black text-white"
                                            >
                                                {{ record.course_code }}
                                            </span>
                                            <span
                                                class="flex items-center gap-1 text-xs font-bold text-slate-700 dark:text-slate-300"
                                            >
                                                <Building2
                                                    class="size-3 text-slate-400"
                                                />
                                                {{
                                                    record.institution ||
                                                    'SIGC-CUSCO'
                                                }}
                                            </span>
                                        </div>
                                        <Badge
                                            class="border-rose-300 bg-rose-100 text-[11px] font-black text-rose-950 dark:bg-rose-950 dark:text-rose-200"
                                        >
                                            <CheckCircle2
                                                class="mr-1 size-3 text-rose-800"
                                            />
                                            Acreditado Oficialmente
                                        </Badge>
                                    </CardHeader>
                                    <CardContent class="space-y-3.5 p-4 sm:p-5">
                                        <h3
                                            class="text-base leading-snug font-bold text-slate-950 sm:text-lg dark:text-white"
                                        >
                                            {{ record.course_title }}
                                        </h3>

                                        <!-- Calificación Oficial Acreditada -->
                                        <div class="flex items-center gap-2">
                                            <Badge
                                                class="border-emerald-300 bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-950 dark:bg-emerald-950 dark:text-emerald-200"
                                            >
                                                <CheckCircle2
                                                    class="mr-1.5 size-3.5 text-emerald-700"
                                                />
                                                Calificación:
                                                {{ record.final_grade }} / 20.00
                                                ({{
                                                    record.final_grade_text ||
                                                    'Aprobado'
                                                }})
                                            </Badge>
                                        </div>

                                        <!-- Datos Públicos Relevantes -->
                                        <div
                                            class="grid grid-cols-1 gap-2.5 text-xs sm:grid-cols-3"
                                        >
                                            <div
                                                class="rounded-lg border border-slate-200 bg-slate-50 p-2.5 dark:border-slate-800 dark:bg-slate-800/50"
                                            >
                                                <div
                                                    class="mb-0.5 text-[10px] font-bold tracking-wider text-slate-500 uppercase"
                                                >
                                                    Ponente / Docente
                                                </div>
                                                <div
                                                    class="truncate font-bold text-slate-900 dark:text-white"
                                                >
                                                    {{ record.instructor_name }}
                                                </div>
                                            </div>
                                            <div
                                                class="rounded-lg border border-slate-200 bg-slate-50 p-2.5 dark:border-slate-800 dark:bg-slate-800/50"
                                            >
                                                <div
                                                    class="mb-0.5 text-[10px] font-bold tracking-wider text-slate-500 uppercase"
                                                >
                                                    Carga Lectiva
                                                </div>
                                                <div
                                                    class="flex items-center gap-1 font-bold text-slate-900 dark:text-white"
                                                >
                                                    <Clock
                                                        class="size-3 text-amber-600"
                                                    />
                                                    {{
                                                        formatHours(
                                                            record.hours,
                                                        )
                                                    }}
                                                </div>
                                            </div>
                                            <div
                                                class="rounded-lg border border-slate-200 bg-slate-50 p-2.5 dark:border-slate-800 dark:bg-slate-800/50"
                                            >
                                                <div
                                                    class="mb-0.5 text-[10px] font-bold tracking-wider text-slate-500 uppercase"
                                                >
                                                    Período
                                                </div>
                                                <div
                                                    class="flex items-center gap-1 font-bold text-slate-900 dark:text-white"
                                                >
                                                    <Calendar
                                                        class="size-3 text-rose-800"
                                                    />
                                                    {{
                                                        formatDateRange(
                                                            record.start_date,
                                                            record.end_date,
                                                            'medium',
                                                        )
                                                    }}
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Código, Fecha y Acciones -->
                                        <div
                                            class="flex flex-col justify-between gap-3 border-t border-slate-200 pt-3 sm:flex-row sm:items-center dark:border-slate-800"
                                        >
                                            <div
                                                class="flex flex-col gap-0.5 font-mono text-xs text-slate-600 dark:text-slate-400"
                                            >
                                                <div
                                                    class="flex items-center gap-2"
                                                >
                                                    <span
                                                        class="font-bold text-slate-900 dark:text-slate-200"
                                                        >Cód.
                                                        Verificación:</span
                                                    >
                                                    <span
                                                        class="rounded border border-slate-200 bg-slate-100 px-2 py-0.5 font-black text-rose-900 dark:border-slate-700 dark:bg-slate-800 dark:text-rose-400"
                                                    >
                                                        {{
                                                            record.certificate_code
                                                        }}
                                                    </span>
                                                </div>
                                                <div
                                                    class="text-[10px] text-slate-500"
                                                >
                                                    Expedido:
                                                    <strong>{{
                                                        record.certificate_issued_at ||
                                                        'Oficial'
                                                    }}</strong>
                                                </div>
                                            </div>

                                            <div
                                                class="flex w-full flex-col items-stretch gap-2 sm:w-auto sm:flex-row sm:items-center"
                                            >
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    class="h-9 w-full cursor-pointer justify-center border-slate-300 px-3.5 text-xs font-bold hover:bg-rose-50 hover:text-rose-900 sm:w-auto"
                                                    @click="
                                                        openCertificatePreview(
                                                            record,
                                                        )
                                                    "
                                                >
                                                    <Eye
                                                        class="mr-1.5 size-3.5 text-slate-700"
                                                    />
                                                    Ver Diploma
                                                </Button>
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    class="h-9 w-full cursor-pointer justify-center bg-rose-900 px-3.5 text-xs font-bold text-white shadow-xs hover:bg-rose-950 sm:w-auto"
                                                    @click="
                                                        printCertificate(record)
                                                    "
                                                >
                                                    <Download
                                                        class="mr-1.5 size-3.5"
                                                    />
                                                    Descargar PDF (2 Páginas)
                                                </Button>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>
                        </template>

                        <!-- Sin registros -->
                        <div
                            v-else
                            class="space-y-3 rounded-xl border border-slate-200 bg-white p-8 text-center shadow-xs dark:border-slate-800 dark:bg-slate-900"
                        >
                            <div
                                class="mx-auto flex size-12 items-center justify-center rounded-full bg-slate-100 text-slate-400 dark:bg-slate-800"
                            >
                                <Award class="size-6" />
                            </div>
                            <h3
                                class="text-base font-bold text-slate-950 dark:text-white"
                            >
                                No se registran certificados oficiales para el
                                DNI {{ dniQuery }}
                            </h3>
                            <p
                                class="mx-auto max-w-md text-xs leading-relaxed text-slate-600 dark:text-slate-400"
                            >
                                Los certificados se publican una vez concluida
                                la capacitación y cerrada el acta oficial de
                                evaluación académica.
                            </p>
                            <div class="pt-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    class="border-slate-300 font-semibold dark:border-slate-700"
                                    @click="resetSearch"
                                >
                                    <RotateCcw class="mr-1.5 size-3.5" />
                                    Consultar otro DNI
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- VISTA 2: VERIFICACIÓN CRIPTOGRÁFICA DE ARCHIVO PDF -->
                <div v-else class="mx-auto max-w-3xl space-y-4">
                    <PdfIntegrityVerifier
                        :expected-hash="
                            selectedCert?.certificate_hash ||
                            records[0]?.certificate_hash
                        "
                    />
                </div>
            </div>
        </div>

        <!-- MODAL DE VISTA PREVIA DEL DIPLOMA OFICIAL (COMPARTIDO - VISOR ESTUDIO A4 LANDSCAPE) -->
        <Dialog v-model:open="isCertModalOpen">
            <DialogContent
                :show-close-button="false"
                class="flex h-[92vh] max-h-[96vh] w-[96vw] max-w-7xl flex-col overflow-hidden rounded-2xl border border-slate-800 bg-slate-950 p-0 text-slate-100 shadow-2xl sm:max-w-none"
            >
                <!-- TOP TOOLBAR INSTITUCIONAL / BARRA DE HERRAMIENTAS ESTILO ESTUDIO -->
                <div
                    class="flex shrink-0 flex-wrap items-center justify-between gap-3 border-b border-slate-800/90 bg-slate-900/95 px-4 py-3 backdrop-blur-md select-none sm:px-6"
                >
                    <!-- Info del Certificado -->
                    <div class="flex min-w-0 items-center gap-3">
                        <div
                            class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-tr from-amber-600 via-[#800020] to-[#800020] text-white shadow-xs"
                        >
                            <Award class="size-5 text-amber-300" />
                        </div>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span
                                    class="text-sm font-black tracking-tight text-white"
                                >
                                    Diploma Oficial Acreditado
                                </span>
                                <Badge
                                    class="border border-emerald-700/80 bg-emerald-950 px-2 py-0.5 text-[10px] font-bold text-emerald-200"
                                >
                                    <CheckCircle2
                                        class="mr-1 size-3 text-emerald-400"
                                    />
                                    Aprobado • Fe Pública
                                </Badge>
                            </div>
                            <div
                                class="mt-0.5 flex items-center gap-2 text-[11px] text-slate-400"
                            >
                                <span class="truncate"
                                    >UNSAAC • Sistema Integral de Gestión de
                                    Capacitaciones</span
                                >
                                <span class="text-slate-600">•</span>
                                <span
                                    class="font-mono font-bold text-amber-400"
                                    >{{ selectedCert?.certificate_code }}</span
                                >
                            </div>
                        </div>
                    </div>

                    <!-- Selector de Modos de Vista: Anverso / Reverso / Vista Completa -->
                    <div
                        class="inline-flex rounded-xl border border-slate-800 bg-slate-950 p-1 shadow-inner"
                    >
                        <button
                            type="button"
                            @click="previewTab = 'anverso'"
                            class="flex cursor-pointer items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-bold transition-all sm:px-4"
                            :class="
                                previewTab === 'anverso'
                                    ? 'bg-[#800020] text-white shadow-xs'
                                    : 'text-slate-400 hover:text-white'
                            "
                        >
                            <Award class="size-3.5" />
                            <span class="hidden sm:inline">Cara 1:</span>
                            <span>Anverso</span>
                        </button>
                        <button
                            type="button"
                            @click="previewTab = 'reverso'"
                            class="flex cursor-pointer items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-bold transition-all sm:px-4"
                            :class="
                                previewTab === 'reverso'
                                    ? 'bg-[#800020] text-white shadow-xs'
                                    : 'text-slate-400 hover:text-white'
                            "
                        >
                            <QrCode class="size-3.5" />
                            <span class="hidden sm:inline">Cara 2:</span>
                            <span>Reverso</span>
                        </button>
                        <button
                            type="button"
                            @click="previewTab = 'completa'"
                            class="flex cursor-pointer items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-bold transition-all sm:px-4"
                            :class="
                                previewTab === 'completa'
                                    ? 'bg-[#800020] text-white shadow-xs'
                                    : 'text-slate-400 hover:text-white'
                            "
                        >
                            <Layers class="size-3.5" />
                            <span>Vista Completa (2 Páginas)</span>
                        </button>
                    </div>

                    <!-- Controles de Zoom y Acciones Rápidas -->
                    <div class="flex items-center gap-2">
                        <!-- Zoom Controls -->
                        <div
                            class="hidden items-center rounded-lg border border-slate-800 bg-slate-950 p-0.5 text-xs text-slate-300 md:inline-flex"
                        >
                            <button
                                type="button"
                                @click="zoomOut"
                                class="cursor-pointer rounded p-1.5 text-slate-400 transition-colors hover:bg-slate-800 hover:text-white"
                                title="Reducir zoom"
                            >
                                <ZoomOut class="size-3.5" />
                            </button>
                            <span
                                class="px-2 font-mono text-[11px] font-bold text-amber-400"
                                >{{ zoomScale }}%</span
                            >
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
                            class="cursor-pointer rounded-xl bg-gradient-to-r from-amber-600 via-[#800020] to-[#800020] px-3.5 py-2 text-xs font-black text-white shadow-md transition-transform hover:brightness-110 active:scale-95 sm:px-4"
                            @click="
                                selectedCert && printCertificate(selectedCert)
                            "
                        >
                            <Printer class="mr-1.5 size-3.5 text-amber-200" />
                            <span>Descargar / Imprimir Diploma PDF</span>
                        </Button>

                        <!-- Botón Cerrar (X) -->
                        <button
                            type="button"
                            @click="isCertModalOpen = false"
                            class="flex size-9 cursor-pointer items-center justify-center rounded-xl border border-slate-800 bg-slate-950 text-slate-400 transition-all hover:border-rose-900 hover:bg-rose-950/60 hover:text-white"
                            title="Cerrar visor de certificado"
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
                            <ShieldCheck
                                class="size-4.5 shrink-0 text-emerald-400"
                            />
                            <span
                                >Verificación Criptográfica Exitosa: El
                                certificado digital coincide con los registros
                                institucionales inmutables de la UNSAAC.</span
                            >
                        </div>
                        <Badge
                            variant="outline"
                            class="border-emerald-600 bg-emerald-900/40 font-mono text-emerald-300"
                        >
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
                        <!-- ========================================== -->
                        <!-- CARA 1: ANVERSO (DIPLOMA DE HONOR OFICIAL) -->
                        <!-- ========================================== -->
                        <div
                            v-if="
                                previewTab === 'anverso' ||
                                previewTab === 'completa'
                            "
                            class="flex w-full max-w-5xl flex-col items-center"
                        >
                            <div
                                v-if="previewTab === 'completa'"
                                class="mb-2 flex w-full items-center justify-between px-2 font-mono text-xs font-bold text-slate-400"
                            >
                                <span
                                    class="flex items-center gap-1.5 text-amber-400"
                                >
                                    <Award class="size-3.5" />
                                    PÁGINA 1 DE 2: DIPLOMA DE HONOR
                                    INSTITUCIONAL
                                </span>
                                <span class="font-sans text-slate-500"
                                    >FORMATO OFICIAL A4 LANDSCAPE (297mm ×
                                    210mm)</span
                                >
                            </div>

                            <!-- LIENZO DE PAPEL A4 LANDSCAPE (ANVERSO) - DOBLE MARCO INSTITUCIONAL LIMPIO -->
                            <div
                                class="relative flex aspect-[297/210] min-h-[580px] w-full flex-col justify-between overflow-hidden rounded-xl border-[3px] border-[#800020] bg-[#fefefc] p-6 text-slate-950 shadow-[0_25px_60px_-15px_rgba(0,0,0,0.7)] select-text sm:min-h-[660px] sm:p-10 md:min-h-[720px] md:p-12"
                            >
                                <!-- Filete Interior Dorado -->
                                <div
                                    class="pointer-events-none absolute inset-2 z-10 rounded-lg border border-[#b45309] sm:inset-3"
                                ></div>

                                <!-- Marca de Agua Institucional de Fondo -->
                                <div
                                    class="pointer-events-none absolute inset-0 z-0 flex items-center justify-center opacity-[0.035] select-none"
                                >
                                    <img
                                        src="/images/unsaac-logo.png"
                                        alt="Marca de Agua UNSAAC"
                                        class="size-80 object-contain sm:size-96"
                                    />
                                </div>

                                <!-- Contenido del Diploma Anverso -->
                                <div
                                    class="relative z-20 flex h-full flex-col justify-between text-center"
                                >
                                    <!-- Cabecera Institucional Oficial con Logos de Alta Resolución -->
                                    <div
                                        class="mb-2 flex items-center justify-between border-b-2 border-[#800020] pb-3"
                                    >
                                        <div
                                            class="flex w-20 shrink-0 justify-center sm:w-28"
                                        >
                                            <img
                                                src="/images/unsaac-logo.png"
                                                alt="UNSAAC Logo"
                                                class="h-16 object-contain drop-shadow-xs filter sm:h-20 md:h-24"
                                            />
                                        </div>
                                        <div
                                            class="min-w-0 flex-1 px-3 text-center"
                                        >
                                            <div
                                                class="text-[10px] font-black tracking-[0.25em] text-[#800020] uppercase sm:text-xs"
                                            >
                                                REPÚBLICA DEL PERÚ • REGIÓN
                                                CUSCO
                                            </div>
                                            <h1
                                                class="mt-1 font-serif text-base leading-tight font-black tracking-tight text-slate-950 uppercase sm:text-2xl md:text-[26px]"
                                            >
                                                Universidad Nacional de San
                                                Antonio Abad del Cusco
                                            </h1>
                                            <div
                                                class="mt-0.5 text-[9px] font-bold tracking-wider text-slate-700 uppercase sm:text-xs"
                                            >
                                                Facultad de Ingeniería
                                                Eléctrica, Electrónica,
                                                Informática y Mecánica
                                            </div>
                                            <div
                                                class="mt-1.5 inline-block rounded bg-[#800020] px-3.5 py-0.5 text-[8px] font-black tracking-widest text-amber-200 uppercase shadow-xs sm:text-[10px]"
                                            >
                                                Sistema Integral de Gestión de
                                                Capacitaciones (SIGC-CUSCO)
                                            </div>
                                        </div>
                                        <div
                                            class="flex w-20 shrink-0 justify-center sm:w-28"
                                        >
                                            <img
                                                src="/images/escudo.png"
                                                alt="Escudo del Perú / UNSAAC"
                                                class="h-16 object-contain drop-shadow-xs filter sm:h-20 md:h-24"
                                            />
                                        </div>
                                    </div>

                                    <!-- Divisor Decorativo Dorado con Diamante Central -->
                                    <div
                                        class="my-1 flex w-full items-center justify-center"
                                    >
                                        <div
                                            class="h-0.5 w-full bg-gradient-to-r from-transparent via-[#b45309] to-transparent"
                                        ></div>
                                        <div class="shrink-0 px-2">
                                            <span
                                                class="inline-block size-2 rotate-45 bg-[#b45309]"
                                            ></span>
                                        </div>
                                        <div
                                            class="h-0.5 w-full bg-gradient-to-r from-transparent via-[#b45309] to-transparent"
                                        ></div>
                                    </div>

                                    <!-- Título Principal del Certificado -->
                                    <div class="my-1 space-y-1">
                                        <h2
                                            class="font-serif text-3xl leading-none font-black tracking-[0.25em] text-[#800020] uppercase sm:text-5xl md:text-[54px]"
                                        >
                                            CERTIFICADO
                                        </h2>
                                        <p
                                            class="font-serif text-[11px] tracking-wide text-slate-600 italic sm:text-xs"
                                        >
                                            Por culminación académica
                                            satisfactoria y acreditación de
                                            competencias profesionales
                                        </p>
                                    </div>

                                    <!-- Otorgado a: Nombre del Participante -->
                                    <div class="my-2 sm:my-3">
                                        <p
                                            class="font-serif text-[11px] font-bold tracking-widest text-slate-600 uppercase sm:text-xs"
                                        >
                                            Conferido en testimonio de honor y
                                            mérito académico a:
                                        </p>
                                        <div
                                            class="mt-0.5 inline-block border-b-2 border-[#800020] px-8 py-1 font-serif text-xl font-black tracking-tight text-slate-950 uppercase sm:text-3xl md:text-[34px]"
                                        >
                                            {{ selectedCert.student_name }}
                                        </div>
                                        <div class="mt-1.5">
                                            <span
                                                class="inline-block rounded-full border border-rose-300 bg-rose-50 px-4 py-0.5 font-mono text-xs font-black text-[#800020] shadow-2xs sm:text-sm"
                                            >
                                                D.N.I. N° {{ selectedCert.dni }}
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Descripción y Datos del Curso -->
                                    <div
                                        class="mx-auto my-1 max-w-3xl font-serif text-xs leading-relaxed text-slate-700 sm:text-sm"
                                    >
                                        Por haber aprobado satisfactoriamente
                                        con alto rendimiento académico el
                                        programa de capacitación en:
                                        <strong
                                            class="my-1.5 block font-serif text-base leading-snug font-black tracking-tight text-[#800020] sm:text-xl md:text-2xl"
                                        >
                                            "{{ selectedCert.course_title }}"
                                        </strong>
                                        desarrollado
                                        <strong>{{
                                            selectedCert.date_range_formal ||
                                            formatSpanishDateRange(
                                                selectedCert.start_date,
                                                selectedCert.end_date,
                                            )
                                        }}</strong
                                        >, con una carga lectiva de
                                        <strong>{{
                                            formatHours(selectedCert.hours)
                                        }}</strong
                                        >, en cumplimiento de los estándares de
                                        acreditación universitaria.
                                    </div>

                                    <!-- Calificación Oficial Obtenida -->
                                    <div
                                        class="mx-auto my-1.5 inline-flex items-center gap-2 rounded-full border border-emerald-400 bg-gradient-to-r from-amber-50 to-emerald-50 px-4 py-1 text-xs font-black text-slate-900 shadow-2xs sm:text-sm"
                                    >
                                        <span class="font-serif text-slate-600"
                                            >Calificación Obtenida:</span
                                        >
                                        <span
                                            class="text-sm font-black text-emerald-700 sm:text-base"
                                            >{{ selectedCert.final_grade }} /
                                            20.00</span
                                        >
                                        <span
                                            class="font-serif font-semibold text-slate-600"
                                            >({{
                                                selectedCert.final_grade_text ||
                                                'Sobresaliente'
                                            }})</span
                                        >
                                    </div>

                                    <!-- Lugar y Fecha de Emisión Formal -->
                                    <div
                                        class="my-1 pr-6 text-right font-serif text-[11px] font-bold text-slate-600 italic sm:pr-10 sm:text-xs"
                                    >
                                        {{
                                            selectedCert.city_issued_formal ||
                                            'Cusco, ' +
                                                formatSpanishDate(
                                                    selectedCert.certificate_issued_at ||
                                                        selectedCert.end_date,
                                                )
                                        }}
                                    </div>

                                    <!-- Firmas Digitales con Sellos Redondos Oficiales -->
                                    <div
                                        class="mt-2 grid grid-cols-2 gap-8 border-t border-slate-300 pt-3 text-xs"
                                    >
                                        <!-- Firma Docente Responsable -->
                                        <div
                                            class="relative flex flex-col items-center text-center"
                                        >
                                            <div
                                                class="-mb-2 flex size-20 items-center justify-center"
                                                v-html="DOCENTE_STAMP_SVG"
                                            ></div>
                                            <div
                                                class="w-48 border-t border-slate-800 pt-1 sm:w-60"
                                            >
                                                <div
                                                    class="font-serif text-xs font-black text-slate-900 uppercase sm:text-sm"
                                                >
                                                    {{
                                                        selectedCert.instructor_name
                                                    }}
                                                </div>
                                                <div
                                                    class="font-serif text-[10px] text-slate-500 uppercase"
                                                >
                                                    {{
                                                        selectedCert.instructor_title ||
                                                        'Docente Responsable / Ponente'
                                                    }}
                                                </div>
                                            </div>
                                        </div>
                                        <!-- Firma Dirección Académica -->
                                        <div
                                            class="relative flex flex-col items-center text-center"
                                        >
                                            <div
                                                class="-mb-2 flex size-20 items-center justify-center"
                                                v-html="DIRECCION_STAMP_SVG"
                                            ></div>
                                            <div
                                                class="w-48 border-t border-slate-800 pt-1 sm:w-60"
                                            >
                                                <div
                                                    class="font-serif text-xs font-black text-slate-900 uppercase sm:text-sm"
                                                >
                                                    Dirección Académica
                                                </div>
                                                <div
                                                    class="font-serif text-[10px] text-slate-500 uppercase"
                                                >
                                                    Coordinación General SIGC •
                                                    UNSAAC
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Pie de Página con Código de Verificación Oficial -->
                                    <div
                                        class="mt-2 flex items-center justify-between border-t border-dashed border-slate-300 pt-2 font-mono text-[10px] text-slate-500"
                                    >
                                        <span
                                            >Cód. Verificación:
                                            <strong class="text-slate-900">{{
                                                selectedCert.certificate_code
                                            }}</strong></span
                                        >
                                        <span
                                            >Fecha de Emisión:
                                            <strong>{{
                                                selectedCert.issued_date_formal ||
                                                formatSpanishDate(
                                                    selectedCert.certificate_issued_at ||
                                                        selectedCert.end_date,
                                                )
                                            }}</strong></span
                                        >
                                        <span class="font-black text-[#800020]"
                                            >REGISTRO OFICIAL DE FE PÚBLICA •
                                            CUSCO, PERÚ</span
                                        >
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ======================================================== -->
                        <!-- CARA 2: REVERSO (PLAN CURRICULAR, EVALUACIÓN Y QR OFICIAL) -->
                        <!-- ======================================================== -->
                        <div
                            v-if="
                                previewTab === 'reverso' ||
                                previewTab === 'completa'
                            "
                            class="flex w-full max-w-5xl flex-col items-center"
                        >
                            <div
                                v-if="previewTab === 'completa'"
                                class="mb-2 flex w-full items-center justify-between px-2 font-mono text-xs font-bold text-slate-400"
                            >
                                <span
                                    class="flex items-center gap-1.5 text-amber-400"
                                >
                                    <QrCode class="size-3.5" />
                                    PÁGINA 2 DE 2: PLAN CURRICULAR, CALIFICACIÓN
                                    Y VERIFICACIÓN QR
                                </span>
                                <span class="font-sans text-slate-500"
                                    >FORMATO OFICIAL A4 LANDSCAPE (297mm ×
                                    210mm)</span
                                >
                            </div>

                            <!-- LIENZO DE PAPEL A4 LANDSCAPE (REVERSO) - DOBLE MARCO INSTITUCIONAL LIMPIO -->
                            <div
                                class="relative flex aspect-[297/210] min-h-[580px] w-full flex-col justify-between overflow-hidden rounded-xl border-[3px] border-[#800020] bg-[#fefefc] p-6 text-slate-950 shadow-[0_25px_60px_-15px_rgba(0,0,0,0.7)] select-text sm:min-h-[660px] sm:p-10 md:min-h-[720px] md:p-12"
                            >
                                <!-- Filete Interior Dorado -->
                                <div
                                    class="pointer-events-none absolute inset-2 z-10 rounded-lg border border-[#b45309] sm:inset-3"
                                ></div>

                                <!-- Contenido del Diploma Reverso -->
                                <div
                                    class="relative z-20 flex h-full flex-col justify-between text-left"
                                >
                                    <!-- Cabecera del Reverso -->
                                    <div
                                        class="mb-3 flex items-center justify-between border-b-2 border-[#800020] pb-2.5"
                                    >
                                        <div>
                                            <div
                                                class="text-[10px] font-black tracking-wider text-[#800020] uppercase"
                                            >
                                                UNIVERSIDAD NACIONAL DE SAN
                                                ANTONIO ABAD DEL CUSCO • SIGC
                                            </div>
                                            <h3
                                                class="mt-0.5 font-serif text-base font-black text-slate-950 uppercase sm:text-xl"
                                            >
                                                Plan Curricular y Registro
                                                Oficial de Calificación
                                            </h3>
                                            <p class="text-xs text-slate-600">
                                                Programa:
                                                <strong
                                                    class="text-slate-900"
                                                    >{{
                                                        selectedCert.course_title
                                                    }}</strong
                                                >
                                                (Código:
                                                {{ selectedCert.course_code }})
                                            </p>
                                        </div>
                                        <div
                                            class="hidden text-right font-mono text-[11px] text-slate-500 sm:block"
                                        >
                                            <div>
                                                Cód:
                                                <strong>{{
                                                    selectedCert.certificate_code
                                                }}</strong>
                                            </div>
                                            <div>
                                                Horas:
                                                <strong
                                                    >{{
                                                        selectedCert.hours
                                                    }}
                                                    hrs. lectivas</strong
                                                >
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Estructura en 2 Columnas: Módulos a la Izquierda vs Evaluación/QR a la Derecha -->
                                    <div
                                        class="grid min-h-0 flex-1 grid-cols-1 gap-5 md:grid-cols-12"
                                    >
                                        <!-- Columna Izquierda: Temario y Módulos Curriculares (7 cols) -->
                                        <div
                                            class="flex flex-col justify-between space-y-2 md:col-span-7"
                                        >
                                            <div
                                                class="flex items-center justify-between border-b border-slate-200 pb-1 text-xs font-black tracking-wider text-[#800020] uppercase"
                                            >
                                                <span
                                                    class="flex items-center gap-1.5"
                                                >
                                                    <BookOpen
                                                        class="size-3.5"
                                                    />
                                                    Módulos Académicos
                                                    Acreditados
                                                </span>
                                                <span
                                                    class="font-mono text-[10px] text-slate-500"
                                                    >{{
                                                        selectedCert.hours
                                                    }}
                                                    Horas Lectivas</span
                                                >
                                            </div>

                                            <div
                                                class="max-h-[300px] space-y-2 overflow-y-auto pr-1"
                                            >
                                                <div
                                                    v-for="(
                                                        module, index
                                                    ) in selectedCert.modules"
                                                    :key="index"
                                                    class="rounded-lg border border-l-4 border-slate-200 border-l-[#800020] bg-slate-50 p-2.5 dark:border-slate-800 dark:border-l-rose-700 dark:bg-slate-900/60"
                                                >
                                                    <div
                                                        class="flex items-center justify-between text-xs font-bold text-slate-950 dark:text-slate-100"
                                                    >
                                                        <span
                                                            class="mr-2 text-[11px] font-black text-[#800020] dark:text-rose-400"
                                                            >{{
                                                                module.number
                                                            }}</span
                                                        >
                                                        <span
                                                            class="flex-1 truncate font-serif"
                                                            >{{
                                                                module.title
                                                            }}</span
                                                        >
                                                        <span
                                                            class="rounded border border-slate-200 bg-white px-2 py-0.5 font-mono text-[10px] text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                                                        >
                                                            {{ module.hours }}
                                                        </span>
                                                    </div>
                                                    <p
                                                        class="mt-1 text-[11px] leading-relaxed text-slate-600 dark:text-slate-400"
                                                    >
                                                        {{ module.topics }}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Columna Derecha: Calificación, Docente, QR y Criptografía (5 cols) -->
                                        <div
                                            class="flex flex-col justify-between space-y-3 md:col-span-5"
                                        >
                                            <!-- Cuadro de Calificación Oficial -->
                                            <div
                                                class="flex items-center justify-between rounded-xl border border-emerald-300 bg-emerald-50 p-3.5 dark:border-emerald-800/60 dark:bg-emerald-950/30"
                                            >
                                                <div>
                                                    <div
                                                        class="text-[10px] font-bold text-emerald-800 uppercase dark:text-emerald-300"
                                                    >
                                                        Registro de Calificación
                                                    </div>
                                                    <div
                                                        class="text-2xl font-black text-emerald-950 dark:text-emerald-100"
                                                    >
                                                        {{
                                                            selectedCert.final_grade
                                                        }}
                                                        / 20.00
                                                    </div>
                                                    <div
                                                        class="text-[10px] font-bold text-emerald-700 dark:text-emerald-400"
                                                    >
                                                        {{
                                                            selectedCert.final_grade_text ||
                                                            'Sobresaliente'
                                                        }}
                                                    </div>
                                                </div>
                                                <Badge
                                                    class="bg-emerald-700 px-2.5 py-1 text-xs font-black text-white"
                                                >
                                                    ✓ APROBADO
                                                </Badge>
                                            </div>

                                            <!-- Docente Responsable -->
                                            <div
                                                class="rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-900/60"
                                            >
                                                <div
                                                    class="text-[10px] font-bold text-slate-500 uppercase dark:text-slate-400"
                                                >
                                                    Docente / Ponente
                                                    Responsable:
                                                </div>
                                                <div
                                                    class="mt-0.5 text-xs font-black text-slate-950 uppercase dark:text-white"
                                                >
                                                    {{
                                                        selectedCert.instructor_name
                                                    }}
                                                </div>
                                                <div
                                                    class="text-[10px] text-slate-600 dark:text-slate-300"
                                                >
                                                    {{
                                                        selectedCert.instructor_title ||
                                                        'Docente Responsable'
                                                    }}
                                                </div>
                                                <div
                                                    class="mt-0.5 text-[9px] text-slate-500 dark:text-slate-400"
                                                >
                                                    {{
                                                        selectedCert.institution ||
                                                        'Universidad Nacional de San Antonio Abad del Cusco'
                                                    }}
                                                </div>
                                            </div>

                                            <!-- Código QR Permanente de Validación -->
                                            <div
                                                class="flex items-center gap-3 rounded-xl border-2 border-[#800020] bg-white p-3 dark:border-rose-900 dark:bg-slate-900/80"
                                            >
                                                <div
                                                    class="flex size-20 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-white p-1 dark:border-slate-700"
                                                >
                                                    <div
                                                        v-html="
                                                            selectedCert.qr_svg
                                                        "
                                                        class="size-full [&>svg]:size-full"
                                                    ></div>
                                                </div>
                                                <div
                                                    class="min-w-0 flex-1 space-y-0.5"
                                                >
                                                    <div
                                                        class="flex items-center gap-1 text-xs font-black text-[#800020] uppercase dark:text-rose-400"
                                                    >
                                                        <QrCode
                                                            class="size-3.5 shrink-0"
                                                        />
                                                        <span
                                                            >Validación QR en
                                                            Línea</span
                                                        >
                                                    </div>
                                                    <p
                                                        class="text-[10px] leading-tight text-slate-600 dark:text-slate-400"
                                                    >
                                                        Escanee el código QR
                                                        para verificar la
                                                        autenticidad e
                                                        integridad del
                                                        certificado en tiempo
                                                        real.
                                                    </p>
                                                    <a
                                                        :href="
                                                            selectedCert.verification_url
                                                        "
                                                        target="_blank"
                                                        class="inline-flex max-w-full items-center gap-1 truncate font-mono text-[9px] font-bold text-blue-700 hover:underline dark:text-blue-400"
                                                    >
                                                        <ExternalLink
                                                            class="size-2.5 shrink-0"
                                                        />
                                                        <span
                                                            class="truncate"
                                                            >{{
                                                                selectedCert.verification_url
                                                            }}</span
                                                        >
                                                    </a>
                                                </div>
                                            </div>

                                            <!-- Huella Criptográfica SHA-256 -->
                                            <div
                                                v-if="
                                                    selectedCert.certificate_hash
                                                "
                                                class="rounded-lg border border-slate-200 bg-slate-50 p-2 font-mono text-[9px] dark:border-slate-800 dark:bg-slate-900/60"
                                            >
                                                <div
                                                    class="mb-0.5 flex items-center justify-between font-bold text-slate-600 dark:text-slate-400"
                                                >
                                                    <span
                                                        class="flex items-center gap-1 text-[#800020] dark:text-rose-400"
                                                    >
                                                        <ShieldCheck
                                                            class="size-3"
                                                        />
                                                        Huella SHA-256:
                                                    </span>
                                                    <span
                                                        class="text-[8px] text-slate-400 dark:text-slate-500"
                                                        >Inmutable</span
                                                    >
                                                </div>
                                                <div
                                                    class="leading-tight font-semibold break-all text-slate-800 select-all dark:text-slate-200"
                                                >
                                                    {{
                                                        selectedCert.certificate_hash
                                                    }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Pie del Reverso -->
                                    <div
                                        class="mt-2 flex items-center justify-between border-t border-dashed border-slate-300 pt-2 font-mono text-[10px] text-slate-500"
                                    >
                                        <span
                                            >Certificado:
                                            <strong>{{
                                                selectedCert.certificate_code
                                            }}</strong></span
                                        >
                                        <span
                                            >Registro en Actas:
                                            <strong>{{
                                                selectedCert.issued_date_formal ||
                                                formatSpanishDate(
                                                    selectedCert.certificate_issued_at ||
                                                        selectedCert.end_date,
                                                )
                                            }}</strong></span
                                        >
                                        <span class="font-black text-[#800020]"
                                            >CUSCO, REPÚBLICA DEL PERÚ •
                                            CERTIFICACIÓN OFICIAL</span
                                        >
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- FOOTER FIJO CON ACCIONES ESTILO ESTUDIO -->
                <div
                    class="flex shrink-0 flex-col items-center justify-between gap-3 border-t border-slate-800 bg-slate-900/95 px-4 py-3 select-none sm:flex-row sm:px-6"
                >
                    <div
                        class="flex w-full flex-wrap items-center gap-2 sm:w-auto"
                    >
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="flex-1 cursor-pointer justify-center rounded-xl border-slate-700 bg-slate-800/80 text-xs font-bold text-slate-200 hover:bg-slate-700 sm:flex-initial"
                            @click="
                                selectedCert &&
                                copyVerificationCode(
                                    selectedCert.certificate_code,
                                )
                            "
                        >
                            <Check
                                v-if="copiedCode"
                                class="mr-1 size-3.5 text-emerald-400"
                            />
                            <Copy v-else class="mr-1 size-3.5 text-slate-400" />
                            <span>{{
                                copiedCode
                                    ? '¡Código Copiado!'
                                    : 'Copiar Código'
                            }}</span>
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="flex-1 cursor-pointer justify-center rounded-xl border-slate-700 bg-slate-800/80 text-xs font-bold text-slate-200 hover:bg-slate-700 sm:flex-initial"
                            @click="
                                selectedCert &&
                                copyVerificationUrl(
                                    selectedCert.verification_url,
                                )
                            "
                        >
                            <Check
                                v-if="copiedUrl"
                                class="mr-1 size-3.5 text-emerald-400"
                            />
                            <Share2
                                v-else
                                class="mr-1 size-3.5 text-slate-400"
                            />
                            <span>{{
                                copiedUrl ? '¡Enlace Copiado!' : 'Copiar QR'
                            }}</span>
                        </Button>
                    </div>

                    <div
                        class="flex w-full flex-wrap items-center justify-end gap-2.5 sm:w-auto"
                    >
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
                            class="w-full cursor-pointer justify-center rounded-xl bg-gradient-to-r from-amber-600 via-[#800020] to-[#800020] px-4 py-2 text-xs font-black text-white shadow-md hover:brightness-110 sm:w-auto"
                            @click="
                                selectedCert && printCertificate(selectedCert)
                            "
                        >
                            <Printer class="mr-1.5 size-3.5 text-amber-200" />
                            <span>Imprimir / PDF (2 Páginas)</span>
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
