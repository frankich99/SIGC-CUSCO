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
    QrCode,
    Sparkles,
    ShieldCheck,
    User,
    ArrowLeft,
    LogIn,
    UserPlus,
    ExternalLink,
    Eye,
    ChevronRight,
} from '@lucide/vue';
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
import { formatDate, formatDateRange, formatHours } from '@/lib/formatters';

interface CertificateRecord {
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
    attended_sessions: number;
    final_grade?: number | string | null;
    certificate_code: string;
}

const props = defineProps<{
    initialDni?: string;
}>();

const page = usePage();
const authUser = computed(() => (page.props.auth as any)?.user);

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

// Modales de Autenticación
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
        // Fallback en caso de bloqueo del portapapeles
    }
}

/**
 * Impresión / Descarga a PDF fiable que no es bloqueada por bloqueadores de ventanas emergentes (popups)
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
                    letter-spacing: 1px;
                }
                .body-text {
                    font-size: 15px;
                    line-height: 1.7;
                    color: #334155;
                    max-width: 780px;
                    margin: 0 auto 25px auto;
                }
                .course-title {
                    font-size: 20px;
                    font-weight: bold;
                    color: #800020;
                    margin: 8px 0;
                    display: block;
                }
                .signatures {
                    margin-top: 45px;
                    display: flex;
                    justify-content: space-around;
                    align-items: flex-end;
                    padding: 0 40px;
                }
                .sig-box {
                    width: 240px;
                    border-top: 1.5px solid #475569;
                    padding-top: 8px;
                    font-size: 12px;
                    line-height: 1.4;
                    color: #1e293b;
                }
                .sig-role {
                    font-size: 11px;
                    color: #64748b;
                    text-transform: uppercase;
                }
                .verification-bar {
                    margin-top: 40px;
                    padding-top: 15px;
                    border-top: 1px dashed #cbd5e1;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    font-family: monospace;
                    font-size: 11px;
                    color: #64748b;
                }
                .badge-valid {
                    color: #800020;
                    font-weight: bold;
                }
            </style>
        </head>
        <body>
            <div class="cert-container">
                <div class="header-inst">${record.institution || 'SIGC-CUSCO'}</div>
                <div class="sub-inst">Sistema Integral de Gestión de Capacitaciones • Cusco, Perú</div>
                
                <h1 class="title-cert">Certificado Oficial</h1>
                <div class="subtitle-cert">Acreditación Académica y Asistencia Digital</div>

                <div class="otorgado">Se otorga el presente reconocimiento a:</div>
                <div class="recipient-name">${record.student_name}</div>

                <div class="body-text">
                    Por haber participado y aprobado satisfactoriamente la capacitación especializada:
                    <span class="course-title">"${record.course_title}"</span>
                    con una duración de <strong>${formatHours(record.hours)}</strong> lectivas, desarrollada
                    del <strong>${record.start_date || 'Fecha de inicio'}</strong> al <strong>${record.end_date || 'Fecha de término'}</strong>,
                    habiendo cumplido con el registro de asistencia mediante código QR y las evaluaciones académicas institucionales.
                </div>

                <div class="signatures">
                    <div class="sig-box">
                        <strong>${record.instructor_name}</strong><br>
                        <span class="sig-role">Docente Instructor</span>
                    </div>
                    <div class="sig-box">
                        <strong>Dirección Académica</strong><br>
                        <span class="sig-role">Coordinación SIGC-CUSCO</span>
                    </div>
                </div>

                <div class="verification-bar">
                    <div>Código de Certificado: <strong>${record.certificate_code}</strong></div>
                    <div class="badge-valid">VERIFICACIÓN OFICIAL VÁLIDA • CUSCO, PERÚ</div>
                    <div>Horas: ${record.hours}h</div>
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
    // Si viene con parámetro en la URL (?dni=...) o prop inicial
    const params = new URLSearchParams(window.location.search);
    const dniParam = params.get('dni') || props.initialDni;
    if (dniParam && /^\d{8}$/.test(dniParam.trim())) {
        dniQuery.value = dniParam.trim();
        searchCertificates();
    }
});
</script>

<template>
    <div class="min-h-screen flex flex-col justify-between bg-slate-50/70 dark:bg-slate-950 text-slate-900 dark:text-slate-100 antialiased selection:bg-rose-900 selection:text-white">
        <Head title="Acreditación Digital Oficial • Consulta y Descarga de Certificados - SIGC-CUSCO" />

        <!-- TOP INSTITUTIONAL HEADER -->
        <header class="sticky top-0 z-40 w-full border-b border-slate-200/80 dark:border-slate-800 bg-white/95 dark:bg-slate-950/90 backdrop-blur-md shadow-xs">
            <div class="max-w-7xl mx-auto flex h-16 items-center justify-between px-4 sm:px-6 lg:px-8">
                <!-- Brand Logo & Retroceso Rápido -->
                <div class="flex items-center gap-3">
                    <Link href="/" class="flex items-center gap-3 group">
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
                </div>

                <!-- Navigation Links & Botón Retroceso -->
                <nav class="hidden md:flex items-center gap-5 text-xs font-bold text-slate-700 dark:text-slate-300">
                    <Button as-child variant="ghost" size="sm" class="text-xs font-bold text-slate-800 dark:text-slate-200 hover:text-rose-900 hover:bg-rose-50/80 dark:hover:bg-rose-950/40 gap-1.5 cursor-pointer">
                        <Link href="/">
                            <ArrowLeft class="size-3.5 text-rose-800 dark:text-rose-400" />
                            <span>Volver a Inicio</span>
                        </Link>
                    </Button>

                    <Link href="/courses" class="hover:text-rose-900 dark:hover:text-rose-400 transition-colors flex items-center gap-1.5">
                        <GraduationCap class="size-4 text-rose-800" />
                        <span>Capacitaciones</span>
                    </Link>

                    <span class="text-rose-900 dark:text-rose-300 flex items-center gap-1.5 border-b-2 border-rose-900 pb-1">
                        <Award class="size-4 text-amber-600" />
                        <span>Certificados Oficiales</span>
                    </span>
                </nav>

                <!-- Auth / Guest Actions -->
                <div class="flex items-center gap-3">
                    <template v-if="authUser">
                        <Button as-child size="sm" class="bg-rose-900 hover:bg-rose-950 text-white font-bold text-xs shadow-xs cursor-pointer">
                            <Link href="/dashboard">
                                <span>Mi Panel</span>
                                <span class="ml-1.5 text-[10px] bg-rose-950 text-amber-300 px-1.5 py-0.5 rounded uppercase font-bold tracking-wider">
                                    {{ authUser.role }}
                                </span>
                            </Link>
                        </Button>
                    </template>
                    <template v-else>
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
                    </template>
                </div>
            </div>
        </header>

        <!-- MAIN CONTENT AREA: LANDING ÚNICA Y AMPLIA -->
        <main class="flex-1 max-w-6xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12 space-y-10">
            <!-- BARRA DE RETROCESO Y BREADCRUMB -->
            <div class="flex items-center justify-between border-b border-slate-200/80 dark:border-slate-800 pb-4">
                <Button as-child variant="outline" size="sm" class="text-xs font-bold border-slate-300 hover:bg-rose-50 hover:text-rose-900 text-slate-800 dark:text-slate-200 cursor-pointer shadow-2xs">
                    <Link href="/">
                        <ArrowLeft class="size-3.5 mr-1.5 text-rose-800" />
                        Volver al Portal de Capacitaciones
                    </Link>
                </Button>

                <div class="hidden sm:flex items-center gap-1.5 text-xs text-slate-500 font-medium">
                    <Link href="/" class="hover:text-rose-900 transition-colors">Portal Principal</Link>
                    <ChevronRight class="size-3" />
                    <span class="text-rose-900 dark:text-rose-400 font-bold">Certificados Digitales</span>
                </div>
            </div>

            <!-- HERO HEADER OF THE PAGE (TEXTOS EXACTOS SOLICITADOS) -->
            <div class="text-center max-w-3xl mx-auto space-y-4">
                <!-- Tag Superior -->
                <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-rose-100/90 dark:bg-rose-950/60 border border-rose-300 dark:border-rose-800 text-rose-950 dark:text-rose-200 text-xs font-black shadow-xs">
                    <Award class="size-4 text-rose-800 dark:text-rose-400" />
                    <span>Acreditación Digital Oficial • Cusco, Perú</span>
                </div>

                <!-- Título Principal -->
                <h1 class="text-3xl sm:text-4xl md:text-5xl font-black tracking-tight text-slate-950 dark:text-white leading-tight">
                    Consulta y Descarga de <span class="text-rose-900 dark:text-rose-400 underline decoration-amber-500 decoration-4 underline-offset-8">Certificados Oficiales</span>
                </h1>

                <!-- Subtítulo -->
                <p class="text-sm sm:text-base md:text-lg text-slate-700 dark:text-neutral-300 max-w-2xl mx-auto leading-relaxed font-semibold">
                    Ingresa tu número de DNI para consultar las capacitaciones aprobadas y descargar tu diploma digital oficial con código de verificación.
                </p>
            </div>

            <!-- BUSCADOR PRINCIPAL POR DNI (AMPLIO Y DESTACADO) -->
            <Card class="max-w-2xl mx-auto border-2 border-rose-900/20 dark:border-rose-900/40 shadow-lg bg-white dark:bg-slate-900 overflow-hidden">
                <CardHeader class="bg-gradient-to-r from-rose-50/90 via-amber-50/30 to-white dark:from-rose-950/40 dark:to-slate-900 border-b pb-4">
                    <div class="flex items-center gap-2 text-xs font-black text-rose-900 dark:text-rose-300 uppercase tracking-wider">
                        <Search class="size-4 text-rose-800" />
                        <span>Búsqueda Oficial con DNI</span>
                    </div>
                    <CardTitle class="text-lg sm:text-xl font-bold text-slate-950 dark:text-white">
                        Consulta Inmediata de Diplomas
                    </CardTitle>
                    <CardDescription class="text-xs text-slate-600 dark:text-slate-400 font-medium">
                        Sistema sincronizado con el padrón de asistencias QR y actas de evaluación académica.
                    </CardDescription>
                </CardHeader>

                <CardContent class="p-6">
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
                                class="pl-10 font-mono text-base tracking-widest font-bold text-slate-950 dark:text-white border-slate-300 focus-visible:ring-rose-900 h-12"
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
                            class="bg-rose-900 hover:bg-rose-950 text-white font-bold h-12 px-7 shadow-md shadow-rose-900/20 shrink-0 text-sm cursor-pointer"
                        >
                            <Loader2 v-if="loading" class="size-4 mr-2 animate-spin" />
                            <Search v-else class="size-4 mr-2" />
                            <span>Consultar DNI</span>
                        </Button>
                    </form>

                    <!-- Mensaje de Error -->
                    <div v-if="error" class="mt-4 p-3.5 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900 text-rose-900 dark:text-rose-200 text-xs font-bold flex items-center gap-2.5">
                        <AlertCircle class="size-4 shrink-0 text-rose-700" />
                        <span>{{ error }}</span>
                    </div>
                </CardContent>
            </Card>

            <!-- 3 PILARES INSTITUCIONALES (CARACTERÍSTICAS DE LA LANDING) -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5 max-w-5xl mx-auto pt-2">
                <div class="p-5 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/80 dark:bg-slate-900/40 space-y-2.5 shadow-2xs">
                    <div class="size-10 rounded-xl bg-rose-100 dark:bg-rose-950 text-rose-900 dark:text-rose-300 flex items-center justify-center font-bold">
                        <ShieldCheck class="size-5" />
                    </div>
                    <h3 class="text-sm font-bold text-slate-950 dark:text-white">Validación con RENIEC</h3>
                    <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed font-medium">
                        Cruce de identidad en tiempo real para emisión con nombre oficial, garantizando cero adulteraciones.
                    </p>
                </div>

                <div class="p-5 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/80 dark:bg-slate-900/40 space-y-2.5 shadow-2xs">
                    <div class="size-10 rounded-xl bg-amber-100 dark:bg-amber-950 text-amber-800 dark:text-amber-300 flex items-center justify-center font-bold">
                        <QrCode class="size-5" />
                    </div>
                    <h3 class="text-sm font-bold text-slate-950 dark:text-white">Asistencia QR Controlada</h3>
                    <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed font-medium">
                        Certificación expedida únicamente a quienes completaron las sesiones presenciales o virtuales con código QR.
                    </p>
                </div>

                <div class="p-5 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/80 dark:bg-slate-900/40 space-y-2.5 shadow-2xs">
                    <div class="size-10 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-200 flex items-center justify-center font-bold">
                        <Download class="size-5 text-rose-900 dark:text-rose-400" />
                    </div>
                    <h3 class="text-sm font-bold text-slate-950 dark:text-white">Descarga Inmediata PDF</h3>
                    <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed font-medium">
                        Diploma oficial en alta resolución con código de verificación verificable desde cualquier dispositivo.
                    </p>
                </div>
            </div>

            <!-- SECCIÓN DE RESULTADOS DE BÚSQUEDA -->
            <div v-if="loading" class="max-w-4xl mx-auto py-12 text-center space-y-3">
                <Loader2 class="size-8 mx-auto animate-spin text-rose-900" />
                <p class="text-sm font-bold text-slate-800 dark:text-slate-200">
                    Consultando capacitaciones y diplomas para el DNI {{ dniQuery }}...
                </p>
            </div>

            <div v-else-if="searched" class="max-w-4xl mx-auto space-y-6">
                <!-- SI SE ENCUENTRAN REGISTROS -->
                <template v-if="records.length > 0">
                    <!-- Tarjeta del Participante Titular -->
                    <div class="p-5 sm:p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                        <div class="flex items-center gap-3.5">
                            <div class="size-12 rounded-xl bg-rose-900 text-white flex items-center justify-center font-black shadow-xs">
                                <User class="size-6 text-amber-300" />
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <h2 class="text-base sm:text-lg font-black text-slate-950 dark:text-white uppercase tracking-tight">
                                        {{ studentName }}
                                    </h2>
                                    <Badge class="bg-rose-100 text-rose-950 border-rose-300 dark:bg-rose-950 dark:text-rose-200 dark:border-rose-800 text-[10px] font-black">
                                        <CheckCircle2 class="size-3 mr-1 text-rose-800" />
                                        Validado
                                    </Badge>
                                </div>
                                <div class="text-xs font-mono text-slate-600 dark:text-slate-400 font-semibold mt-0.5">
                                    Documento Nacional de Identidad: <span class="font-bold text-slate-950 dark:text-white">{{ dniQuery }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 self-stretch sm:self-auto justify-end">
                            <Badge variant="outline" class="border-rose-300 text-rose-900 dark:border-rose-800 dark:text-rose-300 font-bold text-xs py-1 px-3 bg-rose-50 dark:bg-rose-950">
                                <Award class="size-3.5 mr-1 text-rose-800" />
                                {{ records.length }} {{ records.length === 1 ? 'Capacitación Aprobada' : 'Capacitaciones Aprobadas' }}
                            </Badge>
                        </div>
                    </div>

                    <!-- Grid de Certificados Encontrados -->
                    <div class="space-y-4">
                        <Card
                            v-for="record in records"
                            :key="record.id"
                            class="border border-slate-200 dark:border-slate-800 shadow-sm hover:border-rose-300 transition-all bg-white dark:bg-slate-900 overflow-hidden"
                        >
                            <CardHeader class="p-5 pb-3 bg-slate-50/70 dark:bg-slate-900/80 border-b flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-xs font-black font-mono px-2.5 py-0.5 rounded bg-rose-900 text-white">
                                        {{ record.course_code }}
                                    </span>
                                    <span class="text-xs font-bold text-slate-700 dark:text-slate-300 flex items-center gap-1">
                                        <Building2 class="size-3 text-slate-400" />
                                        {{ record.institution || 'SIGC-CUSCO' }}
                                    </span>
                                </div>

                                <Badge
                                    class="text-xs font-bold capitalize self-start sm:self-auto bg-rose-100 text-rose-950 border-rose-300 dark:bg-rose-950 dark:text-rose-200 dark:border-rose-800"
                                >
                                    <CheckCircle2 class="size-3 mr-1 text-rose-800" />
                                    {{ record.status }}
                                </Badge>
                            </CardHeader>

                            <CardContent class="p-5 space-y-4">
                                <h3 class="text-lg sm:text-xl font-bold text-slate-950 dark:text-white leading-snug">
                                    {{ record.course_title }}
                                </h3>

                                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3 text-xs">
                                    <div class="p-2.5 rounded-lg bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800">
                                        <div class="text-[11px] text-slate-500 font-bold uppercase tracking-wider mb-0.5">Docente Instructor</div>
                                        <div class="font-bold text-slate-900 dark:text-white truncate">
                                            {{ record.instructor_name }}
                                        </div>
                                    </div>

                                    <div class="p-2.5 rounded-lg bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800">
                                        <div class="text-[11px] text-slate-500 font-bold uppercase tracking-wider mb-0.5">Horas Lectivas</div>
                                        <div class="font-bold text-slate-900 dark:text-white flex items-center gap-1">
                                            <Clock class="size-3 text-amber-600" />
                                            {{ formatHours(record.hours) }}
                                        </div>
                                    </div>

                                    <div class="p-2.5 rounded-lg bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800">
                                        <div class="text-[11px] text-slate-500 font-bold uppercase tracking-wider mb-0.5">Período de Clases</div>
                                        <div class="font-bold text-slate-900 dark:text-white flex items-center gap-1">
                                            <Calendar class="size-3 text-rose-800" />
                                            {{ formatDateRange(record.start_date, record.end_date, 'short') }}
                                        </div>
                                    </div>

                                    <div class="p-2.5 rounded-lg bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800">
                                        <div class="text-[11px] text-slate-500 font-bold uppercase tracking-wider mb-0.5">Asistencia QR</div>
                                        <div class="font-bold text-slate-900 dark:text-white flex items-center gap-1">
                                            <QrCode class="size-3 text-indigo-600" />
                                            {{ record.attended_sessions }} sesiones marcadas
                                        </div>
                                    </div>
                                </div>

                                <div class="pt-2 flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-t border-slate-200 dark:border-slate-800">
                                    <div class="flex items-center gap-2 text-xs font-mono text-slate-600 dark:text-slate-400">
                                        <span class="font-bold text-slate-900 dark:text-slate-200">Cód. Verificación:</span>
                                        <span class="bg-slate-100 dark:bg-slate-800 px-2.5 py-0.5 rounded font-black text-rose-900 dark:text-rose-400 border border-slate-200 dark:border-slate-700">
                                            {{ record.certificate_code }}
                                        </span>
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            class="text-xs font-bold border-slate-300 hover:text-rose-900 hover:bg-rose-50 cursor-pointer h-9 px-4"
                                            @click="openCertificatePreview(record)"
                                        >
                                            <Eye class="size-3.5 mr-1.5 text-slate-700" />
                                            Ver Diploma
                                        </Button>

                                        <Button
                                            type="button"
                                            size="sm"
                                            class="bg-rose-900 hover:bg-rose-950 text-white font-bold text-xs shadow-xs cursor-pointer h-9 px-4"
                                            @click="printCertificate(record)"
                                        >
                                            <Download class="size-3.5 mr-1.5" />
                                            Descargar Certificado PDF
                                        </Button>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </template>

                <!-- SI NO SE ENCONTRARON REGISTROS -->
                <div v-else class="p-8 sm:p-10 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-center space-y-4 shadow-sm">
                    <div class="size-14 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-500 mx-auto flex items-center justify-center">
                        <Award class="size-7 text-slate-400" />
                    </div>
                    <div class="space-y-1">
                        <h3 class="text-lg font-bold text-slate-950 dark:text-white">
                            No se encontraron certificados para el DNI {{ dniQuery }}
                        </h3>
                        <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-400 max-w-md mx-auto leading-relaxed font-medium">
                            Verifica que los 8 dígitos numéricos sean correctos o inscríbete en una de nuestras capacitaciones oficiales abiertas para obtener tu certificación.
                        </p>
                    </div>

                    <div class="pt-2 flex flex-col sm:flex-row items-center justify-center gap-3">
                        <Button as-child variant="outline" size="sm" class="text-xs font-bold border-slate-300 hover:bg-rose-50 hover:text-rose-900 cursor-pointer">
                            <Link href="/courses">
                                <GraduationCap class="size-3.5 mr-1 text-rose-900" />
                                Ver Cursos Disponibles
                            </Link>
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            class="text-xs font-bold text-slate-700 hover:text-rose-900 cursor-pointer"
                            @click="dniQuery = ''; searched = false;"
                        >
                            Consultar con otro DNI
                        </Button>
                    </div>
                </div>
            </div>

            <!-- BANNER INFORMATIVO FINAL -->
            <div class="max-w-4xl mx-auto rounded-2xl bg-gradient-to-r from-rose-900 to-[#701a31] text-white p-6 sm:p-8 flex flex-col md:flex-row items-center justify-between gap-6 shadow-md">
                <div class="space-y-2 text-center md:text-left">
                    <div class="inline-flex items-center gap-1.5 text-xs font-black uppercase tracking-wider text-amber-300">
                        <Sparkles class="size-4" />
                        <span>Acreditación Institucional Garantizada</span>
                    </div>
                    <h3 class="text-lg sm:text-xl font-black tracking-tight text-white">
                        ¿Deseas participar en nuevas capacitaciones?
                    </h3>
                    <p class="text-xs sm:text-sm text-rose-100/90 max-w-lg leading-relaxed font-medium">
                        Revisa la lista de cursos abiertos con aforo oficial e inscripción directa con DNI y control de asistencia QR.
                    </p>
                </div>

                <div class="shrink-0 flex flex-col sm:flex-row gap-3">
                    <Button as-child variant="secondary" size="sm" class="bg-white text-rose-950 hover:bg-rose-50 font-black text-xs shadow-xs cursor-pointer h-10 px-5">
                        <Link href="/courses">
                            Explorar Convocatorias
                        </Link>
                    </Button>
                </div>
            </div>
        </main>

        <!-- FOOTER INSTITUCIONAL -->
        <footer class="border-t border-slate-200/80 dark:border-slate-800 bg-white/80 dark:bg-slate-950 py-6 text-xs text-slate-600 dark:text-neutral-400">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-2 font-medium">
                    <GraduationCap class="size-4 text-rose-900" />
                    <span class="font-bold text-slate-950 dark:text-white">
                        SIGC-CUSCO
                    </span>
                    <span>— Sistema Integral de Gestión de Capacitaciones • Cusco</span>
                </div>
                <div class="flex items-center gap-4">
                    <PeruGeoBadge />
                    <span class="font-medium text-slate-700 dark:text-slate-300 hidden md:inline">Cusco, Perú 2026</span>
                </div>
            </div>
        </footer>

        <!-- MODAL DE VISTA PREVIA DEL CERTIFICADO (DIPLOMA OFICIAL) -->
        <Dialog v-model:open="isCertModalOpen">
            <DialogContent class="w-[96vw] sm:max-w-3xl max-h-[90vh] flex flex-col p-0 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 overflow-hidden">
                <!-- Header Fijo Pinned -->
                <div class="p-5 sm:p-6 pb-3 border-b border-slate-200 dark:border-slate-800 shrink-0 pr-12">
                    <DialogHeader class="text-left space-y-1">
                        <div class="flex items-center gap-2 text-xs font-black text-rose-900 dark:text-rose-400 uppercase tracking-wider">
                            <Award class="size-4 text-rose-800" />
                            <span>Vista Previa del Diploma Digital Oficial</span>
                        </div>
                        <DialogTitle class="text-lg font-black text-slate-950 dark:text-white">
                            {{ selectedCert?.course_title }}
                        </DialogTitle>
                        <DialogDescription class="text-xs text-slate-600 dark:text-slate-400 font-medium">
                            Acreditación académica válida para trámites profesionales y laborales.
                        </DialogDescription>
                    </DialogHeader>
                </div>

                <!-- Cuerpo Scrollable Interno con scrollbar sutil -->
                <div class="flex-1 overflow-y-auto overscroll-contain custom-scrollbar p-5 sm:p-6">
                    <div v-if="selectedCert" class="space-y-6">
                        <!-- Vista previa realista con doble marco Granate Imperial y acento dorado -->
                        <div class="p-6 sm:p-8 bg-[#fdfbf7] dark:bg-slate-950 border-4 border-double border-[#800020] text-center space-y-4 rounded-xl shadow-inner relative">
                            <div class="text-[11px] font-black tracking-widest text-[#800020] uppercase">
                                {{ selectedCert.institution || 'SIGC-CUSCO' }}
                            </div>
                            <div class="text-[10px] tracking-wider text-slate-500 font-semibold uppercase">
                                Sistema Integral de Gestión de Capacitaciones • Cusco, Perú
                            </div>

                            <div class="pt-2">
                                <h2 class="text-2xl font-black text-[#800020] uppercase tracking-wider">
                                    Certificado Oficial
                                </h2>
                                <p class="text-xs text-slate-600 italic">
                                    Por culminación académica satisfactoria y asistencia
                                </p>
                            </div>

                            <div class="pt-2">
                                <p class="text-xs text-slate-500 uppercase tracking-wider">Otorgado a:</p>
                                <div class="text-xl font-black text-slate-950 dark:text-white uppercase tracking-tight py-1 border-b-2 border-[#800020] inline-block px-4">
                                    {{ selectedCert.student_name }}
                                </div>
                            </div>

                            <div class="text-xs text-slate-700 dark:text-slate-300 max-w-lg mx-auto leading-relaxed pt-1">
                                Habiendo completado satisfactoriamente el curso
                                <strong class="text-[#800020] block my-1 text-sm font-black">"{{ selectedCert.course_title }}"</strong>
                                con un total de <strong>{{ formatHours(selectedCert.hours) }}</strong>, desarrollado
                                del {{ selectedCert.start_date }} al {{ selectedCert.end_date }}, habiendo cumplido con
                                las asistencias mediante código QR y las evaluaciones oficiales.
                            </div>

                            <div class="pt-6 grid grid-cols-2 gap-6 text-xs border-t border-slate-300 dark:border-slate-800">
                                <div>
                                    <div class="font-bold text-slate-900 dark:text-white">{{ selectedCert.instructor_name }}</div>
                                    <div class="text-[10px] text-slate-500 uppercase">Docente Instructor</div>
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
                        </div>
                    </div>
                </div>

                <!-- Footer Fijo Pinned -->
                <DialogFooter class="flex flex-col sm:flex-row items-center justify-between gap-3 p-4 border-t border-slate-200 dark:border-slate-800 shrink-0 bg-slate-50/80 dark:bg-slate-950/60">
                    <div class="flex items-center gap-2">
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
                    </div>

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

        <!-- AUTH MODALS -->
        <LoginModal
            v-model:open="isLoginModalOpen"
            @switch-to-register="switchToRegister"
        />

        <RegisterModal
            v-model:open="isRegisterModalOpen"
            @switch-to-login="switchToLogin"
        />
    </div>
</template>
