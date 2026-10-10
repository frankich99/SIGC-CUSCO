<script setup lang="ts">
import { ref, computed } from 'vue';
import { Link } from '@inertiajs/vue3';
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
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { formatDateRange, formatDate } from '@/lib/formatters';
import { THEME_BUTTONS } from '@/lib/theme';
import {
    generateOfficialCertificateHtml,
    printCertificate,
    type CertificateModule,
    type CertificateRecord,
} from '@/lib/certificateTemplate';
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
    credential_qr_svg?: string | null;
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
const availableSpots = computed(() =>
    Math.max(0, props.course.capacity - totalEnrolled.value),
);
const enrollmentPercentage = computed(() => {
    if (!props.course.capacity || props.course.capacity <= 0) return 0;
    return Math.min(
        100,
        Math.round((totalEnrolled.value / props.course.capacity) * 100),
    );
});

// Nombre y detalles del docente
const instructorDisplayName = computed(() => {
    if (props.course.instructor) {
        const u = props.course.instructor;
        const full = `${u.name} ${u.paterno || ''} ${u.materno || ''}`.trim();
        return full || u.name;
    }
    return (
        props.course.instructor_display_name ||
        props.course.instructor_name ||
        'Docente Titular UNSAAC'
    );
});

// Estado badge
const statusConfig = computed(() => {
    switch (props.course.status) {
        case 'abierto':
            return {
                label: 'Inscripciones Abiertas',
                badgeClass:
                    'bg-emerald-100 text-emerald-900 border-emerald-300 dark:bg-emerald-950 dark:text-emerald-200',
                canEnroll: true,
            };
        case 'en_curso':
            return {
                label: 'Capacitación En Curso',
                badgeClass:
                    'bg-amber-100 text-amber-950 border-amber-300 dark:bg-amber-950 dark:text-amber-200',
                canEnroll: false,
            };
        case 'concluido':
            return {
                label: 'Capacitación Concluida',
                badgeClass:
                    'bg-slate-100 text-slate-800 border-slate-300 dark:bg-slate-800 dark:text-slate-300',
                canEnroll: false,
            };
        case 'cancelado':
            return {
                label: 'Capacitación Cancelada',
                badgeClass:
                    'bg-rose-100 text-rose-950 border-rose-300 dark:bg-rose-950 dark:text-rose-200',
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
const credentialQrSvg = computed(
    () => props.myEnrollment?.credential_qr_svg || '',
);

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
        <div
            class="relative overflow-hidden rounded-2xl border border-rose-900/20 bg-gradient-to-br from-rose-950 via-[#4a0011] to-slate-950 p-6 text-white shadow-lg sm:p-8 lg:p-10"
        >
            <!-- Efecto decorativo de fondo -->
            <div
                class="pointer-events-none absolute -top-16 -right-16 size-72 rounded-full bg-rose-500/10 blur-3xl"
            />
            <div
                class="pointer-events-none absolute -bottom-16 -left-16 size-72 rounded-full bg-amber-500/10 blur-3xl"
            />

            <div class="relative z-10 space-y-4">
                <div class="flex flex-wrap items-center gap-2">
                    <span
                        class="rounded-md border border-white/20 bg-white/10 px-2.5 py-1 font-mono text-xs font-black tracking-wider text-rose-200 uppercase backdrop-blur-xs"
                    >
                        {{ course.code }}
                    </span>
                    <Badge
                        variant="outline"
                        :class="[
                            statusConfig.badgeClass,
                            'border text-xs font-black',
                        ]"
                    >
                        {{ statusConfig.label }}
                    </Badge>
                    <span
                        v-if="course.institution"
                        class="flex items-center gap-1.5 text-xs font-medium text-rose-200/90"
                    >
                        <Building2 class="size-3.5 text-amber-300" />
                        {{ course.institution }}
                    </span>
                </div>

                <div class="max-w-4xl space-y-2">
                    <h1
                        class="text-2xl leading-tight font-black tracking-tight text-white sm:text-3xl lg:text-4xl"
                    >
                        {{ course.title }}
                    </h1>
                    <p
                        v-if="course.description"
                        class="pt-1 text-sm leading-relaxed font-normal text-rose-100/90 sm:text-base"
                    >
                        {{ course.description }}
                    </p>
                </div>

                <!-- Call to action principal -->
                <div class="flex flex-wrap items-center gap-3 pt-2">
                    <!-- Si el usuario logueado ya está matriculado -->
                    <div
                        v-if="myEnrollment"
                        class="flex items-center gap-2 rounded-xl border border-emerald-500/40 bg-emerald-950/80 px-3.5 py-2 text-xs font-bold text-emerald-200"
                    >
                        <CheckCircle2 class="size-4 text-emerald-400" />
                        <span>Ya te encuentras matriculado en este curso</span>
                    </div>

                    <!-- Si el curso está abierto y hay vacantes -->
                    <Button
                        v-else-if="statusConfig.canEnroll && availableSpots > 0"
                        size="lg"
                        class="cursor-pointer bg-amber-600 px-6 text-sm font-black text-slate-950 shadow-md hover:bg-amber-500"
                        @click="emit('openEnrollment')"
                    >
                        <Plus class="mr-2 size-4" />
                        Inscribirme en esta Capacitación
                    </Button>

                    <!-- Si está abierto pero no hay vacantes -->
                    <div
                        v-else-if="
                            statusConfig.canEnroll && availableSpots <= 0
                        "
                        class="rounded-xl border border-rose-500/30 bg-rose-900/60 px-4 py-2 text-xs font-bold text-rose-200"
                    >
                        Vacantes agotadas (Aforo completo)
                    </div>

                    <!-- Si no está abierto -->
                    <div
                        v-else
                        class="rounded-xl bg-white/10 px-4 py-2 text-xs font-bold text-white/80"
                    >
                        Inscripciones no disponibles actualmente
                    </div>

                    <Button
                        as-child
                        variant="ghost"
                        size="sm"
                        class="text-xs text-rose-200 hover:bg-white/10 hover:text-white"
                    >
                        <Link href="/certificates">
                            <ShieldCheck
                                class="mr-1.5 size-3.5 text-amber-300"
                            />
                            Verificar Certificaciones Oficiales
                        </Link>
                    </Button>
                </div>
            </div>
        </div>

        <!-- 2. TARJETAS DE MÉTRICAS PÚBLICAS Y SEGURAS (FICHA TÉCNICA RÁPIDA) -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <!-- Horas Lectivas -->
            <Card
                class="border-slate-200 shadow-xs transition-all hover:border-rose-300 dark:border-slate-800"
            >
                <CardHeader class="pb-2">
                    <CardDescription
                        class="flex items-center justify-between text-xs font-bold text-slate-600 dark:text-slate-400"
                    >
                        <span>Horas Académicas</span>
                        <div
                            class="flex size-8 items-center justify-center rounded-lg bg-rose-100 text-rose-900 dark:bg-rose-950 dark:text-rose-200"
                        >
                            <Clock class="size-4" />
                        </div>
                    </CardDescription>
                    <CardTitle
                        class="pt-1 text-2xl font-black text-slate-900 dark:text-white"
                    >
                        {{ course.hours }} hrs.
                    </CardTitle>
                </CardHeader>
                <CardContent class="text-xs text-slate-600 dark:text-slate-400">
                    Capacitación lectiva y práctica certificada
                </CardContent>
            </Card>

            <!-- Sesiones y Calendario -->
            <Card
                class="border-slate-200 shadow-xs transition-all hover:border-amber-300 dark:border-slate-800"
            >
                <CardHeader class="pb-2">
                    <CardDescription
                        class="flex items-center justify-between text-xs font-bold text-slate-600 dark:text-slate-400"
                    >
                        <span>Sesiones de Clase</span>
                        <div
                            class="flex size-8 items-center justify-center rounded-lg bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-200"
                        >
                            <Calendar class="size-4" />
                        </div>
                    </CardDescription>
                    <CardTitle
                        class="pt-1 text-2xl font-black text-slate-900 dark:text-white"
                    >
                        {{ course.total_sessions }} sesiones
                    </CardTitle>
                </CardHeader>
                <CardContent class="text-xs text-slate-600 dark:text-slate-400">
                    {{ formatDateRange(course.start_date, course.end_date) }}
                </CardContent>
            </Card>

            <!-- Asistencia Mínima Requerida -->
            <Card
                class="border-slate-200 shadow-xs transition-all hover:border-emerald-300 dark:border-slate-800"
            >
                <CardHeader class="pb-2">
                    <CardDescription
                        class="flex items-center justify-between text-xs font-bold text-slate-600 dark:text-slate-400"
                    >
                        <span>Asistencia Requerida</span>
                        <div
                            class="flex size-8 items-center justify-center rounded-lg bg-emerald-100 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-200"
                        >
                            <UserCheck class="size-4" />
                        </div>
                    </CardDescription>
                    <CardTitle
                        class="pt-1 text-2xl font-black text-slate-900 dark:text-white"
                    >
                        {{ course.min_attendance_percentage }}% mínimo
                    </CardTitle>
                </CardHeader>
                <CardContent class="text-xs text-slate-600 dark:text-slate-400">
                    Obligatorio para obtención de certificación
                </CardContent>
            </Card>

            <!-- Vacantes y Disponibilidad -->
            <Card
                class="border-slate-200 shadow-xs transition-all hover:border-rose-300 dark:border-slate-800"
            >
                <CardHeader class="pb-2">
                    <CardDescription
                        class="flex items-center justify-between text-xs font-bold text-slate-600 dark:text-slate-400"
                    >
                        <span>Vacantes Disponibles</span>
                        <div
                            class="flex size-8 items-center justify-center rounded-lg bg-slate-100 text-slate-900 dark:bg-slate-800 dark:text-slate-200"
                        >
                            <Users class="size-4" />
                        </div>
                    </CardDescription>
                    <CardTitle
                        class="flex items-baseline gap-2 pt-1 text-2xl font-black text-rose-950 dark:text-rose-300"
                    >
                        <span>{{ availableSpots }}</span>
                        <span class="text-xs font-normal text-slate-500"
                            >de {{ course.capacity }} cupos</span
                        >
                    </CardTitle>
                </CardHeader>
                <CardContent
                    class="space-y-1.5 text-xs text-slate-600 dark:text-slate-400"
                >
                    <div
                        class="h-1.5 w-full overflow-hidden rounded-full bg-slate-200 dark:bg-slate-800"
                    >
                        <div
                            class="h-full rounded-full bg-rose-900 transition-all dark:bg-rose-600"
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
        <Card
            v-if="myEnrollment"
            class="border-2 border-emerald-400 bg-emerald-50/40 shadow-md dark:border-emerald-800 dark:bg-emerald-950/20"
        >
            <CardHeader
                class="border-b border-emerald-200 bg-emerald-100/50 p-5 dark:border-emerald-900/60 dark:bg-emerald-900/30"
            >
                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="space-y-1">
                        <div class="flex items-center gap-2">
                            <Badge
                                class="bg-emerald-700 text-xs font-bold text-white"
                            >
                                <Check class="mr-1 size-3" />
                                Matrícula Activa
                            </Badge>
                            <span
                                class="font-mono text-xs font-bold text-slate-600 dark:text-slate-400"
                            >
                                DNI: {{ myEnrollment.dni }}
                            </span>
                        </div>
                        <CardTitle
                            class="text-lg font-black text-slate-900 dark:text-white"
                        >
                            Tu Estado Académico en este Curso
                        </CardTitle>
                        <CardDescription
                            class="text-xs text-slate-700 dark:text-slate-300"
                        >
                            Participante:
                            <strong>{{ myEnrollment.full_name }}</strong>
                        </CardDescription>
                    </div>

                    <!-- Botones de Acción Personal -->
                    <div class="flex flex-wrap items-center gap-2">
                        <!-- Botón Ver Credencial con QR -->
                        <Button
                            variant="outline"
                            size="sm"
                            class="border-emerald-300 bg-white text-xs font-bold hover:bg-emerald-50 dark:border-emerald-800 dark:bg-slate-900"
                            @click="isCredentialModalOpen = true"
                        >
                            <QrCode
                                class="mr-1.5 size-3.5 text-rose-900 dark:text-rose-400"
                            />
                            Mi Credencial QR
                        </Button>

                        <!-- Botón Descargar Certificado si fue emitido -->
                        <Button
                            v-if="myEnrollment.certificate"
                            size="sm"
                            class="bg-emerald-700 text-xs font-black text-white shadow-xs hover:bg-emerald-800"
                            @click="openCertificatePreview"
                        >
                            <Award class="mr-1.5 size-4 text-amber-300" />
                            Ver / Descargar Mi Certificado Oficial
                        </Button>
                    </div>
                </div>
            </CardHeader>

            <CardContent class="space-y-4 p-5">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <!-- Estado Académico -->
                    <div
                        class="rounded-xl border border-slate-200 bg-white p-3.5 dark:border-slate-800 dark:bg-slate-900"
                    >
                        <div
                            class="text-xs font-bold tracking-wider text-slate-500 uppercase"
                        >
                            Estado Académico
                        </div>
                        <div
                            class="mt-1 flex items-center gap-1.5 text-sm font-black text-slate-900 capitalize dark:text-white"
                        >
                            <span
                                class="size-2 rounded-full"
                                :class="
                                    myEnrollment.status === 'aprobado'
                                        ? 'bg-emerald-500'
                                        : 'bg-amber-500'
                                "
                            />
                            {{ myEnrollment.status.replace('_', ' ') }}
                        </div>
                    </div>

                    <!-- Asistencia Acumulada -->
                    <div
                        class="rounded-xl border border-slate-200 bg-white p-3.5 dark:border-slate-800 dark:bg-slate-900"
                    >
                        <div
                            class="text-xs font-bold tracking-wider text-slate-500 uppercase"
                        >
                            Asistencia Registrada
                        </div>
                        <div
                            class="mt-1 text-sm font-black text-slate-900 dark:text-white"
                        >
                            {{ myEnrollment.attended_sessions }} de
                            {{ course.total_sessions }} sesiones ({{
                                myEnrollment.attendance_percentage
                            }}%)
                        </div>
                        <div
                            class="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"
                        >
                            <div
                                class="h-full rounded-full transition-all"
                                :class="
                                    myEnrollment.attendance_percentage >=
                                    course.min_attendance_percentage
                                        ? 'bg-emerald-600'
                                        : 'bg-amber-500'
                                "
                                :style="{
                                    width: `${Math.min(100, myEnrollment.attendance_percentage)}%`,
                                }"
                            />
                        </div>
                    </div>

                    <!-- Calificación Final -->
                    <div
                        class="rounded-xl border border-slate-200 bg-white p-3.5 dark:border-slate-800 dark:bg-slate-900"
                    >
                        <div
                            class="text-xs font-bold tracking-wider text-slate-500 uppercase"
                        >
                            Calificación Final
                        </div>
                        <div
                            v-if="
                                myEnrollment.final_grade !== null &&
                                myEnrollment.final_grade !== undefined
                            "
                            class="mt-1 text-base font-black text-rose-950 dark:text-rose-300"
                        >
                            {{ Number(myEnrollment.final_grade).toFixed(2) }} /
                            20.00
                        </div>
                        <div
                            v-else
                            class="mt-1 text-xs font-bold text-slate-500 italic"
                        >
                            Pendiente de calificación final
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- 4. CONTENIDO PRINCIPAL: 2 COLUMNAS (CURRÍCULO Y DOCENTE) -->
        <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
            <!-- Columna Izquierda (2/3): Malla Curricular y Certificación -->
            <div class="space-y-6 lg:col-span-2">
                <!-- Malla Curricular / Temario Oficial -->
                <Card
                    class="overflow-hidden border-slate-200 shadow-sm dark:border-slate-800"
                >
                    <CardHeader
                        class="border-b bg-slate-50/80 p-5 dark:bg-slate-900/80"
                    >
                        <CardTitle
                            class="flex items-center gap-2 text-base font-black"
                        >
                            <Layers
                                class="size-4 text-rose-900 dark:text-rose-400"
                            />
                            Plan de Estudios y Malla Curricular Oficial
                        </CardTitle>
                        <CardDescription class="text-xs">
                            Estructura académica organizada en módulos temáticos
                            con horas pedagógicas acreditadas.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4 p-5">
                        <div
                            v-for="(mod, idx) in modules"
                            :key="idx"
                            class="space-y-2 rounded-xl border border-slate-200 bg-white p-4 transition-all hover:border-rose-300 dark:border-slate-800 dark:bg-slate-900"
                        >
                            <div
                                class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div class="flex items-center gap-2">
                                    <span
                                        class="rounded border border-rose-300 bg-rose-100 px-2 py-0.5 font-mono text-xs font-black text-rose-950 dark:border-rose-800 dark:bg-rose-950 dark:text-rose-200"
                                    >
                                        {{ mod.number }}
                                    </span>
                                    <h3
                                        class="text-sm font-black text-slate-900 dark:text-white"
                                    >
                                        {{ mod.title }}
                                    </h3>
                                </div>
                                <span
                                    class="shrink-0 text-xs font-bold text-slate-500"
                                >
                                    {{ mod.hours }}
                                </span>
                            </div>
                            <p
                                class="pl-1 text-xs leading-relaxed text-slate-600 dark:text-slate-400"
                            >
                                {{ mod.topics }}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <!-- Garantía de Calidad y Certificación UNSAAC -->
                <Card
                    class="border-rose-200 bg-gradient-to-br from-rose-50/40 via-white to-amber-50/30 shadow-sm dark:border-rose-900/40 dark:from-rose-950/20 dark:via-slate-900 dark:to-amber-950/10"
                >
                    <CardHeader
                        class="border-b border-rose-100 p-5 dark:border-rose-900/40"
                    >
                        <CardTitle
                            class="flex items-center gap-2 text-base font-black text-rose-950 dark:text-rose-200"
                        >
                            <Sparkles class="size-4 text-amber-600" />
                            Certificación Oficial con Validez Nacional
                        </CardTitle>
                        <CardDescription
                            class="text-xs text-rose-900/80 dark:text-rose-300/80"
                        >
                            Garantía académica institucional respaldada por la
                            Universidad Nacional de San Antonio Abad del Cusco.
                        </CardDescription>
                    </CardHeader>
                    <CardContent
                        class="space-y-3 p-5 text-xs leading-relaxed text-slate-700 dark:text-slate-300"
                    >
                        <div class="flex items-start gap-2.5">
                            <CheckCircle2
                                class="mt-0.5 size-4 shrink-0 text-emerald-600"
                            />
                            <span
                                ><strong>Requisito de Aprobación:</strong> Haber
                                alcanzado una calificación vigesimal mínima de
                                11.00 puntos y cumplir con al menos el
                                {{ course.min_attendance_percentage }}% de
                                asistencias a las clases programadas.</span
                            >
                        </div>
                        <div class="flex items-start gap-2.5">
                            <CheckCircle2
                                class="mt-0.5 size-4 shrink-0 text-emerald-600"
                            />
                            <span
                                ><strong
                                    >Código QR Perpetuo e Inalterable:</strong
                                >
                                Cada diploma incorpora un código QR vectorial
                                nativo de validación permanente que redirige a
                                los registros oficiales en vivo.</span
                            >
                        </div>
                        <div class="flex items-start gap-2.5">
                            <CheckCircle2
                                class="mt-0.5 size-4 shrink-0 text-emerald-600"
                            />
                            <span
                                ><strong>Formato Oficial de 2 Caras:</strong>
                                Diploma de Honor institucional en el anverso y
                                detalle completo de la malla curricular con
                                firma digital en el reverso.</span
                            >
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Columna Derecha (1/3): Docente Responsable y Admisión -->
            <div class="space-y-6">
                <!-- Tarjeta del Docente Responsable -->
                <Card class="border-slate-200 shadow-sm dark:border-slate-800">
                    <CardHeader
                        class="border-b bg-slate-50/80 p-5 dark:bg-slate-900/80"
                    >
                        <CardTitle
                            class="flex items-center gap-2 text-sm font-black"
                        >
                            <GraduationCap
                                class="size-4 text-rose-900 dark:text-rose-400"
                            />
                            Docente Responsable
                        </CardTitle>
                        <CardDescription class="text-xs">
                            Catedrático o especialista a cargo de la cátedra
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-3 p-5">
                        <div class="flex items-center gap-3">
                            <div
                                class="flex size-12 shrink-0 items-center justify-center rounded-full bg-rose-900 text-sm font-black text-amber-300 shadow-xs"
                            >
                                {{
                                    instructorDisplayName
                                        .slice(0, 2)
                                        .toUpperCase()
                                }}
                            </div>
                            <div>
                                <div
                                    class="text-sm font-black text-slate-900 dark:text-white"
                                >
                                    {{ instructorDisplayName }}
                                </div>
                                <div
                                    class="text-xs font-bold text-rose-900 dark:text-rose-400"
                                >
                                    Docente Titular / Especialista
                                </div>
                                <div class="text-[11px] text-slate-500">
                                    UNSAAC - Cusco, Perú
                                </div>
                            </div>
                        </div>

                        <div
                            v-if="course.instructor?.email"
                            class="flex items-center gap-2 border-t pt-2 text-xs text-slate-600 dark:text-slate-400"
                        >
                            <Mail class="size-3.5 text-slate-400" />
                            <span class="truncate">{{
                                course.instructor.email
                            }}</span>
                        </div>
                    </CardContent>
                </Card>

                <!-- Requisitos y Admisión -->
                <Card class="border-slate-200 shadow-sm dark:border-slate-800">
                    <CardHeader
                        class="border-b bg-slate-50/80 p-5 dark:bg-slate-900/80"
                    >
                        <CardTitle
                            class="flex items-center gap-2 text-sm font-black"
                        >
                            <BookOpen
                                class="size-4 text-rose-900 dark:text-rose-400"
                            />
                            Requisitos de Participación
                        </CardTitle>
                    </CardHeader>
                    <CardContent
                        class="space-y-2.5 p-5 text-xs text-slate-700 dark:text-slate-300"
                    >
                        <div class="flex items-center gap-2">
                            <div
                                class="size-1.5 shrink-0 rounded-full bg-rose-900"
                            />
                            <span
                                >Documento Nacional de Identidad (DNI)
                                vigente.</span
                            >
                        </div>
                        <div class="flex items-center gap-2">
                            <div
                                class="size-1.5 shrink-0 rounded-full bg-rose-900"
                            />
                            <span
                                >Disponibilidad horaria para las
                                {{ course.total_sessions }} sesiones.</span
                            >
                        </div>
                        <div class="flex items-center gap-2">
                            <div
                                class="size-1.5 shrink-0 rounded-full bg-rose-900"
                            />
                            <span
                                >Compromiso de asistencia mínima del
                                {{ course.min_attendance_percentage }}%.</span
                            >
                        </div>
                    </CardContent>
                </Card>

                <!-- Banner de Inscripción Rápida -->
                <div
                    v-if="statusConfig.canEnroll && availableSpots > 0"
                    class="space-y-3 rounded-2xl bg-gradient-to-br from-rose-900 to-rose-950 p-5 text-white shadow-md"
                >
                    <div class="text-sm font-black">
                        ¿Deseas participar en esta capacitación?
                    </div>
                    <p class="text-xs text-rose-200">
                        Quedan únicamente
                        <strong>{{ availableSpots }} vacantes</strong>
                        disponibles para este curso.
                    </p>
                    <Button
                        size="sm"
                        class="w-full cursor-pointer bg-amber-500 text-xs font-black text-slate-950 hover:bg-amber-400"
                        @click="emit('openEnrollment')"
                    >
                        <Plus class="mr-1.5 size-3.5" />
                        Inscribirme Ahora
                    </Button>
                </div>
            </div>
        </div>

        <!-- MODAL: CREDENCIAL QR DEL ALUMNO -->
        <Dialog
            :open="isCredentialModalOpen"
            @update:open="isCredentialModalOpen = $event"
        >
            <DialogContent class="max-w-md p-6">
                <DialogHeader>
                    <DialogTitle
                        class="flex items-center justify-center gap-2 text-center text-lg font-black"
                    >
                        <QrCode class="size-5 text-rose-900" />
                        Credencial Digital del Alumno
                    </DialogTitle>
                    <DialogDescription class="text-center text-xs">
                        Código QR personal de acreditación para registro de
                        asistencia en aula.
                    </DialogDescription>
                </DialogHeader>

                <div
                    v-if="myEnrollment"
                    class="flex flex-col items-center space-y-4 py-4"
                >
                    <div
                        class="rounded-xl border border-slate-200 bg-white p-3 shadow-inner"
                        v-html="credentialQrSvg"
                    />
                    <div class="space-y-1 text-center">
                        <div
                            class="text-base font-black text-slate-900 dark:text-white"
                        >
                            {{ myEnrollment.full_name }}
                        </div>
                        <div class="text-xs text-slate-500">
                            DNI: {{ myEnrollment.dni }}
                        </div>
                        <div class="pt-2">
                            <span
                                class="rounded-full border bg-slate-100 px-2.5 py-1 font-mono text-xs font-bold dark:bg-slate-800"
                            >
                                {{
                                    myEnrollment.credential_code ||
                                    `INS-${course.id}-${myEnrollment.dni.slice(-4)}`
                                }}
                            </span>
                        </div>
                    </div>
                </div>
            </DialogContent>
        </Dialog>

        <!-- MODAL: VISOR DE CERTIFICADO OFICIAL DEL ALUMNO (ANVERSO / REVERSO) -->
        <Dialog
            :open="isCertificateModalOpen"
            @update:open="isCertificateModalOpen = $event"
        >
            <DialogContent class="max-w-5xl p-6">
                <DialogHeader class="border-b pb-3">
                    <div
                        class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div>
                            <DialogTitle
                                class="flex items-center gap-2 text-lg font-black"
                            >
                                <Award class="size-5 text-amber-500" />
                                Certificado Digital Oficial UNSAAC
                            </DialogTitle>
                            <DialogDescription class="mt-0.5 text-xs">
                                Diploma oficial de 2 caras (Anverso / Reverso)
                                emitido con código QR perpetuo.
                            </DialogDescription>
                        </div>
                        <div class="flex items-center gap-2">
                            <Button
                                size="sm"
                                class="cursor-pointer bg-rose-900 text-xs font-black text-white shadow-xs hover:bg-rose-950"
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
                        class="cursor-pointer rounded-lg px-4 py-1.5 text-xs font-black transition-all"
                        :class="
                            activeCertificateFace === 'front'
                                ? 'bg-rose-900 text-white shadow-xs'
                                : 'bg-slate-100 text-slate-600 dark:bg-slate-800'
                        "
                        @click="activeCertificateFace = 'front'"
                    >
                        Anverso (Diploma Oficial)
                    </button>
                    <button
                        type="button"
                        class="cursor-pointer rounded-lg px-4 py-1.5 text-xs font-black transition-all"
                        :class="
                            activeCertificateFace === 'back'
                                ? 'bg-rose-900 text-white shadow-xs'
                                : 'bg-slate-100 text-slate-600 dark:bg-slate-800'
                        "
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
                        class="space-y-4 rounded-2xl border-2 border-[#800020] bg-gradient-to-br from-amber-50/60 via-white to-amber-50/40 p-6 text-center shadow-inner dark:from-slate-900 dark:via-slate-950 dark:to-slate-900"
                    >
                        <div class="flex items-center justify-between px-4">
                            <img
                                src="/images/unsaac-logo.png"
                                alt="UNSAAC"
                                class="h-14 object-contain"
                            />
                            <div class="text-center">
                                <div
                                    class="text-[11px] font-black tracking-widest text-rose-900 uppercase dark:text-rose-400"
                                >
                                    Universidad Nacional de San Antonio Abad del
                                    Cusco
                                </div>
                                <div
                                    class="text-[10px] font-bold text-slate-600 dark:text-slate-400"
                                >
                                    Dirección de Extensión Cultural y Proyección
                                    Social
                                </div>
                            </div>
                            <img
                                src="/images/escudo.png"
                                alt="Escudo"
                                class="h-14 object-contain"
                            />
                        </div>

                        <div class="space-y-2 py-4">
                            <div
                                class="font-serif text-xs font-bold tracking-widest text-amber-700 uppercase dark:text-amber-400"
                            >
                                Otorga el presente
                            </div>
                            <h2
                                class="font-serif text-2xl font-black tracking-wider text-rose-950 uppercase sm:text-3xl dark:text-rose-200"
                            >
                                Diploma de Certificación
                            </h2>
                            <div
                                class="text-xs text-slate-600 dark:text-slate-400"
                            >
                                a favor de:
                            </div>
                            <div
                                class="font-serif text-xl font-black text-slate-900 underline decoration-amber-600/50 underline-offset-8 sm:text-2xl dark:text-white"
                            >
                                {{
                                    myEnrollment.certificate.student_name ||
                                    myEnrollment.certificate.full_name
                                }}
                            </div>
                            <div
                                class="pt-1 font-mono text-xs font-bold text-slate-600 dark:text-slate-400"
                            >
                                Documento Nacional de Identidad N°
                                {{ myEnrollment.certificate.dni }}
                            </div>
                        </div>

                        <div
                            class="mx-auto max-w-2xl font-serif text-xs leading-relaxed text-slate-700 dark:text-slate-300"
                        >
                            Por haber aprobado satisfactoriamente el curso de
                            especialización profesional:
                            <div
                                class="py-1 text-sm font-bold text-rose-950 dark:text-rose-200"
                            >
                                "{{ myEnrollment.certificate.course_title }}"
                            </div>
                            Desarrollado
                            <strong>{{
                                myEnrollment.certificate.date_range_formal ||
                                formatDateRange(
                                    course.start_date,
                                    course.end_date,
                                )
                            }}</strong
                            >, con una duración lectiva de
                            <strong
                                >{{ myEnrollment.certificate.hours }} horas
                                académicas</strong
                            >.
                        </div>

                        <div
                            class="mx-auto my-1 max-w-lg pr-2 text-right font-serif text-[11px] font-bold text-slate-600 italic dark:text-slate-400"
                        >
                            {{
                                myEnrollment.certificate.city_issued_formal ||
                                'Cusco, ' +
                                    formatDate(
                                        myEnrollment.certificate
                                            .certificate_issued_at ||
                                            course.end_date,
                                    )
                            }}
                        </div>

                        <div
                            class="mx-auto grid max-w-lg grid-cols-2 gap-8 border-t pt-4 text-center font-serif text-xs"
                        >
                            <div>
                                <div
                                    class="border-t border-slate-400 pt-1 text-[11px] font-bold"
                                >
                                    {{
                                        myEnrollment.certificate.instructor_name
                                    }}
                                </div>
                                <div class="text-[9px] text-slate-500">
                                    Docente Responsable
                                </div>
                            </div>
                            <div>
                                <div
                                    class="border-t border-slate-400 pt-1 text-[11px] font-bold"
                                >
                                    Dirección Académica
                                </div>
                                <div class="text-[9px] text-slate-500">
                                    UNSAAC - Cusco
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Cara 2: Reverso -->
                    <div
                        v-else
                        class="space-y-4 rounded-2xl border-2 border-slate-300 bg-white p-6 text-xs shadow-sm dark:border-slate-700 dark:bg-slate-900"
                    >
                        <div
                            class="flex items-center justify-between border-b pb-2"
                        >
                            <span
                                class="text-sm font-black text-slate-900 dark:text-white"
                                >Plan Curricular y Calificación Oficial</span
                            >
                            <span class="font-mono text-[10px] text-slate-500"
                                >Código:
                                {{
                                    myEnrollment.certificate.certificate_code
                                }}</span
                            >
                        </div>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <!-- Módulos -->
                            <div class="space-y-2 md:col-span-2">
                                <div
                                    class="text-xs font-bold text-rose-950 dark:text-rose-300"
                                >
                                    Contenido Temático Cursado:
                                </div>
                                <div
                                    v-for="(mod, i) in myEnrollment.certificate
                                        .modules"
                                    :key="i"
                                    class="rounded-xl border bg-slate-50 p-2.5 text-[11px] dark:bg-slate-800"
                                >
                                    <div
                                        class="font-bold text-slate-900 dark:text-white"
                                    >
                                        {{ mod.number }}: {{ mod.title }}
                                    </div>
                                    <div class="text-[10px] text-slate-500">
                                        {{ mod.topics }}
                                    </div>
                                </div>
                            </div>

                            <!-- Calificación y QR -->
                            <div
                                class="flex flex-col items-center space-y-4 rounded-xl border bg-slate-50 p-3 text-center dark:bg-slate-800"
                            >
                                <div class="space-y-1">
                                    <div
                                        class="text-[10px] font-bold text-slate-500 uppercase"
                                    >
                                        Calificación Obtenida
                                    </div>
                                    <div
                                        class="text-xl font-black text-rose-950 dark:text-rose-300"
                                    >
                                        {{
                                            myEnrollment.certificate
                                                .final_grade ||
                                            myEnrollment.certificate
                                                .grade_numeric
                                        }}
                                        / 20.00
                                    </div>
                                    <div
                                        class="text-[10px] font-bold text-emerald-700 dark:text-emerald-400"
                                    >
                                        {{
                                            myEnrollment.certificate
                                                .final_grade_text ||
                                            myEnrollment.certificate.grade_text
                                        }}
                                    </div>
                                </div>

                                <div
                                    class="rounded-md border bg-white p-2"
                                    v-html="myEnrollment.certificate.qr_svg"
                                />
                                <div
                                    class="font-mono text-[10px] text-slate-500"
                                >
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
