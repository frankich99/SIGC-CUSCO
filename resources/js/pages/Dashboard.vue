<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import EnrollmentModal from '@/components/EnrollmentModal.vue';
import { courseQr } from '@/actions/App/Http/Controllers/CourseController';
import { credentialQr } from '@/actions/App/Http/Controllers/EnrollmentController';
import { formatDate, formatDateRange, formatHours } from '@/lib/formatters';
import { getCourseStatusBadge, getEnrollmentStatusBadge } from '@/lib/theme';
import {
    GraduationCap,
    Users,
    QrCode,
    Award,
    ArrowRight,
    Plus,
    CheckCircle2,
    Calendar,
    ShieldCheck,
    Clock,
    BookOpen,
    Camera,
    Sparkles,
    Download,
    ExternalLink,
    Building2,
    TrendingUp,
    DollarSign,
} from '@lucide/vue';
import type { BreadcrumbItem } from '@/types';
import { useBranding } from '@/composables/useBranding';

const { systemName, institutionName, primaryColor } = useBranding();

interface InstructorSnippet {
    id: number;
    name: string;
    paterno?: string;
    materno?: string;
}

interface CourseItem {
    id: number;
    code: string;
    title: string;
    institution?: string | null;
    description?: string | null;
    start_date: string;
    end_date: string;
    hours: number;
    total_sessions?: number;
    capacity: number;
    status: string;
    enrollments_count?: number;
    instructor?: InstructorSnippet;
    instructor_name?: string | null;
}

interface EnrollmentItem {
    id: number;
    course_id: number;
    credential_code?: string | null;
    attendance_percentage?: number;
    dni: string;
    nombres: string;
    paterno: string;
    materno?: string;
    email: string;
    phone?: string;
    status:
        | 'inscrito'
        | 'en_curso'
        | 'aprobado'
        | 'desaprobado'
        | 'reprobado'
        | 'cancelado'
        | string;
    attended_sessions: number;
    final_grade?: number | string | null;
    certificate_code?: string | null;
    created_at: string;
    course: CourseItem;
}

const props = defineProps<{
    metrics: {
        totalCourses: number;
        openCourses: number;
        taughtCount: number;
        enrolledCount: number;
    };
    pdpMetrics?: {
        pdpTotal: number;
        pdpCompleted: number;
        pdpExecutionRate: number;
        budgetAllocated: number;
        budgetExecuted: number;
        totalTrainingHours: number;
        unitBreakdown: Array<{ organizational_unit: string; total: number }>;
        modalityBreakdown: Array<{ modality: string; total: number }>;
    };
    taughtCourses: CourseItem[];
    studentEnrollments: EnrollmentItem[];
    openCourses: CourseItem[];
}>();

const page = usePage();
const user = computed(() => page.props.auth?.user);

const isOrganizerOrTeacher = computed(() => {
    return user.value?.role === 'admin' || user.value?.role === 'docente';
});

const isStudentOrParticipant = computed(() => {
    return (
        user.value?.role === 'participante' ||
        user.value?.role === 'admin' ||
        (props.studentEnrollments && props.studentEnrollments.length > 0)
    );
});

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Panel Principal',
        href: '/dashboard',
    },
];

// Active view toggle when admin has both views or user is enrolled
const activeDashboardTab = ref<'organizador' | 'participante'>(
    user.value?.role === 'participante' ? 'participante' : 'organizador',
);

// Modals
const selectedCourseToEnroll = ref<CourseItem | null>(null);
const isEnrollModalOpen = ref(false);

const isQrModalOpen = ref(false);
const activeQrCourse = ref<CourseItem | null>(null);
const activeQrSvg = ref('');
const isQrLoading = ref(false);
const isCredentialModalOpen = ref(false);
const activeCredentialEnrollment = ref<EnrollmentItem | null>(null);
const activeCredentialQrSvg = ref('');
const isCredentialQrLoading = ref(false);

async function openQrProjection(course: CourseItem) {
    activeQrCourse.value = course;
    isQrModalOpen.value = true;
    activeQrSvg.value = '';
    isQrLoading.value = true;

    try {
        const response = await fetch(courseQr.url(course.id), {
            headers: { Accept: 'application/json' },
        });
        if (!response.ok) {
            throw new Error('No se pudo generar el QR de la capacitación.');
        }
        const data = await response.json();
        activeQrSvg.value = data.svg || '';
    } catch {
        activeQrSvg.value = '';
    } finally {
        isQrLoading.value = false;
    }
}

function openCredentialModal(enrollment: EnrollmentItem) {
    activeCredentialEnrollment.value = enrollment;
    isCredentialModalOpen.value = true;
    activeCredentialQrSvg.value = '';
    isCredentialQrLoading.value = true;

    fetch(credentialQr.url(enrollment.id), {
        headers: { Accept: 'application/json' },
    })
        .then((response) => {
            if (!response.ok) {
                throw new Error('No se pudo generar el QR de la credencial.');
            }
            return response.json();
        })
        .then((data) => {
            activeCredentialQrSvg.value = data.svg || '';
        })
        .catch(() => {
            activeCredentialQrSvg.value = '';
        })
        .finally(() => {
            isCredentialQrLoading.value = false;
        });
}

function openEnroll(course: CourseItem) {
    selectedCourseToEnroll.value = course;
    isEnrollModalOpen.value = true;
}

function roleBadgeData(role?: string) {
    switch (role) {
        case 'admin':
            return {
                label: 'Administrador General',
                desc: 'Control integral de programas, docentes, cupos y actas académicas.',
                badgeClass: 'bg-[#3484a5] text-white font-black shadow-xs',
            };
        case 'docente':
            return {
                label: 'Ponente / Docente',
                desc: 'Dictado de clases, proyección de QR de asistencia en aula y evaluación.',
                badgeClass: 'bg-amber-700 text-white font-black shadow-xs',
            };
        default:
            return {
                label: 'Participante / Alumno',
                desc: 'Inscripción a programas, registro de asistencia con QR y descarga de certificados.',
                badgeClass: 'bg-blue-800 text-white font-black shadow-xs',
            };
    }
}

function enrollmentStatusBadge(status: string) {
    return getEnrollmentStatusBadge(status);
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Panel de Control - SIGC-MDSJ" />

        <div
            class="mx-auto w-full max-w-7xl min-w-0 space-y-8 overflow-x-hidden px-4 py-6 sm:px-6 sm:py-8 lg:px-8"
        >
            <!-- BANNER PRINCIPAL INSTITUCIONAL -->
            <div
                class="relative overflow-hidden rounded-2xl border border-slate-700/60 bg-[#121e27] dark:bg-[#0b1319] p-6 text-white shadow-xl sm:p-8"
                :style="{ borderLeft: `6px solid ${primaryColor || 'var(--institutional, #3484a5)'}` }"
            >
                <div
                    class="relative z-10 flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between"
                >
                    <div class="space-y-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <span
                                class="rounded-full border border-white/20 bg-white/10 px-3 py-1 text-[11px] font-black tracking-wider text-white uppercase"
                            >
                                {{ systemName }} • {{ institutionName }}
                            </span>
                            <span
                                class="rounded-full px-3 py-1 text-[11px] tracking-wider uppercase"
                                :class="roleBadgeData(user?.role).badgeClass"
                            >
                                {{ roleBadgeData(user?.role).label }}
                            </span>
                        </div>

                        <h1
                            class="text-2xl font-black tracking-tight text-white drop-shadow-xs sm:text-4xl"
                        >
                            ¡Bienvenido, {{ user?.name }}!
                        </h1>

                        <p
                            class="max-w-2xl text-xs leading-relaxed font-medium text-sky-100 sm:text-sm"
                        >
                            {{ roleBadgeData(user?.role).desc }}
                        </p>
                    </div>

                    <!-- Botones de Acción Primarios Azul y Dorado -->
                    <div class="flex flex-wrap items-center gap-3">
                        <Button
                            v-if="
                                user?.role === 'admin' ||
                                user?.role === 'docente'
                            "
                            as-child
                            size="default"
                            class="bg-amber-600 text-xs font-black text-white shadow-md hover:bg-amber-700"
                        >
                            <Link
                                href="/courses/create"
                                class="flex items-center gap-1.5"
                            >
                                <Plus class="size-4 stroke-[2.5] text-white" />
                                <span>Agregar Curso</span>
                            </Link>
                        </Button>
                        <Button
                            as-child
                            size="default"
                            variant="secondary"
                            class="bg-white text-xs font-bold text-slate-900 shadow-md hover:bg-slate-100"
                        >
                            <Link href="/courses">
                                <GraduationCap
                                    class="mr-1.5 size-4 text-[#3484a5]"
                                />
                                Catálogo Completo
                            </Link>
                        </Button>
                    </div>
                </div>

                <!-- Selector de Vista para Administradores -->
                <div
                    v-if="user?.role === 'admin'"
                    class="relative z-10 mt-6 flex flex-wrap items-center gap-2 border-t border-white/20 pt-4"
                >
                    <span class="mr-2 text-xs font-bold text-sky-200"
                        >Modo de visualización:</span
                    >
                    <button
                        type="button"
                        @click="activeDashboardTab = 'organizador'"
                        class="cursor-pointer rounded-lg px-3.5 py-1.5 text-xs font-black transition-all"
                        :class="
                            activeDashboardTab === 'organizador'
                                ? 'bg-white text-sky-950 shadow-md ring-2 ring-amber-400'
                                : 'bg-white/10 text-white hover:bg-white/20'
                        "
                    >
                        Vista Organizador / Docente
                    </button>
                    <button
                        type="button"
                        @click="activeDashboardTab = 'participante'"
                        class="cursor-pointer rounded-lg px-3.5 py-1.5 text-xs font-black transition-all"
                        :class="
                            activeDashboardTab === 'participante'
                                ? 'bg-white text-sky-950 shadow-md ring-2 ring-amber-400'
                                : 'bg-white/10 text-white hover:bg-white/20'
                        "
                    >
                        Vista Participante / Alumno
                    </button>
                </div>
            </div>

            <!-- ============================================================ -->
            <!-- 1. VISTA: ORGANIZADOR / DOCENTE / INSTRUCTOR                 -->
            <!-- ============================================================ -->
            <div
                v-if="
                    isOrganizerOrTeacher &&
                    (user?.role !== 'admin' ||
                        activeDashboardTab === 'organizador')
                "
                class="space-y-8"
            >
                <!-- Grilla de Métricas: Azul San Jerónimo, Dorado y Alto Contraste -->
                <div
                    class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4"
                >
                    <!-- Métrica 1: Cursos Asignados (Verde Esmeralda) -->
                    <Card
                        class="border border-slate-200 bg-white shadow-sm transition-all hover:border-emerald-500 hover:shadow-md dark:border-slate-800 dark:bg-card"
                    >
                        <CardHeader class="pb-2">
                            <CardDescription
                                class="flex items-center justify-between text-xs font-bold text-slate-700 dark:text-slate-300"
                            >
                                <span>Cursos a mi Cargo</span>
                                <div
                                    class="flex size-8 items-center justify-center rounded-lg bg-emerald-600 text-white shadow-xs"
                                >
                                    <BookOpen class="size-4" />
                                </div>
                            </CardDescription>
                            <CardTitle
                                class="pt-1 text-3xl font-black text-slate-900 dark:text-white"
                            >
                                {{ metrics.taughtCount }}
                            </CardTitle>
                        </CardHeader>
                        <CardContent
                            class="text-xs font-bold text-slate-700 dark:text-slate-300"
                        >
                            Capacitaciones asignadas activas
                        </CardContent>
                    </Card>

                    <!-- Métrica 2: Inscripciones Abiertas (Dorado Cusco) -->
                    <Card
                        class="border-2 border-amber-300 bg-amber-50/40 shadow-sm transition-all hover:shadow-md dark:border-amber-900 dark:bg-amber-950/20"
                    >
                        <CardHeader class="pb-2">
                            <CardDescription
                                class="flex items-center justify-between text-xs font-bold text-amber-950 dark:text-amber-300"
                            >
                                <span>Inscripciones Abiertas</span>
                                <div
                                    class="flex size-8 items-center justify-center rounded-lg bg-amber-600 text-white shadow-xs"
                                >
                                    <GraduationCap class="size-4" />
                                </div>
                            </CardDescription>
                            <CardTitle
                                class="pt-1 text-3xl font-black text-slate-900 dark:text-white"
                            >
                                {{ metrics.openCourses }}
                            </CardTitle>
                        </CardHeader>
                        <CardContent
                            class="text-xs font-bold text-slate-700 dark:text-slate-300"
                        >
                            Recibiendo nuevos participantes
                        </CardContent>
                    </Card>

                    <!-- Métrica 3: Total Registrados -->
                    <Card
                        class="border-2 border-indigo-300 bg-indigo-50/40 shadow-sm transition-all hover:shadow-md dark:border-indigo-900 dark:bg-indigo-950/20"
                    >
                        <CardHeader class="pb-2">
                            <CardDescription
                                class="flex items-center justify-between text-xs font-bold text-indigo-950 dark:text-indigo-300"
                            >
                                <span>Total Capacitaciones</span>
                                <div
                                    class="flex size-8 items-center justify-center rounded-lg bg-indigo-700 text-white shadow-xs"
                                >
                                    <Award class="size-4" />
                                </div>
                            </CardDescription>
                            <CardTitle
                                class="pt-1 text-3xl font-black text-slate-900 dark:text-white"
                            >
                                {{ metrics.totalCourses }}
                            </CardTitle>
                        </CardHeader>
                        <CardContent
                            class="text-xs font-bold text-slate-700 dark:text-slate-300"
                        >
                            Registradas en SIGC-MDSJ
                        </CardContent>
                    </Card>

                    <!-- Métrica 4: Asistencia QR Dinámica -->
                    <Card
                        class="border-2 border-sky-400 bg-sky-100/50 shadow-sm transition-all hover:shadow-md dark:border-[#296d88] dark:bg-[#153542]/30"
                    >
                        <CardHeader class="pb-2">
                            <CardDescription
                                class="flex items-center justify-between text-xs font-bold text-sky-950 dark:text-sky-200"
                            >
                                <span>Asistencia QR en Vivo</span>
                                <div
                                    class="flex size-8 items-center justify-center rounded-lg bg-[#153542] text-white shadow-xs"
                                >
                                    <QrCode class="size-4" />
                                </div>
                            </CardDescription>
                            <CardTitle
                                class="pt-2 text-base font-black text-sky-950 dark:text-sky-200"
                            >
                                Proyección en Aula
                            </CardTitle>
                        </CardHeader>
                        <CardContent
                            class="text-xs font-bold text-slate-700 dark:text-slate-300"
                        >
                            Genera códigos QR dinámicos para tus clases
                        </CardContent>
                    </Card>
                </div>

                <!-- Panel Municipal: Tablero de Control del Plan de Desarrollo de Personas (PDP • SERVIR • MDSJ) -->
                <div v-if="pdpMetrics" class="rounded-2xl border border-sky-300/80 bg-linear-to-br from-sky-50/90 via-white to-sky-100/50 p-5 shadow-sm dark:border-sky-900/60 dark:from-[#112833] dark:via-slate-900 dark:to-[#0f242e]">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between border-b border-sky-200/80 pb-4 dark:border-sky-800/60">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="rounded-md bg-[#3484a5] px-2 py-0.5 font-mono text-[10px] font-black text-white uppercase tracking-wider">
                                    SERVIR • MDSJ
                                </span>
                                <h3 class="text-lg font-black text-slate-950 dark:text-white flex items-center gap-2">
                                    <TrendingUp class="size-5 text-[#3484a5]" />
                                    Tablero de Control del Plan de Desarrollo de Personas (PDP 2026)
                                </h3>
                            </div>
                            <p class="text-xs font-medium text-slate-600 dark:text-slate-400 mt-1">
                                Supervisión de ejecución de capacitaciones institucionales, presupuesto y participación por área municipal.
                            </p>
                        </div>
                        <Badge class="self-start sm:self-auto rounded-full bg-sky-100 text-[#3484a5] border-sky-300 dark:bg-[#153542] dark:text-sky-200 font-extrabold text-xs px-3 py-1">
                            Municipalidad Distrital de San Jerónimo
                        </Badge>
                    </div>

                    <!-- 3 Métricas Estratégicas del PDP -->
                    <div class="grid grid-cols-1 gap-4 pt-5 sm:grid-cols-3">
                        <!-- KPI 1: Avance de Metas PDP -->
                        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-2xs dark:border-slate-800 dark:bg-slate-950/70">
                            <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Avance del PDP Anual</span>
                            <div class="mt-2 flex items-baseline justify-between">
                                <span class="text-2xl font-black text-[#3484a5] dark:text-sky-300">
                                    {{ pdpMetrics.pdpExecutionRate }}%
                                </span>
                                <span class="text-xs font-bold text-slate-600 dark:text-slate-400">
                                    {{ pdpMetrics.pdpCompleted }} de {{ pdpMetrics.pdpTotal }} concluidos
                                </span>
                            </div>
                            <!-- Barra de Progreso -->
                            <div class="mt-3 h-2 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                <div
                                    class="h-full rounded-full bg-[#3484a5] transition-all duration-500"
                                    :style="{ width: `${Math.min(100, pdpMetrics.pdpExecutionRate)}%` }"
                                />
                            </div>
                        </div>

                        <!-- KPI 2: Presupuesto Municipal Ejecutado -->
                        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-2xs dark:border-slate-800 dark:bg-slate-950/70">
                            <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Presupuesto Municipal</span>
                            <div class="mt-2 flex items-baseline justify-between">
                                <span class="text-xl font-black text-emerald-700 dark:text-emerald-400">
                                    S/. {{ pdpMetrics.budgetExecuted.toLocaleString('es-PE', { minimumFractionDigits: 2 }) }}
                                </span>
                                <span class="text-[11px] font-medium text-slate-500">
                                    de S/. {{ pdpMetrics.budgetAllocated.toLocaleString('es-PE', { minimumFractionDigits: 2 }) }}
                                </span>
                            </div>
                            <div class="mt-3 h-2 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                <div
                                    class="h-full rounded-full bg-emerald-600 transition-all duration-500"
                                    :style="{ width: `${pdpMetrics.budgetAllocated > 0 ? Math.min(100, Math.round((pdpMetrics.budgetExecuted / pdpMetrics.budgetAllocated) * 100)) : 0}%` }"
                                />
                            </div>
                        </div>

                        <!-- KPI 3: Horas de Formación Acreditables -->
                        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-2xs dark:border-slate-800 dark:bg-slate-950/70">
                            <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Carga Horaria Acreditable</span>
                            <div class="mt-2 flex items-baseline justify-between">
                                <span class="text-2xl font-black text-amber-600 dark:text-amber-400">
                                    {{ pdpMetrics.totalTrainingHours }} hrs.
                                </span>
                                <span class="text-xs font-bold text-slate-600 dark:text-slate-400">
                                    Oferta SERVIR
                                </span>
                            </div>
                            <p class="mt-3 text-[11px] font-medium text-slate-500">
                                Horas acumulables para el legajo digital Cero Papel.
                            </p>
                        </div>
                    </div>

                    <!-- Desglose por Unidades Orgánicas (Áreas Municipales) -->
                    <div class="mt-5 pt-4 border-t border-sky-200/60 dark:border-sky-800/40">
                        <h4 class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-wider flex items-center gap-1.5 mb-3">
                            <Building2 class="size-3.5 text-[#3484a5]" />
                            Servidores Capacitados por Unidad Orgánica (MDSJ)
                        </h4>

                        <div v-if="pdpMetrics.unitBreakdown.length > 0" class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-6">
                            <div
                                v-for="unit in pdpMetrics.unitBreakdown"
                                :key="unit.organizational_unit"
                                class="rounded-xl border border-slate-200 bg-white p-2.5 text-center dark:border-slate-800 dark:bg-slate-950/80 shadow-2xs"
                            >
                                <span class="block text-xs font-black text-[#3484a5] dark:text-sky-300 truncate" :title="unit.organizational_unit">
                                    {{ unit.organizational_unit }}
                                </span>
                                <span class="block text-lg font-black text-slate-900 dark:text-white mt-0.5">
                                    {{ unit.total }}
                                </span>
                                <span class="block text-[10px] font-bold text-slate-500">
                                    inscritos
                                </span>
                            </div>
                        </div>

                        <div v-else class="text-xs font-medium text-slate-500 italic py-2">
                            Aún no se registran matrículas con área organizativa en el año en curso.
                        </div>
                    </div>
                </div>

                <!-- Sección: Mis Cursos Asignados -->
                <div class="space-y-4">
                    <div
                        class="flex items-center justify-between border-b border-slate-200 pb-3 dark:border-slate-800"
                    >
                        <div>
                            <h2
                                class="flex items-center gap-2 text-xl font-black tracking-tight text-slate-950 dark:text-white"
                            >
                                <BookOpen class="size-5 text-[#296d88]" />
                                Mis Capacitaciones Asignadas
                            </h2>
                            <p
                                class="text-xs font-semibold text-slate-700 dark:text-slate-300"
                            >
                                Cursos donde figuras como docente responsable o
                                administrador.
                            </p>
                        </div>
                        <Button
                            as-child
                            variant="outline"
                            size="sm"
                            class="border-slate-300 text-xs font-bold text-slate-800 hover:text-[#3484a5]"
                        >
                            <Link href="/courses">Ver Catálogo General →</Link>
                        </Button>
                    </div>

                    <!-- Estado Vacío -->
                    <div
                        v-if="taughtCourses.length === 0"
                        class="space-y-3 rounded-2xl border-2 border-dashed border-slate-300 bg-white p-10 text-center dark:bg-slate-900"
                    >
                        <div
                            class="mx-auto flex size-16 items-center justify-center rounded-full bg-sky-100 text-[#296d88]"
                        >
                            <BookOpen class="size-8" />
                        </div>
                        <h3
                            class="text-base font-bold text-slate-900 dark:text-white"
                        >
                            No tienes cursos asignados actualmente
                        </h3>
                        <p
                            class="mx-auto max-w-md text-xs font-medium text-slate-600"
                        >
                            Cuando seas asignado a un curso o registres uno
                            nuevo como administrador o docente, aparecerá en
                            este panel.
                        </p>
                        <Button
                            v-if="
                                user?.role === 'admin' ||
                                user?.role === 'docente'
                            "
                            as-child
                            size="sm"
                            class="mt-2 bg-[#3484a5] text-xs font-bold text-white hover:bg-[#153542]"
                        >
                            <Link
                                href="/courses/create"
                                class="flex items-center gap-1.5"
                            >
                                <Plus
                                    class="size-3.5 stroke-[2.5] text-amber-300"
                                />
                                <span>Agregar Curso</span>
                            </Link>
                        </Button>
                    </div>

                    <!-- Lista de Cursos Asignados -->
                    <div
                        v-else
                        class="grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-3"
                    >
                        <Card
                            v-for="course in taughtCourses"
                            :key="course.id"
                            class="flex max-w-full min-w-0 flex-col justify-between overflow-hidden rounded-xl border-2 border-slate-200 bg-white shadow-sm transition-all hover:border-[#296d88] hover:shadow-lg dark:border-slate-800 dark:bg-slate-950"
                        >
                            <CardHeader
                                class="max-w-full min-w-0 space-y-2 p-4 pb-3 sm:p-5"
                            >
                                <div
                                    class="flex w-full min-w-0 items-center justify-between gap-1.5 text-xs"
                                >
                                    <span
                                        class="shrink-0 rounded border border-sky-200 bg-sky-100 px-2.5 py-1 font-mono font-bold text-sky-950 dark:border-[#296d88] dark:bg-[#153542] dark:text-sky-200"
                                    >
                                        {{ course.code }}
                                    </span>
                                    <Badge
                                        class="max-w-[62%] shrink-0 truncate px-2.5 py-0.5 text-[10px] font-extrabold sm:text-[11px]"
                                        :class="
                                            getCourseStatusBadge(course.status)
                                                .class
                                        "
                                    >
                                        {{
                                            getCourseStatusBadge(course.status)
                                                .label
                                        }}
                                    </Badge>
                                </div>
                                <CardTitle
                                    class="line-clamp-2 min-w-0 text-base leading-snug font-black break-words text-slate-950 dark:text-white"
                                >
                                    {{ course.title }}
                                </CardTitle>
                                <div
                                    v-if="course.institution"
                                    class="w-full max-w-full min-w-0 truncate overflow-hidden text-xs font-bold text-[#296d88] dark:text-[#88c0d7]"
                                >
                                    {{ course.institution }}
                                </div>
                                <CardDescription
                                    class="flex min-w-0 items-center gap-1.5 pt-1 text-xs font-bold text-slate-700 dark:text-slate-300"
                                >
                                    <Calendar
                                        class="size-3.5 shrink-0 text-[#296d88]"
                                    />
                                    <span class="truncate"
                                        >Inicio:
                                        <strong>{{
                                            formatDate(
                                                course.start_date,
                                                'compact',
                                            )
                                        }}</strong></span
                                    >
                                    <span>•</span>
                                    <Clock
                                        class="size-3.5 shrink-0 text-amber-600"
                                    />
                                    <span class="truncate">{{
                                        formatHours(course.hours)
                                    }}</span>
                                </CardDescription>
                            </CardHeader>

                            <CardContent class="space-y-2.5 text-xs">
                                <div
                                    class="flex items-center justify-between text-xs font-bold"
                                >
                                    <span
                                        class="text-slate-700 dark:text-slate-300"
                                        >Inscritos / Aforo:</span
                                    >
                                    <span
                                        class="font-extrabold text-[#3484a5] dark:text-[#88c0d7]"
                                    >
                                        {{ course.enrollments_count || 0 }} de
                                        {{ course.capacity }} vacantes
                                    </span>
                                </div>
                                <div
                                    class="h-2.5 w-full overflow-hidden rounded-full border border-slate-200 bg-slate-100 dark:border-slate-700 dark:bg-slate-800"
                                >
                                    <div
                                        class="h-full rounded-full bg-[#3484a5] transition-all"
                                        :style="{
                                            width: `${Math.min(100, ((course.enrollments_count || 0) / course.capacity) * 100)}%`,
                                        }"
                                    />
                                </div>
                            </CardContent>

                            <div
                                class="flex items-center justify-between gap-2 rounded-b-xl border-t border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-900"
                            >
                                <Button
                                    size="sm"
                                    variant="outline"
                                    class="h-9 border-[#3484a5] text-xs font-bold text-[#3484a5] hover:bg-slate-100 dark:border-[#3484a5] dark:text-[#88c0d7] dark:hover:bg-slate-800"
                                    @click="openQrProjection(course)"
                                >
                                    <QrCode
                                        class="mr-1.5 size-4 text-[#3484a5] dark:text-[#88c0d7]"
                                    />
                                    Proyectar QR
                                </Button>
                                <Button
                                    as-child
                                    size="sm"
                                    class="h-9 bg-emerald-600 text-xs font-bold text-white shadow-xs hover:bg-emerald-700"
                                >
                                    <Link :href="`/courses/${course.id}`">
                                        Ver Alumnos →
                                    </Link>
                                </Button>
                            </div>
                        </Card>
                    </div>
                </div>
            </div>

            <!-- ============================================================ -->
            <!-- 2. VISTA: PARTICIPANTE / ALUMNO / ESTUDIANTE                 -->
            <!-- ============================================================ -->
            <div
                v-if="
                    isStudentOrParticipant &&
                    (user?.role === 'participante' ||
                        activeDashboardTab === 'participante')
                "
                class="space-y-8"
                id="mis-cursos"
            >
                <!-- Métricas del Participante -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <Card
                        class="border border-slate-200 bg-white shadow-sm transition-all hover:border-emerald-500 hover:shadow-md dark:border-slate-800 dark:bg-card"
                    >
                        <CardHeader class="pb-2">
                            <CardDescription
                                class="flex items-center justify-between text-xs font-bold text-slate-700 dark:text-slate-300"
                            >
                                <span>Mis Cursos Matriculados</span>
                                <div
                                    class="flex size-8 items-center justify-center rounded-lg bg-emerald-600 text-white shadow-xs"
                                >
                                    <GraduationCap class="size-4" />
                                </div>
                            </CardDescription>
                            <CardTitle
                                class="pt-1 text-3xl font-black text-slate-900 dark:text-white"
                            >
                                {{ metrics.enrolledCount }}
                            </CardTitle>
                        </CardHeader>
                        <CardContent
                            class="text-xs font-bold text-slate-700 dark:text-slate-300"
                        >
                            Capacitaciones activas en tu cuenta
                        </CardContent>
                    </Card>

                    <Card
                        class="border-2 border-amber-300 bg-amber-50/40 shadow-sm dark:border-amber-900 dark:bg-amber-950/20"
                    >
                        <CardHeader class="pb-2">
                            <CardDescription
                                class="flex items-center justify-between text-xs font-bold text-amber-950 dark:text-amber-300"
                            >
                                <span>Asistencias Marcadas</span>
                                <div
                                    class="flex size-8 items-center justify-center rounded-lg bg-amber-600 text-white shadow-xs"
                                >
                                    <QrCode class="size-4" />
                                </div>
                            </CardDescription>
                            <CardTitle
                                class="pt-1 text-3xl font-black text-slate-900 dark:text-white"
                            >
                                {{
                                    studentEnrollments.reduce(
                                        (acc, curr) =>
                                            acc + (curr.attended_sessions || 0),
                                        0,
                                    )
                                }}
                            </CardTitle>
                        </CardHeader>
                        <CardContent
                            class="text-xs font-bold text-slate-700 dark:text-slate-300"
                        >
                            Sesiones validadas con código QR
                        </CardContent>
                    </Card>

                    <Card
                        class="border-2 border-indigo-300 bg-indigo-50/40 shadow-sm dark:border-indigo-900 dark:bg-indigo-950/20"
                    >
                        <CardHeader class="pb-2">
                            <CardDescription
                                class="flex items-center justify-between text-xs font-bold text-indigo-950 dark:text-indigo-300"
                            >
                                <span>Certificados Obtenidos</span>
                                <div
                                    class="flex size-8 items-center justify-center rounded-lg bg-indigo-700 text-white shadow-xs"
                                >
                                    <Award class="size-4" />
                                </div>
                            </CardDescription>
                            <CardTitle
                                class="pt-1 text-3xl font-black text-slate-900 dark:text-white"
                            >
                                {{
                                    studentEnrollments.filter(
                                        (e) =>
                                            e.status === 'aprobado' ||
                                            e.certificate_code,
                                    ).length
                                }}
                            </CardTitle>
                        </CardHeader>
                        <CardContent
                            class="text-xs font-bold text-slate-700 dark:text-slate-300"
                        >
                            Diplomas oficiales con validez web
                        </CardContent>
                    </Card>
                </div>

                <!-- Sección: Mis Capacitaciones Inscritas -->
                <div class="space-y-4">
                    <div
                        class="flex items-center justify-between border-b border-slate-200 pb-3 dark:border-slate-800"
                    >
                        <div>
                            <h2
                                class="flex items-center gap-2 text-xl font-black tracking-tight text-slate-950 dark:text-white"
                            >
                                <GraduationCap class="size-5 text-[#296d88]" />
                                Mis Capacitaciones Inscritas
                            </h2>
                            <p
                                class="text-xs font-semibold text-slate-700 dark:text-slate-300"
                            >
                                Cursos en los que estás formalmente matriculado.
                            </p>
                        </div>
                    </div>

                    <!-- Estado Vacío -->
                    <div
                        v-if="studentEnrollments.length === 0"
                        class="space-y-3 rounded-2xl border-2 border-dashed border-slate-300 bg-white p-10 text-center dark:bg-slate-900"
                    >
                        <div
                            class="mx-auto flex size-16 items-center justify-center rounded-full bg-sky-100 text-[#296d88]"
                        >
                            <GraduationCap class="size-8" />
                        </div>
                        <h3
                            class="text-base font-bold text-slate-900 dark:text-white"
                        >
                            Aún no estás matriculado en ninguna capacitación
                        </h3>
                        <p
                            class="mx-auto max-w-sm text-xs font-medium text-slate-600"
                        >
                            Explora los cursos abiertos disponibles en la parte
                            inferior e inscríbete con un solo clic.
                        </p>
                        <Button
                            as-child
                            size="default"
                            class="bg-[#3484a5] text-xs font-bold text-white hover:bg-[#153542]"
                        >
                            <a href="#cursos-abiertos">Ver Cursos Abiertos</a>
                        </Button>
                    </div>

                    <!-- Tarjetas de Cursos Matriculados -->
                    <div v-else class="grid grid-cols-1 gap-5 md:grid-cols-2">
                        <Card
                            v-for="item in studentEnrollments"
                            :key="item.id"
                            class="flex flex-col justify-between overflow-hidden rounded-xl border-2 border-slate-200 bg-white shadow-sm transition-all hover:border-[#296d88] hover:shadow-lg dark:border-slate-800 dark:bg-slate-950"
                        >
                            <CardHeader class="space-y-2 pb-3">
                                <div class="flex items-center justify-between">
                                    <span
                                        class="rounded border border-sky-200 bg-sky-100 px-2.5 py-1 font-mono text-xs font-bold text-sky-950 dark:border-[#296d88] dark:bg-[#153542] dark:text-sky-200"
                                    >
                                        {{ item.course?.code }}
                                    </span>
                                    <Badge
                                        class="px-2.5 py-0.5 text-xs font-extrabold capitalize"
                                        :class="
                                            enrollmentStatusBadge(item.status)
                                                .class
                                        "
                                    >
                                        {{
                                            enrollmentStatusBadge(item.status)
                                                .label
                                        }}
                                    </Badge>
                                </div>
                                <CardTitle
                                    class="line-clamp-2 text-base leading-snug font-black text-slate-950 dark:text-white"
                                >
                                    {{ item.course?.title }}
                                </CardTitle>

                                <!-- Entidad Organizadora Destacada (Azul Institucional MDSJ) -->
                                <div
                                    class="flex w-full max-w-full min-w-0 items-center gap-1.5 overflow-hidden rounded-lg border border-[#1a6b8a]/20 bg-[#1a6b8a]/5 px-2.5 py-1 text-xs font-bold text-[#1a6b8a] dark:border-[#3484a5]/40 dark:bg-[#1a6b8a]/20 dark:text-[#88c0d7]"
                                >
                                    <Building2
                                        class="size-3.5 shrink-0 text-[#1a6b8a] dark:text-[#3484a5]"
                                    />
                                    <span class="block min-w-0 flex-1 truncate">
                                        <span class="font-extrabold text-[#1a6b8a] dark:text-[#88c0d7]">Organiza:</span>
                                        {{ item.course?.institution || institutionName || 'Municipalidad de San Jerónimo' }}
                                    </span>
                                </div>

                                <!-- Ponente / Docente a Cargo (Ámbar Vivo) -->
                                <div
                                    class="flex max-w-full min-w-0 items-center gap-2 rounded-lg border border-amber-200/90 bg-amber-50/90 px-2.5 py-1.5 text-xs font-bold text-amber-950 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-200"
                                >
                                    <GraduationCap
                                        class="size-4 shrink-0 text-amber-600 dark:text-amber-400"
                                    />
                                    <span class="min-w-0 flex-1 truncate">
                                        <span class="font-extrabold text-amber-700 dark:text-amber-400">Ponente / Docente:</span>
                                        {{
                                            item.course?.instructor
                                                ? `${item.course.instructor.name} ${item.course.instructor.paterno || ''}`
                                                : item.course?.instructor_name ||
                                                  'Por asignar'
                                        }}
                                    </span>
                                </div>
                            </CardHeader>

                            <CardContent class="space-y-3 text-xs">
                                <div
                                    class="grid grid-cols-2 gap-2 rounded-lg border border-slate-200 bg-slate-50 p-2.5 font-bold text-slate-800 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200"
                                >
                                    <div class="flex items-center gap-1.5">
                                        <Calendar
                                            class="size-3.5 shrink-0 text-[#296d88]"
                                        />
                                        <span
                                            >Inicio:
                                            <strong>{{
                                                formatDate(
                                                    item.course?.start_date,
                                                    'compact',
                                                )
                                            }}</strong></span
                                        >
                                    </div>
                                    <div class="flex items-center gap-1.5">
                                        <Clock
                                            class="size-3.5 shrink-0 text-amber-600"
                                        />
                                        <span>{{
                                            formatHours(item.course?.hours)
                                        }}</span>
                                    </div>
                                </div>
                                <!-- Barra de Asistencia Oficial (Mínimo 75%) -->
                                <div
                                    class="space-y-2 rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-900"
                                >
                                    <div
                                        class="flex items-center justify-between text-xs font-bold"
                                    >
                                        <span
                                            class="text-slate-700 dark:text-slate-300"
                                            >Asistencia:</span
                                        >
                                        <span
                                            class="font-black text-[#3484a5] dark:text-[#badbe9]"
                                        >
                                            {{ item.attended_sessions }} de
                                            {{
                                                item.course?.total_sessions || 4
                                            }}
                                            sesiones ({{
                                                item.attendance_percentage || 0
                                            }}%)
                                        </span>
                                    </div>
                                    <div
                                        class="h-2 w-full overflow-hidden rounded-full bg-slate-200 dark:bg-slate-800"
                                    >
                                        <div
                                            class="h-2 rounded-full bg-[#296d88] transition-all duration-500"
                                            :style="{
                                                width: `${Math.min(100, Math.round(((item.attended_sessions || 0) / (item.course?.total_sessions || 4)) * 100))}%`,
                                            }"
                                        />
                                    </div>
                                    <div
                                        class="flex items-center justify-between text-[11px] text-slate-500"
                                    >
                                        <span
                                            >Meta para certificar:
                                            <strong>75%</strong></span
                                        >
                                        <span
                                            v-if="
                                                (item.attendance_percentage ||
                                                    0) >= 75
                                            "
                                            class="font-bold text-emerald-700 dark:text-emerald-400"
                                            >✓ Cumple requisito</span
                                        >
                                        <span
                                            v-else
                                            class="font-bold text-amber-700 dark:text-amber-400"
                                            >Faltan sesiones</span
                                        >
                                    </div>
                                </div>

                                <!-- Calificación y Estado de Certificado -->
                                <div
                                    class="grid grid-cols-2 gap-2 text-[11px] font-bold"
                                >
                                    <div
                                        class="rounded-lg border border-slate-200 bg-white p-2 dark:border-slate-800 dark:bg-slate-950"
                                    >
                                        <span
                                            class="block text-[10px] text-slate-500"
                                            >Nota Final:</span
                                        >
                                        <span
                                            v-if="
                                                item.final_grade !== null &&
                                                item.final_grade !== undefined
                                            "
                                            class="font-mono text-sm font-black text-slate-950 dark:text-white"
                                        >
                                            {{
                                                Number(
                                                    item.final_grade,
                                                ).toFixed(1)
                                            }}
                                            / 20
                                        </span>
                                        <span v-else class="text-slate-400"
                                            >Aún sin nota</span
                                        >
                                    </div>
                                    <div
                                        class="rounded-lg border border-slate-200 bg-white p-2 dark:border-slate-800 dark:bg-slate-950"
                                    >
                                        <span
                                            class="block text-[10px] text-slate-500"
                                            >Certificado:</span
                                        >
                                        <span
                                            v-if="
                                                item.status === 'aprobado' ||
                                                item.certificate_code
                                            "
                                            class="font-bold text-emerald-700 dark:text-emerald-400"
                                        >
                                            Disponible
                                        </span>
                                        <span v-else class="text-slate-400"
                                            >Al cerrar acta</span
                                        >
                                    </div>
                                </div>
                            </CardContent>

                            <div
                                class="flex flex-wrap items-center gap-2 rounded-b-xl border-t border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-900"
                            >
                                <Button
                                    size="sm"
                                    variant="outline"
                                    class="h-9 min-w-[120px] flex-1 cursor-pointer border-[#3484a5] text-xs font-bold text-[#3484a5] hover:bg-slate-100 dark:border-[#3484a5] dark:text-[#88c0d7] dark:hover:bg-slate-800"
                                    @click="openCredentialModal(item)"
                                >
                                    <QrCode
                                        class="mr-1.5 size-4 text-[#3484a5] dark:text-[#88c0d7]"
                                    />
                                    Mi Credencial QR
                                </Button>
                                <Button
                                    as-child
                                    variant="outline"
                                    size="sm"
                                    class="h-9 min-w-[100px] flex-1 border-slate-300 text-xs font-bold text-slate-800 hover:text-[#3484a5] dark:border-slate-700 dark:text-slate-200 dark:hover:text-white dark:bg-slate-800/80"
                                >
                                    <Link :href="`/courses/${item.course_id}`">
                                        <BookOpen
                                            class="mr-1.5 size-4 text-slate-700 dark:text-slate-300"
                                        />
                                        Ver Curso
                                    </Link>
                                </Button>
                                <Button
                                    v-if="
                                        item.status === 'aprobado' ||
                                        item.certificate_code
                                    "
                                    as-child
                                    size="sm"
                                    variant="outline"
                                    class="h-9 min-w-[110px] flex-1 border-amber-500 bg-amber-50 text-xs font-black text-amber-950 hover:bg-amber-100 dark:bg-amber-950 dark:text-amber-200"
                                >
                                    <a
                                        :href="`/certificates?dni=${item.dni}&code=${item.certificate_code || ''}`"
                                        target="_blank"
                                    >
                                        <Award
                                            class="mr-1.5 size-4 text-amber-700"
                                        />
                                        Certificado
                                    </a>
                                </Button>
                            </div>
                        </Card>
                    </div>
                </div>

                <!-- Sección: Convocatorias Abiertas -->
                <div
                    id="cursos-abiertos"
                    class="space-y-4 border-t border-slate-200 pt-6 dark:border-slate-800"
                >
                    <div class="flex items-center justify-between">
                        <div>
                            <h2
                                class="flex items-center gap-2 text-xl font-black tracking-tight text-slate-950 dark:text-white"
                            >
                                <Sparkles class="size-5 text-amber-600" />
                                Convocatorias Abiertas para Inscripción
                            </h2>
                            <p
                                class="text-xs font-semibold text-slate-700 dark:text-slate-300"
                            >
                                Puedes inscribirte directamente completando tu
                                ficha oficial en línea.
                            </p>
                        </div>
                        <Button
                            as-child
                            variant="outline"
                            size="sm"
                            class="border-slate-300 text-xs font-bold text-slate-800 hover:text-[#3484a5]"
                        >
                            <Link href="/courses">Ver Catálogo Completo →</Link>
                        </Button>
                    </div>

                    <div
                        class="grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-3"
                    >
                        <Card
                            v-for="course in openCourses"
                            :key="course.id"
                            class="flex max-w-full min-w-0 flex-col justify-between overflow-hidden rounded-xl border-2 border-slate-200 bg-white shadow-sm transition-all hover:border-[#296d88] hover:shadow-lg dark:border-slate-800 dark:bg-slate-950"
                        >
                            <CardHeader
                                class="max-w-full min-w-0 space-y-1.5 p-4 pb-3 sm:p-5"
                            >
                                <div
                                    class="flex w-full min-w-0 items-center justify-between gap-1.5 text-xs"
                                >
                                    <span
                                        class="shrink-0 rounded border border-sky-200 bg-sky-100 px-2.5 py-0.5 font-mono font-bold text-sky-950 dark:border-[#296d88] dark:bg-[#153542] dark:text-sky-200"
                                    >
                                        {{ course.code }}
                                    </span>
                                    <Badge
                                        class="max-w-[62%] shrink-0 truncate border border-amber-300 bg-amber-100 text-[10px] font-bold text-amber-950"
                                    >
                                        Inscripción Abierta
                                    </Badge>
                                </div>
                                <CardTitle
                                    class="line-clamp-2 min-w-0 text-sm leading-snug font-extrabold break-words text-slate-950 sm:text-base dark:text-white"
                                >
                                    {{ course.title }}
                                </CardTitle>

                                <!-- Entidad Organizadora (Azul Institucional MDSJ) -->
                                <div
                                    class="flex w-full max-w-full min-w-0 items-center gap-1.5 overflow-hidden rounded-lg border border-[#1a6b8a]/20 bg-[#1a6b8a]/5 px-2.5 py-1 text-xs font-bold text-[#1a6b8a] dark:border-[#3484a5]/40 dark:bg-[#1a6b8a]/20 dark:text-[#88c0d7]"
                                >
                                    <Building2
                                        class="size-3.5 shrink-0 text-[#1a6b8a] dark:text-[#3484a5]"
                                    />
                                    <span class="block min-w-0 flex-1 truncate">
                                        <span class="font-extrabold text-[#1a6b8a] dark:text-[#88c0d7]">Organiza:</span>
                                        {{ course.institution || institutionName || 'Municipalidad de San Jerónimo' }}
                                    </span>
                                </div>

                                <!-- Ponente / Docente a Cargo (Ámbar Vivo) -->
                                <div
                                    class="flex max-w-full min-w-0 items-center gap-2 rounded-lg border border-amber-200/90 bg-amber-50/90 px-2.5 py-1 text-xs font-bold text-amber-950 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-200"
                                >
                                    <GraduationCap
                                        class="size-3.5 shrink-0 text-amber-600 dark:text-amber-400"
                                    />
                                    <span class="min-w-0 flex-1 truncate">
                                        <span class="font-extrabold text-amber-700 dark:text-amber-400">Ponente:</span>
                                        {{
                                            course.instructor
                                                ? `${course.instructor.name} ${course.instructor.paterno || ''}`
                                                : 'Docente asignado'
                                        }}
                                    </span>
                                </div>
                                <CardDescription
                                    class="flex min-w-0 items-center gap-1.5 pt-1 text-xs font-bold text-slate-700 dark:text-slate-300"
                                >
                                    <Calendar
                                        class="size-3.5 shrink-0 text-[#296d88]"
                                    />
                                    <span class="truncate"
                                        >Inicio:
                                        <strong>{{
                                            formatDate(
                                                course.start_date,
                                                'compact',
                                            )
                                        }}</strong></span
                                    >
                                    <span>•</span>
                                    <Clock
                                        class="size-3.5 shrink-0 text-amber-600"
                                    />
                                    <span class="truncate">{{
                                        formatHours(course.hours)
                                    }}</span>
                                </CardDescription>
                            </CardHeader>

                            <CardContent class="text-xs">
                                <div
                                    class="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 p-2.5 text-xs font-bold text-slate-800 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200"
                                >
                                    <span>Vacantes disponibles:</span>
                                    <strong
                                        class="text-sm font-black text-[#3484a5] dark:text-[#88c0d7]"
                                    >
                                        {{
                                            Math.max(
                                                0,
                                                course.capacity -
                                                    (course.enrollments_count ||
                                                        0),
                                            )
                                        }}
                                        cupos
                                    </strong>
                                </div>
                            </CardContent>

                            <div
                                class="flex items-center justify-between gap-2 rounded-b-xl border-t border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-900"
                            >
                                <Button
                                    as-child
                                    variant="outline"
                                    size="sm"
                                    class="h-9 flex-1 border-slate-300 text-xs font-bold text-slate-800 hover:text-slate-900 dark:border-slate-700 dark:text-slate-200 dark:hover:text-white dark:bg-slate-800/80"
                                >
                                    <Link :href="`/courses/${course.id}`"
                                        >Temario</Link
                                    >
                                </Button>
                                <Button
                                    size="sm"
                                    class="h-9 flex-1 bg-[#3484a5] px-4 text-xs font-black text-white shadow-sm hover:bg-[#153542]"
                                    @click="openEnroll(course)"
                                >
                                    <CheckCircle2 class="mr-1.5 size-4" />
                                    Inscribirme
                                </Button>
                            </div>
                        </Card>
                    </div>
                </div>
            </div>

            <!-- MODAL: PROYECCIÓN QR (PARA DOCENTE) -->
            <Dialog :open="isQrModalOpen" @update:open="isQrModalOpen = $event">
                <DialogContent
                    class="flex max-h-[92vh] w-[96vw] flex-col overflow-hidden rounded-2xl border-4 border-[#3484a5] bg-white p-0 text-center shadow-2xl sm:max-w-2xl md:max-w-3xl dark:bg-slate-900"
                >
                    <div
                        class="shrink-0 border-b border-slate-200 p-6 pr-12 pb-4 dark:border-slate-800"
                    >
                        <DialogHeader class="space-y-2">
                            <div
                                class="mx-auto inline-flex items-center gap-2 rounded-full bg-sky-100 px-3 py-1 text-xs font-black tracking-wider text-sky-950 uppercase"
                            >
                                <QrCode class="size-4 text-[#296d88]" />
                                <span>Control de Asistencia Digital</span>
                            </div>
                            <DialogTitle
                                class="text-2xl font-black text-slate-950 dark:text-white"
                            >
                                {{ activeQrCourse?.title }}
                            </DialogTitle>
                            <DialogDescription
                                class="mx-auto max-w-lg text-xs font-medium text-slate-700 sm:text-sm dark:text-slate-300"
                            >
                                Proyecta esta pantalla en el aula para que los
                                alumnos escaneen el código desde sus teléfonos
                                celulares.
                            </DialogDescription>
                        </DialogHeader>
                    </div>

                    <!-- QR Visualization Gigante Real y Escaneable -->
                    <div
                        class="custom-scrollbar flex-1 space-y-5 overflow-y-auto overscroll-contain p-6"
                    >
                        <div
                            v-if="isQrLoading"
                            class="inline-flex min-h-[280px] min-w-[280px] items-center justify-center rounded-3xl border-4 border-slate-200 bg-white p-6 text-xs font-bold text-slate-500 dark:border-slate-800"
                        >
                            Generando QR...
                        </div>
                        <div
                            v-else-if="activeQrSvg"
                            class="inline-block rounded-3xl border-4 border-[#3484a5] bg-white p-6 shadow-xl"
                            v-html="activeQrSvg"
                        ></div>
                        <div
                            v-else
                            class="rounded-3xl border border-slate-200 bg-slate-50 p-6 text-xs font-bold text-slate-700 dark:border-slate-800 dark:bg-slate-900/60 dark:text-slate-300"
                        >
                            No se pudo generar el QR. Intenta nuevamente.
                        </div>
                        <div class="space-y-2">
                            <div
                                class="inline-block rounded-full border-2 border-emerald-500/40 bg-emerald-50 px-5 py-2 font-mono text-sm font-black tracking-widest text-emerald-900 shadow-sm sm:text-base dark:border-emerald-600/40 dark:bg-emerald-950/40 dark:text-emerald-200"
                            >
                                CAPACITACIÓN: {{ activeQrCourse?.code }}
                            </div>
                            <p
                                class="text-xs font-bold text-slate-700 dark:text-slate-300"
                            >
                                Escanea el código para abrir la página pública
                                de la capacitación. Para asistencia, utiliza el
                                QR de la sesión desde el módulo académico.
                            </p>
                        </div>

                        <div class="pt-2">
                            <Button
                                @click="isQrModalOpen = false"
                                class="h-11 w-full cursor-pointer bg-emerald-600 text-sm font-bold text-white shadow-md hover:bg-emerald-700"
                            >
                                Finalizar y Cerrar Proyección
                            </Button>
                        </div>
                    </div>
                </DialogContent>
            </Dialog>

            <!-- ENROLLMENT MODAL (REUTILIZABLE Y AMPLIO) -->
            <EnrollmentModal
                :course="selectedCourseToEnroll"
                v-model:open="isEnrollModalOpen"
                @enrolled="isEnrollModalOpen = false"
            />
        </div>

        <!-- MODAL CREDENCIAL QR DEL PARTICIPANTE (SIGC-6) -->
        <Dialog
            :open="isCredentialModalOpen"
            @update:open="isCredentialModalOpen = $event"
        >
            <DialogContent
                class="w-[94vw] overflow-hidden rounded-3xl border border-sky-200 bg-white p-0 shadow-2xl sm:max-w-md dark:bg-slate-950"
            >
                <div
                    class="space-y-1 bg-gradient-to-br from-[#0b1e27] via-[#153542] to-[#296d88] p-6 text-center text-white"
                >
                    <Badge
                        variant="outline"
                        class="border-amber-400 text-[10px] font-black text-amber-300 uppercase"
                    >
                        Credencial Oficial de Asistencia
                    </Badge>
                    <h3 class="pt-1 text-xl font-black">
                        {{ activeCredentialEnrollment?.nombres }}
                        {{ activeCredentialEnrollment?.paterno }}
                    </h3>
                    <p class="font-mono text-xs text-sky-200">
                        DNI: {{ activeCredentialEnrollment?.dni }}
                    </p>
                </div>

                <div
                    class="flex flex-col items-center justify-center space-y-4 p-6 text-center"
                >
                    <div
                        v-if="isCredentialQrLoading"
                        class="flex min-h-[260px] min-w-[260px] items-center justify-center rounded-2xl border-4 border-slate-200 bg-white p-3 text-xs font-bold text-slate-500 shadow-md dark:border-slate-800"
                    >
                        Generando QR...
                    </div>
                    <div
                        v-else-if="activeCredentialQrSvg"
                        class="rounded-2xl border-4 border-[#3484a5] bg-white p-3 shadow-md"
                        v-html="activeCredentialQrSvg"
                    ></div>
                    <div
                        v-else
                        class="rounded-2xl border border-slate-200 bg-slate-50 p-3 text-xs font-bold text-slate-600 dark:border-slate-800 dark:bg-slate-900/50 dark:text-slate-300"
                    >
                        No se pudo generar el QR de la credencial.
                    </div>

                    <div class="space-y-1">
                        <div
                            class="text-[11px] font-bold text-slate-500 uppercase dark:text-slate-400"
                        >
                            Código Único de Matrícula
                        </div>
                        <div
                            class="rounded-lg border border-slate-200 bg-slate-100 px-4 py-1.5 font-mono text-lg font-black tracking-wider text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                        >
                            {{
                                activeCredentialEnrollment?.credential_code ||
                                'INS-' +
                                    activeCredentialEnrollment?.course_id +
                                    '-' +
                                    activeCredentialEnrollment?.dni?.slice(-4)
                            }}
                        </div>
                        <p class="pt-1 text-[11px] text-slate-500">
                            Presenta este código al ingresar a la clase para
                            registrar tu asistencia.
                        </p>
                    </div>

                    <Button
                        variant="outline"
                        size="sm"
                        class="border-[#3484a5] text-xs font-bold text-[#3484a5] hover:bg-slate-100 dark:border-[#3484a5] dark:text-[#88c0d7] dark:hover:bg-slate-800"
                        @click="isCredentialModalOpen = false"
                    >
                        Cerrar Credencial
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
