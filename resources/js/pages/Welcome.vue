<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    GraduationCap,
    Users,
    QrCode,
    Award,
    Calendar,
    Clock,
    Search,
    CheckCircle2,
    ArrowRight,
    Sparkles,
    ShieldCheck,
    ExternalLink,
    Building2,
    LogIn,
    UserPlus,
    Plus,
    XCircle,
    AlertCircle,
} from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import EnrollmentModal from '@/components/EnrollmentModal.vue';
import LoginModal from '@/components/auth/LoginModal.vue';
import RegisterModal from '@/components/auth/RegisterModal.vue';
import AppHeader from '@/components/AppHeader.vue';
import GlobalToast from '@/components/GlobalToast.vue';
import PeruGeoBadge from '@/components/PeruGeoBadge.vue';
import { useAuthModal } from '@/composables/useAuthModal';
import { formatDate, formatDateRange, formatHours } from '@/lib/formatters';
import { getCourseStatusBadge } from '@/lib/theme';
import { useBranding } from '@/composables/useBranding';

const { institutionName, systemName, systemSubtitle, primaryColor } = useBranding();

interface CourseItem {
    id: number;
    code: string;
    title: string;
    institution?: string | null;
    description: string | null;
    start_date: string;
    end_date: string;
    hours: number;
    capacity: number;
    status: 'abierto' | 'en_curso' | 'concluido' | 'cancelado' | string;
    enrollments_count: number;
    instructor?: {
        id: number;
        name: string;
        paterno?: string;
        materno?: string;
    };
    instructor_name?: string | null;
}

const props = defineProps<{
    courses: CourseItem[];
    stats: {
        totalCourses: number;
        openCourses: number;
        totalEnrolled: number;
    };
}>();

const page = usePage();
const authUser = computed(() => page.props.auth?.user);

// Search & Filter state
const searchQuery = ref('');
const selectedStatus = ref<string>('all');

// Modals state
const {
    isLoginModalOpen,
    isRegisterModalOpen,
    openLogin,
    openRegister,
    switchToRegister,
    switchToLogin,
} = useAuthModal();

const selectedCourseForEnrollment = ref<CourseItem | null>(null);
const isEnrollmentModalOpen = ref(false);

function openEnrollment(course: CourseItem) {
    selectedCourseForEnrollment.value = course;
    isEnrollmentModalOpen.value = true;
}

// Filtered courses
const filteredCourses = computed(() => {
    return props.courses.filter((course) => {
        const matchesStatus =
            selectedStatus.value === 'all' ||
            course.status === selectedStatus.value;

        const q = searchQuery.value.toLowerCase().trim();
        const matchesQuery =
            !q ||
            course.title.toLowerCase().includes(q) ||
            course.code.toLowerCase().includes(q) ||
            (course.institution &&
                course.institution.toLowerCase().includes(q)) ||
            (course.description &&
                course.description.toLowerCase().includes(q)) ||
            (course.instructor &&
                `${course.instructor.name} ${course.instructor.paterno || ''}`
                    .toLowerCase()
                    .includes(q));

        return matchesStatus && matchesQuery;
    });
});

function statusBadgeInfo(status: string) {
    return getCourseStatusBadge(status);
}
</script>

<template>
    <Head :title="`${systemName} — Capacitaciones y Certificación • ${institutionName}`" />

    <div
        class="min-h-screen max-w-full overflow-x-hidden bg-slate-50/50 text-slate-900 antialiased selection:bg-emerald-600 selection:text-white dark:bg-background dark:text-foreground"
    >
        <!-- TOP NAVBAR UNIFICADA -->
        <AppHeader />

        <!-- HERO SECTION -->
        <section
            class="relative overflow-hidden bg-gradient-to-b from-emerald-50/40 via-transparent to-transparent pt-12 pb-16 md:pt-20 md:pb-24 dark:from-emerald-950/15"
        >
            <div
                class="mx-auto max-w-7xl space-y-6 px-4 text-center sm:px-6 lg:px-8"
            >
                <!-- Pill tags -->
                <div class="flex flex-wrap items-center justify-center gap-2">
                    <div
                        class="inline-flex items-center gap-1.5 rounded-full border border-emerald-300 bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-950 shadow-xs dark:border-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-200"
                    >
                        <Sparkles class="size-3.5 text-emerald-700 dark:text-emerald-400" />
                        <span>Convocatorias 2026 • {{ institutionName }}</span>
                    </div>
                    <PeruGeoBadge />
                </div>

                <!-- Headline Ampliado, Proporcional y de Alto Impacto -->
                <h1
                    class="mx-auto max-w-4xl text-4xl leading-[1.12] font-black tracking-tight text-slate-950 sm:text-5xl md:text-6xl lg:text-7xl dark:text-white"
                >
                    Capacitaciones con
                    <span
                        class="text-emerald-600 underline decoration-[#f0c84f] decoration-4 underline-offset-8 dark:text-emerald-400"
                        >Asistencia QR</span
                    >
                    y Certificación Oficial
                </h1>

                <!-- Subtitle -->
                <p
                    class="mx-auto max-w-3xl text-base leading-relaxed font-semibold text-slate-700 sm:text-lg md:text-xl dark:text-slate-300"
                >
                    Inscripción inmediata con DNI, control de asistencia por
                    código QR y diplomas digitales con validación web
                    institucional.
                </p>

                <!-- CTA Buttons -->
                <div
                    class="flex flex-col items-center justify-center gap-3 pt-3 sm:flex-row"
                >
                    <Button
                        as-child
                        size="lg"
                        class="w-full bg-emerald-600 px-7 py-6 text-sm font-bold text-white shadow-lg shadow-emerald-600/25 transition-transform hover:scale-[1.02] hover:bg-emerald-700 sm:w-auto sm:text-base"
                    >
                        <a href="#cursos">
                            <GraduationCap class="mr-2 size-5 text-[#f0c84f]" />
                            Ver Cursos Disponibles
                        </a>
                    </Button>
                    <Button
                        as-child
                        variant="outline"
                        size="lg"
                        class="w-full border-2 border-[#153542] px-7 py-6 text-sm font-bold text-[#153542] hover:bg-[#153542] hover:text-white dark:border-sky-400 dark:text-sky-200 dark:hover:bg-sky-950 sm:w-auto sm:text-base transition-all"
                    >
                        <Link href="/certificates">
                            <Award class="mr-2 size-5 text-amber-500" />
                            Consultar Certificado por DNI
                        </Link>
                    </Button>
                </div>
            </div>
        </section>

        <!-- CATALOG SECTION ("EN AFUERA") -->
        <section
            id="cursos"
            class="border-t border-slate-200/80 bg-white py-12 md:py-20 dark:border-border dark:bg-background"
        >
            <div class="mx-auto max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">
                <!-- Section Header -->
                <div
                    class="flex flex-col justify-between gap-4 md:flex-row md:items-end"
                >
                    <div class="space-y-1">
                        <div
                            class="text-xs font-extrabold tracking-wider text-emerald-700 uppercase dark:text-emerald-400"
                        >
                            Convocatorias Abiertas
                        </div>
                        <h2
                            class="text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl dark:text-white"
                        >
                            Cursos y Capacitaciones
                        </h2>
                        <p
                            class="max-w-xl text-xs font-medium text-slate-600 sm:text-sm dark:text-slate-400"
                        >
                            Capacitaciones con entidad convocante, aforo oficial
                            e inscripción directa con DNI.
                        </p>
                    </div>

                    <!-- Filter Chips -->
                    <div
                        class="flex flex-wrap items-center gap-1.5 self-start rounded-xl border border-slate-200 bg-slate-100 p-1 dark:border-slate-800 dark:bg-slate-900"
                    >
                        <button
                            type="button"
                            @click="selectedStatus = 'all'"
                            class="rounded-lg px-3 py-1 text-xs font-bold transition-all"
                            :class="
                                selectedStatus === 'all'
                                    ? 'bg-white text-slate-900 shadow-xs dark:bg-slate-800 dark:text-white'
                                    : 'text-slate-600 hover:text-slate-900 dark:text-slate-400'
                            "
                        >
                            Todos ({{ courses.length }})
                        </button>
                        <button
                            type="button"
                            @click="selectedStatus = 'abierto'"
                            class="rounded-lg px-3 py-1 text-xs font-bold transition-all"
                            :class="
                                selectedStatus === 'abierto'
                                    ? 'bg-emerald-600 text-white shadow-xs'
                                    : 'text-slate-600 hover:text-slate-900 dark:text-slate-400'
                            "
                        >
                            Abiertos
                        </button>
                        <button
                            type="button"
                            @click="selectedStatus = 'en_curso'"
                            class="rounded-lg px-3 py-1 text-xs font-bold transition-all"
                            :class="
                                selectedStatus === 'en_curso'
                                    ? 'bg-amber-600 text-white shadow-xs'
                                    : 'text-slate-600 hover:text-slate-900 dark:text-slate-400'
                            "
                        >
                            En Curso
                        </button>
                    </div>
                </div>

                <!-- Search Bar -->
                <div class="relative max-w-md">
                    <Search
                        class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400"
                    />
                    <Input
                        v-model="searchQuery"
                        type="search"
                        placeholder="Buscar por título, entidad organizadora, código o docente..."
                        class="bg-slate-50/80 pl-9 text-xs dark:bg-card"
                    />
                </div>

                <!-- Empty State -->
                <div
                    v-if="filteredCourses.length === 0"
                    class="space-y-3 rounded-2xl border border-dashed border-neutral-300 p-8 py-16 text-center dark:border-neutral-800"
                >
                    <GraduationCap class="mx-auto size-12 text-neutral-400" />
                    <div class="space-y-1">
                        <h3 class="text-base font-semibold">
                            No se encontraron capacitaciones
                        </h3>
                        <p class="mx-auto max-w-sm text-xs text-neutral-500">
                            No hay cursos que coincidan con el término de
                            búsqueda o el filtro seleccionado.
                        </p>
                    </div>
                    <Button
                        variant="outline"
                        size="sm"
                        @click="
                            searchQuery = '';
                            selectedStatus = 'all';
                        "
                        class="text-xs"
                    >
                        Restablecer filtros
                    </Button>
                </div>

                <!-- Course Cards Grid -->
                <div
                    v-else
                    class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3"
                >
                    <Card
                        v-for="course in filteredCourses"
                        :key="course.id"
                        class="flex max-w-full min-w-0 flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white transition-all duration-200 hover:border-emerald-500/80 hover:shadow-md dark:border-border dark:bg-card dark:hover:border-emerald-500/80"
                    >
                        <!-- Card Header -->
                        <CardHeader
                            class="max-w-full min-w-0 space-y-2 p-4 pb-3 sm:p-5"
                        >
                            <div
                                class="flex w-full min-w-0 items-center justify-between gap-1.5"
                            >
                                <span
                                    class="shrink-0 rounded border border-slate-200 bg-slate-100 px-2 py-0.5 font-mono text-[11px] font-bold text-slate-800 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300"
                                >
                                    {{ course.code }}
                                </span>
                                <span
                                    class="max-w-[62%] shrink-0 truncate rounded-full border px-2 py-0.5 text-center text-[10px] font-bold sm:text-[11px]"
                                    :class="
                                        statusBadgeInfo(course.status).class
                                    "
                                >
                                    {{ statusBadgeInfo(course.status).label }}
                                </span>
                            </div>

                            <CardTitle
                                class="line-clamp-2 min-w-0 text-base leading-snug font-bold break-words text-slate-950 sm:text-lg dark:text-white"
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

                            <CardDescription
                                class="line-clamp-2 min-w-0 text-xs font-medium break-words text-slate-600 dark:text-slate-400"
                            >
                                {{
                                    course.description ||
                                    'Capacitación profesional oficial con asistencia controlada por código QR y certificación digital.'
                                }}
                            </CardDescription>
                        </CardHeader>

                        <!-- Card Body Details -->
                        <CardContent
                            class="max-w-full min-w-0 flex-1 space-y-3 overflow-hidden p-4 pt-0 text-xs sm:p-5"
                        >
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
                                        course.instructor
                                            ? `${course.instructor.name} ${course.instructor.paterno || ''}`
                                            : course.instructor_name ||
                                              'Por asignar'
                                    }}
                                </span>
                            </div>

                            <!-- Meta info: dates & hours -->
                            <div
                                class="grid min-w-0 grid-cols-2 gap-2 text-xs"
                            >
                                <div
                                    class="flex min-w-0 items-center gap-1.5 rounded-md border border-slate-200/80 bg-slate-50 px-2 py-1 font-semibold text-slate-800 dark:border-slate-800 dark:bg-slate-900/70 dark:text-slate-200"
                                >
                                    <Calendar
                                        class="size-3.5 shrink-0 text-sky-600 dark:text-sky-400"
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
                                </div>
                                <div
                                    class="flex min-w-0 items-center gap-1.5 rounded-md border border-slate-200/80 bg-slate-50 px-2 py-1 font-semibold text-slate-800 dark:border-slate-800 dark:bg-slate-900/70 dark:text-slate-200"
                                >
                                    <Clock
                                        class="size-3.5 shrink-0 text-amber-600 dark:text-amber-400"
                                    />
                                    <span class="truncate"
                                        ><strong>{{
                                            formatHours(course.hours)
                                        }}</strong></span
                                    >
                                </div>
                            </div>

                            <!-- Vacantes / Capacity Bar con Alerta Viva -->
                            <div class="max-w-full min-w-0 space-y-1.5 pt-1">
                                <div
                                    class="flex min-w-0 items-center justify-between gap-2 text-[11px]"
                                >
                                    <span
                                        class="truncate font-medium text-slate-600 dark:text-slate-400"
                                        >Vacantes Disponibles:</span
                                    >
                                    <span
                                        class="shrink-0 font-bold"
                                        :class="
                                            course.capacity - course.enrollments_count <= 5 && course.capacity - course.enrollments_count > 0
                                                ? 'text-red-600 dark:text-red-400 font-extrabold'
                                                : course.capacity - course.enrollments_count > 5
                                                  ? 'text-emerald-700 dark:text-emerald-400 font-bold'
                                                  : 'text-slate-600 dark:text-slate-400'
                                        "
                                    >
                                        {{
                                            Math.max(
                                                0,
                                                course.capacity -
                                                    course.enrollments_count,
                                            )
                                        }}
                                        de {{ course.capacity }}
                                    </span>
                                </div>
                                <div
                                    class="h-1.5 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"
                                >
                                    <div
                                        class="h-full rounded-full bg-gradient-to-r from-emerald-500 to-teal-500 transition-all"
                                        :style="{
                                            width: `${Math.min(100, (course.enrollments_count / course.capacity) * 100)}%`,
                                        }"
                                    />
                                </div>
                            </div>
                        </CardContent>

                        <!-- Card Footer Action -->
                        <CardFooter
                            class="flex max-w-full min-w-0 items-center justify-between gap-2 border-t border-slate-100 bg-slate-50/50 p-4 pt-3 sm:p-5 dark:border-slate-800 dark:bg-slate-900/50"
                        >
                            <Button
                                as-child
                                variant="outline"
                                size="sm"
                                class="flex-1 border-slate-300 text-xs font-bold text-slate-800 hover:border-[#153542] hover:text-[#153542] dark:border-slate-700 dark:text-slate-200 dark:hover:border-sky-400 dark:hover:text-sky-200"
                            >
                                <Link :href="`/courses/${course.id}`">
                                    Detalles
                                </Link>
                            </Button>

                            <Button
                                v-if="
                                    course.status === 'abierto' &&
                                    course.capacity - course.enrollments_count >
                                        0
                                "
                                size="sm"
                                class="flex-1 bg-emerald-600 text-xs font-bold text-white shadow-md shadow-emerald-600/20 hover:bg-emerald-700 active:scale-95 transition-all"
                                @click="openEnrollment(course)"
                            >
                                <CheckCircle2 class="mr-1.5 size-3.5" />
                                Inscribirme
                            </Button>
                            <div
                                v-else
                                class="flex-1 flex items-center justify-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-black shadow-xs transition-all select-none"
                                :class="
                                    course.status !== 'abierto'
                                        ? 'border border-rose-300 bg-rose-100/90 text-rose-800 dark:border-rose-600 dark:bg-rose-950/80 dark:text-rose-200'
                                        : 'border border-amber-300 bg-amber-100/90 text-amber-900 dark:border-amber-600 dark:bg-amber-950/80 dark:text-amber-200'
                                "
                            >
                                <XCircle
                                    v-if="course.status !== 'abierto'"
                                    class="size-3.5 shrink-0 text-rose-600 dark:text-rose-300"
                                />
                                <AlertCircle
                                    v-else
                                    class="size-3.5 shrink-0 text-amber-600 dark:text-amber-300"
                                />
                                <span>{{
                                    course.status !== 'abierto'
                                        ? 'Cerrado'
                                        : 'Agotado'
                                }}</span>
                            </div>
                        </CardFooter>
                    </Card>
                </div>
            </div>
        </section>

        <!-- SYSTEM FEATURES / BENEFITS -->
        <section
            id="beneficios"
            class="border-t border-slate-200/80 bg-white py-12 md:py-20 dark:border-border dark:bg-background"
        >
            <div class="mx-auto max-w-7xl space-y-12 px-4 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-2xl space-y-2 text-center">
                    <h2
                        class="text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl dark:text-white"
                    >
                        ¿Por qué capacitarte con {{ institutionName }}?
                    </h2>
                    <p
                        class="text-xs font-medium text-slate-600 sm:text-sm dark:text-slate-400"
                    >
                        Tecnología moderna para entidades educativas,
                        instituciones y empresas capacitadoras.
                    </p>
                </div>

                <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                    <div
                        class="space-y-3 rounded-2xl border border-slate-200 bg-slate-50/50 p-6 dark:border-border dark:bg-card"
                    >
                        <div
                            class="flex size-10 items-center justify-center rounded-xl bg-emerald-100 font-bold text-emerald-950 dark:bg-emerald-950 dark:text-emerald-200"
                        >
                            <QrCode class="size-5" />
                        </div>
                        <h3
                            class="text-base font-bold text-slate-900 dark:text-white"
                        >
                            Control de Asistencia con QR
                        </h3>
                        <p
                            class="text-xs leading-relaxed font-medium text-slate-600 dark:text-slate-400"
                        >
                            El docente proyecta un código QR dinámico por sesión
                            de clase. Los alumnos escanean el código en segundos
                            desde sus celulares para marcar asistencia.
                        </p>
                    </div>

                    <div
                        class="space-y-3 rounded-2xl border border-slate-200 bg-slate-50/50 p-6 dark:border-border dark:bg-card"
                    >
                        <div
                            class="flex size-10 items-center justify-center rounded-xl bg-amber-100 font-bold text-amber-800 dark:bg-amber-950 dark:text-amber-300"
                        >
                            <ShieldCheck class="size-5" />
                        </div>
                        <h3
                            class="text-base font-bold text-slate-900 dark:text-white"
                        >
                            Cero Falsificaciones RENIEC
                        </h3>
                        <p
                            class="text-xs leading-relaxed font-medium text-slate-600 dark:text-slate-400"
                        >
                            Integración en tiempo real con RENIEC Perú para
                            validar nombres legítimos y generar certificados
                            protegidos institucionalmente.
                        </p>
                    </div>

                    <div
                        class="space-y-3 rounded-2xl border border-slate-200 bg-slate-50/50 p-6 dark:border-border dark:bg-card"
                    >
                        <div
                            class="flex size-10 items-center justify-center rounded-xl bg-sky-100 font-bold text-sky-950 dark:bg-sky-950 dark:text-sky-200"
                        >
                            <Award class="size-5" />
                        </div>
                        <h3
                            class="text-base font-bold text-slate-900 dark:text-white"
                        >
                            Certificación Digital Inmediata
                        </h3>
                        <p
                            class="text-xs leading-relaxed font-medium text-slate-600 dark:text-slate-400"
                        >
                            Al completar los criterios de asistencia y
                            evaluación, el participante descarga su certificado
                            en formato PDF con código de verificación
                            permanente.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- FOOTER -->
        <footer
            class="border-t border-slate-200/80 bg-slate-100/60 py-8 text-xs text-slate-600 dark:border-border dark:bg-background dark:text-slate-400"
        >
            <div
                class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-4 px-4 sm:flex-row sm:px-6 lg:px-8"
            >
                <div class="flex items-center gap-2 font-medium">
                    <GraduationCap class="size-4 text-emerald-600 dark:text-emerald-400" />
                    <span
                        class="font-bold text-slate-900 dark:text-white"
                    >
                        {{ systemName }}
                    </span>
                    <span
                        >— {{ systemSubtitle }} •
                        {{ institutionName }}</span
                    >
                </div>
                <div class="font-medium">
                    Gestión Institucional y Comunitaria • San Jerónimo, Cusco 2026
                </div>
            </div>
        </footer>

        <!-- REUSABLE ENROLLMENT MODAL (CON RENIEC DNI INTEGRADO) -->
        <EnrollmentModal
            :course="selectedCourseForEnrollment"
            v-model:open="isEnrollmentModalOpen"
            @enrolled="isEnrollmentModalOpen = false"
        />

        <!-- AUTH MODALS: LOGIN & REGISTER -->
        <LoginModal
            v-model:open="isLoginModalOpen"
            @switch-to-register="switchToRegister"
        />

        <RegisterModal
            v-model:open="isRegisterModalOpen"
            @switch-to-login="switchToLogin"
        />

        <GlobalToast />
    </div>
</template>
