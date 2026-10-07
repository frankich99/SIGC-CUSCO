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
import PeruGeoBadge from '@/components/PeruGeoBadge.vue';
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
    UserCheck,
    Search,
    AlertCircle,
    Download,
    ExternalLink,
    Building2,
    Layers,
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
    capacity: number;
    status: string;
    enrollments_count?: number;
    instructor?: InstructorSnippet;
}

interface EnrollmentItem {
    id: number;
    course_id: number;
    dni: string;
    nombres: string;
    paterno: string;
    materno?: string;
    email: string;
    phone?: string;
    status: 'inscrito' | 'en_curso' | 'aprobado' | 'desaprobado' | 'cancelado' | string;
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
    return user.value?.role === 'participante' || user.value?.role === 'admin';
});

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Panel Principal',
        href: '/dashboard',
    },
];

// Active view toggle when admin has both views
const activeDashboardTab = ref<'organizador' | 'participante'>(
    user.value?.role === 'participante' ? 'participante' : 'organizador'
);

// Modals
const selectedCourseToEnroll = ref<CourseItem | null>(null);
const isEnrollModalOpen = ref(false);

const isQrModalOpen = ref(false);
const activeQrCourse = ref<CourseItem | null>(null);
const isScanQrModalOpen = ref(false);
const scanCodeInput = ref('');
const scanSuccessMessage = ref<string | null>(null);

function openEnroll(course: CourseItem) {
    selectedCourseToEnroll.value = course;
    isEnrollModalOpen.value = true;
}

function openQrProjection(course: CourseItem) {
    activeQrCourse.value = course;
    isQrModalOpen.value = true;
}

function openScanModal(enrollment: EnrollmentItem) {
    activeQrCourse.value = enrollment.course;
    scanCodeInput.value = '';
    scanSuccessMessage.value = null;
    isScanQrModalOpen.value = true;
}

function simulateScan() {
    if (!scanCodeInput.value) return;
    scanSuccessMessage.value = `¡Asistencia registrada exitosamente para la sesión de hoy! (Código: ${scanCodeInput.value.toUpperCase()})`;
    setTimeout(() => {
        scanCodeInput.value = '';
    }, 4000);
}

function roleBadgeData(role?: string) {
    switch (role) {
        case 'admin':
            return {
                label: 'Administrador General',
                desc: 'Control integral de capacitaciones, docentes, vacantes y actas académicas.',
                badgeClass: 'bg-indigo-600 text-white font-extrabold shadow-xs',
            };
        case 'docente':
            return {
                label: 'Docente / Instructor',
                desc: 'Control de sesiones, proyección de QR de asistencia en vivo y registro de evaluaciones.',
                badgeClass: 'bg-emerald-600 text-white font-extrabold shadow-xs',
            };
        default:
            return {
                label: 'Participante / Alumno',
                desc: 'Inscripción a programas, registro de asistencia con QR y descarga de certificados.',
                badgeClass: 'bg-blue-600 text-white font-extrabold shadow-xs',
            };
    }
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Panel de Control - SIGC-CUSCO" />

        <div class="space-y-8 px-4 py-6 md:px-8 max-w-7xl mx-auto">
            <!-- BANNER PRINCIPAL CON COLORES VIVOS Y ALTO CONTRASTE -->
            <div class="relative overflow-hidden rounded-2xl border-2 border-emerald-600/30 bg-gradient-to-r from-emerald-800 via-teal-900 to-slate-950 p-6 sm:p-8 text-white shadow-xl">
                <!-- Background decorative shapes -->
                <div class="absolute -right-10 -bottom-10 size-64 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>

                <div class="relative z-10 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                    <div class="space-y-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-[11px] uppercase tracking-wider font-extrabold bg-white/20 text-white px-3 py-1 rounded-full backdrop-blur-sm border border-white/20">
                                SIGC-CUSCO • Sistema Integrado
                            </span>
                            <span class="text-[11px] uppercase tracking-wider px-3 py-1 rounded-full" :class="roleBadgeData(user?.role).badgeClass">
                                {{ roleBadgeData(user?.role).label }}
                            </span>
                            <PeruGeoBadge />
                        </div>

                        <h1 class="text-2xl sm:text-4xl font-black tracking-tight text-white drop-shadow-xs">
                            ¡Bienvenido, {{ user?.name }}!
                        </h1>

                        <p class="text-xs sm:text-sm text-emerald-100 font-medium max-w-2xl leading-relaxed">
                            {{ roleBadgeData(user?.role).desc }}
                        </p>
                    </div>

                    <!-- Botones de Acción Primarios Claros -->
                    <div class="flex flex-wrap items-center gap-3">
                        <Button v-if="user?.role === 'admin'" as-child size="default" class="bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs shadow-md">
                            <Link href="/courses/create">
                                <Plus class="mr-1.5 size-4" />
                                Nueva Capacitación
                            </Link>
                        </Button>
                        <Button as-child size="default" variant="secondary" class="bg-white hover:bg-emerald-50 text-slate-900 font-bold text-xs shadow-md">
                            <Link href="/courses">
                                <GraduationCap class="mr-1.5 size-4 text-emerald-700" />
                                Catálogo Completo
                            </Link>
                        </Button>
                    </div>
                </div>

                <!-- Selector de Vista para Administradores -->
                <div v-if="user?.role === 'admin'" class="relative z-10 mt-6 pt-4 border-t border-white/20 flex flex-wrap items-center gap-2">
                    <span class="text-xs font-bold text-emerald-200 mr-2">Modo de visualización:</span>
                    <button
                        type="button"
                        @click="activeDashboardTab = 'organizador'"
                        class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer"
                        :class="activeDashboardTab === 'organizador' ? 'bg-white text-emerald-950 shadow-md ring-2 ring-emerald-400' : 'bg-white/10 text-white hover:bg-white/20'"
                    >
                        Vista Organizador / Docente
                    </button>
                    <button
                        type="button"
                        @click="activeDashboardTab = 'participante'"
                        class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer"
                        :class="activeDashboardTab === 'participante' ? 'bg-white text-emerald-950 shadow-md ring-2 ring-emerald-400' : 'bg-white/10 text-white hover:bg-white/20'"
                    >
                        Vista Participante / Alumno
                    </button>
                </div>
            </div>

            <!-- ============================================================ -->
            <!-- 1. VISTA: ORGANIZADOR / DOCENTE / INSTRUCTOR                 -->
            <!-- ============================================================ -->
            <div v-if="isOrganizerOrTeacher && (user?.role !== 'admin' || activeDashboardTab === 'organizador')" class="space-y-8">
                <!-- Grilla de Métricas Compacta y Responsiva -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-2.5 sm:gap-4">
                    <!-- Métrica 1: Cursos Asignados -->
                    <Card class="border border-emerald-300 dark:border-emerald-800 bg-emerald-50/50 dark:bg-emerald-950/20 shadow-xs hover:shadow-md transition-all">
                        <CardHeader class="p-3 sm:p-4 pb-1 sm:pb-2">
                            <CardDescription class="text-[11px] sm:text-xs font-bold text-emerald-900 dark:text-emerald-300 flex items-center justify-between">
                                <span class="truncate">Mis Cursos</span>
                                <div class="size-7 sm:size-8 rounded-lg bg-emerald-600 text-white flex items-center justify-center shadow-xs shrink-0">
                                    <BookOpen class="size-3.5 sm:size-4" />
                                </div>
                            </CardDescription>
                            <CardTitle class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white pt-1">
                                {{ metrics.taughtCount }}
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="p-3 sm:p-4 pt-0 text-[11px] sm:text-xs font-medium text-slate-600 dark:text-slate-400">
                            Cursos activos
                        </CardContent>
                    </Card>

                    <!-- Métrica 2: Inscripciones Abiertas -->
                    <Card class="border border-teal-300 dark:border-teal-800 bg-teal-50/50 dark:bg-teal-950/20 shadow-xs hover:shadow-md transition-all">
                        <CardHeader class="p-3 sm:p-4 pb-1 sm:pb-2">
                            <CardDescription class="text-[11px] sm:text-xs font-bold text-teal-900 dark:text-teal-300 flex items-center justify-between">
                                <span class="truncate">Convocatorias</span>
                                <div class="size-7 sm:size-8 rounded-lg bg-teal-600 text-white flex items-center justify-center shadow-xs shrink-0">
                                    <GraduationCap class="size-3.5 sm:size-4" />
                                </div>
                            </CardDescription>
                            <CardTitle class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white pt-1">
                                {{ metrics.openCourses }}
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="p-3 sm:p-4 pt-0 text-[11px] sm:text-xs font-medium text-slate-600 dark:text-slate-400">
                            Con vacantes
                        </CardContent>
                    </Card>

                    <!-- Métrica 3: Total Registrados -->
                    <Card class="border border-indigo-300 dark:border-indigo-800 bg-indigo-50/50 dark:bg-indigo-950/20 shadow-xs hover:shadow-md transition-all">
                        <CardHeader class="p-3 sm:p-4 pb-1 sm:pb-2">
                            <CardDescription class="text-[11px] sm:text-xs font-bold text-indigo-900 dark:text-indigo-300 flex items-center justify-between">
                                <span class="truncate">Total Cursos</span>
                                <div class="size-7 sm:size-8 rounded-lg bg-indigo-600 text-white flex items-center justify-center shadow-xs shrink-0">
                                    <Award class="size-3.5 sm:size-4" />
                                </div>
                            </CardDescription>
                            <CardTitle class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white pt-1">
                                {{ metrics.totalCourses }}
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="p-3 sm:p-4 pt-0 text-[11px] sm:text-xs font-medium text-slate-600 dark:text-slate-400">
                            En catálogo
                        </CardContent>
                    </Card>

                    <!-- Métrica 4: Asistencia QR Dinámica -->
                    <Card class="border border-amber-300 dark:border-amber-800 bg-amber-50/60 dark:bg-amber-950/20 shadow-xs hover:shadow-md transition-all">
                        <CardHeader class="p-3 sm:p-4 pb-1 sm:pb-2">
                            <CardDescription class="text-[11px] sm:text-xs font-bold text-amber-900 dark:text-amber-300 flex items-center justify-between">
                                <span class="truncate">Asistencia QR</span>
                                <div class="size-7 sm:size-8 rounded-lg bg-amber-600 text-white flex items-center justify-center shadow-xs shrink-0">
                                    <QrCode class="size-3.5 sm:size-4" />
                                </div>
                            </CardDescription>
                            <CardTitle class="text-sm sm:text-base font-extrabold text-amber-950 dark:text-amber-200 pt-1">
                                QR en Vivo
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="p-3 sm:p-4 pt-0 text-[11px] sm:text-xs font-medium text-slate-600 dark:text-slate-400">
                            Proyección en aula
                        </CardContent>
                    </Card>
                </div>

                <!-- Sección: Mis Cursos Asignados -->
                <div class="space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
                        <div>
                            <h2 class="text-xl font-black tracking-tight text-slate-900 dark:text-white flex items-center gap-2">
                                <BookOpen class="size-5 text-emerald-600" />
                                Mis Capacitaciones Asignadas
                            </h2>
                            <p class="text-xs font-semibold text-slate-700 dark:text-slate-300">
                                Cursos donde figuras como docente responsable o administrador.
                            </p>
                        </div>
                        <Button as-child variant="outline" size="sm" class="text-xs font-bold border-slate-300 text-slate-800 hover:text-emerald-700">
                            <Link href="/courses">Ver Catálogo General →</Link>
                        </Button>
                    </div>

                    <!-- Estado Vacío -->
                    <div v-if="taughtCourses.length === 0" class="p-10 text-center border-2 border-dashed border-slate-300 rounded-2xl space-y-3 bg-white dark:bg-slate-900">
                        <div class="size-16 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center mx-auto">
                            <BookOpen class="size-8" />
                        </div>
                        <h3 class="text-base font-bold text-slate-900 dark:text-white">No tienes cursos asignados actualmente</h3>
                        <p class="text-xs font-medium text-slate-600 max-w-md mx-auto">
                            Cuando seas asignado a un curso o registres uno nuevo como administrador, aparecerá en este panel.
                        </p>
                        <Button v-if="user?.role === 'admin'" as-child size="sm" class="mt-2 text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white">
                            <Link href="/courses/create">Crear Mi Primera Capacitación</Link>
                        </Button>
                    </div>

                    <!-- Lista de Cursos Asignados -->
                    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                        <Card v-for="course in taughtCourses" :key="course.id" class="border-2 border-slate-200 dark:border-slate-800 hover:border-emerald-500 shadow-sm hover:shadow-lg transition-all flex flex-col justify-between rounded-xl bg-white dark:bg-slate-950">
                            <CardHeader class="pb-3 space-y-2">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="font-mono font-bold text-slate-800 dark:text-slate-200 bg-slate-100 dark:bg-slate-800 px-2.5 py-1 rounded border border-slate-300 dark:border-slate-700">
                                        {{ course.code }}
                                    </span>
                                    <Badge class="text-[11px] font-extrabold uppercase px-2.5 py-0.5" :class="course.status === 'abierto' ? 'bg-emerald-100 text-emerald-900 border border-emerald-300' : 'bg-slate-100 text-slate-800'">
                                        {{ course.status }}
                                    </Badge>
                                </div>
                                <CardTitle class="text-base font-black text-slate-900 dark:text-white line-clamp-2 leading-snug">
                                    {{ course.title }}
                                </CardTitle>
                                <div v-if="course.institution" class="text-xs font-bold text-blue-700 dark:text-blue-400">
                                    {{ course.institution }}
                                </div>
                                <CardDescription class="text-xs font-semibold text-slate-700 dark:text-slate-300 flex items-center gap-1.5 pt-1">
                                    <Calendar class="size-3.5 text-emerald-600 shrink-0" />
                                    <span>Inicio: <strong>{{ formatDate(course.start_date, 'compact') }}</strong></span>
                                    <span>•</span>
                                    <Clock class="size-3.5 text-blue-600 shrink-0" />
                                    <span>{{ formatHours(course.hours) }}</span>
                                </CardDescription>
                            </CardHeader>

                            <CardContent class="text-xs space-y-2.5">
                                <div class="flex items-center justify-between text-xs font-bold">
                                    <span class="text-slate-700 dark:text-slate-300">Inscritos / Aforo:</span>
                                    <span class="text-emerald-700 dark:text-emerald-400 font-extrabold">
                                        {{ course.enrollments_count || 0 }} de {{ course.capacity }} vacantes
                                    </span>
                                </div>
                                <div class="w-full bg-slate-100 dark:bg-slate-800 h-2.5 rounded-full overflow-hidden border border-slate-200 dark:border-slate-700">
                                    <div
                                        class="h-full bg-emerald-600 rounded-full transition-all"
                                        :style="{ width: `${Math.min(100, ((course.enrollments_count || 0) / course.capacity) * 100)}%` }"
                                    />
                                </div>
                            </CardContent>

                            <div class="p-3 bg-slate-50 dark:bg-slate-900 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between gap-2 rounded-b-xl">
                                <Button size="sm" variant="outline" class="text-xs font-bold border-emerald-600 text-emerald-800 hover:bg-emerald-50 h-9 flex-1 sm:flex-none" @click="openQrProjection(course)">
                                    <QrCode class="size-4 mr-1.5 text-emerald-600" />
                                    Proyectar QR
                                </Button>
                                <Button as-child size="sm" class="text-xs font-bold bg-slate-900 hover:bg-slate-800 text-white h-9 flex-1 sm:flex-none">
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
                <!-- Métricas del Participante (3 columnas compactas y responsivas) -->
                <div class="grid grid-cols-3 gap-2 sm:gap-4">
                    <Card class="border border-blue-300 dark:border-blue-800 bg-blue-50/50 dark:bg-blue-950/20 shadow-xs">
                        <CardHeader class="p-3 sm:p-4 pb-1 sm:pb-2">
                            <CardDescription class="text-[11px] sm:text-xs font-bold text-blue-900 dark:text-blue-300 flex items-center justify-between">
                                <span class="truncate">Mis Cursos</span>
                                <div class="size-7 sm:size-8 rounded-lg bg-blue-600 text-white flex items-center justify-center shadow-xs shrink-0">
                                    <GraduationCap class="size-3.5 sm:size-4" />
                                </div>
                            </CardDescription>
                            <CardTitle class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white pt-1">
                                {{ metrics.enrolledCount }}
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="p-3 sm:p-4 pt-0 text-[11px] sm:text-xs font-medium text-slate-600 dark:text-slate-400 truncate">
                            Inscritos
                        </CardContent>
                    </Card>

                    <Card class="border border-emerald-300 dark:border-emerald-800 bg-emerald-50/50 dark:bg-emerald-950/20 shadow-xs">
                        <CardHeader class="p-3 sm:p-4 pb-1 sm:pb-2">
                            <CardDescription class="text-[11px] sm:text-xs font-bold text-emerald-900 dark:text-emerald-300 flex items-center justify-between">
                                <span class="truncate">Asistencias</span>
                                <div class="size-7 sm:size-8 rounded-lg bg-emerald-600 text-white flex items-center justify-center shadow-xs shrink-0">
                                    <QrCode class="size-3.5 sm:size-4" />
                                </div>
                            </CardDescription>
                            <CardTitle class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white pt-1">
                                {{ studentEnrollments.reduce((acc, curr) => acc + (curr.attended_sessions || 0), 0) }}
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="p-3 sm:p-4 pt-0 text-[11px] sm:text-xs font-medium text-slate-600 dark:text-slate-400 truncate">
                            Sesiones QR
                        </CardContent>
                    </Card>

                    <Card class="border border-indigo-300 dark:border-indigo-800 bg-indigo-50/50 dark:bg-indigo-950/20 shadow-xs">
                        <CardHeader class="p-3 sm:p-4 pb-1 sm:pb-2">
                            <CardDescription class="text-[11px] sm:text-xs font-bold text-indigo-900 dark:text-indigo-300 flex items-center justify-between">
                                <span class="truncate">Certificados</span>
                                <div class="size-7 sm:size-8 rounded-lg bg-indigo-600 text-white flex items-center justify-center shadow-xs shrink-0">
                                    <Award class="size-3.5 sm:size-4" />
                                </div>
                            </CardDescription>
                            <CardTitle class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white pt-1">
                                {{ studentEnrollments.filter(e => e.status === 'aprobado' || e.certificate_code).length }}
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="p-3 sm:p-4 pt-0 text-[11px] sm:text-xs font-medium text-slate-600 dark:text-slate-400 truncate">
                            Aprobados
                        </CardContent>
                    </Card>
                </div>

                <!-- Sección: Mis Capacitaciones Inscritas -->
                <div class="space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
                        <div>
                            <h2 class="text-xl font-black tracking-tight text-slate-900 dark:text-white flex items-center gap-2">
                                <GraduationCap class="size-5 text-emerald-600" />
                                Mis Capacitaciones Inscritas
                            </h2>
                            <p class="text-xs font-semibold text-slate-700 dark:text-slate-300">
                                Cursos en los que estás formalmente matriculado.
                            </p>
                        </div>
                    </div>

                    <!-- Estado Vacío -->
                    <div v-if="studentEnrollments.length === 0" class="p-10 text-center rounded-2xl border-2 border-dashed border-slate-300 space-y-3 bg-white dark:bg-slate-900">
                        <div class="size-16 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center mx-auto">
                            <GraduationCap class="size-8" />
                        </div>
                        <h3 class="text-base font-bold text-slate-900 dark:text-white">Aún no estás matriculado en ninguna capacitación</h3>
                        <p class="text-xs font-medium text-slate-600 max-w-sm mx-auto">
                            Explora los cursos abiertos disponibles en la parte inferior e inscríbete con un solo clic.
                        </p>
                        <Button as-child size="default" class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold">
                            <a href="#cursos-abiertos">Ver Cursos Abiertos</a>
                        </Button>
                    </div>

                    <!-- Tarjetas de Cursos Matriculados -->
                    <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <Card v-for="item in studentEnrollments" :key="item.id" class="border-2 border-slate-200 dark:border-slate-800 hover:border-blue-500 shadow-sm hover:shadow-lg transition-all overflow-hidden flex flex-col justify-between rounded-xl bg-white dark:bg-slate-950">
                            <CardHeader class="pb-3 space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="font-mono text-xs font-bold text-slate-800 dark:text-slate-200 bg-slate-100 dark:bg-slate-800 px-2.5 py-1 rounded border border-slate-300 dark:border-slate-700">
                                        {{ item.course?.code }}
                                    </span>
                                    <Badge class="capitalize text-xs font-extrabold px-2.5 py-0.5" :class="item.status === 'aprobado' ? 'bg-emerald-100 text-emerald-900 border border-emerald-300' : 'bg-blue-100 text-blue-900 border border-blue-300'">
                                        {{ item.status }}
                                    </Badge>
                                </div>
                                <CardTitle class="text-base font-black text-slate-900 dark:text-white line-clamp-2 leading-snug">
                                    {{ item.course?.title }}
                                </CardTitle>
                                <CardDescription class="text-xs font-semibold text-slate-700 dark:text-slate-300">
                                    Docente: {{ item.course?.instructor ? `${item.course.instructor.name} ${item.course.instructor.paterno || ''}` : 'Por asignar' }}
                                </CardDescription>
                            </CardHeader>

                            <CardContent class="text-xs space-y-3">
                                <div class="grid grid-cols-2 gap-2 text-slate-700 dark:text-slate-300 font-semibold bg-slate-50 dark:bg-slate-900 p-2.5 rounded-lg border border-slate-200 dark:border-slate-800">
                                    <div class="flex items-center gap-1.5">
                                        <Calendar class="size-3.5 text-emerald-600 shrink-0" />
                                        <span>Inicio: <strong>{{ formatDate(item.course?.start_date, 'compact') }}</strong></span>
                                    </div>
                                    <div class="flex items-center gap-1.5">
                                        <Clock class="size-3.5 text-blue-600 shrink-0" />
                                        <span>{{ formatHours(item.course?.hours) }}</span>
                                    </div>
                                </div>
                                <div class="p-3 rounded-lg bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-xs flex items-center justify-between font-bold">
                                    <span class="text-emerald-950 dark:text-emerald-200">Asistencias acumuladas:</span>
                                    <span class="text-emerald-700 dark:text-emerald-300 text-sm font-black">{{ item.attended_sessions }} sesiones</span>
                                </div>
                            </CardContent>

                            <div class="p-3 bg-slate-50 dark:bg-slate-900 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between gap-2 rounded-b-xl">
                                <Button size="sm" class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold h-9 shadow-xs" @click="openScanModal(item)">
                                    <Camera class="size-4 mr-1.5" />
                                    Marcar Asistencia QR
                                </Button>
                                <Button v-if="item.status === 'aprobado' || item.certificate_code" size="sm" variant="outline" class="text-xs font-bold h-9 border-indigo-300 text-indigo-800">
                                    <Download class="size-4 mr-1.5 text-indigo-600" />
                                    Certificado PDF
                                </Button>
                                <Button v-else as-child variant="outline" size="sm" class="text-xs font-bold h-9 border-slate-300 text-slate-800">
                                    <Link :href="`/courses/${item.course_id}`">
                                        Detalles →
                                    </Link>
                                </Button>
                            </div>
                        </Card>
                    </div>
                </div>

                <!-- Sección: Descubre Nuevas Capacitaciones Abiertas -->
                <div id="cursos-abiertos" class="space-y-4 pt-6 border-t border-slate-200 dark:border-slate-800">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-xl font-black tracking-tight text-slate-900 dark:text-white flex items-center gap-2">
                                <Sparkles class="size-5 text-amber-500" />
                                Convocatorias Abiertas para Inscripción
                            </h2>
                            <p class="text-xs font-semibold text-slate-700 dark:text-slate-300">
                                Puedes inscribirte directamente completando tu ficha oficial en línea.
                            </p>
                        </div>
                        <Button as-child variant="outline" size="sm" class="text-xs font-bold border-slate-300 text-slate-800 hover:text-emerald-700">
                            <Link href="/courses">Ver Catálogo Completo →</Link>
                        </Button>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                        <Card v-for="course in openCourses" :key="course.id" class="border-2 border-slate-200 dark:border-slate-800 flex flex-col justify-between hover:border-emerald-500 shadow-sm hover:shadow-lg transition-all rounded-xl bg-white dark:bg-slate-950">
                            <CardHeader class="pb-3 space-y-1.5">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="font-mono font-bold text-slate-800 dark:text-slate-200 bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded border border-slate-300 dark:border-slate-700">
                                        {{ course.code }}
                                    </span>
                                    <Badge class="text-[10px] font-bold bg-emerald-100 text-emerald-900 border border-emerald-300">
                                        Inscripción Abierta
                                    </Badge>
                                </div>
                                <CardTitle class="text-sm sm:text-base font-extrabold text-slate-900 dark:text-white line-clamp-2 leading-snug">
                                    {{ course.title }}
                                </CardTitle>
                                <div class="text-xs font-bold text-emerald-800 dark:text-emerald-400 truncate">
                                    {{ course.institution || 'Universidad Nacional de San Antonio Abad del Cusco' }}
                                </div>
                                <CardDescription class="text-xs font-semibold text-slate-700 dark:text-slate-300 flex items-center gap-1.5 pt-1">
                                    <Calendar class="size-3.5 text-emerald-600 shrink-0" />
                                    <span>Inicio: <strong>{{ formatDate(course.start_date, 'compact') }}</strong></span>
                                    <span>•</span>
                                    <Clock class="size-3.5 text-blue-600 shrink-0" />
                                    <span>{{ formatHours(course.hours) }}</span>
                                </CardDescription>
                            </CardHeader>

                            <CardContent class="text-xs">
                                <div class="flex items-center justify-between text-xs font-bold text-slate-700 dark:text-slate-300 bg-slate-50 dark:bg-slate-900 p-2.5 rounded-lg border border-slate-200 dark:border-slate-800">
                                    <span>Vacantes disponibles:</span>
                                    <strong class="text-emerald-700 dark:text-emerald-400 text-sm font-black">
                                        {{ Math.max(0, course.capacity - (course.enrollments_count || 0)) }} cupos
                                    </strong>
                                </div>
                            </CardContent>

                            <div class="p-3 bg-slate-50 dark:bg-slate-900 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between gap-2 rounded-b-xl">
                                <Button as-child variant="ghost" size="sm" class="text-xs font-bold h-9 text-slate-700 hover:text-slate-900">
                                    <Link :href="`/courses/${course.id}`">Ver Temario</Link>
                                </Button>
                                <Button size="sm" class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-extrabold h-9 px-4 shadow-sm" @click="openEnroll(course)">
                                    <CheckCircle2 class="size-4 mr-1.5" />
                                    Inscribirme
                                </Button>
                            </div>
                        </Card>
                    </div>
                </div>
            </div>

            <!-- MODAL: PROYECCIÓN QR (PARA DOCENTE) - DIMENSIONES AMPLIAS -->
            <Dialog :open="isQrModalOpen" @update:open="isQrModalOpen = $event">
                <DialogContent class="w-full sm:max-w-2xl md:max-w-3xl p-6 sm:p-8 rounded-2xl shadow-2xl border-2 border-emerald-500/40 text-center bg-white dark:bg-slate-900">
                    <DialogHeader class="space-y-2 border-b border-slate-200 dark:border-slate-800 pb-4">
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-100 text-emerald-900 text-xs font-bold uppercase tracking-wider mx-auto">
                            <QrCode class="size-4 text-emerald-700" />
                            <span>Control de Asistencia Digital en Vivo</span>
                        </div>
                        <DialogTitle class="text-2xl font-black text-slate-900 dark:text-white">
                            {{ activeQrCourse?.title }}
                        </DialogTitle>
                        <DialogDescription class="text-xs sm:text-sm font-medium text-slate-700 dark:text-slate-300 max-w-lg mx-auto">
                            Proyecta esta pantalla en el aula para que los alumnos escaneen el código desde sus teléfonos celulares.
                        </DialogDescription>
                    </DialogHeader>

                    <!-- QR Visualization Gigante y Claro -->
                    <div class="py-6 space-y-5">
                        <div class="p-8 bg-white rounded-3xl border-4 border-emerald-500 inline-block shadow-xl">
                            <QrCode class="size-60 sm:size-72 text-slate-950 mx-auto" />
                        </div>
                        <div class="space-y-2">
                            <div class="inline-block text-sm sm:text-base font-mono font-extrabold tracking-widest text-emerald-900 bg-emerald-100 px-5 py-2 rounded-full border-2 border-emerald-300 shadow-sm">
                                CÓDIGO DE SESIÓN: {{ activeQrCourse?.code }}-{{ new Date().getDate() }}
                            </div>
                            <p class="text-xs font-bold text-slate-600 dark:text-slate-400">
                                ⏳ Código de asistencia dinámico activo únicamente durante el horario de la clase de hoy.
                            </p>
                        </div>
                    </div>

                    <Button @click="isQrModalOpen = false" class="w-full bg-slate-900 hover:bg-slate-800 text-white font-bold h-11 text-sm shadow-md">
                        Finalizar y Cerrar Proyección
                    </Button>
                </DialogContent>
            </Dialog>

            <!-- MODAL: MARCAR ASISTENCIA (PARA ALUMNO) - DIMENSIONES AMPLIAS -->
            <Dialog :open="isScanQrModalOpen" @update:open="isScanQrModalOpen = $event">
                <DialogContent class="w-full sm:max-w-xl p-6 sm:p-8 rounded-2xl shadow-2xl border border-slate-300 text-left bg-white dark:bg-slate-900">
                    <DialogHeader class="space-y-1 border-b border-slate-200 dark:border-slate-800 pb-4">
                        <DialogTitle class="text-xl font-black text-slate-900 dark:text-white flex items-center gap-2">
                            <Camera class="size-6 text-emerald-600" />
                            <span>Marcar Mi Asistencia Oficial</span>
                        </DialogTitle>
                        <DialogDescription class="text-xs sm:text-sm font-semibold text-slate-700 dark:text-slate-300">
                            Curso: <strong class="text-emerald-800 dark:text-emerald-400">{{ activeQrCourse?.title }}</strong>
                        </DialogDescription>
                    </DialogHeader>

                    <div v-if="scanSuccessMessage" class="py-8 text-center space-y-4">
                        <div class="size-16 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center mx-auto">
                            <CheckCircle2 class="size-10" />
                        </div>
                        <p class="text-base font-extrabold text-emerald-900 dark:text-emerald-300">
                            {{ scanSuccessMessage }}
                        </p>
                        <Button @click="isScanQrModalOpen = false" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold h-11 text-sm shadow-md">
                            Aceptar y Continuar
                        </Button>
                    </div>

                    <div v-else class="space-y-5 py-4">
                        <div class="p-4 bg-slate-50 dark:bg-slate-800/80 rounded-xl text-left space-y-2 border border-slate-200 dark:border-slate-700">
                            <div class="flex items-center gap-2 text-xs font-bold text-slate-900 dark:text-white">
                                <QrCode class="size-5 text-emerald-600" />
                                <span>Instrucciones para validar asistencia</span>
                            </div>
                            <p class="text-xs font-medium text-slate-600 dark:text-slate-300 leading-relaxed">
                                Escanea con la cámara de tu smartphone el código QR que proyecta tu docente en clase, o copia el código numérico de sesión.
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="qr-code-text" class="text-xs font-bold text-slate-900 dark:text-white">
                                Ingrese Código de Sesión del Docente
                            </Label>
                            <div class="flex gap-2">
                                <Input
                                    id="qr-code-text"
                                    v-model="scanCodeInput"
                                    type="text"
                                    placeholder="Ej: UNS-AI-07"
                                    class="text-sm font-mono uppercase font-bold tracking-wider border-slate-300"
                                />
                                <Button size="default" class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold px-5 shrink-0 shadow-xs" @click="simulateScan">
                                    Validar Asistencia
                                </Button>
                            </div>
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
    </AppLayout>
</template>
