<script setup lang="ts">
import { ref, computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { formatDateRange, formatDate } from '@/lib/formatters';
import { THEME_BUTTONS } from '@/lib/theme';
import { generateQrSvg } from '@/lib/qr';
import { generateOfficialCertificateHtml, printCertificate, type CertificateModule, type CertificateRecord } from '@/lib/certificateTemplate';
import { notify } from '@/lib/notify';
import {
    Calendar,
    Clock,
    Users,
    CheckCircle2,
    Award,
    UserCheck,
    Building2,
    Sparkles,
    ShieldCheck,
    FileText,
    ExternalLink,
    Plus,
    Download,
    QrCode,
    GraduationCap,
    Check,
    Mail,
    BookOpen,
    Layers,
    User,
    ArrowRight,
    Copy,
} from '@lucide/vue';

export interface PublicCourseDetail {
    id: number;
    code: string;
    title: string;
    institution?: string;
    description?: string;
    start_date: string;
    end_date: string;
    hours: number;
    total_sessions: number;
    min_attendance_percentage: number;
    capacity: number;
    status: 'abierto' | 'en_curso' | 'concluido' | 'cancelado';
    enrollments_count?: number;
    instructor_name?: string;
    instructor_display_name?: string;
    instructor?: {
        id: number;
        name: string;
        paterno?: string;
        materno?: string;
        email?: string;
        role?: string;
    } | null;
}

export interface MyEnrollmentInfo {
    id: number;
    dni: string;
    nombres: string;
    paterno: string;
    materno?: string | null;
    full_name: string;
    email: string;
    phone?: string | null;
    status: 'inscrito' | 'en_curso' | 'aprobado' | 'reprobado' | 'cancelado';
    attended_sessions: number;
    attendance_percentage: number;
    final_grade?: number | string | null;
    credential_code?: string | null;
    certificate_code?: string | null;
    certificate_hash?: string | null;
    certificate_issued_at?: string | null;
    certificate?: CertificateRecord | null;
}

const props = defineProps<{
    course: PublicCourseDetail;
    modules: CertificateModule[];
    myEnrollment?: MyEnrollmentInfo | null;
}>();

const emit = defineEmits<{
    (e: 'openEnrollment'): void;
}>();

// Cálculos de aforo público
const totalEnrolled = computed(() => props.course.enrollments_count ?? 0);
const availableSpots = computed(() => Math.max(0, props.course.capacity - totalEnrolled.value));
const enrollmentPercentage = computed(() => {
    if (!props.course.capacity || props.course.capacity <= 0) return 0;
    return Math.min(100, Math.round((totalEnrolled.value / props.course.capacity) * 100));
});

// Nombre y detalles del docente
const instructorDisplayName = computed(() => {
    if (props.course.instructor) {
        const u = props.course.instructor;
        const full = `${u.name} ${u.paterno || ''} ${u.materno || ''}`.trim();
        return full || u.name;
    }
    return props.course.instructor_display_name || props.course.instructor_name || 'Docente Titular UNSAAC';
});

// Estado badge
const statusConfig = computed(() => {
    switch (props.course.status) {
        case 'abierto':
            return {
                label: 'Inscripciones Abiertas',
                badgeClass: 'bg-emerald-100 text-emerald-900 border-emerald-300 dark:bg-emerald-950 dark:text-emerald-200',
                canEnroll: true,
            };
        case 'en_curso':
            return {
                label: 'Capacitación En Curso',
                badgeClass: 'bg-amber-100 text-amber-950 border-amber-300 dark:bg-amber-950 dark:text-amber-200',
                canEnroll: false,
            };
        case 'concluido':
            return {
                label: 'Capacitación Concluida',
                badgeClass: 'bg-slate-100 text-slate-800 border-slate-300 dark:bg-slate-800 dark:text-slate-300',
                canEnroll: false,
            };
        case 'cancelado':
            return {
                label: 'Capacitación Cancelada',
                badgeClass: 'bg-rose-100 text-rose-950 border-rose-300 dark:bg-rose-950 dark:text-rose-200',
                canEnroll: false,
            };
        default:
            return {
                label: String(props.course.status).toUpperCase(),
                badgeClass: 'bg-slate-100 text-slate-800 border-slate-300',
                canEnroll: false,
            };
    }
});

// Modal de Credencial QR Personal del Alumno
const isCredentialModalOpen = ref(false);
const credentialQrSvg = computed(() => {
    if (!props.myEnrollment) return '';
    const code = props.myEnrollment.credential_code || `INS-${props.course.id}-${props.myEnrollment.dni.slice(-4)}`;
    return generateQrSvg(code, 260, '#800020');
});

// Modal y Visor de Certificado Oficial (Anverso / Reverso)
const isCertificateModalOpen = ref(false);
const activeCertificateFace = ref<'front' | 'back'>('front');

function openCertificatePreview() {
    if (!props.myEnrollment?.certificate) return;
    activeCertificateFace.value = 'front';
    isCertificateModalOpen.value = true;
}

function handlePrintCertificate() {
    if (!props.myEnrollment?.certificate) return;
    printCertificate(props.myEnrollment.certificate);
}

async function copyCode(code: string) {
    try {
        await navigator.clipboard.writeText(code);
        notify.success('Copiado al portapapeles', code, 1500);
    } catch {
        notify.info('Código', code, 2000);
    }
}
</script>

<template>
    <div class="space-y-8">
        <!-- 1. HERO INFORMATIVO INSTITUCIONAL -->
        <div class="relative overflow-hidden rounded-2xl border border-rose-900/20 bg-gradient-to-br from-rose-950 via-[#4a0011] to-slate-950 text-white p-6 sm:p-8 lg:p-10 shadow-lg">
            <!-- Efecto decorativo de fondo -->
            <div class="absolute -right-16 -top-16 size-72 rounded-full bg-rose-500/10 blur-3xl pointer-events-none" />
            <div class="absolute -left-16 -bottom-16 size-72 rounded-full bg-amber-500/10 blur-3xl pointer-events-none" />

            <div class="relative z-10 space-y-4">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="font-mono text-xs font-black tracking-wider uppercase px-2.5 py-1 rounded-md bg-white/10 backdrop-blur-xs border border-white/20 text-rose-200">
                        {{ course.code }}
                    </span>
                    <Badge variant="outline" :class="[statusConfig.badgeClass, 'text-xs font-black border']">
                        {{ statusConfig.label }}
                    </Badge>
                    <span v-if="course.institution" class="flex items-center gap-1.5 text-xs text-rose-200/90 font-medium">
                        <Building2 class="size-3.5 text-amber-300" />
                        {{ course.institution }}
                    </span>
                </div>

                <div class="max-w-4xl space-y-2">
                    <h1 class="text-2xl sm:text-3xl lg:text-4xl font-black tracking-tight text-white leading-tight">
                        {{ course.title }}
                    </h1>
                    <p v-if="course.description" class="text-sm sm:text-base text-rose-100/90 leading-relaxed font-normal pt-1">
                        {{ course.description }}
                    </p>
                </div>

                <!-- Call to action principal -->
                <div class="pt-2 flex flex-wrap items-center gap-3">
                    <!-- Si el usuario logueado ya está matriculado -->
                    <div v-if="myEnrollment" class="flex items-center gap-2 px-3.5 py-2 rounded-xl bg-emerald-950/80 border border-emerald-500/40 text-emerald-200 text-xs font-bold">
                        <CheckCircle2 class="size-4 text-emerald-400" />
                        <span>Ya te encuentras matriculado en este curso</span>
                    </div>

                    <!-- Si el curso está abierto y hay vacantes -->
                    <Button
                        v-else-if="statusConfig.canEnroll && availableSpots > 0"
                        size="lg"
                        class="bg-amber-600 hover:bg-amber-500 text-slate-950 font-black shadow-md text-sm px-6 cursor-pointer"
                        @click="emit('openEnrollment')"
                    >
                        <Plus class="mr-2 size-4" />
                        Inscribirme en esta Capacitación
                    </Button>

                    <!-- Si está abierto pero no hay vacantes -->
                    <div v-else-if="statusConfig.canEnroll && availableSpots <= 0" class="px-4 py-2 rounded-xl bg-rose-900/60 border border-rose-500/30 text-rose-200 text-xs font-bold">
                        Vacantes agotadas (Aforo completo)
                    </div>

                    <!-- Si no está abierto -->
                    <div v-else class="px-4 py-2 rounded-xl bg-white/10 text-white/80 text-xs font-bold">
                        Inscripciones no disponibles actualmente
                    </div>

                    <Button as-child variant="ghost" size="sm" class="text-rose-200 hover:text-white hover:bg-white/10 text-xs">
                        <Link href="/certificates">
                            <ShieldCheck class="mr-1.5 size-3.5 text-amber-300" />
                            Verificar Certificaciones Oficiales
                        </Link>
                    </Button>
                </div>
            </div>
        </div>

        <!-- 2. TARJETAS DE MÉTRICAS PÚBLICAS Y SEGURAS (FICHA TÉCNICA RÁPIDA) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Horas Lectivas -->
            <Card class="border-slate-200 dark:border-slate-800 shadow-xs hover:border-rose-300 transition-all">
                <CardHeader class="pb-2">
                    <CardDescription class="text-xs font-bold text-slate-600 dark:text-slate-400 flex items-center justify-between">
                        <span>Horas Académicas</span>
                        <div class="size-8 rounded-lg bg-rose-100 text-rose-900 dark:bg-rose-950 dark:text-rose-200 flex items-center justify-center">
                            <Clock class="size-4" />
                        </div>
                    </CardDescription>
                    <CardTitle class="text-2xl font-black text-slate-900 dark:text-white pt-1">
                        {{ course.hours }} hrs.
                    </CardTitle>
                </CardHeader>
                <CardContent class="text-xs text-slate-600 dark:text-slate-400">
                    Capacitación lectiva y práctica certificada
                </CardContent>
            </Card>

            <!-- Sesiones y Calendario -->
            <Card class="border-slate-200 dark:border-slate-800 shadow-xs hover:border-amber-300 transition-all">
                <CardHeader class="pb-2">
                    <CardDescription class="text-xs font-bold text-slate-600 dark:text-slate-400 flex items-center justify-between">
                        <span>Sesiones de Clase</span>
                        <div class="size-8 rounded-lg bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-200 flex items-center justify-center">
                            <Calendar class="size-4" />
                        </div>
                    </CardDescription>
                    <CardTitle class="text-2xl font-black text-slate-900 dark:text-white pt-1">
                        {{ course.total_sessions }} sesiones
                    </CardTitle>
                </CardHeader>
                <CardContent class="text-xs text-slate-600 dark:text-slate-400">
                    {{ formatDateRange(course.start_date, course.end_date) }}
                </CardContent>
            </Card>

            <!-- Asistencia Mínima Requerida -->
            <Card class="border-slate-200 dark:border-slate-800 shadow-xs hover:border-emerald-300 transition-all">
                <CardHeader class="pb-2">
                    <CardDescription class="text-xs font-bold text-slate-600 dark:text-slate-400 flex items-center justify-between">
                        <span>Asistencia Requerida</span>
                        <div class="size-8 rounded-lg bg-emerald-100 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-200 flex items-center justify-center">
                            <UserCheck class="size-4" />
                        </div>
                    </CardDescription>
                    <CardTitle class="text-2xl font-black text-slate-900 dark:text-white pt-1">
                        {{ course.min_attendance_percentage }}% mínimo
                    </CardTitle>
                </CardHeader>
                <CardContent class="text-xs text-slate-600 dark:text-slate-400">
                    Obligatorio para obtención de certificación
                </CardContent>
            </Card>

            <!-- Vacantes y Disponibilidad -->
            <Card class="border-slate-200 dark:border-slate-800 shadow-xs hover:border-rose-300 transition-all">
                <CardHeader class="pb-2">
                    <CardDescription class="text-xs font-bold text-slate-600 dark:text-slate-400 flex items-center justify-between">
                        <span>Vacantes Disponibles</span>
                        <div class="size-8 rounded-lg bg-slate-100 text-slate-900 dark:bg-slate-800 dark:text-slate-200 flex items-center justify-center">
                            <Users class="size-4" />
                        </div>
                    </CardDescription>
                    <CardTitle class="text-2xl font-black text-rose-950 dark:text-rose-300 pt-1 flex items-baseline gap-2">
                        <span>{{ availableSpots }}</span>
                        <span class="text-xs font-normal text-slate-500">de {{ course.capacity }} cupos</span>
                    </CardTitle>
                </CardHeader>
                <CardContent class="text-xs text-slate-600 dark:text-slate-400 space-y-1.5">
                    <div class="w-full bg-slate-200 dark:bg-slate-800 h-1.5 rounded-full overflow-hidden">
                        <div
                            class="h-full bg-rose-900 dark:bg-rose-600 rounded-full transition-all"
                            :style="{ width: `${enrollmentPercentage}%` }"
                        />
                    </div>
                    <div class="text-[11px] text-slate-500">
                        {{ totalEnrolled }} participantes registrados
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- 3. SI EL PARTICIPANTE ESTÁ AUTENTICADO Y MATRICULADO: TARJETA CONFIDENCIAL PERSONAL -->
        <Card v-if="myEnrollment" class="border-2 border-emerald-400 dark:border-emerald-800 bg-emerald-50/40 dark:bg-emerald-950/20 shadow-md">
            <CardHeader class="p-5 border-b border-emerald-200 dark:border-emerald-900/60 bg-emerald-100/50 dark:bg-emerald-900/30">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2">
                            <Badge class="bg-emerald-700 text-white font-bold text-xs">
                                <Check class="size-3 mr-1" />
                                Matrícula Activa
                            </Badge>
                            <span class="text-xs font-mono font-bold text-slate-600 dark:text-slate-400">
                                DNI: {{ myEnrollment.dni }}
                            </span>
                        </div>
                        <CardTitle class="text-lg font-black text-slate-900 dark:text-white">
                            Tu Estado Académico en este Curso
                        </CardTitle>
                        <CardDescription class="text-xs text-slate-700 dark:text-slate-300">
                            Participante: <strong>{{ myEnrollment.full_name }}</strong>
                        </CardDescription>
                    </div>

                    <!-- Botones de Acción Personal -->
                    <div class="flex flex-wrap items-center gap-2">
                        <!-- Botón Ver Credencial con QR -->
                        <Button
                            variant="outline"
                            size="sm"
                            class="bg-white dark:bg-slate-900 text-xs font-bold border-emerald-300 dark:border-emerald-800 hover:bg-emerald-50"
                            @click="isCredentialModalOpen = true"
                        >
                            <QrCode class="mr-1.5 size-3.5 text-rose-900 dark:text-rose-400" />
                            Mi Credencial QR
                        </Button>

                        <!-- Botón Descargar Certificado si fue emitido -->
                        <Button
                            v-if="myEnrollment.certificate"
                            size="sm"
                            class="bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-black shadow-xs"
                            @click="openCertificatePreview"
                        >
                            <Award class="mr-1.5 size-4 text-amber-300" />
                            Ver / Descargar Mi Certificado Oficial
                        </Button>
                    </div>
                </div>
            </CardHeader>

            <CardContent class="p-5 space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <!-- Estado Académico -->
                    <div class="p-3.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                        <div class="text-xs font-bold text-slate-500 uppercase tracking-wider">Estado Académico</div>
                        <div class="mt-1 text-sm font-black capitalize text-slate-900 dark:text-white flex items-center gap-1.5">
                            <span class="size-2 rounded-full" :class="myEnrollment.status === 'aprobado' ? 'bg-emerald-500' : 'bg-amber-500'" />
                            {{ myEnrollment.status.replace('_', ' ') }}
                        </div>
                    </div>

                    <!-- Asistencia Acumulada -->
                    <div class="p-3.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                        <div class="text-xs font-bold text-slate-500 uppercase tracking-wider">Asistencia Registrada</div>
                        <div class="mt-1 text-sm font-black text-slate-900 dark:text-white">
                            {{ myEnrollment.attended_sessions }} de {{ course.total_sessions }} sesiones ({{ myEnrollment.attendance_percentage }}%)
                        </div>
                        <div class="w-full bg-slate-100 dark:bg-slate-800 h-1.5 rounded-full overflow-hidden mt-1.5">
                            <div
                                class="h-full rounded-full transition-all"
                                :class="myEnrollment.attendance_percentage >= course.min_attendance_percentage ? 'bg-emerald-600' : 'bg-amber-500'"
                                :style="{ width: `${Math.min(100, myEnrollment.attendance_percentage)}%` }"
                            />
                        </div>
                    </div>

                    <!-- Calificación Final -->
                    <div class="p-3.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                        <div class="text-xs font-bold text-slate-500 uppercase tracking-wider">Calificación Final</div>
                        <div v-if="myEnrollment.final_grade !== null && myEnrollment.final_grade !== undefined" class="mt-1 text-base font-black text-rose-950 dark:text-rose-300">
                            {{ Number(myEnrollment.final_grade).toFixed(2) }} / 20.00
                        </div>
                        <div v-else class="mt-1 text-xs font-bold text-slate-500 italic">
                            Pendiente de calificación final
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- 4. CONTENIDO PRINCIPAL: 2 COLUMNAS (CURRÍCULO Y DOCENTE) -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Columna Izquierda (2/3): Malla Curricular y Certificación -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Malla Curricular / Temario Oficial -->
                <Card class="border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
                    <CardHeader class="p-5 border-b bg-slate-50/80 dark:bg-slate-900/80">
                        <CardTitle class="text-base font-black flex items-center gap-2">
                            <Layers class="size-4 text-rose-900 dark:text-rose-400" />
                            Plan de Estudios y Malla Curricular Oficial
                        </CardTitle>
                        <CardDescription class="text-xs">
                            Estructura académica organizada en módulos temáticos con horas pedagógicas acreditadas.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="p-5 space-y-4">
                        <div
                            v-for="(mod, idx) in modules"
                            :key="idx"
                            class="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 space-y-2 hover:border-rose-300 transition-all"
                        >
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1">
                                <div class="flex items-center gap-2">
                                    <span class="font-mono text-xs font-black text-rose-950 dark:text-rose-200 bg-rose-100 dark:bg-rose-950 px-2 py-0.5 rounded border border-rose-300 dark:border-rose-800">
                                        {{ mod.number }}
                                    </span>
                                    <h3 class="text-sm font-black text-slate-900 dark:text-white">
                                        {{ mod.title }}
                                    </h3>
                                </div>
                                <span class="text-xs font-bold text-slate-500 shrink-0">
                                    {{ mod.hours }}
                                </span>
                            </div>
                            <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed pl-1">
                                {{ mod.topics }}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <!-- Garantía de Calidad y Certificación UNSAAC -->
                <Card class="border-rose-200 dark:border-rose-900/40 bg-gradient-to-br from-rose-50/40 via-white to-amber-50/30 dark:from-rose-950/20 dark:via-slate-900 dark:to-amber-950/10 shadow-sm">
                    <CardHeader class="p-5 border-b border-rose-100 dark:border-rose-900/40">
                        <CardTitle class="text-base font-black flex items-center gap-2 text-rose-950 dark:text-rose-200">
                            <Sparkles class="size-4 text-amber-600" />
                            Certificación Oficial con Validez Nacional
                        </CardTitle>
                        <CardDescription class="text-xs text-rose-900/80 dark:text-rose-300/80">
                            Garantía académica institucional respaldada por la Universidad Nacional de San Antonio Abad del Cusco.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="p-5 space-y-3 text-xs text-slate-700 dark:text-slate-300 leading-relaxed">
                        <div class="flex items-start gap-2.5">
                            <CheckCircle2 class="size-4 text-emerald-600 shrink-0 mt-0.5" />
                            <span><strong>Requisito de Aprobación:</strong> Haber alcanzado una calificación vigesimal mínima de 11.00 puntos y cumplir con al menos el {{ course.min_attendance_percentage }}% de asistencias a las clases programadas.</span>
                        </div>
                        <div class="flex items-start gap-2.5">
                            <CheckCircle2 class="size-4 text-emerald-600 shrink-0 mt-0.5" />
                            <span><strong>Código QR Perpetuo e Inalterable:</strong> Cada diploma incorpora un código QR vectorial nativo de validación permanente que redirige a los registros oficiales en vivo.</span>
                        </div>
                        <div class="flex items-start gap-2.5">
                            <CheckCircle2 class="size-4 text-emerald-600 shrink-0 mt-0.5" />
                            <span><strong>Formato Oficial de 2 Caras:</strong> Diploma de Honor institucional en el anverso y detalle completo de la malla curricular con firma digital en el reverso.</span>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Columna Derecha (1/3): Docente Responsable y Admisión -->
            <div class="space-y-6">
                <!-- Tarjeta del Docente Responsable -->
                <Card class="border-slate-200 dark:border-slate-800 shadow-sm">
                    <CardHeader class="p-5 border-b bg-slate-50/80 dark:bg-slate-900/80">
                        <CardTitle class="text-sm font-black flex items-center gap-2">
                            <GraduationCap class="size-4 text-rose-900 dark:text-rose-400" />
                            Docente Responsable
                        </CardTitle>
                        <CardDescription class="text-xs">
                            Catedrático o especialista a cargo de la cátedra
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="p-5 space-y-3">
                        <div class="flex items-center gap-3">
                            <div class="size-12 rounded-full bg-rose-900 text-amber-300 flex items-center justify-center font-black text-sm shrink-0 shadow-xs">
                                {{ instructorDisplayName.slice(0, 2).toUpperCase() }}
                            </div>
                            <div>
                                <div class="text-sm font-black text-slate-900 dark:text-white">
                                    {{ instructorDisplayName }}
                                </div>
                                <div class="text-xs font-bold text-rose-900 dark:text-rose-400">
                                    Docente Titular / Especialista
                                </div>
                                <div class="text-[11px] text-slate-500">
                                    UNSAAC - Cusco, Perú
                                </div>
                            </div>
                        </div>

                        <div v-if="course.instructor?.email" class="pt-2 border-t text-xs flex items-center gap-2 text-slate-600 dark:text-slate-400">
                            <Mail class="size-3.5 text-slate-400" />
                            <span class="truncate">{{ course.instructor.email }}</span>
                        </div>
                    </CardContent>
                </Card>

                <!-- Requisitos y Admisión -->
                <Card class="border-slate-200 dark:border-slate-800 shadow-sm">
                    <CardHeader class="p-5 border-b bg-slate-50/80 dark:bg-slate-900/80">
                        <CardTitle class="text-sm font-black flex items-center gap-2">
                            <BookOpen class="size-4 text-rose-900 dark:text-rose-400" />
                            Requisitos de Participación
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="p-5 space-y-2.5 text-xs text-slate-700 dark:text-slate-300">
                        <div class="flex items-center gap-2">
                            <div class="size-1.5 rounded-full bg-rose-900 shrink-0" />
                            <span>Documento Nacional de Identidad (DNI) vigente.</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="size-1.5 rounded-full bg-rose-900 shrink-0" />
                            <span>Disponibilidad horaria para las {{ course.total_sessions }} sesiones.</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="size-1.5 rounded-full bg-rose-900 shrink-0" />
                            <span>Compromiso de asistencia mínima del {{ course.min_attendance_percentage }}%.</span>
                        </div>
                    </CardContent>
                </Card>

                <!-- Banner de Inscripción Rápida -->
                <div v-if="statusConfig.canEnroll && availableSpots > 0" class="p-5 rounded-2xl bg-gradient-to-br from-rose-900 to-rose-950 text-white space-y-3 shadow-md">
                    <div class="font-black text-sm">¿Deseas participar en esta capacitación?</div>
                    <p class="text-xs text-rose-200">
                        Quedan únicamente <strong>{{ availableSpots }} vacantes</strong> disponibles para este curso.
                    </p>
                    <Button
                        size="sm"
                        class="w-full bg-amber-500 hover:bg-amber-400 text-slate-950 font-black text-xs cursor-pointer"
                        @click="emit('openEnrollment')"
                    >
                        <Plus class="mr-1.5 size-3.5" />
                        Inscribirme Ahora
                    </Button>
                </div>
            </div>
        </div>

        <!-- MODAL: CREDENCIAL QR DEL ALUMNO -->
        <Dialog :open="isCredentialModalOpen" @update:open="isCredentialModalOpen = $event">
            <DialogContent class="max-w-md p-6">
                <DialogHeader>
                    <DialogTitle class="text-center font-black text-lg flex items-center justify-center gap-2">
                        <QrCode class="size-5 text-rose-900" />
                        Credencial Digital del Alumno
                    </DialogTitle>
                    <DialogDescription class="text-center text-xs">
                        Código QR personal de acreditación para registro de asistencia en aula.
                    </DialogDescription>
                </DialogHeader>

                <div v-if="myEnrollment" class="py-4 flex flex-col items-center space-y-4">
                    <div class="p-3 bg-white rounded-xl shadow-inner border border-slate-200" v-html="credentialQrSvg" />
                    <div class="text-center space-y-1">
                        <div class="font-black text-base text-slate-900 dark:text-white">
                            {{ myEnrollment.full_name }}
                        </div>
                        <div class="text-xs text-slate-500">
                            DNI: {{ myEnrollment.dni }}
                        </div>
                        <div class="pt-2">
                            <span class="font-mono text-xs font-bold px-2.5 py-1 rounded-md bg-slate-100 dark:bg-slate-800 border">
                                {{ myEnrollment.credential_code || `INS-${course.id}-${myEnrollment.dni.slice(-4)}` }}
                            </span>
                        </div>
                    </div>
                </div>
            </DialogContent>
        </Dialog>

        <!-- MODAL: VISOR DE CERTIFICADO OFICIAL DEL ALUMNO (ANVERSO / REVERSO) -->
        <Dialog :open="isCertificateModalOpen" @update:open="isCertificateModalOpen = $event">
            <DialogContent class="max-w-5xl p-6">
                <DialogHeader class="border-b pb-3">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div>
                            <DialogTitle class="text-lg font-black flex items-center gap-2">
                                <Award class="size-5 text-amber-500" />
                                Certificado Digital Oficial UNSAAC
                            </DialogTitle>
                            <DialogDescription class="text-xs mt-0.5">
                                Diploma oficial de 2 caras (Anverso / Reverso) emitido con código QR perpetuo.
                            </DialogDescription>
                        </div>
                        <div class="flex items-center gap-2">
                            <Button
                                size="sm"
                                class="bg-rose-900 hover:bg-rose-950 text-white font-black text-xs cursor-pointer shadow-xs"
                                @click="handlePrintCertificate"
                            >
                                <Download class="mr-1.5 size-3.5" />
                                Descargar PDF (2 Páginas)
                            </Button>
                        </div>
                    </div>
                </DialogHeader>

                <!-- Selector de Caras: Anverso / Reverso -->
                <div class="flex items-center justify-center gap-2 pt-2">
                    <button
                        type="button"
                        class="px-4 py-1.5 rounded-lg text-xs font-black transition-all cursor-pointer"
                        :class="activeCertificateFace === 'front' ? 'bg-rose-900 text-white shadow-xs' : 'bg-slate-100 dark:bg-slate-800 text-slate-600'"
                        @click="activeCertificateFace = 'front'"
                    >
                        Anverso (Diploma Oficial)
                    </button>
                    <button
                        type="button"
                        class="px-4 py-1.5 rounded-lg text-xs font-black transition-all cursor-pointer"
                        :class="activeCertificateFace === 'back' ? 'bg-rose-900 text-white shadow-xs' : 'bg-slate-100 dark:bg-slate-800 text-slate-600'"
                        @click="activeCertificateFace = 'back'"
                    >
                        Reverso (Malla y Calificación)
                    </button>
                </div>

                <!-- Visor Gráfico -->
                <div v-if="myEnrollment?.certificate" class="py-4">
                    <!-- Cara 1: Anverso -->
                    <div
                        v-if="activeCertificateFace === 'front'"
                        class="p-6 rounded-xl border-4 border-amber-600/40 bg-gradient-to-br from-amber-50/60 via-white to-amber-50/40 dark:from-slate-900 dark:via-slate-950 dark:to-slate-900 text-center space-y-4 shadow-inner"
                    >
                        <div class="flex items-center justify-between px-4">
                            <img src="/images/unsaac-logo.png" alt="UNSAAC" class="h-14 object-contain" />
                            <div class="text-center">
                                <div class="text-[11px] font-black tracking-widest uppercase text-rose-900 dark:text-rose-400">
                                    Universidad Nacional de San Antonio Abad del Cusco
                                </div>
                                <div class="text-[10px] font-bold text-slate-600 dark:text-slate-400">
                                    Dirección de Extensión Cultural y Proyección Social
                                </div>
                            </div>
                            <img src="/images/escudo.png" alt="Escudo" class="h-14 object-contain" />
                        </div>

                        <div class="py-4 space-y-2">
                            <div class="text-xs uppercase font-serif tracking-widest text-amber-700 dark:text-amber-400 font-bold">
                                Otorga el presente
                            </div>
                            <h2 class="text-2xl sm:text-3xl font-serif font-black text-rose-950 dark:text-rose-200 uppercase tracking-wider">
                                Diploma de Certificación
                            </h2>
                            <div class="text-xs text-slate-600 dark:text-slate-400">a favor de:</div>
                            <div class="text-xl sm:text-2xl font-serif font-black text-slate-900 dark:text-white underline decoration-amber-600/50 underline-offset-8">
                                {{ myEnrollment.certificate.student_name || myEnrollment.certificate.full_name }}
                            </div>
                            <div class="text-xs font-mono font-bold text-slate-600 dark:text-slate-400 pt-1">
                                Documento Nacional de Identidad N° {{ myEnrollment.certificate.dni }}
                            </div>
                        </div>

                        <div class="max-w-2xl mx-auto text-xs text-slate-700 dark:text-slate-300 leading-relaxed font-serif">
                            Por haber aprobado satisfactoriamente el curso de especialización profesional:
                            <div class="font-bold text-sm text-rose-950 dark:text-rose-200 py-1">
                                "{{ myEnrollment.certificate.course_title }}"
                            </div>
                            Desarrollado <strong>{{ myEnrollment.certificate.date_range_formal || formatDateRange(course.start_date, course.end_date) }}</strong>,
                            con una duración lectiva de <strong>{{ myEnrollment.certificate.hours }} horas académicas</strong>.
                        </div>

                        <div class="text-right text-[11px] font-serif font-bold text-slate-600 dark:text-slate-400 italic max-w-lg mx-auto pr-2 my-1">
                            {{ myEnrollment.certificate.city_issued_formal || ('Cusco, ' + formatDate(myEnrollment.certificate.certificate_issued_at || course.end_date)) }}
                        </div>

                        <div class="pt-4 grid grid-cols-2 gap-8 text-center text-xs font-serif border-t max-w-lg mx-auto">
                            <div>
                                <div class="font-bold border-t border-slate-400 pt-1 text-[11px]">
                                    {{ myEnrollment.certificate.instructor_name }}
                                </div>
                                <div class="text-[9px] text-slate-500">Docente Responsable</div>
                            </div>
                            <div>
                                <div class="font-bold border-t border-slate-400 pt-1 text-[11px]">
                                    Dirección Académica
                                </div>
                                <div class="text-[9px] text-slate-500">UNSAAC - Cusco</div>
                            </div>
                        </div>
                    </div>

                    <!-- Cara 2: Reverso -->
                    <div
                        v-else
                        class="p-6 rounded-xl border-4 border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-xs space-y-4"
                    >
                        <div class="border-b pb-2 flex items-center justify-between">
                            <span class="font-black text-sm text-slate-900 dark:text-white">Plan Curricular y Calificación Oficial</span>
                            <span class="font-mono text-[10px] text-slate-500">Código: {{ myEnrollment.certificate.certificate_code }}</span>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <!-- Módulos -->
                            <div class="md:col-span-2 space-y-2">
                                <div class="font-bold text-xs text-rose-950 dark:text-rose-300">Contenido Temático Cursado:</div>
                                <div
                                    v-for="(mod, i) in myEnrollment.certificate.modules"
                                    :key="i"
                                    class="p-2 rounded-md bg-slate-50 dark:bg-slate-800 border text-[11px]"
                                >
                                    <div class="font-bold text-slate-900 dark:text-white">{{ mod.number }}: {{ mod.title }}</div>
                                    <div class="text-[10px] text-slate-500">{{ mod.topics }}</div>
                                </div>
                            </div>

                            <!-- Calificación y QR -->
                            <div class="space-y-4 flex flex-col items-center text-center p-3 rounded-lg bg-slate-50 dark:bg-slate-800 border">
                                <div class="space-y-1">
                                    <div class="text-[10px] font-bold text-slate-500 uppercase">Calificación Obtenida</div>
                                    <div class="text-xl font-black text-rose-950 dark:text-rose-300">
                                        {{ myEnrollment.certificate.final_grade || myEnrollment.certificate.grade_numeric }} / 20.00
                                    </div>
                                    <div class="text-[10px] font-bold text-emerald-700 dark:text-emerald-400">
                                        {{ myEnrollment.certificate.final_grade_text || myEnrollment.certificate.grade_text }}
                                    </div>
                                </div>

                                <div class="p-2 bg-white rounded-md border" v-html="myEnrollment.certificate.qr_svg" />
                                <div class="text-[10px] font-mono text-slate-500">
                                    Validación QR en tiempo real
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>
