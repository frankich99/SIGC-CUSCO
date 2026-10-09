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
    KeyRound,
    HelpCircle,
    Info,
    Lock,
} from '@lucide/vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
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


// Flujo Institucional en 2 Pasos para consulta pública por DNI
const searchStep = ref<1 | 2 | 3>(1); // 1 = DNI | 2 = Dígito Verificador | 3 = Diplomas Mostrados
const verificationDigit = ref('');
const validatingDni = ref(false);
const reniecPerson = ref<{
    dni: string;
    nombres: string;
    apellido_paterno?: string;
    apellido_materno?: string;
    paterno?: string;
    materno?: string;
    nombre_completo?: string;
    codigo_verificacion?: string | null;
} | null>(null);
const verificationError = ref<string | null>(null);
const showHelpModal = ref(false);

function allowOnlyNumbers(e: KeyboardEvent) {
    if (['Backspace', 'Delete', 'Tab', 'ArrowLeft', 'ArrowRight', 'Home', 'End', 'Enter'].includes(e.key)) {
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
        reniecPerson.value = null;
        verificationDigit.value = '';
        verificationError.value = null;
        records.value = [];
        searched.value = false;
    }
}

function onVerificationInput(e: Event) {
    const target = e.target as HTMLInputElement;
    const sanitized = target.value.replace(/[^0-9A-Za-z]/g, '').slice(0, 1).toUpperCase();
    if (target.value !== sanitized) {
        target.value = sanitized;
    }
    verificationDigit.value = sanitized;
    if (verificationError.value) verificationError.value = null;
}

async function validateDniStep() {
    const clean = dniQuery.value.trim();
    if (!/^\d{8}$/.test(clean)) {
        error.value = 'Ingrese un número de DNI válido de exactamente 8 dígitos.';
        return;
    }

    validatingDni.value = true;
    error.value = null;
    verificationError.value = null;
    verificationDigit.value = '';

    try {
        // 1. Validar identidad con la API de RENIEC
        const response = await fetch(`/api/dni/${clean}`);
        const data = await response.json();

        if (response.ok && data.success && data.data) {
            reniecPerson.value = data.data;
            const fullName = data.data.nombre_completo || `${data.data.nombres} ${data.data.apellido_paterno || ''} ${data.data.apellido_materno || ''}`.trim();
            studentName.value = fullName;
            searchStep.value = 2;
            notify.success('Identidad Encontrada en RENIEC', fullName, 2000);
            return;
        }

        // 2. Fallback con verificación en padrón local institucional
        const localCheck = await fetch(`/api/certificates/lookup?dni=${clean}&check_only=1`);
        const localData = await localCheck.json();

        if (localCheck.ok && localData.success && localData.has_records) {
            reniecPerson.value = {
                dni: clean,
                nombres: localData.student_name || 'Participante Institucional',
                nombre_completo: localData.student_name || 'Participante Institucional',
                codigo_verificacion: null,
            };
            studentName.value = localData.student_name;
            searchStep.value = 2;
            notify.info('Registro Académico Encontrado', studentName.value || clean, 2000);
        } else {
            error.value = data.message || 'No se encontró el DNI en el padrón de RENIEC ni en los registros académicos.';
            notify.warning('DNI no encontrado', error.value, 3000);
        }
    } catch {
        error.value = 'Error al conectarse con el servicio de validación de identidad. Intente nuevamente.';
        notify.error('Error de conexión', error.value, 3000);
    } finally {
        validatingDni.value = false;
    }
}

async function verifyAndFetchCertificates() {
    const cleanDigit = verificationDigit.value.trim().toUpperCase();
    if (!cleanDigit || cleanDigit.length !== 1) {
        verificationError.value = 'Ingrese el código de verificación de 1 dígito de su DNI.';
        return;
    }

    // Cotejar con el código oficial de verificación de RENIEC si está presente
    if (reniecPerson.value?.codigo_verificacion) {
        const expected = String(reniecPerson.value.codigo_verificacion).trim().toUpperCase();
        if (cleanDigit !== expected) {
            verificationError.value = 'El código de verificación no coincide con el registrado en su DNI. Por favor revise el número ubicado a la derecha de su DNI físico.';
            notify.error('Código Incorrecto', 'El dígito verificador no coincide con su documento físico.', 3000);
            return;
        }
    }

    verificationError.value = null;
    await searchCertificates();
    searchStep.value = 3;
}

function resetSearch() {
    searchStep.value = 1;
    dniQuery.value = '';
    verificationDigit.value = '';
    reniecPerson.value = null;
    error.value = null;
    verificationError.value = null;
    records.value = [];
    searched.value = false;
    studentName.value = null;
}

async function searchCertificates(targetCode?: string) {
    const clean = dniQuery.value.trim();
    const code = targetCode?.trim() || '';

    if (!code && !/^\d{8}$/.test(clean)) {
        error.value = 'Ingrese un número de DNI válido de exactamente 8 dígitos.';
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

        const response = await fetch(`/api/certificates/lookup?${queryParams.toString()}`, {
            headers: {
                Accept: 'application/json',
            },
        });

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
                        (r) => r.certificate_code.toLowerCase() === code.toLowerCase()
                    );
                    if (matched) {
                        selectedCert.value = matched;
                        isVerifiedByQr.value = true;
                        previewTab.value = 'anverso';
                        isCertModalOpen.value = true;
                        notify.success('Certificado Verificado con Éxito', 'Acreditado oficialmente por la UNSAAC.', 3000);
                    }
                } else {
                    notify.success('Certificados encontrados', `Se encontró ${records.value.length} diploma(s) oficial(es)`, 2000);
                }
            } else {
                studentName.value = null;
                notify.warning('Sin registros', 'No se encontraron certificados para el DNI consultado.', 2500);
            }
        } else {
            error.value = data.message || 'No se pudo consultar el registro de certificados.';
            notify.error('Consulta fallida', error.value, 3000);
        }
    } catch {
        error.value = 'Error al conectarse con el servidor de validación. Intente nuevamente.';
        notify.error('Error de conexión', error.value, 3000);
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

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8 space-y-8 min-w-0 w-full overflow-x-hidden">
            <!-- HEADER DE SECCIÓN ACADÉMICA -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200/90 dark:border-slate-800 pb-5">
                <div class="space-y-1">
                    <div class="flex items-center gap-2">
                        <div class="size-10 rounded-xl bg-gradient-to-tr from-[#701a31] to-[#800020] text-white flex items-center justify-center shadow-sm shrink-0">
                            <Award class="size-5 text-amber-300" />
                        </div>
                        <div>
                            <h1 class="text-xl sm:text-2xl font-black tracking-tight text-slate-950 dark:text-white">
                                Consulta y Validación de Certificados
                            </h1>
                            <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-400 font-medium">
                                Acreditación oficial institucional, diplomas con firma digital y verificación criptográfica SHA-256.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <Button as-child variant="outline" size="sm" class="text-xs font-bold border-slate-300 hover:text-rose-900 cursor-pointer">
                        <Link :href="authUser ? '/dashboard' : '/'">
                            <ArrowLeft class="size-3.5 mr-1 text-rose-800" />
                            {{ authUser ? 'Volver al Panel' : 'Portal Principal' }}
                        </Link>
                    </Button>
                </div>
            </div>

            <!-- CONTENIDO MODULAR DE CERTIFICACIÓN -->
            <div class="space-y-6">
                <!-- SELECTOR DE MODALIDAD (TABS RESPONSIVE) -->
                <div class="flex items-center justify-center w-full max-w-xl mx-auto px-1">
                    <div class="grid grid-cols-1 sm:grid-cols-2 p-1 bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl w-full gap-1">
                        <button
                            type="button"
                            @click="activeTab = 'dni'"
                            class="flex items-center justify-center gap-2 px-3 sm:px-5 py-2 rounded-lg text-xs font-black transition-all cursor-pointer text-center"
                            :class="activeTab === 'dni' ? 'bg-rose-900 text-white shadow-xs' : 'text-slate-700 dark:text-slate-300 hover:text-rose-900'"
                        >
                            <Search class="size-3.5 shrink-0" />
                            <span class="truncate">Búsqueda Oficial por DNI</span>
                        </button>
                        <button
                            type="button"
                            @click="activeTab = 'pdf'"
                            class="flex items-center justify-center gap-2 px-3 sm:px-5 py-2 rounded-lg text-xs font-black transition-all cursor-pointer text-center"
                            :class="activeTab === 'pdf' ? 'bg-rose-900 text-white shadow-xs' : 'text-slate-700 dark:text-slate-300 hover:text-rose-900'"
                        >
                            <FileCheck class="size-3.5 text-amber-500 shrink-0" />
                            <span class="truncate">Comprobador PDF (SHA-256)</span>
                        </button>
                    </div>
                </div>

                <!-- VISTA 1: CONSULTA INSTITUCIONAL CON VERIFICACIÓN DE IDENTIDAD EN 2 PASOS -->
                <div v-if="activeTab === 'dni'" class="space-y-6">
                    <!-- STEPPER INDICADOR INSTITUCIONAL -->
                    <div class="max-w-2xl mx-auto px-2 w-full">
                        <div class="grid grid-cols-3 gap-1.5 sm:gap-2 text-center text-[11px] sm:text-xs">
                            <!-- Paso 1 -->
                            <div
                                class="flex items-center justify-center gap-1 sm:gap-1.5 py-2 px-1.5 sm:px-3 rounded-xl border font-bold transition-all min-w-0"
                                :class="searchStep === 1 ? 'bg-rose-900 text-white border-rose-900 shadow-xs' : (searchStep > 1 ? 'bg-emerald-50 dark:bg-emerald-950/40 border-emerald-300 text-emerald-800 dark:text-emerald-300' : 'bg-slate-100 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-500')"
                            >
                                <CheckCircle2 v-if="searchStep > 1" class="size-3.5 text-emerald-600 shrink-0" />
                                <span v-else class="size-4 rounded-full bg-white/20 text-[10px] flex items-center justify-center shrink-0">1</span>
                                <span class="hidden md:inline">Paso 1:</span>
                                <span class="truncate">DNI</span>
                            </div>

                            <!-- Paso 2 -->
                            <div
                                class="flex items-center justify-center gap-1 sm:gap-1.5 py-2 px-1.5 sm:px-3 rounded-xl border font-bold transition-all min-w-0"
                                :class="searchStep === 2 ? 'bg-rose-900 text-white border-rose-900 shadow-xs' : (searchStep > 2 ? 'bg-emerald-50 dark:bg-emerald-950/40 border-emerald-300 text-emerald-800 dark:text-emerald-300' : 'bg-slate-100 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-500')"
                            >
                                <CheckCircle2 v-if="searchStep > 2" class="size-3.5 text-emerald-600 shrink-0" />
                                <span v-else class="size-4 rounded-full bg-white/20 text-[10px] flex items-center justify-center shrink-0">2</span>
                                <span class="hidden md:inline">Paso 2:</span>
                                <span class="truncate">Verificación</span>
                            </div>

                            <!-- Paso 3 -->
                            <div
                                class="flex items-center justify-center gap-1 sm:gap-1.5 py-2 px-1.5 sm:px-3 rounded-xl border font-bold transition-all min-w-0"
                                :class="searchStep === 3 ? 'bg-rose-900 text-white border-rose-900 shadow-xs' : 'bg-slate-100 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-500'"
                            >
                                <span class="size-4 rounded-full bg-white/20 text-[10px] flex items-center justify-center shrink-0">3</span>
                                <span class="truncate">Diplomas</span>
                            </div>
                        </div>
                    </div>

                    <!-- PASO 1: INGRESO DE DNI -->
                    <Card v-if="searchStep === 1" class="max-w-2xl mx-auto border border-rose-900/20 dark:border-rose-900/40 shadow-sm bg-white dark:bg-slate-900 overflow-hidden">
                        <CardHeader class="bg-gradient-to-r from-rose-50/80 via-white to-amber-50/20 dark:from-rose-950/30 dark:to-slate-900 border-b pb-3.5">
                            <div class="flex items-center gap-2 text-xs font-black text-rose-900 dark:text-rose-300 uppercase tracking-wider">
                                <Search class="size-4 text-rose-800" />
                                <span>Paso 1: Validación de Identidad</span>
                            </div>
                            <CardTitle class="text-base sm:text-lg font-bold text-slate-950 dark:text-white">
                                Ingrese el Número de DNI
                            </CardTitle>
                            <CardDescription class="text-xs text-slate-600 dark:text-slate-400">
                                Se validará su identidad en el padrón oficial de RENIEC antes de solicitar el código de verificación.
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="p-5 sm:p-6 space-y-4">
                            <form @submit.prevent="validateDniStep" class="flex flex-col sm:flex-row gap-3">
                                <div class="relative flex-1">
                                    <Search class="absolute left-3.5 top-1/2 -translate-y-1/2 size-4 text-slate-400 pointer-events-none" />
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
                                        class="w-full pl-10 pr-14 font-mono text-base tracking-widest font-bold text-slate-950 dark:text-white bg-white dark:bg-slate-950 rounded-xl border border-slate-300 dark:border-slate-700 focus:outline-hidden focus:border-rose-900 focus:ring-2 focus:ring-rose-900/30 h-11 transition-all disabled:opacity-50 disabled:cursor-not-allowed shadow-2xs placeholder:text-slate-400 placeholder:font-normal placeholder:tracking-normal"
                                        :disabled="validatingDni"
                                        autofocus
                                    />
                                    <div class="absolute right-3.5 top-1/2 -translate-y-1/2 text-[11px] font-mono font-bold text-slate-400 pointer-events-none">
                                        {{ dniQuery.length }}/8
                                    </div>
                                </div>
                                <Button
                                    type="submit"
                                    :disabled="validatingDni || dniQuery.length !== 8"
                                    class="bg-rose-900 hover:bg-rose-950 text-white font-bold h-11 px-6 shadow-xs shrink-0 text-xs sm:text-sm cursor-pointer"
                                >
                                    <Loader2 v-if="validatingDni" class="size-4 mr-2 animate-spin" />
                                    <ShieldCheck v-else class="size-4 mr-2 text-amber-300" />
                                    <span>Validar DNI en RENIEC</span>
                                </Button>
                            </form>

                            <div v-if="error" class="p-3 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900 text-rose-900 dark:text-rose-200 text-xs font-bold flex items-center gap-2">
                                <AlertCircle class="size-4 shrink-0 text-rose-700" />
                                <span>{{ error }}</span>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- PASO 2: CÓDIGO DE VERIFICACIÓN DEL DNI -->
                    <Card v-else-if="searchStep === 2" class="max-w-2xl mx-auto border border-rose-900/20 dark:border-rose-900/40 shadow-sm bg-white dark:bg-slate-900 overflow-hidden">
                        <!-- Cabecera con datos de la persona validada en RENIEC -->
                        <div class="p-4 sm:p-5 bg-gradient-to-r from-emerald-50/80 via-white to-rose-50/30 dark:from-emerald-950/20 dark:via-slate-900 dark:to-slate-900 border-b flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="size-11 rounded-xl bg-emerald-700 text-white flex items-center justify-center font-black shadow-xs shrink-0">
                                    <ShieldCheck class="size-6 text-emerald-200" />
                                </div>
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <Badge class="bg-emerald-100 text-emerald-900 border-emerald-300 dark:bg-emerald-950 dark:text-emerald-200 text-[10px] font-black">
                                            <CheckCircle2 class="size-3 mr-1 text-emerald-700" />
                                            Identidad Verificada en RENIEC
                                        </Badge>
                                        <span class="text-xs font-mono font-bold text-slate-500">DNI: {{ dniQuery }}</span>
                                    </div>
                                    <h3 class="text-sm sm:text-base font-black text-slate-950 dark:text-white uppercase tracking-tight truncate mt-0.5">
                                        {{ studentName }}
                                    </h3>
                                </div>
                            </div>

                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="resetSearch"
                                class="text-xs text-slate-600 hover:text-rose-900 border-slate-300 shrink-0 cursor-pointer"
                            >
                                <RotateCcw class="size-3.5 mr-1 text-rose-800" />
                                Cambiar DNI
                            </Button>
                        </div>

                        <!-- Formulario de ingreso de Código de Verificación (1 dígito) -->
                        <CardContent class="p-6 sm:p-8 space-y-6">
                            <div class="text-center space-y-2">
                                <div class="inline-flex items-center justify-center size-10 rounded-full bg-amber-50 dark:bg-amber-950/40 text-amber-600 mb-1">
                                    <KeyRound class="size-5" />
                                </div>
                                <CardTitle class="text-base sm:text-lg font-black text-slate-950 dark:text-white">
                                    Paso 2: Ingrese el Código de Verificación de su DNI
                                </CardTitle>
                                <CardDescription class="text-xs sm:text-sm text-slate-600 dark:text-slate-400 max-w-md mx-auto">
                                    Por seguridad institucional y para proteger la privacidad de sus diplomas, ingrese el dígito verificador que figura en su DNI físico.
                                </CardDescription>
                            </div>

                            <form @submit.prevent="verifyAndFetchCertificates" class="space-y-5 max-w-sm mx-auto">
                                <div class="flex flex-col items-center justify-center gap-2">
                                    <div class="relative">
                                        <input
                                            id="verification-digit-input"
                                            v-model="verificationDigit"
                                            @input="onVerificationInput"
                                            type="text"
                                            maxlength="1"
                                            placeholder="•"
                                            class="size-16 sm:size-20 text-center text-3xl sm:text-4xl font-mono font-black border-2 border-rose-900/60 focus:border-rose-900 rounded-2xl shadow-sm uppercase text-slate-950 dark:text-white bg-white dark:bg-slate-950 focus:outline-hidden focus:ring-2 focus:ring-rose-900/30 transition-all disabled:opacity-50"
                                            autofocus
                                            :disabled="loading"
                                        />
                                    </div>
                                    <p class="text-[11px] text-slate-500 font-medium">
                                        Es un único dígito numérico o alfanumérico (1 carácter)
                                    </p>
                                </div>

                                <div class="text-center">
                                    <button
                                        type="button"
                                        @click="showHelpModal = true"
                                        class="text-xs font-bold text-rose-900 dark:text-rose-400 hover:underline inline-flex items-center gap-1 cursor-pointer"
                                    >
                                        <HelpCircle class="size-3.5" />
                                        <span>¿Dónde encuentro el código de verificación en mi DNI?</span>
                                    </button>
                                </div>

                                <div v-if="verificationError" class="p-3 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900 text-rose-900 dark:text-rose-200 text-xs font-bold flex items-center gap-2">
                                    <AlertCircle class="size-4 shrink-0 text-rose-700" />
                                    <span>{{ verificationError }}</span>
                                </div>

                                <Button
                                    type="submit"
                                    :disabled="loading || verificationDigit.length !== 1"
                                    class="w-full h-12 bg-rose-900 hover:bg-rose-950 text-white font-black text-sm shadow-md cursor-pointer rounded-xl"
                                >
                                    <Loader2 v-if="loading" class="size-4 mr-2 animate-spin" />
                                    <Award v-else class="size-4 mr-2 text-amber-300" />
                                    <span>Verificar y Mostrar Certificados</span>
                                </Button>
                            </form>
                        </CardContent>
                    </Card>

                    <!-- Resultados de Búsqueda -->
                    <div v-if="loading" class="max-w-3xl mx-auto py-10 text-center space-y-3">
                        <Loader2 class="size-8 mx-auto animate-spin text-rose-900" />
                        <p class="text-xs sm:text-sm font-bold text-slate-700 dark:text-slate-300">
                            Consultando certificados oficiales emitidos para el DNI {{ dniQuery }}...
                        </p>
                    </div>

                    <div v-else-if="searched" class="max-w-3xl mx-auto space-y-5">
                        <template v-if="records.length > 0">
                            <!-- Titular Acreditado -->
                            <div class="p-4 sm:p-5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                                <div class="flex items-center gap-3">
                                    <div class="size-11 rounded-xl bg-rose-900 text-white flex items-center justify-center font-black shadow-xs shrink-0">
                                        <User class="size-5 text-amber-300" />
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <h2 class="text-base sm:text-lg font-black text-slate-950 dark:text-white uppercase tracking-tight">
                                                {{ studentName }}
                                            </h2>
                                            <Badge class="bg-emerald-100 text-emerald-900 border-emerald-300 dark:bg-emerald-950 dark:text-emerald-200 text-[10px] font-black">
                                                <CheckCircle2 class="size-3 mr-1 text-emerald-700" />
                                                Titular Oficial
                                            </Badge>
                                        </div>
                                        <div class="text-xs font-mono text-slate-600 dark:text-slate-400 mt-0.5">
                                            DNI: <strong class="text-slate-900 dark:text-white">{{ dniQuery }}</strong>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 flex-wrap">
                                    <Badge variant="outline" class="border-rose-300 text-rose-900 dark:border-rose-800 dark:text-rose-300 font-bold text-xs py-1 px-3 bg-rose-50 dark:bg-rose-950">
                                        <Award class="size-3.5 mr-1 text-rose-800" />
                                        {{ records.length }} {{ records.length === 1 ? 'Certificado Oficial' : 'Certificados Oficiales' }}
                                    </Badge>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        class="text-xs font-bold border-slate-300 hover:text-rose-900 cursor-pointer"
                                        @click="resetSearch"
                                    >
                                        <RotateCcw class="size-3.5 mr-1 text-rose-800" />
                                        <span>Consultar otro DNI</span>
                                    </Button>
                                </div>
                            </div>

                            <!-- Lista de Certificados Oficiales -->
                            <div class="space-y-4">
                                <Card
                                    v-for="record in records"
                                    :key="record.id"
                                    class="border border-slate-200 dark:border-slate-800 shadow-xs hover:border-rose-300 transition-all bg-white dark:bg-slate-900 overflow-hidden"
                                >
                                    <CardHeader class="p-4 sm:p-5 pb-3 bg-slate-50/70 dark:bg-slate-900/80 border-b flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs font-black font-mono px-2 py-0.5 rounded bg-rose-900 text-white">
                                                {{ record.course_code }}
                                            </span>
                                            <span class="text-xs font-bold text-slate-700 dark:text-slate-300 flex items-center gap-1">
                                                <Building2 class="size-3 text-slate-400" />
                                                {{ record.institution || 'SIGC-CUSCO' }}
                                            </span>
                                        </div>
                                        <Badge class="text-[11px] font-black bg-rose-100 text-rose-950 border-rose-300 dark:bg-rose-950 dark:text-rose-200">
                                            <CheckCircle2 class="size-3 mr-1 text-rose-800" />
                                            Acreditado Oficialmente
                                        </Badge>
                                    </CardHeader>
                                    <CardContent class="p-4 sm:p-5 space-y-3.5">
                                        <h3 class="text-base sm:text-lg font-bold text-slate-950 dark:text-white leading-snug">
                                            {{ record.course_title }}
                                        </h3>

                                        <!-- Calificación Oficial Acreditada -->
                                        <div class="flex items-center gap-2">
                                            <Badge class="bg-emerald-100 text-emerald-950 dark:bg-emerald-950 dark:text-emerald-200 border-emerald-300 font-bold text-xs py-1 px-3">
                                                <CheckCircle2 class="size-3.5 mr-1.5 text-emerald-700" />
                                                Calificación: {{ record.final_grade }} / 20.00 ({{ record.final_grade_text || 'Aprobado' }})
                                            </Badge>
                                        </div>

                                        <!-- Datos Públicos Relevantes -->
                                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 text-xs">
                                            <div class="p-2.5 rounded-lg bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800">
                                                <div class="text-[10px] text-slate-500 font-bold uppercase tracking-wider mb-0.5">Ponente / Docente</div>
                                                <div class="font-bold text-slate-900 dark:text-white truncate">
                                                    {{ record.instructor_name }}
                                                </div>
                                            </div>
                                            <div class="p-2.5 rounded-lg bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800">
                                                <div class="text-[10px] text-slate-500 font-bold uppercase tracking-wider mb-0.5">Carga Lectiva</div>
                                                <div class="font-bold text-slate-900 dark:text-white flex items-center gap-1">
                                                    <Clock class="size-3 text-amber-600" />
                                                    {{ formatHours(record.hours) }}
                                                </div>
                                            </div>
                                            <div class="p-2.5 rounded-lg bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800">
                                                <div class="text-[10px] text-slate-500 font-bold uppercase tracking-wider mb-0.5">Período</div>
                                                <div class="font-bold text-slate-900 dark:text-white flex items-center gap-1">
                                                    <Calendar class="size-3 text-rose-800" />
                                                    {{ formatDateRange(record.start_date, record.end_date, 'short') }}
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Código, Fecha y Acciones -->
                                        <div class="pt-3 flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-t border-slate-200 dark:border-slate-800">
                                            <div class="flex flex-col gap-0.5 text-xs font-mono text-slate-600 dark:text-slate-400">
                                                <div class="flex items-center gap-2">
                                                    <span class="font-bold text-slate-900 dark:text-slate-200">Cód. Verificación:</span>
                                                    <span class="bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded font-black text-rose-900 dark:text-rose-400 border border-slate-200 dark:border-slate-700">
                                                        {{ record.certificate_code }}
                                                    </span>
                                                </div>
                                                <div class="text-[10px] text-slate-500">
                                                    Expedido: <strong>{{ record.certificate_issued_at || 'Oficial' }}</strong>
                                                </div>
                                            </div>

                                            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full sm:w-auto">
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    class="text-xs font-bold border-slate-300 hover:text-rose-900 hover:bg-rose-50 cursor-pointer h-9 px-3.5 w-full sm:w-auto justify-center"
                                                    @click="openCertificatePreview(record)"
                                                >
                                                    <Eye class="size-3.5 mr-1.5 text-slate-700" />
                                                    Ver Diploma
                                                </Button>
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    class="bg-rose-900 hover:bg-rose-950 text-white font-bold text-xs shadow-xs cursor-pointer h-9 px-3.5 w-full sm:w-auto justify-center"
                                                    @click="printCertificate(record)"
                                                >
                                                    <Download class="size-3.5 mr-1.5" />
                                                    Descargar PDF (2 Páginas)
                                                </Button>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>
                        </template>

                        <!-- Sin registros -->
                        <div v-else class="p-8 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-center space-y-3 shadow-xs">
                            <div class="size-12 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-400 mx-auto flex items-center justify-center">
                                <Award class="size-6" />
                            </div>
                            <h3 class="text-base font-bold text-slate-950 dark:text-white">
                                No se registran certificados oficiales para el DNI {{ dniQuery }}
                            </h3>
                            <p class="text-xs text-slate-600 dark:text-slate-400 max-w-md mx-auto leading-relaxed">
                                Los certificados se publican una vez concluida la capacitación y cerrada el acta oficial de evaluación académica.
                            </p>
                            <div class="pt-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    class="border-slate-300 dark:border-slate-700 font-semibold"
                                    @click="resetSearch"
                                >
                                    <RotateCcw class="size-3.5 mr-1.5" />
                                    Consultar otro DNI
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- VISTA 2: VERIFICACIÓN CRIPTOGRÁFICA DE ARCHIVO PDF -->
                <div v-else class="max-w-3xl mx-auto space-y-4">
                    <PdfIntegrityVerifier :expected-hash="selectedCert?.certificate_hash || records[0]?.certificate_hash" />
                </div>
            </div>
        </div>

    <!-- MODAL DE VISTA PREVIA DEL DIPLOMA OFICIAL (COMPARTIDO - VISOR ESTUDIO A4 LANDSCAPE) -->
    <Dialog v-model:open="isCertModalOpen">
        <DialogContent
            :show-close-button="false"
            class="max-w-7xl w-[96vw] max-h-[96vh] h-[92vh] flex flex-col p-0 overflow-hidden bg-slate-950 text-slate-100 border border-slate-800 shadow-2xl rounded-2xl sm:max-w-none"
        >
            <!-- TOP TOOLBAR INSTITUCIONAL / BARRA DE HERRAMIENTAS ESTILO ESTUDIO -->
            <div class="px-4 sm:px-6 py-3 border-b border-slate-800/90 bg-slate-900/95 backdrop-blur-md shrink-0 flex flex-wrap items-center justify-between gap-3 select-none">
                <!-- Info del Certificado -->
                <div class="flex items-center gap-3 min-w-0">
                    <div class="size-10 rounded-xl bg-gradient-to-tr from-amber-600 via-[#800020] to-[#800020] text-white flex items-center justify-center shadow-xs shrink-0">
                        <Award class="size-5 text-amber-300" />
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-sm font-black text-white tracking-tight">
                                Diploma Oficial Acreditado
                            </span>
                            <Badge class="bg-emerald-950 text-emerald-200 border border-emerald-700/80 font-bold text-[10px] px-2 py-0.5">
                                <CheckCircle2 class="size-3 mr-1 text-emerald-400" />
                                Aprobado • Fe Pública
                            </Badge>
                        </div>
                        <div class="flex items-center gap-2 text-[11px] text-slate-400 mt-0.5">
                            <span class="truncate">UNSAAC • Sistema Integral de Gestión de Capacitaciones</span>
                            <span class="text-slate-600">•</span>
                            <span class="font-mono font-bold text-amber-400">{{ selectedCert?.certificate_code }}</span>
                        </div>
                    </div>
                </div>

                <!-- Selector de Modos de Vista: Anverso / Reverso / Vista Completa -->
                <div class="inline-flex p-1 bg-slate-950 rounded-xl border border-slate-800 shadow-inner">
                    <button
                        type="button"
                        @click="previewTab = 'anverso'"
                        class="flex items-center gap-1.5 px-3 sm:px-4 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer"
                        :class="previewTab === 'anverso' ? 'bg-[#800020] text-white shadow-xs' : 'text-slate-400 hover:text-white'"
                    >
                        <Award class="size-3.5" />
                        <span class="hidden sm:inline">Cara 1:</span>
                        <span>Anverso</span>
                    </button>
                    <button
                        type="button"
                        @click="previewTab = 'reverso'"
                        class="flex items-center gap-1.5 px-3 sm:px-4 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer"
                        :class="previewTab === 'reverso' ? 'bg-[#800020] text-white shadow-xs' : 'text-slate-400 hover:text-white'"
                    >
                        <QrCode class="size-3.5" />
                        <span class="hidden sm:inline">Cara 2:</span>
                        <span>Reverso</span>
                    </button>
                    <button
                        type="button"
                        @click="previewTab = 'completa'"
                        class="flex items-center gap-1.5 px-3 sm:px-4 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer"
                        :class="previewTab === 'completa' ? 'bg-[#800020] text-white shadow-xs' : 'text-slate-400 hover:text-white'"
                    >
                        <Layers class="size-3.5" />
                        <span>Vista Completa (2 Páginas)</span>
                    </button>
                </div>

                <!-- Controles de Zoom y Acciones Rápidas -->
                <div class="flex items-center gap-2">
                    <!-- Zoom Controls -->
                    <div class="hidden md:inline-flex items-center bg-slate-950 rounded-lg border border-slate-800 p-0.5 text-xs text-slate-300">
                        <button
                            type="button"
                            @click="zoomOut"
                            class="p-1.5 hover:bg-slate-800 rounded text-slate-400 hover:text-white cursor-pointer transition-colors"
                            title="Reducir zoom"
                        >
                            <ZoomOut class="size-3.5" />
                        </button>
                        <span class="px-2 font-mono font-bold text-[11px] text-amber-400">{{ zoomScale }}%</span>
                        <button
                            type="button"
                            @click="zoomIn"
                            class="p-1.5 hover:bg-slate-800 rounded text-slate-400 hover:text-white cursor-pointer transition-colors"
                            title="Aumentar zoom"
                        >
                            <ZoomIn class="size-3.5" />
                        </button>
                        <button
                            type="button"
                            @click="resetZoom"
                            class="p-1.5 hover:bg-slate-800 rounded text-slate-400 hover:text-white cursor-pointer transition-colors border-l border-slate-800 ml-0.5"
                            title="Ajuste original 100%"
                        >
                            <RotateCcw class="size-3.5" />
                        </button>
                    </div>

                    <!-- Botón Principal: Imprimir / Descargar PDF Oficial -->
                    <Button
                        size="sm"
                        class="bg-gradient-to-r from-amber-600 via-[#800020] to-[#800020] hover:brightness-110 text-white font-black text-xs shadow-md cursor-pointer px-3.5 sm:px-4 py-2 rounded-xl transition-transform active:scale-95"
                        @click="selectedCert && printCertificate(selectedCert)"
                    >
                        <Printer class="size-3.5 mr-1.5 text-amber-200" />
                        <span>Descargar / Imprimir Diploma PDF</span>
                    </Button>

                    <!-- Botón Cerrar (X) -->
                    <button
                        type="button"
                        @click="isCertModalOpen = false"
                        class="size-9 rounded-xl border border-slate-800 hover:border-rose-900 bg-slate-950 hover:bg-rose-950/60 text-slate-400 hover:text-white flex items-center justify-center transition-all cursor-pointer"
                        title="Cerrar visor de certificado"
                    >
                        <X class="size-4" />
                    </button>
                </div>
            </div>

            <!-- LIENZO / VISOR DEL DOCUMENTO A4 LANDSCAPE -->
            <div
                v-if="selectedCert"
                class="flex-1 min-h-0 overflow-y-auto p-4 sm:p-8 bg-zinc-950 flex flex-col items-center justify-start space-y-8 select-text relative"
                style="overscroll-behavior-y: contain;"
            >
                <!-- Banner Flotante de Verificación QR si corresponde -->
                <div
                    v-if="isVerifiedByQr"
                    class="w-full max-w-5xl p-3 rounded-xl bg-emerald-950/80 border border-emerald-700 text-emerald-200 flex items-center justify-between text-xs font-bold shadow-lg shrink-0"
                >
                    <div class="flex items-center gap-2">
                        <ShieldCheck class="size-4.5 text-emerald-400 shrink-0" />
                        <span>Verificación Criptográfica Exitosa: El certificado digital coincide con los registros institucionales inmutables de la UNSAAC.</span>
                    </div>
                    <Badge variant="outline" class="font-mono text-emerald-300 border-emerald-600 bg-emerald-900/40">
                        VALIDADO
                    </Badge>
                </div>

                <!-- CONTENEDOR CON ESCALADO DE ZOOM -->
                <div
                    class="w-full flex flex-col items-center space-y-8 transition-transform duration-150 origin-top"
                    :style="{ transform: `scale(${zoomScale / 100})`, transformOrigin: 'top center' }"
                >
                    <!-- ========================================== -->
                    <!-- CARA 1: ANVERSO (DIPLOMA DE HONOR OFICIAL) -->
                    <!-- ========================================== -->
                    <div
                        v-if="previewTab === 'anverso' || previewTab === 'completa'"
                        class="w-full max-w-5xl flex flex-col items-center"
                    >
                        <div v-if="previewTab === 'completa'" class="w-full flex items-center justify-between text-xs font-mono font-bold text-slate-400 px-2 mb-2">
                            <span class="text-amber-400 flex items-center gap-1.5">
                                <Award class="size-3.5" />
                                PÁGINA 1 DE 2: DIPLOMA DE HONOR INSTITUCIONAL
                            </span>
                            <span class="text-slate-500 font-sans">FORMATO OFICIAL A4 LANDSCAPE (297mm × 210mm)</span>
                        </div>

                        <!-- LIENZO DE PAPEL A4 LANDSCAPE (ANVERSO) -->
                        <div class="w-full aspect-[297/210] min-h-[580px] sm:min-h-[660px] md:min-h-[720px] bg-[#fefefc] text-slate-950 p-6 sm:p-10 md:p-12 relative flex flex-col justify-between shadow-[0_25px_60px_-15px_rgba(0,0,0,0.7)] border-[8px] sm:border-[10px] border-[#800020] rounded-xs select-text overflow-hidden">
                            <!-- Filete Interior Dorado -->
                            <div class="absolute inset-2 sm:inset-3 border-2 border-[#b45309] pointer-events-none z-10"></div>

                            <!-- Cintas Esquinadas Geométricas Académicas (4 Esquinas) -->
                            <div class="absolute top-0 left-0 size-8 sm:size-11 pointer-events-none z-10">
                                <svg viewBox="0 0 46 46" class="size-full">
                                    <polygon points="0,0 46,0 0,46" fill="#800020" />
                                    <polygon points="0,46 46,0 46,4.5 4.5,46" fill="#b45309" />
                                    <polygon points="0,32 32,0 35,0 0,35" fill="#f59e0b" opacity="0.95" />
                                    <polygon points="0,18 18,0 20,0 0,20" fill="#fef08a" opacity="0.85" />
                                </svg>
                            </div>
                            <div class="absolute top-0 right-0 size-8 sm:size-11 pointer-events-none z-10 scale-x-[-1]">
                                <svg viewBox="0 0 46 46" class="size-full">
                                    <polygon points="0,0 46,0 0,46" fill="#800020" />
                                    <polygon points="0,46 46,0 46,4.5 4.5,46" fill="#b45309" />
                                    <polygon points="0,32 32,0 35,0 0,35" fill="#f59e0b" opacity="0.95" />
                                    <polygon points="0,18 18,0 20,0 0,20" fill="#fef08a" opacity="0.85" />
                                </svg>
                            </div>
                            <div class="absolute bottom-0 left-0 size-8 sm:size-11 pointer-events-none z-10 scale-y-[-1]">
                                <svg viewBox="0 0 46 46" class="size-full">
                                    <polygon points="0,0 46,0 0,46" fill="#800020" />
                                    <polygon points="0,46 46,0 46,4.5 4.5,46" fill="#b45309" />
                                    <polygon points="0,32 32,0 35,0 0,35" fill="#f59e0b" opacity="0.95" />
                                    <polygon points="0,18 18,0 20,0 0,20" fill="#fef08a" opacity="0.85" />
                                </svg>
                            </div>
                            <div class="absolute bottom-0 right-0 size-8 sm:size-11 pointer-events-none z-10 scale-[-1]">
                                <svg viewBox="0 0 46 46" class="size-full">
                                    <polygon points="0,0 46,0 0,46" fill="#800020" />
                                    <polygon points="0,46 46,0 46,4.5 4.5,46" fill="#b45309" />
                                    <polygon points="0,32 32,0 35,0 0,35" fill="#f59e0b" opacity="0.95" />
                                    <polygon points="0,18 18,0 20,0 0,20" fill="#fef08a" opacity="0.85" />
                                </svg>
                            </div>

                            <!-- Marca de Agua Institucional de Fondo -->
                            <div class="absolute inset-0 flex items-center justify-center opacity-[0.035] pointer-events-none select-none z-0">
                                <img src="/images/unsaac-logo.png" alt="Marca de Agua UNSAAC" class="size-80 sm:size-96 object-contain" />
                            </div>

                            <!-- Contenido del Diploma Anverso -->
                            <div class="relative z-20 h-full flex flex-col justify-between text-center">
                                <!-- Cabecera Institucional Oficial con Logos de Alta Resolución -->
                                <div class="flex items-center justify-between border-b-2 border-[#800020] pb-3 mb-2">
                                    <div class="w-20 sm:w-28 flex justify-center shrink-0">
                                        <img src="/images/unsaac-logo.png" alt="UNSAAC Logo" class="h-16 sm:h-20 md:h-24 object-contain filter drop-shadow-xs" />
                                    </div>
                                    <div class="flex-1 px-3 text-center min-w-0">
                                        <div class="text-[10px] sm:text-xs font-black text-[#800020] tracking-[0.25em] uppercase">
                                             REPÚBLICA DEL PERÚ • REGIÓN CUSCO
                                        </div>
                                        <h1 class="text-base sm:text-2xl md:text-[26px] font-black text-slate-950 uppercase tracking-tight font-serif mt-1 leading-tight">
                                            Universidad Nacional de San Antonio Abad del Cusco
                                        </h1>
                                        <div class="text-[9px] sm:text-xs text-slate-700 uppercase tracking-wider font-bold mt-0.5">
                                            Facultad de Ingeniería Eléctrica, Electrónica, Informática y Mecánica
                                        </div>
                                        <div class="inline-block bg-[#800020] text-amber-200 text-[8px] sm:text-[10px] font-black px-3.5 py-0.5 rounded uppercase tracking-widest mt-1.5 shadow-xs">
                                            Sistema Integral de Gestión de Capacitaciones (SIGC-CUSCO)
                                        </div>
                                    </div>
                                    <div class="w-20 sm:w-28 flex justify-center shrink-0">
                                        <img src="/images/escudo.png" alt="Escudo del Perú / UNSAAC" class="h-16 sm:h-20 md:h-24 object-contain filter drop-shadow-xs" />
                                    </div>
                                </div>

                                <!-- Divisor Decorativo Dorado con Diamante Central -->
                                <div class="w-full flex items-center justify-center my-1">
                                    <div class="h-0.5 bg-gradient-to-r from-transparent via-[#b45309] to-transparent w-full"></div>
                                    <div class="px-2 shrink-0">
                                        <span class="size-2 bg-[#b45309] rotate-45 inline-block"></span>
                                    </div>
                                    <div class="h-0.5 bg-gradient-to-r from-transparent via-[#b45309] to-transparent w-full"></div>
                                </div>

                                <!-- Título Principal del Certificado -->
                                <div class="space-y-1 my-1">
                                    <h2 class="text-3xl sm:text-5xl md:text-[54px] font-black text-[#800020] uppercase tracking-[0.25em] font-serif leading-none">
                                        CERTIFICADO
                                    </h2>
                                    <p class="text-[11px] sm:text-xs text-slate-600 font-serif italic tracking-wide">
                                        Por culminación académica satisfactoria y acreditación de competencias profesionales
                                    </p>
                                </div>

                                <!-- Otorgado a: Nombre del Participante -->
                                <div class="my-2 sm:my-3">
                                    <p class="text-[11px] sm:text-xs text-slate-600 uppercase tracking-widest font-serif font-bold">
                                        Conferido en testimonio de honor y mérito académico a:
                                    </p>
                                    <div class="text-xl sm:text-3xl md:text-[34px] font-black text-slate-950 uppercase tracking-tight font-serif py-1 px-8 border-b-2 border-[#800020] inline-block mt-0.5">
                                        {{ selectedCert.student_name }}
                                    </div>
                                    <div class="mt-1.5">
                                        <span class="text-xs sm:text-sm font-mono font-black bg-rose-50 text-[#800020] border border-rose-300 px-4 py-0.5 rounded-sm shadow-2xs inline-block">
                                            D.N.I. N° {{ selectedCert.dni }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Descripción y Datos del Curso -->
                                <div class="text-xs sm:text-sm text-slate-700 max-w-3xl mx-auto leading-relaxed my-1 font-serif">
                                    Por haber aprobado satisfactoriamente con alto rendimiento académico el programa de capacitación en:
                                    <strong class="text-[#800020] block my-1.5 text-base sm:text-xl md:text-2xl font-black font-serif tracking-tight leading-snug">
                                        "{{ selectedCert.course_title }}"
                                    </strong>
                                    desarrollado <strong>{{ selectedCert.date_range_formal || formatSpanishDateRange(selectedCert.start_date, selectedCert.end_date) }}</strong>,
                                    con una carga lectiva de <strong>{{ formatHours(selectedCert.hours) }}</strong>, en cumplimiento de los estándares de acreditación universitaria.
                                </div>

                                <!-- Calificación Oficial Obtenida -->
                                <div class="my-1.5 inline-flex items-center gap-2 bg-gradient-to-r from-amber-50 to-emerald-50 border border-emerald-400 px-4 py-1 rounded-full text-xs sm:text-sm font-black text-slate-900 shadow-2xs mx-auto">
                                    <span class="text-slate-600 font-serif">Calificación Obtenida:</span>
                                    <span class="text-emerald-700 text-sm sm:text-base font-black">{{ selectedCert.final_grade }} / 20.00</span>
                                    <span class="text-slate-600 font-semibold font-serif">({{ selectedCert.final_grade_text || 'Sobresaliente' }})</span>
                                </div>

                                <!-- Lugar y Fecha de Emisión Formal -->
                                <div class="text-right text-[11px] sm:text-xs font-serif font-bold text-slate-600 italic pr-6 sm:pr-10 my-1">
                                    {{ selectedCert.city_issued_formal || ('Cusco, ' + formatSpanishDate(selectedCert.certificate_issued_at || selectedCert.end_date)) }}
                                </div>

                                <!-- Firmas Digitales con Sellos Redondos Oficiales -->
                                <div class="pt-3 grid grid-cols-2 gap-8 text-xs border-t border-slate-300 mt-2">
                                    <!-- Firma Docente Responsable -->
                                    <div class="text-center relative flex flex-col items-center">
                                        <div class="size-20 flex items-center justify-center -mb-2" v-html="DOCENTE_STAMP_SVG"></div>
                                        <div class="w-48 sm:w-60 border-t border-slate-800 pt-1">
                                            <div class="font-black text-slate-900 uppercase font-serif text-xs sm:text-sm">{{ selectedCert.instructor_name }}</div>
                                            <div class="text-[10px] text-slate-500 uppercase font-serif">{{ selectedCert.instructor_title || 'Docente Responsable / Ponente' }}</div>
                                        </div>
                                    </div>
                                    <!-- Firma Dirección Académica -->
                                    <div class="text-center relative flex flex-col items-center">
                                        <div class="size-20 flex items-center justify-center -mb-2" v-html="DIRECCION_STAMP_SVG"></div>
                                        <div class="w-48 sm:w-60 border-t border-slate-800 pt-1">
                                            <div class="font-black text-slate-900 uppercase font-serif text-xs sm:text-sm">Dirección Académica</div>
                                            <div class="text-[10px] text-slate-500 uppercase font-serif">Coordinación General SIGC • UNSAAC</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Pie de Página con Código de Verificación Oficial -->
                                <div class="pt-2 text-[10px] font-mono text-slate-500 flex justify-between items-center border-t border-dashed border-slate-300 mt-2">
                                    <span>Cód. Verificación: <strong class="text-slate-900">{{ selectedCert.certificate_code }}</strong></span>
                                    <span>Fecha de Emisión: <strong>{{ selectedCert.issued_date_formal || formatSpanishDate(selectedCert.certificate_issued_at || selectedCert.end_date) }}</strong></span>
                                    <span class="text-[#800020] font-black">REGISTRO OFICIAL DE FE PÚBLICA • CUSCO, PERÚ</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ======================================================== -->
                    <!-- CARA 2: REVERSO (PLAN CURRICULAR, EVALUACIÓN Y QR OFICIAL) -->
                    <!-- ======================================================== -->
                    <div
                        v-if="previewTab === 'reverso' || previewTab === 'completa'"
                        class="w-full max-w-5xl flex flex-col items-center"
                    >
                        <div v-if="previewTab === 'completa'" class="w-full flex items-center justify-between text-xs font-mono font-bold text-slate-400 px-2 mb-2">
                            <span class="text-amber-400 flex items-center gap-1.5">
                                <QrCode class="size-3.5" />
                                PÁGINA 2 DE 2: PLAN CURRICULAR, CALIFICACIÓN Y VERIFICACIÓN QR
                            </span>
                            <span class="text-slate-500 font-sans">FORMATO OFICIAL A4 LANDSCAPE (297mm × 210mm)</span>
                        </div>

                        <!-- LIENZO DE PAPEL A4 LANDSCAPE (REVERSO) -->
                        <div class="w-full aspect-[297/210] min-h-[580px] sm:min-h-[660px] md:min-h-[720px] bg-[#fefefc] text-slate-950 p-6 sm:p-10 md:p-12 relative flex flex-col justify-between shadow-[0_25px_60px_-15px_rgba(0,0,0,0.7)] border-[8px] sm:border-[10px] border-[#800020] rounded-xs select-text overflow-hidden">
                            <!-- Filete Interior Dorado -->
                            <div class="absolute inset-2 sm:inset-3 border-2 border-[#b45309] pointer-events-none z-10"></div>

                            <!-- Cintas Esquinadas Geométricas Académicas (4 Esquinas) -->
                            <div class="absolute top-0 left-0 size-8 sm:size-11 pointer-events-none z-10">
                                <svg viewBox="0 0 46 46" class="size-full">
                                    <polygon points="0,0 46,0 0,46" fill="#800020" />
                                    <polygon points="0,46 46,0 46,4.5 4.5,46" fill="#b45309" />
                                    <polygon points="0,32 32,0 35,0 0,35" fill="#f59e0b" opacity="0.95" />
                                    <polygon points="0,18 18,0 20,0 0,20" fill="#fef08a" opacity="0.85" />
                                </svg>
                            </div>
                            <div class="absolute top-0 right-0 size-8 sm:size-11 pointer-events-none z-10 scale-x-[-1]">
                                <svg viewBox="0 0 46 46" class="size-full">
                                    <polygon points="0,0 46,0 0,46" fill="#800020" />
                                    <polygon points="0,46 46,0 46,4.5 4.5,46" fill="#b45309" />
                                    <polygon points="0,32 32,0 35,0 0,35" fill="#f59e0b" opacity="0.95" />
                                    <polygon points="0,18 18,0 20,0 0,20" fill="#fef08a" opacity="0.85" />
                                </svg>
                            </div>
                            <div class="absolute bottom-0 left-0 size-8 sm:size-11 pointer-events-none z-10 scale-y-[-1]">
                                <svg viewBox="0 0 46 46" class="size-full">
                                    <polygon points="0,0 46,0 0,46" fill="#800020" />
                                    <polygon points="0,46 46,0 46,4.5 4.5,46" fill="#b45309" />
                                    <polygon points="0,32 32,0 35,0 0,35" fill="#f59e0b" opacity="0.95" />
                                    <polygon points="0,18 18,0 20,0 0,20" fill="#fef08a" opacity="0.85" />
                                </svg>
                            </div>
                            <div class="absolute bottom-0 right-0 size-8 sm:size-11 pointer-events-none z-10 scale-[-1]">
                                <svg viewBox="0 0 46 46" class="size-full">
                                    <polygon points="0,0 46,0 0,46" fill="#800020" />
                                    <polygon points="0,46 46,0 46,4.5 4.5,46" fill="#b45309" />
                                    <polygon points="0,32 32,0 35,0 0,35" fill="#f59e0b" opacity="0.95" />
                                    <polygon points="0,18 18,0 20,0 0,20" fill="#fef08a" opacity="0.85" />
                                </svg>
                            </div>

                            <!-- Contenido del Diploma Reverso -->
                            <div class="relative z-20 h-full flex flex-col justify-between text-left">
                                <!-- Cabecera del Reverso -->
                                <div class="border-b-2 border-[#800020] pb-2.5 mb-3 flex items-center justify-between">
                                    <div>
                                        <div class="text-[10px] font-black text-[#800020] uppercase tracking-wider">
                                            UNIVERSIDAD NACIONAL DE SAN ANTONIO ABAD DEL CUSCO • SIGC
                                        </div>
                                        <h3 class="text-base sm:text-xl font-black text-slate-950 uppercase font-serif mt-0.5">
                                            Plan Curricular y Registro Oficial de Calificación
                                        </h3>
                                        <p class="text-xs text-slate-600">
                                            Programa: <strong class="text-slate-900">{{ selectedCert.course_title }}</strong> (Código: {{ selectedCert.course_code }})
                                        </p>
                                    </div>
                                    <div class="hidden sm:block text-right font-mono text-[11px] text-slate-500">
                                        <div>Cód: <strong>{{ selectedCert.certificate_code }}</strong></div>
                                        <div>Horas: <strong>{{ selectedCert.hours }} hrs. lectivas</strong></div>
                                    </div>
                                </div>

                                <!-- Estructura en 2 Columnas: Módulos a la Izquierda vs Evaluación/QR a la Derecha -->
                                <div class="grid grid-cols-1 md:grid-cols-12 gap-5 flex-1 min-h-0">
                                    <!-- Columna Izquierda: Temario y Módulos Curriculares (7 cols) -->
                                    <div class="md:col-span-7 flex flex-col justify-between space-y-2">
                                        <div class="text-xs font-black text-[#800020] uppercase tracking-wider flex items-center justify-between pb-1 border-b border-slate-200">
                                            <span class="flex items-center gap-1.5">
                                                <BookOpen class="size-3.5" />
                                                Módulos Académicos Acreditados
                                            </span>
                                            <span class="text-[10px] font-mono text-slate-500">{{ selectedCert.hours }} Horas Lectivas</span>
                                        </div>

                                        <div class="space-y-2 overflow-y-auto max-h-[300px] pr-1">
                                            <div
                                                v-for="(module, index) in selectedCert.modules"
                                                :key="index"
                                                class="p-2.5 rounded-lg bg-slate-50 border border-slate-200 border-l-4 border-l-[#800020]"
                                            >
                                                <div class="flex items-center justify-between text-xs font-bold text-slate-950">
                                                    <span class="text-[#800020] font-black text-[11px] mr-2">{{ module.number }}</span>
                                                    <span class="flex-1 truncate font-serif">{{ module.title }}</span>
                                                    <span class="text-[10px] font-mono bg-white px-2 py-0.5 rounded border border-slate-200 text-slate-600">
                                                        {{ module.hours }}
                                                    </span>
                                                </div>
                                                <p class="text-[11px] text-slate-600 mt-1 leading-relaxed">
                                                    {{ module.topics }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Columna Derecha: Calificación, Docente, QR y Criptografía (5 cols) -->
                                    <div class="md:col-span-5 flex flex-col justify-between space-y-3">
                                        <!-- Cuadro de Calificación Oficial -->
                                        <div class="p-3.5 rounded-xl bg-emerald-50 border border-emerald-300 flex items-center justify-between">
                                            <div>
                                                <div class="text-[10px] font-bold text-emerald-800 uppercase">
                                                    Registro de Calificación
                                                </div>
                                                <div class="text-2xl font-black text-emerald-950">
                                                    {{ selectedCert.final_grade }} / 20.00
                                                </div>
                                                <div class="text-[10px] font-bold text-emerald-700">
                                                    {{ selectedCert.final_grade_text || 'Sobresaliente' }}
                                                </div>
                                            </div>
                                            <Badge class="bg-emerald-700 text-white font-black text-xs py-1 px-2.5">
                                                ✓ APROBADO
                                            </Badge>
                                        </div>

                                        <!-- Docente Responsable -->
                                        <div class="p-3 rounded-xl bg-slate-50 border border-slate-200">
                                            <div class="text-[10px] font-bold text-slate-500 uppercase">Docente / Ponente Responsable:</div>
                                            <div class="text-xs font-black text-slate-950 uppercase mt-0.5">
                                                {{ selectedCert.instructor_name }}
                                            </div>
                                            <div class="text-[10px] text-slate-600">
                                                {{ selectedCert.instructor_title || 'Docente Responsable' }}
                                            </div>
                                            <div class="text-[9px] text-slate-500 mt-0.5">
                                                {{ selectedCert.institution || 'Universidad Nacional de San Antonio Abad del Cusco' }}
                                            </div>
                                        </div>

                                        <!-- Código QR Permanente de Validación -->
                                        <div class="p-3 rounded-xl bg-white border-2 border-[#800020] flex items-center gap-3">
                                            <div class="size-20 bg-white border border-slate-200 rounded-lg p-1 shrink-0 flex items-center justify-center">
                                                <div v-html="selectedCert.qr_svg" class="size-full [&>svg]:size-full"></div>
                                            </div>
                                            <div class="flex-1 min-w-0 space-y-0.5">
                                                <div class="text-xs font-black text-[#800020] uppercase flex items-center gap-1">
                                                    <QrCode class="size-3.5 shrink-0" />
                                                    <span>Validación QR en Línea</span>
                                                </div>
                                                <p class="text-[10px] text-slate-600 leading-tight">
                                                    Escanee el código QR para verificar la autenticidad e integridad del certificado en tiempo real.
                                                </p>
                                                <a
                                                    :href="selectedCert.verification_url"
                                                    target="_blank"
                                                    class="inline-flex items-center gap-1 font-mono text-[9px] text-blue-700 hover:underline truncate max-w-full font-bold"
                                                >
                                                    <ExternalLink class="size-2.5 shrink-0" />
                                                    <span class="truncate">{{ selectedCert.verification_url }}</span>
                                                </a>
                                            </div>
                                        </div>

                                        <!-- Huella Criptográfica SHA-256 -->
                                        <div v-if="selectedCert.certificate_hash" class="p-2 rounded-lg bg-slate-50 border border-slate-200 text-[9px] font-mono">
                                            <div class="flex items-center justify-between text-slate-600 font-bold mb-0.5">
                                                <span class="flex items-center gap-1 text-[#800020]">
                                                    <ShieldCheck class="size-3" />
                                                    Huella SHA-256:
                                                </span>
                                                <span class="text-[8px] text-slate-400">Inmutable</span>
                                            </div>
                                            <div class="text-slate-800 break-all select-all font-semibold leading-tight">
                                                {{ selectedCert.certificate_hash }}
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Pie del Reverso -->
                                <div class="pt-2 text-[10px] font-mono text-slate-500 flex justify-between items-center border-t border-dashed border-slate-300 mt-2">
                                    <span>Certificado: <strong>{{ selectedCert.certificate_code }}</strong></span>
                                    <span>Registro en Actas: <strong>{{ selectedCert.issued_date_formal || formatSpanishDate(selectedCert.certificate_issued_at || selectedCert.end_date) }}</strong></span>
                                    <span class="text-[#800020] font-black">CUSCO, REPÚBLICA DEL PERÚ • CERTIFICACIÓN OFICIAL</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- FOOTER FIJO CON ACCIONES ESTILO ESTUDIO -->
            <div class="flex flex-col sm:flex-row items-center justify-between gap-3 px-4 sm:px-6 py-3 border-t border-slate-800 bg-slate-900/95 shrink-0 select-none">
                <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="text-xs font-bold border-slate-700 bg-slate-800/80 hover:bg-slate-700 text-slate-200 cursor-pointer rounded-xl flex-1 sm:flex-initial justify-center"
                        @click="selectedCert && copyVerificationCode(selectedCert.certificate_code)"
                    >
                        <Check v-if="copiedCode" class="size-3.5 mr-1 text-emerald-400" />
                        <Copy v-else class="size-3.5 mr-1 text-slate-400" />
                        <span>{{ copiedCode ? '¡Código Copiado!' : 'Copiar Código' }}</span>
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="text-xs font-bold border-slate-700 bg-slate-800/80 hover:bg-slate-700 text-slate-200 cursor-pointer rounded-xl flex-1 sm:flex-initial justify-center"
                        @click="selectedCert && copyVerificationUrl(selectedCert.verification_url)"
                    >
                        <Check v-if="copiedUrl" class="size-3.5 mr-1 text-emerald-400" />
                        <Share2 v-else class="size-3.5 mr-1 text-slate-400" />
                        <span>{{ copiedUrl ? '¡Enlace Copiado!' : 'Copiar QR' }}</span>
                    </Button>
                </div>

                <div class="flex flex-wrap items-center gap-2.5 w-full sm:w-auto justify-end">
                    <Button
                        variant="ghost"
                        size="sm"
                        class="text-xs text-slate-400 hover:text-white hover:bg-slate-800 cursor-pointer rounded-xl px-3"
                        @click="isCertModalOpen = false"
                    >
                        Cerrar
                    </Button>
                    <Button
                        size="sm"
                        class="bg-gradient-to-r from-amber-600 via-[#800020] to-[#800020] hover:brightness-110 text-white font-black text-xs shadow-md cursor-pointer rounded-xl px-4 py-2 w-full sm:w-auto justify-center"
                        @click="selectedCert && printCertificate(selectedCert)"
                    >
                        <Printer class="size-3.5 mr-1.5 text-amber-200" />
                        <span>Imprimir / PDF (2 Páginas)</span>
                    </Button>
                </div>
            </div>
        </DialogContent>
    </Dialog>

    <!-- MODAL DE AYUDA VISUAL: DÓNDE UBICAR EL CÓDIGO DE VERIFICACIÓN DEL DNI -->
    <Dialog v-model:open="showHelpModal">
        <DialogContent class="max-w-md w-[92vw] p-5 sm:p-6 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-2xl">
            <DialogHeader class="space-y-1.5 text-left border-b pb-3">
                <div class="flex items-center gap-2 text-xs font-bold text-rose-900 dark:text-rose-300">
                    <HelpCircle class="size-4" />
                    <span>Guía Oficial RENIEC • Identidad Nacional</span>
                </div>
                <DialogTitle class="text-base sm:text-lg font-black text-slate-950 dark:text-white">
                    ¿Dónde encuentro el Código de Verificación?
                </DialogTitle>
                <DialogDescription class="text-xs text-slate-600 dark:text-slate-400">
                    El código verificador es un único dígito que valida la autenticidad física de su documento.
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-3.5 py-3 text-xs">
                <!-- DNI Azul -->
                <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/60 space-y-1.5">
                    <div class="font-bold text-slate-900 dark:text-white flex items-center justify-between">
                        <span>1. DNI Azul Convencional</span>
                        <Badge variant="outline" class="text-[10px] font-mono border-blue-400 text-blue-800 bg-blue-50/60">DNI Azul</Badge>
                    </div>
                    <p class="text-slate-600 dark:text-slate-300 leading-relaxed text-[11px]">
                        En la esquina superior derecha, inmediatamente después del guion:
                        <span class="font-mono font-black text-rose-950 dark:text-amber-300 bg-rose-100 dark:bg-rose-950/60 px-1.5 py-0.5 rounded border border-rose-300">00000000 - [X]</span>.
                    </p>
                </div>

                <!-- DNI Electrónico DNIe -->
                <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/60 space-y-1.5">
                    <div class="font-bold text-slate-900 dark:text-white flex items-center justify-between">
                        <span>2. DNI Electrónico (DNIe)</span>
                        <Badge variant="outline" class="text-[10px] font-mono border-emerald-400 text-emerald-800 bg-emerald-50/60">DNIe</Badge>
                    </div>
                    <p class="text-slate-600 dark:text-slate-300 leading-relaxed text-[11px]">
                        En el anverso (cara principal), al lado derecho del número de DNI dentro de un recuadro sombreado independiente.
                    </p>
                </div>

                <!-- DNI Amarillo -->
                <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/60 space-y-1.5">
                    <div class="font-bold text-slate-900 dark:text-white flex items-center justify-between">
                        <span>3. DNI Amarillo (Menores)</span>
                        <Badge variant="outline" class="text-[10px] font-mono border-amber-400 text-amber-800 bg-amber-50/60">Menores</Badge>
                    </div>
                    <p class="text-slate-600 dark:text-slate-300 leading-relaxed text-[11px]">
                        En la parte superior derecha, junto a los 8 dígitos numéricos del documento.
                    </p>
                </div>
            </div>

            <DialogFooter class="pt-2 border-t">
                <Button
                    type="button"
                    class="w-full bg-rose-900 hover:bg-rose-950 text-white font-bold text-xs h-10 rounded-xl cursor-pointer shadow-xs"
                    @click="showHelpModal = false"
                >
                    Entendido, ya identifiqué mi código
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    </AppLayout>
</template>
