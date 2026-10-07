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
} from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import PeruGeoBadge from '@/components/PeruGeoBadge.vue';
import AppLogo from '@/components/AppLogo.vue';
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

// Certificate Modal Preview
const selectedCert = ref<CertificateRecord | null>(null);
const isCertModalOpen = ref(false);
const copiedCode = ref(false);

// Auth Modals
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

function printCertificate(record: CertificateRecord) {
    const printWindow = window.open('', '_blank');
    if (!printWindow) return;

    printWindow.document.write(`
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
                    background: #fdfdfd;
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
                    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
                    text-align: center;
                }
                .cert-container::before {
                    content: "";
                    position: absolute;
                    top: 8px;
                    left: 8px;
                    right: 8px;
                    bottom: 8px;
                    border: 1px solid #d97706;
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
                @media print {
                    body {
                        padding: 0;
                        background: #fff;
                    }
                    .cert-container {
                        box-shadow: none;
                        max-width: 100%;
                    }
                }
            </style>
        </head>
        <body>
            <div class="cert-container">
                <div class="header-inst">Universidad Nacional de San Antonio Abad del Cusco</div>
                <div class="sub-inst">Sistema Integral de Gestión de Capacitaciones • SIGC-CUSCO</div>
                
                <h1 class="title-cert">Certificado Oficial</h1>
                <div class="subtitle-cert">Acreditación Académica y Asistencia Digital</div>

                <div class="otorgado">Se otorga el presente reconocimiento a:</div>
                <div class="recipient-name">${record.student_name}</div>

                <div class="body-text">
                    Por haber participado y aprobado satisfactoriamente el curso especializado:
                    <span class="course-title">"${record.course_title}"</span>
                    con una duración de <strong>${formatHours(record.hours)}</strong> lectivas, desarrollado
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
            <script>
                window.onload = function() {
                    window.print();
                };
            <\/script>
        </body>
        </html>
    `);
    printWindow.document.close();
}

onMounted(() => {
    if (dniQuery.value && /^\d{8}$/.test(dniQuery.value.trim())) {
        searchCertificates();
    }
});
</script>

<template>
    <div class="min-h-screen flex flex-col justify-between bg-slate-50/70 dark:bg-slate-950 text-slate-900 dark:text-slate-100 antialiased selection:bg-rose-900 selection:text-white">
        <Head title="Consulta y Descarga de Certificados Digitales - SIGC-CUSCO" />

        <!-- TOP INSTITUTIONAL HEADER -->
        <header class="sticky top-0 z-40 w-full border-b border-slate-200/80 dark:border-slate-800 bg-white/95 dark:bg-slate-950/90 backdrop-blur-md shadow-xs">
            <div class="max-w-7xl mx-auto flex h-16 items-center justify-between px-4 sm:px-6 lg:px-8">
                <!-- Brand Logo -->
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
                                UNSAAC • Verificación de Diplomas Oficiales
                            </p>
                        </div>
                    </Link>
                </div>

                <!-- Navigation Links -->
                <nav class="hidden md:flex items-center gap-6 text-xs font-bold text-slate-700 dark:text-slate-300">
                    <Link href="/" class="hover:text-rose-900 dark:hover:text-rose-400 transition-colors flex items-center gap-1.5">
                        <ArrowLeft class="size-3.5 text-rose-800" />
                        <span>Inicio</span>
                    </Link>
                    <Link href="/courses" class="hover:text-rose-900 dark:hover:text-rose-400 transition-colors flex items-center gap-1.5">
                        <GraduationCap class="size-4 text-rose-800" />
                        <span>Capacitaciones</span>
                    </Link>
                    <span class="text-rose-900 dark:text-rose-400 flex items-center gap-1.5 border-b-2 border-rose-900 pb-1">
                        <Award class="size-4 text-amber-600" />
                        <span>Certificados Digitales</span>
                    </span>
                </nav>

                <!-- Auth / Guest Actions -->
                <div class="flex items-center gap-3">
                    <div class="hidden lg:block">
                        <PeruGeoBadge />
                    </div>

                    <template v-if="authUser">
                        <Button as-child size="sm" class="bg-rose-900 hover:bg-rose-950 text-white font-bold text-xs shadow-xs">
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
                            class="text-xs font-bold text-slate-800 hover:text-rose-900"
                            @click="isLoginModalOpen = true"
                        >
                            <LogIn class="size-3.5 mr-1 text-rose-900" />
                            Ingresar
                        </Button>
                        <Button
                            size="sm"
                            class="bg-rose-900 hover:bg-rose-950 text-white font-bold text-xs shadow-xs"
                            @click="isRegisterModalOpen = true"
                        >
                            <UserPlus class="size-3.5 mr-1" />
                            Registrarse
                        </Button>
                    </template>
                </div>
            </div>
        </header>

        <!-- MAIN CONTENT AREA -->
        <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12 space-y-8">
            <!-- HERO HEADER OF THE PAGE -->
            <div class="text-center max-w-3xl mx-auto space-y-4">
                <div class="inline-flex items-center gap-2 px-3.5 py-1 rounded-full bg-rose-100/90 dark:bg-rose-950/60 border border-rose-300 dark:border-rose-800 text-rose-950 dark:text-rose-200 text-xs font-bold shadow-xs">
                    <ShieldCheck class="size-4 text-rose-800 dark:text-rose-400" />
                    <span>Validación Oficial UNSAAC / Perú</span>
                </div>

                <h1 class="text-3xl sm:text-4xl md:text-5xl font-black tracking-tight text-slate-950 dark:text-white leading-tight">
                    Consulta y Descarga de <span class="text-rose-900 dark:text-rose-400 underline decoration-amber-500 decoration-4 underline-offset-4">Certificados Digitales</span>
                </h1>

                <p class="text-sm sm:text-base text-slate-700 dark:text-neutral-300 max-w-2xl mx-auto leading-relaxed font-medium">
                    Ingresa tu número de DNI para verificar en tiempo real tus cursos aprobados, registrar horas académicas acreditadas y descargar tus diplomas oficiales en formato PDF.
                </p>
            </div>

            <!-- SEARCH CARD -->
            <Card class="max-w-2xl mx-auto border-2 border-rose-900/20 dark:border-rose-900/40 shadow-md bg-white dark:bg-slate-900 overflow-hidden">
                <CardHeader class="bg-gradient-to-r from-rose-50/90 via-amber-50/30 to-white dark:from-rose-950/40 dark:to-slate-900 border-b pb-4">
                    <div class="flex items-center gap-2 text-xs font-black text-rose-900 dark:text-rose-300 uppercase tracking-wider">
                        <Search class="size-4 text-rose-800" />
                        <span>Búsqueda por Documento Nacional de Identidad</span>
                    </div>
                    <CardTitle class="text-lg font-bold text-slate-950 dark:text-white">
                        Consulta Inmediata con DNI
                    </CardTitle>
                    <CardDescription class="text-xs text-slate-600 dark:text-slate-400 font-medium">
                        Sistema conectado al padrón institucional de matrículas y control de asistencia QR.
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
                                class="pl-10 font-mono text-base tracking-widest font-bold text-slate-950 border-slate-300 focus-visible:ring-rose-900 h-11"
                                :disabled="loading"
                                autofocus
                            />
                            <div class="absolute right-3 top-1/2 -translate-y-1/2 text-[11px] font-mono font-bold text-slate-400">
                                {{ dniQuery.length }}/8
                            </div>
                        </div>

                        <Button
                            type="submit"
                            :disabled="loading || dniQuery.length !== 8"
                            class="bg-rose-900 hover:bg-rose-950 text-white font-bold h-11 px-6 shadow-sm shadow-rose-900/20 shrink-0 text-sm"
                        >
                            <Loader2 v-if="loading" class="size-4 mr-2 animate-spin" />
                            <Award v-else class="size-4 mr-2 text-amber-300" />
                            <span>Consultar Certificados</span>
                        </Button>
                    </form>

                    <!-- Feedback error -->
                    <div v-if="error" class="mt-4 p-3 rounded-lg bg-rose-50 border border-rose-200 text-rose-900 text-xs font-semibold flex items-center gap-2">
                        <AlertCircle class="size-4 shrink-0 text-rose-700" />
                        <span>{{ error }}</span>
                    </div>
                </CardContent>
            </Card>

            <!-- RESULTS SECTION -->
            <div v-if="loading" class="max-w-4xl mx-auto py-12 text-center space-y-3">
                <Loader2 class="size-8 mx-auto animate-spin text-rose-900" />
                <p class="text-sm font-bold text-slate-800">
                    Buscando registros y validaciones para el DNI {{ dniQuery }}...
                </p>
            </div>

            <div v-else-if="searched" class="max-w-4xl mx-auto space-y-6">
                <!-- If records found -->
                <template v-if="records.length > 0">
                    <!-- Participant Verified Profile Header -->
                    <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
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
                            <Badge variant="outline" class="border-rose-300 text-rose-900 font-bold text-xs py-1 px-3">
                                <Award class="size-3.5 mr-1 text-rose-800" />
                                {{ records.length }} {{ records.length === 1 ? 'Capacitación Encontrada' : 'Capacitaciones Encontradas' }}
                            </Badge>
                        </div>
                    </div>

                    <!-- Courses Cards Grid -->
                    <div class="space-y-4">
                        <Card
                            v-for="record in records"
                            :key="record.id"
                            class="border border-slate-200 dark:border-slate-800 shadow-sm hover:shadow-md transition-shadow bg-white dark:bg-slate-900 overflow-hidden"
                        >
                            <CardHeader class="p-5 pb-3 bg-slate-50/60 dark:bg-slate-900/80 border-b flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-xs font-black font-mono px-2 py-0.5 rounded bg-rose-900 text-white">
                                        {{ record.course_code }}
                                    </span>
                                    <span class="text-xs font-bold text-slate-700 dark:text-slate-300 flex items-center gap-1">
                                        <Building2 class="size-3 text-slate-400" />
                                        {{ record.institution }}
                                    </span>
                                </div>

                                <Badge
                                    :class="record.status === 'aprobado' ? 'bg-rose-100 text-rose-950 border-rose-300 dark:bg-rose-950 dark:text-rose-200 dark:border-rose-800' : 'bg-sky-100 text-sky-900 border-sky-300 dark:bg-sky-950 dark:text-sky-200'"
                                    class="text-xs font-bold capitalize self-start sm:self-auto"
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
                                        <span class="bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded font-black text-rose-900 dark:text-rose-400">
                                            {{ record.certificate_code }}
                                        </span>
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            class="text-xs font-bold border-slate-300 hover:text-rose-900 hover:bg-rose-50"
                                            @click="openCertificatePreview(record)"
                                        >
                                            <FileCheck class="size-3.5 mr-1 text-slate-700" />
                                            Vista Previa
                                        </Button>

                                        <Button
                                            size="sm"
                                            class="bg-rose-900 hover:bg-rose-950 text-white font-bold text-xs shadow-xs"
                                            @click="printCertificate(record)"
                                        >
                                            <Download class="size-3.5 mr-1" />
                                            Descargar Certificado PDF
                                        </Button>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </template>

                <!-- If NO records found for DNI -->
                <div v-else class="p-8 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-center space-y-4">
                    <div class="size-14 rounded-full bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-400 mx-auto flex items-center justify-center">
                        <AlertCircle class="size-7" />
                    </div>
                    <div class="space-y-1">
                        <h3 class="text-lg font-bold text-slate-950 dark:text-white">
                            No se encontraron cursos concluidos para el DNI {{ dniQuery }}
                        </h3>
                        <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-400 max-w-md mx-auto leading-relaxed">
                            Es posible que aún te encuentres en proceso de capacitación o que tu asistencia esté pendiente de cierre por la coordinación académica.
                        </p>
                    </div>

                    <div class="pt-2 flex flex-col sm:flex-row items-center justify-center gap-3">
                        <Button as-child variant="outline" size="sm" class="text-xs font-bold border-slate-300">
                            <Link href="/courses">
                                <GraduationCap class="size-3.5 mr-1 text-rose-900" />
                                Ver Cursos Disponibles
                            </Link>
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            class="text-xs font-bold text-slate-700 hover:text-rose-900"
                            @click="dniQuery = ''; searched = false;"
                        >
                            Consultar con otro DNI
                        </Button>
                    </div>
                </div>
            </div>

            <!-- INFORMATIONAL FOOTER BANNER -->
            <div class="max-w-4xl mx-auto rounded-2xl bg-gradient-to-r from-rose-900 to-[#701a31] text-white p-6 sm:p-8 flex flex-col md:flex-row items-center justify-between gap-6 shadow-md">
                <div class="space-y-2 text-center md:text-left">
                    <div class="inline-flex items-center gap-1.5 text-xs font-black uppercase tracking-wider text-amber-300">
                        <Sparkles class="size-4" />
                        <span>Acreditación Institucional Garantizada</span>
                    </div>
                    <h3 class="text-lg sm:text-xl font-black tracking-tight text-white">
                        ¿Requieres validar un código impreso?
                    </h3>
                    <p class="text-xs sm:text-sm text-rose-100/90 max-w-lg leading-relaxed">
                        Cada certificado cuenta con un código digital intransferible y control biométrico mediante cruce de datos con RENIEC Perú y registro de QR.
                    </p>
                </div>

                <div class="shrink-0 flex flex-col sm:flex-row gap-3">
                    <Button as-child variant="secondary" size="sm" class="bg-white text-rose-950 hover:bg-rose-50 font-black text-xs shadow-xs">
                        <Link href="/courses">
                            Explorar Convocatorias
                        </Link>
                    </Button>
                </div>
            </div>
        </main>

        <!-- FOOTER -->
        <footer class="border-t border-slate-200/80 dark:border-slate-800 bg-white/80 dark:bg-slate-950 py-8 text-xs text-slate-600 dark:text-neutral-400">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-2 font-medium">
                    <GraduationCap class="size-4 text-rose-900" />
                    <span class="font-bold text-slate-950 dark:text-white">
                        SIGC-CUSCO
                    </span>
                    <span>— Sistema Integral de Gestión de Capacitaciones • UNSAAC</span>
                </div>
                <div class="font-medium text-slate-700 dark:text-slate-300">
                    Portal Oficial de Verificación de Diplomas • Cusco, Perú 2026
                </div>
            </div>
        </footer>

        <!-- MODAL DE VISTA PREVIA DEL CERTIFICADO -->
        <Dialog v-model:open="isCertModalOpen">
            <DialogContent class="w-[96vw] sm:max-w-3xl max-h-[90vh] flex flex-col p-0 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 overflow-hidden">
                <div class="p-5 sm:p-6 pb-3 border-b border-slate-200 dark:border-slate-800 shrink-0 pr-12">
                    <DialogHeader class="text-left space-y-1">
                        <div class="flex items-center gap-2 text-xs font-black text-rose-900 dark:text-rose-400 uppercase tracking-wider">
                            <Award class="size-4 text-rose-800" />
                            <span>Vista Previa del Diploma Digital</span>
                        </div>
                        <DialogTitle class="text-lg font-black text-slate-950 dark:text-white">
                            {{ selectedCert?.course_title }}
                        </DialogTitle>
                        <DialogDescription class="text-xs text-slate-600 dark:text-slate-400 font-medium">
                            Certificado oficial emitido bajo supervisión académica institucional.
                        </DialogDescription>
                    </DialogHeader>
                </div>

                <div class="flex-1 overflow-y-auto overscroll-contain custom-scrollbar p-5 sm:p-6">
                    <div v-if="selectedCert" class="space-y-6">
                        <!-- Vista previa realista con doble marco Granate Imperial -->
                        <div class="p-6 sm:p-8 bg-[#fdfbf7] dark:bg-slate-950 border-4 border-double border-[#800020] text-center space-y-4 rounded-lg shadow-inner relative">
                            <div class="text-[11px] font-black tracking-widest text-[#800020] uppercase">
                                Universidad Nacional de San Antonio Abad del Cusco
                            </div>
                            <div class="text-[10px] tracking-wider text-slate-500 font-semibold uppercase">
                                Sistema Integral de Gestión de Capacitaciones (SIGC-CUSCO)
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
                                del {{ selectedCert.start_date }} al {{ selectedCert.end_date }}, cumpliendo con
                                las asistencias por QR y las evaluaciones oficiales.
                            </div>

                            <div class="pt-6 grid grid-cols-2 gap-6 text-xs border-t border-slate-300 dark:border-slate-800">
                                <div>
                                    <div class="font-bold text-slate-900 dark:text-white">{{ selectedCert.instructor_name }}</div>
                                    <div class="text-[10px] text-slate-500 uppercase">Docente Instructor</div>
                                </div>
                                <div>
                                    <div class="font-bold text-slate-900 dark:text-white">Dirección Académica</div>
                                    <div class="text-[10px] text-slate-500 uppercase">SIGC-CUSCO</div>
                                </div>
                            </div>

                            <div class="pt-3 text-[10px] font-mono text-slate-500 flex justify-between items-center border-t border-dashed border-slate-300 dark:border-slate-800">
                                <span>Código: <strong>{{ selectedCert.certificate_code }}</strong></span>
                                <span class="text-[#800020] font-bold">VÁLIDO OFICIALMENTE</span>
                            </div>
                        </div>
                    </div>
                </div>

                <DialogFooter class="flex flex-col sm:flex-row items-center justify-between gap-3 p-4 border-t border-slate-200 dark:border-slate-800 shrink-0 bg-slate-50/80 dark:bg-slate-950/60">
                    <div class="flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            class="text-xs font-bold"
                            @click="selectedCert && copyVerificationCode(selectedCert.certificate_code)"
                        >
                            <Check v-if="copiedCode" class="size-3.5 mr-1 text-rose-800" />
                            <Copy v-else class="size-3.5 mr-1" />
                            <span>{{ copiedCode ? '¡Código Copiado!' : 'Copiar Código' }}</span>
                        </Button>
                    </div>

                    <div class="flex items-center gap-2">
                        <Button variant="ghost" size="sm" class="text-xs" @click="isCertModalOpen = false">
                            Cerrar
                        </Button>
                        <Button
                            size="sm"
                            class="bg-rose-900 hover:bg-rose-950 text-white font-bold text-xs shadow-xs"
                            @click="selectedCert && printCertificate(selectedCert)"
                        >
                            <Printer class="size-3.5 mr-1" />
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
