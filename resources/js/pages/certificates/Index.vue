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
} from '@lucide/vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
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
import PeruGeoBadge from '@/components/PeruGeoBadge.vue';
import LoginModal from '@/components/auth/LoginModal.vue';
import RegisterModal from '@/components/auth/RegisterModal.vue';
import PdfIntegrityVerifier from '@/components/PdfIntegrityVerifier.vue';
import { formatDateRange, formatHours } from '@/lib/formatters';
import type { BreadcrumbItem } from '@/types';

export interface CertificateRecord {
    id: number;
    course_code: string;
    course_title: string;
    institution: string;
    hours: number;
    start_date: string;
    end_date: string;
    instructor_name: string;
    student_name: string;
    status: string;
    certificate_code: string;
    certificate_hash?: string | null;
    certificate_issued_at?: string | null;
}

const props = defineProps<{
    initialDni?: string;
}>();

const page = usePage();
const authUser = computed(() => (page.props.auth as any)?.user);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Panel Principal', href: '/dashboard' },
    { title: 'Validación de Certificados', href: '/certificates' },
];

// Pestaña activa: 'dni' para consulta por documento | 'pdf' para validación de archivo PDF
const activeTab = ref<'dni' | 'pdf'>('dni');

const dniQuery = ref(props.initialDni || '');
const loading = ref(false);
const searched = ref(false);
const error = ref<string | null>(null);
const studentName = ref<string | null>(null);
const records = ref<CertificateRecord[]>([]);

// Modal de Vista Previa del Diploma
const selectedCert = ref<CertificateRecord | null>(null);
const isCertModalOpen = ref(false);
const copiedCode = ref(false);

// Modales de Autenticación para invitados
const isLoginModalOpen = ref(false);
const isRegisterModalOpen = ref(false);

function switchToRegister() {
    isLoginModalOpen.value = false;
    isRegisterModalOpen.value = true;
}

function switchToLogin() {
    isRegisterModalOpen.value = false;
    isLoginModalOpen.value = true;
}

function onDniInput(e: Event) {
    const target = e.target as HTMLInputElement;
    dniQuery.value = target.value.replace(/\D/g, '').slice(0, 8);
    if (error.value) error.value = null;
}

async function searchCertificates() {
    const clean = dniQuery.value.trim();
    if (!/^\d{8}$/.test(clean)) {
        error.value = 'Ingrese un número de DNI válido de exactamente 8 dígitos.';
        return;
    }

    loading.value = true;
    error.value = null;
    records.value = [];
    searched.value = true;

    try {
        const response = await fetch(`/api/certificates/lookup?dni=${clean}`, {
            headers: {
                Accept: 'application/json',
            },
        });

        const data = await response.json();

        if (response.ok && data.success) {
            records.value = data.records || [];
            if (records.value.length > 0) {
                studentName.value = records.value[0].student_name;
            } else {
                studentName.value = null;
            }
        } else {
            error.value = data.message || 'No se pudo consultar el registro de certificados.';
        }
    } catch {
        error.value = 'Error al conectarse con el servidor de validación. Intente nuevamente.';
    } finally {
        loading.value = false;
    }
}

function openCertificatePreview(record: CertificateRecord) {
    selectedCert.value = record;
    isCertModalOpen.value = true;
}

async function copyVerificationCode(code: string) {
    try {
        await navigator.clipboard.writeText(code);
        copiedCode.value = true;
        setTimeout(() => {
            copiedCode.value = false;
        }, 2000);
    } catch {
        // Fallback
    }
}

/**
 * Impresión / Descarga a PDF fiable del Diploma Oficial
 */
function printCertificate(record: CertificateRecord) {
    const html = `
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <title>Certificado Oficial - ${record.course_title}</title>
            <style>
                @page {
                    size: A4 landscape;
                    margin: 10mm;
                }
                * {
                    box-sizing: border-box;
                    margin: 0;
                    padding: 0;
                }
                body {
                    font-family: 'Times New Roman', serif;
                    background: #ffffff;
                    color: #1a1a1a;
                    padding: 20px;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    min-height: 100vh;
                }
                .cert-container {
                    width: 100%;
                    max-width: 960px;
                    background: #ffffff;
                    border: 8px double #800020;
                    padding: 40px 50px;
                    position: relative;
                    text-align: center;
                }
                .cert-container::before {
                    content: "";
                    position: absolute;
                    top: 8px;
                    left: 8px;
                    right: 8px;
                    bottom: 8px;
                    border: 1.5px solid #d97706;
                    pointer-events: none;
                }
                .header-inst {
                    font-size: 13px;
                    font-weight: bold;
                    letter-spacing: 2px;
                    text-transform: uppercase;
                    color: #800020;
                    margin-bottom: 4px;
                }
                .sub-inst {
                    font-size: 11px;
                    color: #4b5563;
                    letter-spacing: 1px;
                    text-transform: uppercase;
                    margin-bottom: 25px;
                }
                .title-cert {
                    font-size: 34px;
                    font-weight: bold;
                    letter-spacing: 3px;
                    color: #800020;
                    text-transform: uppercase;
                    margin-bottom: 8px;
                }
                .subtitle-cert {
                    font-size: 14px;
                    color: #6b7280;
                    font-style: italic;
                    margin-bottom: 25px;
                }
                .otorgado {
                    font-size: 13px;
                    text-transform: uppercase;
                    color: #4b5563;
                    letter-spacing: 1px;
                    margin-bottom: 8px;
                }
                .recipient-name {
                    font-size: 26px;
                    font-weight: bold;
                    color: #0f172a;
                    text-transform: uppercase;
                    padding: 6px 30px;
                    border-bottom: 2px solid #800020;
                    display: inline-block;
                    margin-bottom: 20px;
                }
                .body-text {
                    font-size: 15px;
                    line-height: 1.8;
                    color: #374151;
                    max-width: 800px;
                    margin: 0 auto 35px auto;
                }
                .body-text strong {
                    color: #0f172a;
                }
                .signatures {
                    display: flex;
                    justify-content: space-around;
                    margin-top: 40px;
                    padding-top: 20px;
                }
                .sign-box {
                    width: 220px;
                    text-align: center;
                    border-top: 1px solid #9ca3af;
                    padding-top: 8px;
                }
                .sign-name {
                    font-weight: bold;
                    font-size: 13px;
                    color: #111827;
                }
                .sign-role {
                    font-size: 11px;
                    color: #6b7280;
                    text-transform: uppercase;
                }
                .footer-meta {
                    margin-top: 30px;
                    display: flex;
                    justify-content: space-between;
                    font-size: 10px;
                    font-family: monospace;
                    color: #6b7280;
                    border-top: 1px dashed #e5e7eb;
                    padding-top: 10px;
                }
                .badge-valid {
                    color: #800020;
                    font-weight: bold;
                }
            </style>
        </head>
        <body>
            <div class="cert-container">
                <div class="header-inst">REPÚBLICA DEL PERÚ • REGIÓN CUSCO</div>
                <div class="sub-inst">SISTEMA INTEGRAL DE GESTIÓN DE CAPACITACIONES (SIGC-CUSCO / UNSAAC)</div>

                <div class="title-cert">CERTIFICADO OFICIAL</div>
                <div class="subtitle-cert">Por culminación académica satisfactoria y acreditación oficial</div>

                <div class="otorgado">Conferido en testimonio de honor a:</div>
                <div class="recipient-name">${record.student_name}</div>

                <div class="body-text">
                    Por haber participado y completado satisfactoriamente el programa de capacitación oficial
                    <strong>"${record.course_title}"</strong>, con un total de <strong>${formatHours(record.hours)}</strong> lectivas,
                    desarrollado bajo las normas académicas oficiales de fe pública.
                </div>

                <div class="signatures">
                    <div class="sign-box">
                        <div class="sign-name">${record.instructor_name}</div>
                        <div class="sign-role">Ponente / Docente Responsable</div>
                    </div>
                    <div class="sign-box">
                        <div class="sign-name">Dirección Académica</div>
                        <div class="sign-role">Coordinación General SIGC-CUSCO</div>
                    </div>
                </div>

                <div class="footer-meta">
                    <span>Código de Registro: <strong>${record.certificate_code}</strong></span>
                    <span>Fecha de Emisión: <strong>${record.certificate_issued_at || 'Oficial'}</strong></span>
                    <span class="badge-valid">VÁLIDO OFICIALMENTE • CUSCO, PERÚ</span>
                </div>
            </div>
        </body>
        </html>
    `;

    const iframe = document.createElement('iframe');
    iframe.style.position = 'fixed';
    iframe.style.right = '0';
    iframe.style.bottom = '0';
    iframe.style.width = '0';
    iframe.style.height = '0';
    iframe.style.border = '0';
    document.body.appendChild(iframe);

    const doc = iframe.contentWindow?.document;
    if (!doc) return;

    doc.open();
    doc.write(html);
    doc.close();

    setTimeout(() => {
        iframe.contentWindow?.focus();
        iframe.contentWindow?.print();
        setTimeout(() => {
            if (document.body.contains(iframe)) {
                document.body.removeChild(iframe);
            }
        }, 3000);
    }, 350);
}

onMounted(() => {
    const params = new URLSearchParams(window.location.search);
    const dniParam = params.get('dni') || props.initialDni;
    if (dniParam && /^\d{8}$/.test(dniParam.trim())) {
        dniQuery.value = dniParam.trim();
        searchCertificates();
    }
});
</script>

<template>
    <!-- CASO 1: USUARIO AUTENTICADO (MANTIENE AL 100% SU NAVBAR VERIFICADO APPLAYOUT) -->
    <AppLayout v-if="authUser" :breadcrumbs="breadcrumbs">
        <Head title="Validación Oficial de Certificados - SIGC-CUSCO" />

        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8 space-y-8">
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
                        <Link href="/dashboard">
                            <ArrowLeft class="size-3.5 mr-1 text-rose-800" />
                            Volver al Panel
                        </Link>
                    </Button>
                </div>
            </div>

            <!-- CONTENIDO MODULAR DE CERTIFICACIÓN -->
            <div class="space-y-6">
                <!-- SELECTOR DE MODALIDAD (TABS LIMPIOS) -->
                <div class="flex items-center justify-center">
                    <div class="inline-flex p-1 bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl">
                        <button
                            type="button"
                            @click="activeTab = 'dni'"
                            class="flex items-center gap-2 px-5 py-2 rounded-lg text-xs font-black transition-all cursor-pointer"
                            :class="activeTab === 'dni' ? 'bg-rose-900 text-white shadow-xs' : 'text-slate-700 dark:text-slate-300 hover:text-rose-900'"
                        >
                            <Search class="size-3.5" />
                            <span>Búsqueda Oficial por DNI</span>
                        </button>
                        <button
                            type="button"
                            @click="activeTab = 'pdf'"
                            class="flex items-center gap-2 px-5 py-2 rounded-lg text-xs font-black transition-all cursor-pointer"
                            :class="activeTab === 'pdf' ? 'bg-rose-900 text-white shadow-xs' : 'text-slate-700 dark:text-slate-300 hover:text-rose-900'"
                        >
                            <FileCheck class="size-3.5 text-amber-500" />
                            <span>Comprobador de Archivo PDF (SHA-256)</span>
                        </button>
                    </div>
                </div>

                <!-- VISTA 1: BÚSQUEDA POR DNI -->
                <div v-if="activeTab === 'dni'" class="space-y-6">
                    <!-- Tarjeta de Búsqueda -->
                    <Card class="max-w-2xl mx-auto border border-rose-900/20 dark:border-rose-900/40 shadow-sm bg-white dark:bg-slate-900 overflow-hidden">
                        <CardHeader class="bg-gradient-to-r from-rose-50/80 via-white to-amber-50/20 dark:from-rose-950/30 dark:to-slate-900 border-b pb-3.5">
                            <div class="flex items-center gap-2 text-xs font-black text-rose-900 dark:text-rose-300 uppercase tracking-wider">
                                <Search class="size-4 text-rose-800" />
                                <span>Consulta Pública Oficial</span>
                            </div>
                            <CardTitle class="text-base sm:text-lg font-bold text-slate-950 dark:text-white">
                                Ingrese el DNI del Participante
                            </CardTitle>
                            <CardDescription class="text-xs text-slate-600 dark:text-slate-400">
                                Valida las capacitaciones concluidas y diplomas oficiales emitidos por el sistema.
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="p-5 sm:p-6 space-y-4">
                            <form @submit.prevent="searchCertificates" class="flex flex-col sm:flex-row gap-3">
                                <div class="relative flex-1">
                                    <Search class="absolute left-3.5 top-1/2 -translate-y-1/2 size-4 text-slate-400" />
                                    <Input
                                        :value="dniQuery"
                                        @input="onDniInput"
                                        type="text"
                                        inputmode="numeric"
                                        maxlength="8"
                                        placeholder="Ingresa DNI (8 dígitos)"
                                        class="pl-10 font-mono text-base tracking-widest font-bold text-slate-950 dark:text-white border-slate-300 focus-visible:ring-rose-900 h-11"
                                        :disabled="loading"
                                        autofocus
                                    />
                                    <div class="absolute right-3.5 top-1/2 -translate-y-1/2 text-[11px] font-mono font-bold text-slate-400">
                                        {{ dniQuery.length }}/8
                                    </div>
                                </div>
                                <Button
                                    type="submit"
                                    :disabled="loading || dniQuery.length !== 8"
                                    class="bg-rose-900 hover:bg-rose-950 text-white font-bold h-11 px-6 shadow-xs shrink-0 text-xs sm:text-sm cursor-pointer"
                                >
                                    <Loader2 v-if="loading" class="size-4 mr-2 animate-spin" />
                                    <Search v-else class="size-4 mr-2" />
                                    <span>Consultar Acreditación</span>
                                </Button>
                            </form>

                            <div v-if="error" class="p-3 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900 text-rose-900 dark:text-rose-200 text-xs font-bold flex items-center gap-2">
                                <AlertCircle class="size-4 shrink-0 text-rose-700" />
                                <span>{{ error }}</span>
                            </div>
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
                                <Badge variant="outline" class="border-rose-300 text-rose-900 dark:border-rose-800 dark:text-rose-300 font-bold text-xs py-1 px-3 bg-rose-50 dark:bg-rose-950">
                                    <Award class="size-3.5 mr-1 text-rose-800" />
                                    {{ records.length }} {{ records.length === 1 ? 'Certificado Oficial' : 'Certificados Oficiales' }}
                                </Badge>
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

                                        <!-- Datos Públicos Relevantes (SIN ASISTENCIA NI NOTAS INTERNAS) -->
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

                                            <div class="flex items-center gap-2">
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    class="text-xs font-bold border-slate-300 hover:text-rose-900 hover:bg-rose-50 cursor-pointer h-9 px-3.5"
                                                    @click="openCertificatePreview(record)"
                                                >
                                                    <Eye class="size-3.5 mr-1.5 text-slate-700" />
                                                    Ver Diploma
                                                </Button>
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    class="bg-rose-900 hover:bg-rose-950 text-white font-bold text-xs shadow-xs cursor-pointer h-9 px-3.5"
                                                    @click="printCertificate(record)"
                                                >
                                                    <Download class="size-3.5 mr-1.5" />
                                                    Descargar PDF
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
                        </div>
                    </div>
                </div>

                <!-- VISTA 2: VERIFICACIÓN CRIPTOGRÁFICA DE ARCHIVO PDF -->
                <div v-else class="max-w-3xl mx-auto space-y-4">
                    <PdfIntegrityVerifier :expected-hash="selectedCert?.certificate_hash || records[0]?.certificate_hash" />
                </div>
            </div>
        </div>
    </AppLayout>

    <!-- CASO 2: VISITANTE PÚBLICO (CABECERA INSTITUCIONAL LIMPIA Y ELEGANTE) -->
    <div v-else class="min-h-screen flex flex-col justify-between bg-slate-50/70 dark:bg-slate-950 text-slate-900 dark:text-slate-100 antialiased selection:bg-rose-900 selection:text-white">
        <Head title="Acreditación Digital Oficial • Consulta de Certificados - SIGC-CUSCO" />

        <!-- TOP INSTITUTIONAL GUEST HEADER -->
        <header class="sticky top-0 z-40 w-full border-b border-slate-200/80 dark:border-slate-800 bg-white/95 dark:bg-slate-950/90 backdrop-blur-md shadow-xs">
            <div class="max-w-7xl mx-auto flex h-16 items-center justify-between px-4 sm:px-6 lg:px-8 gap-4">
                <div class="flex items-center gap-4 lg:gap-6 min-w-0">
                    <Link href="/" class="flex items-center gap-3 group shrink-0">
                        <div class="size-10 rounded-xl bg-gradient-to-tr from-[#701a31] to-[#800020] flex items-center justify-center text-white shadow-sm shadow-rose-900/20 group-hover:scale-105 transition-transform">
                            <GraduationCap class="size-5 text-amber-300" />
                        </div>
                        <div>
                            <div class="flex items-center gap-1.5 font-bold text-base tracking-tight text-slate-950 dark:text-white">
                                <span>SIGC-CUSCO</span>
                            </div>
                            <p class="text-[11px] text-slate-600 dark:text-neutral-400 font-medium hidden sm:block">
                                Acreditación Digital Oficial • Cusco, Perú
                            </p>
                        </div>
                    </Link>

                    <div class="h-6 w-px bg-slate-200 dark:bg-slate-800 hidden md:block shrink-0" />

                    <nav class="hidden md:flex items-center gap-4 text-xs font-bold text-slate-700 dark:text-slate-300 shrink-0">
                        <Button as-child variant="ghost" size="sm" class="text-xs font-bold text-slate-800 hover:text-rose-900 cursor-pointer">
                            <Link href="/">
                                <ArrowLeft class="size-3.5 mr-1 text-rose-800" />
                                <span>Portal Principal</span>
                            </Link>
                        </Button>
                        <Link href="/courses" class="hover:text-rose-900 transition-colors flex items-center gap-1.5">
                            <GraduationCap class="size-4 text-rose-800" />
                            <span>Capacitaciones</span>
                        </Link>
                        <span class="text-rose-900 dark:text-rose-300 flex items-center gap-1.5 border-b-2 border-rose-900 pb-1">
                            <Award class="size-4 text-amber-600" />
                            <span>Validar Certificados</span>
                        </span>
                    </nav>
                </div>

                <div class="flex items-center gap-2.5">
                    <Button
                        variant="ghost"
                        size="sm"
                        class="text-xs font-bold text-slate-800 dark:text-slate-200 hover:text-rose-900 cursor-pointer"
                        @click="isLoginModalOpen = true"
                    >
                        <LogIn class="size-3.5 mr-1 text-rose-900" />
                        Ingresar
                    </Button>
                    <Button
                        size="sm"
                        class="bg-rose-900 hover:bg-rose-950 text-white font-bold text-xs shadow-xs cursor-pointer"
                        @click="isRegisterModalOpen = true"
                    >
                        <UserPlus class="size-3.5 mr-1" />
                        Registrarse
                    </Button>
                </div>
            </div>
        </header>

        <!-- MAIN CONTENT FOR GUEST -->
        <main class="flex-1 max-w-5xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12 space-y-8">
            <div class="text-center max-w-2xl mx-auto space-y-3">
                <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-rose-100/90 dark:bg-rose-950/60 border border-rose-300 dark:border-rose-800 text-rose-950 dark:text-rose-200 text-xs font-black shadow-xs">
                    <ShieldCheck class="size-3.5 text-rose-800" />
                    <span>Fe Pública Oficial • UNSAAC / Cusco</span>
                </div>
                <h1 class="text-2xl sm:text-3xl md:text-4xl font-black tracking-tight text-slate-950 dark:text-white leading-tight">
                    Consulta y Validación de <span class="text-rose-900 dark:text-rose-400">Certificados Digitales</span>
                </h1>
                <p class="text-xs sm:text-sm text-slate-700 dark:text-neutral-300 leading-relaxed font-semibold">
                    Verifica la autenticidad e integridad de los diplomas oficiales expedidos en las capacitaciones académicas.
                </p>
            </div>

            <!-- SELECTOR DE MODALIDAD (TABS LIMPIOS) -->
            <div class="flex items-center justify-center">
                <div class="inline-flex p-1 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-xs">
                    <button
                        type="button"
                        @click="activeTab = 'dni'"
                        class="flex items-center gap-2 px-5 py-2 rounded-lg text-xs font-black transition-all cursor-pointer"
                        :class="activeTab === 'dni' ? 'bg-rose-900 text-white shadow-xs' : 'text-slate-700 dark:text-slate-300 hover:text-rose-900'"
                    >
                        <Search class="size-3.5" />
                        <span>Búsqueda Oficial por DNI</span>
                    </button>
                    <button
                        type="button"
                        @click="activeTab = 'pdf'"
                        class="flex items-center gap-2 px-5 py-2 rounded-lg text-xs font-black transition-all cursor-pointer"
                        :class="activeTab === 'pdf' ? 'bg-rose-900 text-white shadow-xs' : 'text-slate-700 dark:text-slate-300 hover:text-rose-900'"
                    >
                        <FileCheck class="size-3.5 text-amber-500" />
                        <span>Comprobador de Archivo PDF (SHA-256)</span>
                    </button>
                </div>
            </div>

            <!-- VISTA 1: BÚSQUEDA POR DNI (GUEST) -->
            <div v-if="activeTab === 'dni'" class="space-y-6">
                <!-- Tarjeta de Búsqueda -->
                <Card class="max-w-2xl mx-auto border-2 border-rose-900/20 dark:border-rose-900/40 shadow-sm bg-white dark:bg-slate-900 overflow-hidden">
                    <CardHeader class="bg-gradient-to-r from-rose-50/80 via-white to-amber-50/20 dark:from-rose-950/30 dark:to-slate-900 border-b pb-3.5">
                        <div class="flex items-center gap-2 text-xs font-black text-rose-900 dark:text-rose-300 uppercase tracking-wider">
                            <Search class="size-4 text-rose-800" />
                            <span>Búsqueda Oficial con DNI</span>
                        </div>
                        <CardTitle class="text-base sm:text-lg font-bold text-slate-950 dark:text-white">
                            Consulta Inmediata de Diplomas
                        </CardTitle>
                        <CardDescription class="text-xs text-slate-600 dark:text-slate-400">
                            Ingresa el número de documento de identidad de 8 dígitos.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="p-5 sm:p-6 space-y-4">
                        <form @submit.prevent="searchCertificates" class="flex flex-col sm:flex-row gap-3">
                            <div class="relative flex-1">
                                <Search class="absolute left-3.5 top-1/2 -translate-y-1/2 size-4 text-slate-400" />
                                <Input
                                    :value="dniQuery"
                                    @input="onDniInput"
                                    type="text"
                                    inputmode="numeric"
                                    maxlength="8"
                                    placeholder="Ingresa tu DNI (8 dígitos)"
                                    class="pl-10 font-mono text-base tracking-widest font-bold text-slate-950 dark:text-white border-slate-300 focus-visible:ring-rose-900 h-11"
                                    :disabled="loading"
                                    autofocus
                                />
                                <div class="absolute right-3.5 top-1/2 -translate-y-1/2 text-[11px] font-mono font-bold text-slate-400">
                                    {{ dniQuery.length }}/8
                                </div>
                            </div>
                            <Button
                                type="submit"
                                :disabled="loading || dniQuery.length !== 8"
                                class="bg-rose-900 hover:bg-rose-950 text-white font-bold h-11 px-6 shadow-xs shrink-0 text-xs sm:text-sm cursor-pointer"
                            >
                                <Loader2 v-if="loading" class="size-4 mr-2 animate-spin" />
                                <Search v-else class="size-4 mr-2" />
                                <span>Consultar DNI</span>
                            </Button>
                        </form>

                        <div v-if="error" class="p-3 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900 text-rose-900 dark:text-rose-200 text-xs font-bold flex items-center gap-2">
                            <AlertCircle class="size-4 shrink-0 text-rose-700" />
                            <span>{{ error }}</span>
                        </div>
                    </CardContent>
                </Card>

                <!-- Resultados de Búsqueda -->
                <div v-if="loading" class="max-w-3xl mx-auto py-10 text-center space-y-3">
                    <Loader2 class="size-8 mx-auto animate-spin text-rose-900" />
                    <p class="text-xs sm:text-sm font-bold text-slate-700 dark:text-slate-300">
                        Consultando capacitaciones y diplomas emitidos para el DNI {{ dniQuery }}...
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
                            <Badge variant="outline" class="border-rose-300 text-rose-900 dark:border-rose-800 dark:text-rose-300 font-bold text-xs py-1 px-3 bg-rose-50 dark:bg-rose-950">
                                <Award class="size-3.5 mr-1 text-rose-800" />
                                {{ records.length }} {{ records.length === 1 ? 'Capacitación Aprobada' : 'Capacitaciones Aprobadas' }}
                            </Badge>
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

                                        <div class="flex items-center gap-2">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                class="text-xs font-bold border-slate-300 hover:text-rose-900 hover:bg-rose-50 cursor-pointer h-9 px-3.5"
                                                @click="openCertificatePreview(record)"
                                            >
                                                <Eye class="size-3.5 mr-1.5 text-slate-700" />
                                                Ver Diploma
                                            </Button>
                                            <Button
                                                type="button"
                                                size="sm"
                                                class="bg-rose-900 hover:bg-rose-950 text-white font-bold text-xs shadow-xs cursor-pointer h-9 px-3.5"
                                                @click="printCertificate(record)"
                                            >
                                                <Download class="size-3.5 mr-1.5" />
                                                Descargar PDF
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
                            No se registran certificados para el DNI {{ dniQuery }}
                        </h3>
                        <p class="text-xs text-slate-600 dark:text-slate-400 max-w-md mx-auto leading-relaxed">
                            Los certificados se generan una vez concluida la capacitación y cerrada el acta de evaluación oficial.
                        </p>
                    </div>
                </div>
            </div>

            <!-- VISTA 2: VERIFICACIÓN CRIPTOGRÁFICA DE ARCHIVO PDF (GUEST) -->
            <div v-else class="max-w-3xl mx-auto space-y-4">
                <PdfIntegrityVerifier :expected-hash="selectedCert?.certificate_hash || records[0]?.certificate_hash" />
            </div>
        </main>

        <!-- FOOTER INSTITUCIONAL PARA INVITADO -->
        <footer class="border-t border-slate-200/90 dark:border-slate-800 bg-white/90 dark:bg-slate-950/90 py-5 text-xs text-slate-600 dark:text-slate-400">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-3">
                <div class="flex items-center gap-2 font-bold text-slate-900 dark:text-slate-200">
                    <GraduationCap class="size-4 text-rose-900" />
                    <span>SIGC-CUSCO • Acreditación Oficial</span>
                </div>
                <div class="flex items-center">
                    <PeruGeoBadge />
                </div>
            </div>
        </footer>
    </div>

    <!-- MODAL DE VISTA PREVIA DEL DIPLOMA OFICIAL (COMPARTIDO) -->
    <Dialog v-model:open="isCertModalOpen">
        <DialogContent class="max-w-3xl max-h-[90vh] flex flex-col p-0 overflow-hidden bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800">
            <DialogHeader class="p-4 border-b border-slate-200 dark:border-slate-800 shrink-0 bg-slate-50/80 dark:bg-slate-950/60">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <Award class="size-4 text-rose-800" />
                        <DialogTitle class="text-sm font-bold text-slate-950 dark:text-white">
                            Vista Previa de Diploma Oficial
                        </DialogTitle>
                    </div>
                    <Badge variant="outline" class="font-mono text-[10px] font-bold border-rose-300 text-rose-900">
                        {{ selectedCert?.certificate_code }}
                    </Badge>
                </div>
                <DialogDescription class="text-[11px] text-slate-500">
                    Documento con valor oficial emitido por SIGC-CUSCO bajo la normativa académica vigente.
                </DialogDescription>
            </DialogHeader>

            <!-- Cuerpo del Diploma -->
            <div v-if="selectedCert" class="flex-1 overflow-y-auto p-4 sm:p-6 bg-slate-100/60 dark:bg-slate-900/60 flex items-center justify-center">
                <div class="w-full bg-white dark:bg-slate-900 border-4 border-double border-[#800020] p-6 sm:p-8 relative text-center shadow-md">
                    <div class="space-y-4">
                        <div class="border-b border-[#800020]/20 pb-3">
                            <div class="text-[10px] font-black tracking-widest text-[#800020] uppercase">
                                REPÚBLICA DEL PERÚ • REGIÓN CUSCO
                            </div>
                            <div class="text-[9px] text-slate-600 uppercase tracking-wider font-semibold">
                                SISTEMA INTEGRAL DE GESTIÓN DE CAPACITACIONES (SIGC-CUSCO / UNSAAC)
                            </div>
                        </div>

                        <div class="pt-1">
                            <h2 class="text-xl sm:text-2xl font-black text-[#800020] uppercase tracking-wider">
                                Certificado Oficial
                            </h2>
                            <p class="text-xs text-slate-600 italic">
                                Por culminación académica satisfactoria y acreditación oficial
                            </p>
                        </div>

                        <div class="pt-1">
                            <p class="text-xs text-slate-500 uppercase tracking-wider">Otorgado a:</p>
                            <div class="text-lg sm:text-xl font-black text-slate-950 dark:text-white uppercase tracking-tight py-1 border-b-2 border-[#800020] inline-block px-4">
                                {{ selectedCert.student_name }}
                            </div>
                        </div>

                        <div class="text-xs text-slate-700 dark:text-slate-300 max-w-lg mx-auto leading-relaxed pt-1">
                            Habiendo completado satisfactoriamente el curso
                            <strong class="text-[#800020] block my-1 text-sm font-black">"${selectedCert.course_title}"</strong>
                            con un total de <strong>${formatHours(selectedCert.hours)}</strong> lectivas, desarrollado
                            del ${selectedCert.start_date} al ${selectedCert.end_date}, habiendo cumplido con
                            los requisitos institucionales de fe pública.
                        </div>

                        <div class="pt-5 grid grid-cols-2 gap-6 text-xs border-t border-slate-300 dark:border-slate-800">
                            <div>
                                <div class="font-bold text-slate-900 dark:text-white">{{ selectedCert.instructor_name }}</div>
                                <div class="text-[10px] text-slate-500 uppercase">Ponente / Docente</div>
                            </div>
                            <div>
                                <div class="font-bold text-slate-900 dark:text-white">Dirección Académica</div>
                                <div class="text-[10px] text-slate-500 uppercase">Coordinación SIGC-CUSCO</div>
                            </div>
                        </div>

                        <div class="pt-3 text-[10px] font-mono text-slate-500 flex justify-between items-center border-t border-dashed border-slate-300 dark:border-slate-800">
                            <span>Código: <strong>{{ selectedCert.certificate_code }}</strong></span>
                            <span class="text-[#800020] font-bold">VÁLIDO OFICIALMENTE • CUSCO</span>
                        </div>

                        <div v-if="selectedCert.certificate_hash" class="pt-2 text-[10px] font-mono text-slate-500 break-all text-left bg-slate-50 dark:bg-slate-800 p-2 rounded border border-slate-200 dark:border-slate-700">
                            <span class="font-bold text-slate-700 dark:text-slate-300 block mb-0.5">Hash Criptográfico SHA-256 Oficial:</span>
                            <span class="text-slate-600 dark:text-slate-400 select-all">{{ selectedCert.certificate_hash }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Fijo -->
            <DialogFooter class="flex flex-col sm:flex-row items-center justify-between gap-3 p-4 border-t border-slate-200 dark:border-slate-800 shrink-0 bg-slate-50/80 dark:bg-slate-950/60">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    class="text-xs font-bold border-slate-300 cursor-pointer"
                    @click="selectedCert && copyVerificationCode(selectedCert.certificate_code)"
                >
                    <Check v-if="copiedCode" class="size-3.5 mr-1 text-rose-800" />
                    <Copy v-else class="size-3.5 mr-1" />
                    <span>{{ copiedCode ? '¡Código Copiado!' : 'Copiar Código' }}</span>
                </Button>

                <div class="flex items-center gap-2">
                    <Button variant="ghost" size="sm" class="text-xs cursor-pointer" @click="isCertModalOpen = false">
                        Cerrar
                    </Button>
                    <Button
                        size="sm"
                        class="bg-rose-900 hover:bg-rose-950 text-white font-bold text-xs shadow-xs cursor-pointer"
                        @click="selectedCert && printCertificate(selectedCert)"
                    >
                        <Printer class="size-3.5 mr-1.5" />
                        Imprimir / Guardar en PDF
                    </Button>
                </div>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- AUTH MODALS (SOLO PARA INVITADOS) -->
    <template v-if="!authUser">
        <LoginModal
            v-model:open="isLoginModalOpen"
            @switch-to-register="switchToRegister"
        />
        <RegisterModal
            v-model:open="isRegisterModalOpen"
            @switch-to-login="switchToLogin"
        />
    </template>
</template>
