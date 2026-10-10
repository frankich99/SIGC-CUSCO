<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
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
} from '@lucide/vue';
import type { BreadcrumbItem } from '@/types';

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
    status: 'inscrito' | 'en_curso' | 'aprobado' | 'desaprobado' | 'reprobado' | 'cancelado' | string;
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
    return user.value?.role === 'participante' || user.value?.role === 'admin' || (props.studentEnrollments && props.studentEnrollments.length > 0);
});

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Panel Principal',
        href: '/dashboard',
    },
];

// Active view toggle when admin has both views or user is enrolled
const activeDashboardTab = ref<'organizador' | 'participante'>(
    user.value?.role === 'participante' ? 'participante' : 'organizador'
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
        const response = await fetch(courseQr.url(course.id), { headers: { Accept: 'application/json' } });
        if (!response.ok) { throw new Error('No se pudo generar el QR de la capacitación.'); }
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

    fetch(credentialQr.url(enrollment.id), { headers: { Accept: 'application/json' } })
        .then((response) => {
            if (!response.ok) { throw new Error('No se pudo generar el QR de la credencial.'); }
            return response.json();
        })
        .then((data) => { activeCredentialQrSvg.value = data.svg || ''; })
        .catch(() => { activeCredentialQrSvg.value = ''; })
        .finally(() => { isCredentialQrLoading.value = false; });
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
                badgeClass: 'bg-rose-900 text-white font-black shadow-xs',
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
    switch (status) {
        case 'aprobado':
            return {
                label: 'Aprobado',
                class: 'bg-emerald-100 text-emerald-950 border border-emerald-300 dark:bg-emerald-950 dark:text-emerald-200 dark:border-emerald-800',
            };
        case 'en_curso':
            return {
                label: 'En Curso',
                class: 'bg-amber-100 text-amber-950 border border-amber-300 dark:bg-amber-950 dark:text-amber-200 dark:border-amber-800',
            };
        case 'inscrito':
            return {
                label: 'Inscrito',
                class: 'bg-sky-100 text-sky-950 border border-sky-300 dark:bg-sky-950 dark:text-sky-200 dark:border-sky-800',
            };
        case 'reprobado':
        case 'desaprobado':
            return {
                label: 'No Aprobado',
                class: 'bg-rose-100 text-rose-950 border border-rose-300 dark:bg-rose-950 dark:text-rose-200 dark:border-rose-800',
            };
        case 'cancelado':
            return {
                label: 'Cancelado',
                class: 'bg-slate-100 text-slate-800 border border-slate-300 dark:bg-slate-900 dark:text-slate-300 dark:border-slate-700',
            };
        default:
            return {
                label: status,
                class: 'bg-slate-100 text-slate-800 border border-slate-300',
            };
    }
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Panel de Control - SIGC-CUSCO" />

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8 space-y-8 min-w-0 w-full overflow-x-hidden">
            <!-- BANNER PRINCIPAL: GRANATE IMPERIAL CUSCO (UNSAAC) -->
            <div class="relative overflow-hidden rounded-2xl border-2 border-rose-900/40 bg-gradient-to-r from-[#4c0519] via-[#701a31] to-slate-950 p-6 sm:p-8 text-white shadow-xl">
                <!-- Background decorative glow -->
                <div class="absolute -right-10 -bottom-10 size-64 bg-amber-500/10 rounded-full blur-3xl pointer-events-none"></div>

                <div class="relative z-10 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                    <div class="space-y-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-[11px] uppercase tracking-wider font-black bg-white/20 text-white px-3 py-1 rounded-full backdrop-blur-sm border border-white/20">
                                SIGC-CUSCO • Sistema Integral
                            </span>
                            <span class="text-[11px] uppercase tracking-wider px-3 py-1 rounded-full" :class="roleBadgeData(user?.role).badgeClass">
                                {{ roleBadgeData(user?.role).label }}
                            </span>
                        </div>

                        <h1 class="text-2xl sm:text-4xl font-black tracking-tight text-white drop-shadow-xs">
                            ¡Bienvenido, {{ user?.name }}!
                        </h1>

                        <p class="text-xs sm:text-sm text-rose-100 font-medium max-w-2xl leading-relaxed">
                            {{ roleBadgeData(user?.role).desc }}
                        </p>
                    </div>

                    <!-- Botones de Acción Primarios Granate y Dorado -->
                    <div class="flex flex-wrap items-center gap-3">
                        <Button v-if="user?.role === 'admin' || user?.role === 'docente'" as-child size="default" class="bg-amber-600 hover:bg-amber-700 text-white font-black text-xs shadow-md">
                            <Link href="/courses/create" class="flex items-center gap-1.5">
                                <Plus class="size-4 text-white stroke-[2.5]" />
                                <span>Agregar Curso</span>
                            </Link>
                        </Button>
                        <Button as-child size="default" variant="secondary" class="bg-white hover:bg-rose-50 text-rose-950 font-bold text-xs shadow-md">
                            <Link href="/courses">
                                <GraduationCap class="mr-1.5 size-4 text-rose-900" />
                                Catálogo Completo
                            </Link>
                        </Button>
                    </div>
                </div>

                <!-- Selector de Vista para Administradores -->
                <div v-if="user?.role === 'admin'" class="relative z-10 mt-6 pt-4 border-t border-white/20 flex flex-wrap items-center gap-2">
                    <span class="text-xs font-bold text-rose-200 mr-2">Modo de visualización:</span>
                    <button
                        type="button"
                        @click="activeDashboardTab = 'organizador'"
                        class="px-3.5 py-1.5 rounded-lg text-xs font-black transition-all cursor-pointer"
                        :class="activeDashboardTab === 'organizador' ? 'bg-white text-rose-950 shadow-md ring-2 ring-amber-400' : 'bg-white/10 text-white hover:bg-white/20'"
                    >
                        Vista Organizador / Docente
                    </button>
                    <button
                        type="button"
                        @click="activeDashboardTab = 'participante'"
                        class="px-3.5 py-1.5 rounded-lg text-xs font-black transition-all cursor-pointer"
                        :class="activeDashboardTab === 'participante' ? 'bg-white text-rose-950 shadow-md ring-2 ring-amber-400' : 'bg-white/10 text-white hover:bg-white/20'"
                    >
                        Vista Participante / Alumno
                    </button>
                </div>
            </div>

            <!-- ============================================================ -->
            <!-- 1. VISTA: ORGANIZADOR / DOCENTE / INSTRUCTOR                 -->
            <!-- ============================================================ -->
            <div v-if="isOrganizerOrTeacher && (user?.role !== 'admin' || activeDashboardTab === 'organizador')" class="space-y-8">
                <!-- Grilla de Métricas: Granate, Dorado y Alto Contraste -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Métrica 1: Cursos Asignados (Granate Cusco) -->
                    <Card class="border-2 border-rose-300 dark:border-rose-900 bg-rose-50/40 dark:bg-rose-950/20 shadow-sm hover:shadow-md transition-all">
                        <CardHeader class="pb-2">
                            <CardDescription class="text-xs font-bold text-rose-950 dark:text-rose-300 flex items-center justify-between">
                                <span>Cursos a mi Cargo</span>
                                <div class="size-8 rounded-lg bg-rose-900 text-white flex items-center justify-center shadow-xs">
                                    <BookOpen class="size-4" />
                                </div>
                            </CardDescription>
                            <CardTitle class="text-3xl font-black text-slate-900 dark:text-white pt-1">
                                {{ metrics.taughtCount }}
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="text-xs font-bold text-slate-700 dark:text-slate-300">
                            Capacitaciones asignadas activas
                        </CardContent>
                    </Card>

                    <!-- Métrica 2: Inscripciones Abiertas (Dorado Cusco) -->
                    <Card class="border-2 border-amber-300 dark:border-amber-900 bg-amber-50/40 dark:bg-amber-950/20 shadow-sm hover:shadow-md transition-all">
                        <CardHeader class="pb-2">
                            <CardDescription class="text-xs font-bold text-amber-950 dark:text-amber-300 flex items-center justify-between">
                                <span>Inscripciones Abiertas</span>
                                <div class="size-8 rounded-lg bg-amber-600 text-white flex items-center justify-center shadow-xs">
                                    <GraduationCap class="size-4" />
                                </div>
                            </CardDescription>
                            <CardTitle class="text-3xl font-black text-slate-900 dark:text-white pt-1">
                                {{ metrics.openCourses }}
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="text-xs font-bold text-slate-700 dark:text-slate-300">
                            Recibiendo nuevos participantes
                        </CardContent>
                    </Card>

                    <!-- Métrica 3: Total Registrados -->
                    <Card class="border-2 border-indigo-300 dark:border-indigo-900 bg-indigo-50/40 dark:bg-indigo-950/20 shadow-sm hover:shadow-md transition-all">
                        <CardHeader class="pb-2">
                            <CardDescription class="text-xs font-bold text-indigo-950 dark:text-indigo-300 flex items-center justify-between">
                                <span>Total Capacitaciones</span>
                                <div class="size-8 rounded-lg bg-indigo-700 text-white flex items-center justify-center shadow-xs">
                                    <Award class="size-4" />
                                </div>
                            </CardDescription>
                            <CardTitle class="text-3xl font-black text-slate-900 dark:text-white pt-1">
                                {{ metrics.totalCourses }}
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="text-xs font-bold text-slate-700 dark:text-slate-300">
                            Registradas en SIGC-CUSCO
                        </CardContent>
                    </Card>

                    <!-- Métrica 4: Asistencia QR Dinámica -->
                    <Card class="border-2 border-rose-400 dark:border-rose-800 bg-rose-100/50 dark:bg-rose-950/30 shadow-sm hover:shadow-md transition-all">
                        <CardHeader class="pb-2">
                            <CardDescription class="text-xs font-bold text-rose-950 dark:text-rose-200 flex items-center justify-between">
                                <span>Asistencia QR en Vivo</span>
                                <div class="size-8 rounded-lg bg-rose-950 text-white flex items-center justify-center shadow-xs">
                                    <QrCode class="size-4" />
                                </div>
                            </CardDescription>
                            <CardTitle class="text-base font-black text-rose-950 dark:text-rose-200 pt-2">
                                Proyección en Aula
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="text-xs font-bold text-slate-700 dark:text-slate-300">
                            Genera códigos QR dinámicos para tus clases
                        </CardContent>
                    </Card>
                </div>

                <!-- Sección: Mis Cursos Asignados -->
                <div class="space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
                        <div>
                            <h2 class="text-xl font-black tracking-tight text-slate-950 dark:text-white flex items-center gap-2">
                                <BookOpen class="size-5 text-rose-800" />
                                Mis Capacitaciones Asignadas
                            </h2>
                            <p class="text-xs font-semibold text-slate-700 dark:text-slate-300">
                                Cursos donde figuras como docente responsable o administrador.
                            </p>
                        </div>
                        <Button as-child variant="outline" size="sm" class="text-xs font-bold border-slate-300 text-slate-800 hover:text-rose-900">
                            <Link href="/courses">Ver Catálogo General →</Link>
                        </Button>
                    </div>

                    <!-- Estado Vacío -->
                    <div v-if="taughtCourses.length === 0" class="p-10 text-center border-2 border-dashed border-slate-300 rounded-2xl space-y-3 bg-white dark:bg-slate-900">
                        <div class="size-16 rounded-full bg-rose-100 text-rose-800 flex items-center justify-center mx-auto">
                            <BookOpen class="size-8" />
                        </div>
                        <h3 class="text-base font-bold text-slate-900 dark:text-white">No tienes cursos asignados actualmente</h3>
                        <p class="text-xs font-medium text-slate-600 max-w-md mx-auto">
                            Cuando seas asignado a un curso o registres uno nuevo como administrador o docente, aparecerá en este panel.
                        </p>
                        <Button v-if="user?.role === 'admin' || user?.role === 'docente'" as-child size="sm" class="mt-2 text-xs font-bold bg-rose-900 hover:bg-rose-950 text-white">
                            <Link href="/courses/create" class="flex items-center gap-1.5">
                                <Plus class="size-3.5 text-amber-300 stroke-[2.5]" />
                                <span>Agregar Curso</span>
                            </Link>
                        </Button>
                    </div>

                    <!-- Lista de Cursos Asignados -->
                    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                        <Card v-for="course in taughtCourses" :key="course.id" class="border-2 border-slate-200 dark:border-slate-800 hover:border-rose-800 shadow-sm hover:shadow-lg transition-all flex flex-col justify-between rounded-xl bg-white dark:bg-slate-950 min-w-0 max-w-full overflow-hidden">
                            <CardHeader class="p-4 sm:p-5 pb-3 space-y-2 min-w-0 max-w-full">
                                <div class="flex items-center justify-between gap-1.5 min-w-0 w-full text-xs">
                                    <span class="font-mono font-bold text-rose-950 bg-rose-100 dark:bg-rose-950 dark:text-rose-200 px-2.5 py-1 rounded border border-rose-200 dark:border-rose-800 shrink-0">
                                        {{ course.code }}
                                    </span>
                                    <Badge class="text-[10px] sm:text-[11px] font-extrabold uppercase px-2.5 py-0.5 shrink-0 truncate max-w-[62%]" :class="course.status === 'abierto' ? 'bg-amber-100 text-amber-950 border border-amber-300 dark:bg-amber-950 dark:text-amber-200 dark:border-amber-800' : 'bg-slate-100 text-slate-800'">
                                        {{ course.status }}
                                    </Badge>
                                </div>
                                <CardTitle class="text-base font-black text-slate-950 dark:text-white line-clamp-2 leading-snug break-words min-w-0">
                                    {{ course.title }}
                                </CardTitle>
                                <div v-if="course.institution" class="text-xs font-bold text-rose-800 dark:text-rose-400 truncate w-full min-w-0 max-w-full overflow-hidden">
                                    {{ course.institution }}
                                </div>
                                <CardDescription class="text-xs font-bold text-slate-700 dark:text-slate-300 flex items-center gap-1.5 pt-1 min-w-0">
                                    <Calendar class="size-3.5 text-rose-800 shrink-0" />
                                    <span class="truncate">Inicio: <strong>{{ formatDate(course.start_date, 'compact') }}</strong></span>
                                    <span>•</span>
                                    <Clock class="size-3.5 text-amber-600 shrink-0" />
                                    <span class="truncate">{{ formatHours(course.hours) }}</span>
                                </CardDescription>
                            </CardHeader>

                            <CardContent class="text-xs space-y-2.5">
                                <div class="flex items-center justify-between text-xs font-bold">
                                    <span class="text-slate-700 dark:text-slate-300">Inscritos / Aforo:</span>
                                    <span class="text-rose-900 dark:text-rose-400 font-extrabold">
                                        {{ course.enrollments_count || 0 }} de {{ course.capacity }} vacantes
                                    </span>
                                </div>
                                <div class="w-full bg-slate-100 dark:bg-slate-800 h-2.5 rounded-full overflow-hidden border border-slate-200 dark:border-slate-700">
                                    <div
                                        class="h-full bg-rose-900 rounded-full transition-all"
                                        :style="{ width: `${Math.min(100, ((course.enrollments_count || 0) / course.capacity) * 100)}%` }"
                                    />
                                </div>
                            </CardContent>

                            <div class="p-3 bg-slate-50 dark:bg-slate-900 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between gap-2 rounded-b-xl">
                                <Button size="sm" variant="outline" class="text-xs font-bold border-rose-800 text-rose-900 hover:bg-rose-50 h-9" @click="openQrProjection(course)">
                                    <QrCode class="size-4 mr-1.5 text-rose-800" />
                                    Proyectar QR
                                </Button>
                                <Button as-child size="sm" class="text-xs font-bold bg-rose-900 hover:bg-rose-950 text-white h-9">
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
            <div v-if="isStudentOrParticipant && (user?.role === 'participante' || activeDashboardTab === 'participante')" class="space-y-8" id="mis-cursos">
                <!-- Métricas del Participante -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <Card class="border-2 border-rose-300 dark:border-rose-900 bg-rose-50/40 dark:bg-rose-950/20 shadow-sm">
                        <CardHeader class="pb-2">
                            <CardDescription class="text-xs font-bold text-rose-950 dark:text-rose-300 flex items-center justify-between">
                                <span>Mis Cursos Matriculados</span>
                                <div class="size-8 rounded-lg bg-rose-900 text-white flex items-center justify-center shadow-xs">
                                    <GraduationCap class="size-4" />
                                </div>
                            </CardDescription>
                            <CardTitle class="text-3xl font-black text-slate-900 dark:text-white pt-1">
                                {{ metrics.enrolledCount }}
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="text-xs font-bold text-slate-700 dark:text-slate-300">
                            Capacitaciones activas en tu cuenta
                        </CardContent>
                    </Card>

                    <Card class="border-2 border-amber-300 dark:border-amber-900 bg-amber-50/40 dark:bg-amber-950/20 shadow-sm">
                        <CardHeader class="pb-2">
                            <CardDescription class="text-xs font-bold text-amber-950 dark:text-amber-300 flex items-center justify-between">
                                <span>Asistencias Marcadas</span>
                                <div class="size-8 rounded-lg bg-amber-600 text-white flex items-center justify-center shadow-xs">
                                    <QrCode class="size-4" />
                                </div>
                            </CardDescription>
                            <CardTitle class="text-3xl font-black text-slate-900 dark:text-white pt-1">
                                {{ studentEnrollments.reduce((acc, curr) => acc + (curr.attended_sessions || 0), 0) }}
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="text-xs font-bold text-slate-700 dark:text-slate-300">
                            Sesiones validadas con código QR
                        </CardContent>
                    </Card>

                    <Card class="border-2 border-indigo-300 dark:border-indigo-900 bg-indigo-50/40 dark:bg-indigo-950/20 shadow-sm">
                        <CardHeader class="pb-2">
                            <CardDescription class="text-xs font-bold text-indigo-950 dark:text-indigo-300 flex items-center justify-between">
                                <span>Certificados Obtenidos</span>
                                <div class="size-8 rounded-lg bg-indigo-700 text-white flex items-center justify-center shadow-xs">
                                    <Award class="size-4" />
                                </div>
                            </CardDescription>
                            <CardTitle class="text-3xl font-black text-slate-900 dark:text-white pt-1">
                                {{ studentEnrollments.filter(e => e.status === 'aprobado' || e.certificate_code).length }}
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="text-xs font-bold text-slate-700 dark:text-slate-300">
                            Diplomas oficiales con validez web
                        </CardContent>
                    </Card>
                </div>

                <!-- Sección: Mis Capacitaciones Inscritas -->
                <div class="space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
                        <div>
                            <h2 class="text-xl font-black tracking-tight text-slate-950 dark:text-white flex items-center gap-2">
                                <GraduationCap class="size-5 text-rose-800" />
                                Mis Capacitaciones Inscritas
                            </h2>
                            <p class="text-xs font-semibold text-slate-700 dark:text-slate-300">
                                Cursos en los que estás formalmente matriculado.
                            </p>
                        </div>
                    </div>

                    <!-- Estado Vacío -->
                    <div v-if="studentEnrollments.length === 0" class="p-10 text-center rounded-2xl border-2 border-dashed border-slate-300 space-y-3 bg-white dark:bg-slate-900">
                        <div class="size-16 rounded-full bg-rose-100 text-rose-800 flex items-center justify-center mx-auto">
                            <GraduationCap class="size-8" />
                        </div>
                        <h3 class="text-base font-bold text-slate-900 dark:text-white">Aún no estás matriculado en ninguna capacitación</h3>
                        <p class="text-xs font-medium text-slate-600 max-w-sm mx-auto">
                            Explora los cursos abiertos disponibles en la parte inferior e inscríbete con un solo clic.
                        </p>
                        <Button as-child size="default" class="bg-rose-900 hover:bg-rose-950 text-white text-xs font-bold">
                            <a href="#cursos-abiertos">Ver Cursos Abiertos</a>
                        </Button>
                    </div>

                    <!-- Tarjetas de Cursos Matriculados -->
                    <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <Card v-for="item in studentEnrollments" :key="item.id" class="border-2 border-slate-200 dark:border-slate-800 hover:border-rose-800 shadow-sm hover:shadow-lg transition-all overflow-hidden flex flex-col justify-between rounded-xl bg-white dark:bg-slate-950">
                            <CardHeader class="pb-3 space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="font-mono text-xs font-bold text-rose-950 bg-rose-100 dark:bg-rose-950 dark:text-rose-200 px-2.5 py-1 rounded border border-rose-200 dark:border-rose-800">
                                        {{ item.course?.code }}
                                    </span>
                                    <Badge class="capitalize text-xs font-extrabold px-2.5 py-0.5" :class="enrollmentStatusBadge(item.status).class">
                                        {{ enrollmentStatusBadge(item.status).label }}
                                    </Badge>
                                </div>
                                <CardTitle class="text-base font-black text-slate-950 dark:text-white line-clamp-2 leading-snug">
                                    {{ item.course?.title }}
                                </CardTitle>
                                <CardDescription class="text-xs font-bold text-slate-700 dark:text-slate-300">
                                    Ponente / Docente: {{ item.course?.instructor ? `${item.course.instructor.name} ${item.course.instructor.paterno || ''}` : (item.course?.instructor_name || 'Por asignar') }}
                                </CardDescription>
                            </CardHeader>

                            <CardContent class="text-xs space-y-3">
                                <div class="grid grid-cols-2 gap-2 text-slate-800 dark:text-slate-200 font-bold bg-slate-50 dark:bg-slate-900 p-2.5 rounded-lg border border-slate-200 dark:border-slate-800">
                                    <div class="flex items-center gap-1.5">
                                        <Calendar class="size-3.5 text-rose-800 shrink-0" />
                                        <span>Inicio: <strong>{{ formatDate(item.course?.start_date, 'compact') }}</strong></span>
                                    </div>
                                    <div class="flex items-center gap-1.5">
                                        <Clock class="size-3.5 text-amber-600 shrink-0" />
                                        <span>{{ formatHours(item.course?.hours) }}</span>
                                    </div>
                                </div>
                                <!-- Barra de Asistencia Oficial (Mínimo 75%) -->
                                <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-2">
                                    <div class="flex justify-between items-center text-xs font-bold">
                                        <span class="text-slate-700 dark:text-slate-300">Asistencia:</span>
                                        <span class="text-rose-900 dark:text-rose-300 font-black">
                                            {{ item.attended_sessions }} de {{ item.course?.total_sessions || 4 }} sesiones ({{ item.attendance_percentage || 0 }}%)
                                        </span>
                                    </div>
                                    <div class="w-full bg-slate-200 dark:bg-slate-800 rounded-full h-2 overflow-hidden">
                                        <div
                                            class="bg-rose-800 h-2 rounded-full transition-all duration-500"
                                            :style="{ width: `${Math.min(100, Math.round(((item.attended_sessions || 0) / (item.course?.total_sessions || 4)) * 100))}%` }"
                                        />
                                    </div>
                                    <div class="flex justify-between items-center text-[11px] text-slate-500">
                                        <span>Meta para certificar: <strong>75%</strong></span>
                                        <span v-if="(item.attendance_percentage || 0) >= 75" class="text-emerald-700 dark:text-emerald-400 font-bold">✓ Cumple requisito</span>
                                        <span v-else class="text-amber-700 dark:text-amber-400 font-bold">Faltan sesiones</span>
                                    </div>
                                </div>

                                <!-- Calificación y Estado de Certificado -->
                                <div class="grid grid-cols-2 gap-2 text-[11px] font-bold">
                                    <div class="p-2 rounded-lg bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800">
                                        <span class="text-slate-500 block text-[10px]">Nota Final:</span>
                                        <span v-if="item.final_grade !== null && item.final_grade !== undefined" class="text-slate-950 dark:text-white font-mono font-black text-sm">
                                            {{ Number(item.final_grade).toFixed(1) }} / 20
                                        </span>
                                        <span v-else class="text-slate-400">Aún sin nota</span>
                                    </div>
                                    <div class="p-2 rounded-lg bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800">
                                        <span class="text-slate-500 block text-[10px]">Certificado:</span>
                                        <span v-if="item.status === 'aprobado' || item.certificate_code" class="text-emerald-700 dark:text-emerald-400 font-bold">
                                            Disponible
                                        </span>
                                        <span v-else class="text-slate-400">Al cerrar acta</span>
                                    </div>
                                </div>
                            </CardContent>

                            <div class="p-3 bg-slate-50 dark:bg-slate-900 border-t border-slate-200 dark:border-slate-800 flex flex-wrap items-center gap-2 rounded-b-xl">
                                <Button size="sm" variant="outline" class="flex-1 min-w-[120px] text-xs font-bold h-9 border-rose-300 text-rose-950 hover:bg-rose-50 cursor-pointer" @click="openCredentialModal(item)">
                                    <QrCode class="size-4 mr-1.5 text-rose-800" />
                                    Mi Credencial QR
                                </Button>
                                <Button as-child variant="outline" size="sm" class="flex-1 min-w-[100px] text-xs font-bold h-9 border-slate-300 text-slate-800 hover:text-rose-900">
                                    <Link :href="`/courses/${item.course_id}`">
                                        <BookOpen class="size-4 mr-1.5 text-slate-700" />
                                        Ver Curso
                                    </Link>
                                </Button>
                                <Button
                                    v-if="item.status === 'aprobado' || item.certificate_code"
                                    as-child
                                    size="sm"
                                    variant="outline"
                                    class="flex-1 min-w-[110px] text-xs font-black h-9 border-amber-500 text-amber-950 bg-amber-50 hover:bg-amber-100 dark:bg-amber-950 dark:text-amber-200"
                                >
                                    <a :href="`/certificates?dni=${item.dni}&code=${item.certificate_code || ''}`" target="_blank">
                                        <Award class="size-4 mr-1.5 text-amber-700" />
                                        Certificado
                                    </a>
                                </Button>
                            </div>
                        </Card>
                    </div>
                </div>

                <!-- Sección: Convocatorias Abiertas -->
                <div id="cursos-abiertos" class="space-y-4 pt-6 border-t border-slate-200 dark:border-slate-800">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-xl font-black tracking-tight text-slate-950 dark:text-white flex items-center gap-2">
                                <Sparkles class="size-5 text-amber-600" />
                                Convocatorias Abiertas para Inscripción
                            </h2>
                            <p class="text-xs font-semibold text-slate-700 dark:text-slate-300">
                                Puedes inscribirte directamente completando tu ficha oficial en línea.
                            </p>
                        </div>
                        <Button as-child variant="outline" size="sm" class="text-xs font-bold border-slate-300 text-slate-800 hover:text-rose-900">
                            <Link href="/courses">Ver Catálogo Completo →</Link>
                        </Button>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                        <Card v-for="course in openCourses" :key="course.id" class="border-2 border-slate-200 dark:border-slate-800 flex flex-col justify-between hover:border-rose-800 shadow-sm hover:shadow-lg transition-all rounded-xl bg-white dark:bg-slate-950 min-w-0 max-w-full overflow-hidden">
                            <CardHeader class="p-4 sm:p-5 pb-3 space-y-1.5 min-w-0 max-w-full">
                                <div class="flex items-center justify-between gap-1.5 min-w-0 w-full text-xs">
                                    <span class="font-mono font-bold text-rose-950 bg-rose-100 dark:bg-rose-950 dark:text-rose-200 px-2.5 py-0.5 rounded border border-rose-200 dark:border-rose-800 shrink-0">
                                        {{ course.code }}
                                    </span>
                                    <Badge class="text-[10px] font-bold bg-amber-100 text-amber-950 border border-amber-300 shrink-0 truncate max-w-[62%]">
                                        Inscripción Abierta
                                    </Badge>
                                </div>
                                <CardTitle class="text-sm sm:text-base font-extrabold text-slate-950 dark:text-white line-clamp-2 leading-snug break-words min-w-0">
                                    {{ course.title }}
                                </CardTitle>
                                <div class="text-xs font-bold text-rose-900 dark:text-rose-400 truncate w-full min-w-0 max-w-full overflow-hidden">
                                    {{ course.institution || 'Entidad Organizadora' }}
                                </div>
                                <CardDescription class="text-xs font-bold text-slate-700 dark:text-slate-300 flex items-center gap-1.5 pt-1 min-w-0">
                                    <Calendar class="size-3.5 text-rose-800 shrink-0" />
                                    <span class="truncate">Inicio: <strong>{{ formatDate(course.start_date, 'compact') }}</strong></span>
                                    <span>•</span>
                                    <Clock class="size-3.5 text-amber-600 shrink-0" />
                                    <span class="truncate">{{ formatHours(course.hours) }}</span>
                                </CardDescription>
                            </CardHeader>

                            <CardContent class="text-xs">
                                <div class="flex items-center justify-between text-xs font-bold text-slate-800 dark:text-slate-200 bg-slate-50 dark:bg-slate-900 p-2.5 rounded-lg border border-slate-200 dark:border-slate-800">
                                    <span>Vacantes disponibles:</span>
                                    <strong class="text-rose-900 dark:text-rose-400 text-sm font-black">
                                        {{ Math.max(0, course.capacity - (course.enrollments_count || 0)) }} cupos
                                    </strong>
                                </div>
                            </CardContent>

                            <div class="p-3 bg-slate-50 dark:bg-slate-900 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between gap-2 rounded-b-xl">
                                <Button as-child variant="outline" size="sm" class="flex-1 text-xs font-bold h-9 border-slate-300 text-slate-800 hover:text-slate-900">
                                    <Link :href="`/courses/${course.id}`">Temario</Link>
                                </Button>
                                <Button size="sm" class="flex-1 bg-rose-900 hover:bg-rose-950 text-white text-xs font-black h-9 px-4 shadow-sm" @click="openEnroll(course)">
                                    <CheckCircle2 class="size-4 mr-1.5" />
                                    Inscribirme
                                </Button>
                            </div>
                        </Card>
                    </div>
                </div>
            </div>

            <!-- MODAL: PROYECCIÓN QR (PARA DOCENTE) -->
            <Dialog :open="isQrModalOpen" @update:open="isQrModalOpen = $event">
                <DialogContent class="w-[96vw] sm:max-w-2xl md:max-w-3xl max-h-[92vh] flex flex-col p-0 rounded-2xl shadow-2xl border-4 border-rose-900 text-center bg-white dark:bg-slate-900 overflow-hidden">
                    <div class="p-6 pb-4 border-b border-slate-200 dark:border-slate-800 shrink-0 pr-12">
                        <DialogHeader class="space-y-2">
                            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-rose-100 text-rose-950 text-xs font-black uppercase tracking-wider mx-auto">
                                <QrCode class="size-4 text-rose-800" />
                                <span>Control de Asistencia Digital</span>
                            </div>
                            <DialogTitle class="text-2xl font-black text-slate-950 dark:text-white">
                                {{ activeQrCourse?.title }}
                            </DialogTitle>
                            <DialogDescription class="text-xs sm:text-sm font-medium text-slate-700 dark:text-slate-300 max-w-lg mx-auto">
                                Proyecta esta pantalla en el aula para que los alumnos escaneen el código desde sus teléfonos celulares.
                            </DialogDescription>
                        </DialogHeader>
                    </div>

                    <!-- QR Visualization Gigante Real y Escaneable -->
                    <div class="flex-1 overflow-y-auto overscroll-contain custom-scrollbar p-6 space-y-5">
                        <div v-if="isQrLoading" class="p-6 bg-white rounded-3xl border-4 border-rose-900 inline-flex min-h-[280px] min-w-[280px] items-center justify-center text-xs font-bold text-slate-500">
                            Generando QR...
                        </div>
                        <div v-else-if="activeQrSvg" class="p-6 bg-white rounded-3xl border-4 border-rose-900 inline-block shadow-xl" v-html="activeQrSvg"></div>
                        <div v-else class="p-6 bg-rose-50 rounded-3xl border-2 border-rose-200 text-xs font-bold text-rose-900">
                            No se pudo generar el QR. Intenta nuevamente.
                        </div>
                        <div class="space-y-2">
                            <div class="inline-block text-sm sm:text-base font-mono font-black tracking-widest text-rose-950 bg-rose-100 px-5 py-2 rounded-full border-2 border-rose-300 shadow-sm">
                                CAPACITACIÓN: {{ activeQrCourse?.code }}
                            </div>
                            <p class="text-xs font-bold text-slate-700 dark:text-slate-300">
                                Escanea el código para abrir la página pública de la capacitación. Para asistencia, utiliza el QR de la sesión desde el módulo académico.
                            </p>
                        </div>

                        <div class="pt-2">
                            <Button @click="isQrModalOpen = false" class="w-full bg-rose-900 hover:bg-rose-950 text-white font-bold h-11 text-sm shadow-md cursor-pointer">
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
        <Dialog :open="isCredentialModalOpen" @update:open="isCredentialModalOpen = $event">
            <DialogContent class="w-[94vw] sm:max-w-md p-0 rounded-3xl border border-rose-200 bg-white dark:bg-slate-950 overflow-hidden shadow-2xl">
                <div class="p-6 bg-gradient-to-br from-rose-950 via-rose-900 to-rose-950 text-white text-center space-y-1">
                    <Badge variant="outline" class="border-amber-400 text-amber-300 text-[10px] font-black uppercase">
                        Credencial Oficial de Asistencia
                    </Badge>
                    <h3 class="text-xl font-black pt-1">
                        {{ activeCredentialEnrollment?.nombres }} {{ activeCredentialEnrollment?.paterno }}
                    </h3>
                    <p class="text-xs text-rose-200 font-mono">
                        DNI: {{ activeCredentialEnrollment?.dni }}
                    </p>
                </div>

                <div class="p-6 flex flex-col items-center justify-center space-y-4 text-center">
                    <div v-if="isCredentialQrLoading" class="p-3 bg-white rounded-2xl border-4 border-rose-950/20 shadow-md min-h-[260px] min-w-[260px] flex items-center justify-center text-xs font-bold text-slate-500">
                        Generando QR...
                    </div>
                    <div v-else-if="activeCredentialQrSvg" class="p-3 bg-white rounded-2xl border-4 border-rose-950/20 shadow-md" v-html="activeCredentialQrSvg"></div>
                    <div v-else class="p-3 bg-rose-50 rounded-2xl border-2 border-rose-200 text-xs font-bold text-rose-900">
                        No se pudo generar el QR de la credencial.
                    </div>

                    <div class="space-y-1">
                        <div class="text-[11px] font-bold text-slate-500 uppercase">Código Único de Matrícula</div>
                        <div class="font-mono text-lg font-black text-rose-950 bg-rose-50 px-4 py-1 rounded-lg border border-rose-200 tracking-wider">
                            {{ activeCredentialEnrollment?.credential_code || ('INS-' + activeCredentialEnrollment?.course_id + '-' + activeCredentialEnrollment?.dni?.slice(-4)) }}
                        </div>
                        <p class="text-[11px] text-slate-500 pt-1">
                            Presenta este código al ingresar a la clase para registrar tu asistencia.
                        </p>
                    </div>

                    <Button variant="outline" size="sm" class="text-xs font-bold" @click="isCredentialModalOpen = false">
                        Cerrar Credencial
                    </Button>
                </div>
            </DialogContent>
        </Dialog>

    </AppLayout>
</template>
