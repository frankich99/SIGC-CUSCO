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
const canCreateCourse = computed(
    () =>
        authUser.value?.role === 'admin' || authUser.value?.role === 'docente',
);

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
    switch (status) {
        case 'abierto':
            return {
                label: 'Inscripciones Abiertas',
                class: 'bg-rose-100 text-rose-950 border border-rose-300 dark:bg-rose-950/60 dark:text-rose-200 dark:border-rose-800 font-bold',
            };
        case 'en_curso':
            return {
                label: 'En Curso',
                class: 'bg-sky-100 text-sky-800 border-sky-200 dark:bg-sky-950/60 dark:text-sky-300 dark:border-sky-800',
            };
        case 'concluido':
            return {
                label: 'Concluido',
                class: 'bg-neutral-100 text-neutral-700 border-neutral-200 dark:bg-neutral-800 dark:text-neutral-300 dark:border-neutral-700',
            };
        default:
            return {
                label: 'Cancelado',
                class: 'bg-rose-100 text-rose-800 border-rose-200 dark:bg-rose-950/60 dark:text-rose-300 dark:border-rose-800',
            };
    }
}
</script>

<template>
    <Head title="SIGC-CUSCO — Catálogo de Capacitaciones y Certificación" />

    <div
        class="min-h-screen max-w-full overflow-x-hidden bg-slate-50/50 text-slate-900 antialiased selection:bg-rose-900 selection:text-white dark:bg-neutral-950 dark:text-neutral-100"
    >
        <!-- TOP NAVBAR UNIFICADA -->
        <AppHeader />

        <!-- HERO SECTION -->
        <section
            class="relative overflow-hidden bg-gradient-to-b from-rose-50/70 via-transparent to-transparent pt-12 pb-16 md:pt-20 md:pb-24 dark:from-rose-950/20"
        >
            <div
                class="mx-auto max-w-7xl space-y-6 px-4 text-center sm:px-6 lg:px-8"
            >
                <!-- Pill tags -->
                <div class="flex flex-wrap items-center justify-center gap-2">
                    <div
                        class="inline-flex items-center gap-1.5 rounded-full border border-rose-300 bg-rose-100/90 px-3 py-1 text-xs font-bold text-rose-950 shadow-xs dark:border-rose-800 dark:bg-rose-950/60 dark:text-rose-200"
                    >
                        <Sparkles class="size-3.5 text-rose-800" />
                        <span>Convocatorias 2026 • Cusco</span>
                    </div>
                    <PeruGeoBadge />
                </div>

                <!-- Headline Ampliado, Proporcional y de Alto Impacto -->
                <h1
                    class="mx-auto max-w-4xl text-4xl leading-[1.12] font-black tracking-tight text-slate-950 sm:text-5xl md:text-6xl lg:text-7xl dark:text-white"
                >
                    Capacitaciones con
                    <span
                        class="text-rose-900 underline decoration-amber-500 decoration-4 underline-offset-8 dark:text-rose-400"
                        >Asistencia QR</span
                    >
                    y Certificación Oficial
                </h1>

                <!-- Subtitle -->
                <p
                    class="mx-auto max-w-3xl text-base leading-relaxed font-semibold text-slate-700 sm:text-lg md:text-xl dark:text-neutral-300"
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
                        class="w-full bg-rose-900 px-7 py-6 text-sm font-bold text-white shadow-md shadow-rose-900/20 hover:bg-rose-950 sm:w-auto sm:text-base"
                    >
                        <a href="#cursos">
                            <GraduationCap class="mr-2 size-5 text-amber-300" />
                            Ver Cursos Disponibles
                        </a>
                    </Button>
                    <Button
                        as-child
                        variant="outline"
                        size="lg"
                        class="w-full border-slate-300 px-7 py-6 text-sm font-bold text-slate-900 hover:bg-rose-50 hover:text-rose-900 sm:w-auto sm:text-base"
                    >
                        <Link href="/certificates">
                            <Award class="mr-2 size-5 text-rose-800" />
                            Consultar Certificado por DNI
                        </Link>
                    </Button>
                </div>
            </div>
        </section>

        <!-- CATALOG SECTION ("EN AFUERA") -->
        <section
            id="cursos"
            class="border-t border-slate-200/80 bg-white py-12 md:py-20 dark:border-neutral-800 dark:bg-neutral-900/50"
        >
            <div class="mx-auto max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">
                <!-- Section Header -->
                <div
                    class="flex flex-col justify-between gap-4 md:flex-row md:items-end"
                >
                    <div class="space-y-1">
                        <div
                            class="text-xs font-bold tracking-wider text-rose-900 uppercase dark:text-rose-400"
                        >
                            Convocatorias Abiertas
                        </div>
                        <h2
                            class="text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl dark:text-white"
                        >
                            Cursos y Capacitaciones
                        </h2>
                        <p
                            class="max-w-xl text-xs font-medium text-slate-600 sm:text-sm dark:text-neutral-400"
                        >
                            Capacitaciones con entidad convocante, aforo oficial
                            e inscripción directa con DNI.
                        </p>
                    </div>

                    <!-- Filter Chips -->
                    <div
                        class="flex flex-wrap items-center gap-1.5 self-start rounded-lg bg-slate-100 p-1 dark:bg-neutral-800"
                    >
                        <button
                            type="button"
                            @click="selectedStatus = 'all'"
                            class="rounded-md px-3 py-1 text-xs font-bold transition-all"
                            :class="
                                selectedStatus === 'all'
                                    ? 'bg-white text-slate-900 shadow-xs dark:bg-neutral-700 dark:text-white'
                                    : 'text-slate-600 hover:text-slate-900 dark:text-neutral-400'
                            "
                        >
                            Todos ({{ courses.length }})
                        </button>
                        <button
                            type="button"
                            @click="selectedStatus = 'abierto'"
                            class="rounded-md px-3 py-1 text-xs font-bold transition-all"
                            :class="
                                selectedStatus === 'abierto'
                                    ? 'bg-rose-900 text-white shadow-xs'
                                    : 'text-slate-600 hover:text-slate-900 dark:text-neutral-400'
                            "
                        >
                            Abiertos
                        </button>
                        <button
                            type="button"
                            @click="selectedStatus = 'en_curso'"
                            class="rounded-md px-3 py-1 text-xs font-bold transition-all"
                            :class="
                                selectedStatus === 'en_curso'
                                    ? 'bg-amber-700 text-white shadow-xs'
                                    : 'text-slate-600 hover:text-slate-900 dark:text-neutral-400'
                            "
                        >
                            En Curso
                        </button>
                    </div>
                </div>

                <!-- Search Bar -->
                <div class="relative max-w-md">
                    <Search
                        class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-neutral-400"
                    />
                    <Input
                        v-model="searchQuery"
                        type="search"
                        placeholder="Buscar por título, entidad organizadora, código o docente..."
                        class="bg-neutral-50/60 pl-9 text-xs dark:bg-neutral-900"
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
                        class="flex max-w-full min-w-0 flex-col overflow-hidden border border-slate-200 bg-white transition-all duration-200 hover:border-rose-400/80 hover:shadow-md dark:border-neutral-800 dark:bg-neutral-900"
                    >
                        <!-- Card Header -->
                        <CardHeader
                            class="max-w-full min-w-0 space-y-2 p-4 pb-3 sm:p-5"
                        >
                            <div
                                class="flex w-full min-w-0 items-center justify-between gap-1.5"
                            >
                                <span
                                    class="shrink-0 rounded border border-rose-200 bg-rose-50 px-2 py-0.5 font-mono text-[11px] font-bold text-rose-900 dark:border-rose-800 dark:bg-rose-950/60 dark:text-rose-300"
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
                                class="line-clamp-2 min-w-0 text-base leading-snug font-bold break-words text-slate-900 sm:text-lg dark:text-white"
                            >
                                {{ course.title }}
                            </CardTitle>

                            <!-- Organizing Entity / Institución -->
                            <div
                                class="flex w-full max-w-full min-w-0 items-center gap-1.5 overflow-hidden rounded-md border border-rose-200/80 bg-rose-50/80 px-2.5 py-1 text-xs font-semibold text-rose-950 dark:border-rose-800/60 dark:bg-rose-950/40 dark:text-rose-200"
                            >
                                <Building2
                                    class="size-3.5 shrink-0 text-rose-800 dark:text-rose-400"
                                />
                                <span class="block min-w-0 flex-1 truncate">{{
                                    course.institution ||
                                    'Dirección Académica • Cusco'
                                }}</span>
                            </div>

                            <CardDescription
                                class="line-clamp-2 min-w-0 text-xs font-medium break-words text-slate-600 dark:text-neutral-400"
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
                            <!-- Instructor snippet -->
                            <div
                                class="flex max-w-full min-w-0 items-center gap-2 border-t border-slate-100 pt-2 text-slate-800 dark:border-neutral-800 dark:text-neutral-200"
                            >
                                <div
                                    class="flex size-6 shrink-0 items-center justify-center rounded-full bg-rose-100 text-[10px] font-bold text-rose-900 dark:bg-rose-950 dark:text-rose-300"
                                >
                                    {{
                                        (course.instructor?.name ||
                                            course.instructor_name ||
                                            'P')[0]
                                    }}
                                </div>
                                <span class="min-w-0 flex-1 truncate">
                                    Ponente / Docente:
                                    <strong>{{
                                        course.instructor
                                            ? `${course.instructor.name} ${course.instructor.paterno || ''}`
                                            : course.instructor_name ||
                                              'Por asignar'
                                    }}</strong>
                                </span>
                            </div>

                            <!-- Meta info: dates & hours -->
                            <div
                                class="grid min-w-0 grid-cols-2 gap-2 text-xs font-semibold text-slate-800 dark:text-slate-200"
                            >
                                <div class="flex min-w-0 items-center gap-1.5">
                                    <Calendar
                                        class="size-3.5 shrink-0 text-rose-800 dark:text-rose-400"
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
                                <div class="flex min-w-0 items-center gap-1.5">
                                    <Clock
                                        class="size-3.5 shrink-0 text-amber-700 dark:text-amber-400"
                                    />
                                    <span class="truncate"
                                        ><strong>{{
                                            formatHours(course.hours)
                                        }}</strong></span
                                    >
                                </div>
                            </div>

                            <!-- Vacantes / Capacity Bar -->
                            <div class="max-w-full min-w-0 space-y-1.5 pt-1">
                                <div
                                    class="flex min-w-0 items-center justify-between gap-2 text-[11px]"
                                >
                                    <span
                                        class="truncate font-medium text-slate-600 dark:text-neutral-400"
                                        >Vacantes Disponibles:</span
                                    >
                                    <span
                                        class="shrink-0 font-bold"
                                        :class="
                                            course.capacity -
                                                course.enrollments_count >
                                            0
                                                ? 'text-rose-900 dark:text-rose-300'
                                                : 'text-rose-600'
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
                                    class="h-1.5 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-neutral-800"
                                >
                                    <div
                                        class="h-full rounded-full bg-rose-900 transition-all"
                                        :style="{
                                            width: `${Math.min(100, (course.enrollments_count / course.capacity) * 100)}%`,
                                        }"
                                    />
                                </div>
                            </div>
                        </CardContent>

                        <!-- Card Footer Action -->
                        <CardFooter
                            class="flex max-w-full min-w-0 items-center justify-between gap-2 border-t border-slate-100 bg-slate-50/50 p-4 pt-3 sm:p-5 dark:border-neutral-800 dark:bg-neutral-900/50"
                        >
                            <Button
                                as-child
                                variant="ghost"
                                size="sm"
                                class="flex-1 text-xs font-semibold text-slate-700 hover:text-rose-900"
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
                                class="flex-1 bg-rose-900 text-xs font-bold text-white shadow-xs hover:bg-rose-950"
                                @click="openEnrollment(course)"
                            >
                                <CheckCircle2 class="mr-1.5 size-3.5" />
                                Inscribirme
                            </Button>
                            <Button
                                v-else
                                size="sm"
                                variant="secondary"
                                disabled
                                class="flex-1 text-xs font-medium opacity-75"
                            >
                                {{
                                    course.status !== 'abierto'
                                        ? 'Cerrado'
                                        : 'Agotado'
                                }}
                            </Button>
                        </CardFooter>
                    </Card>
                </div>
            </div>
        </section>

        <!-- SYSTEM FEATURES / BENEFITS -->
        <section
            id="beneficios"
            class="border-t border-slate-200/80 bg-white py-12 md:py-20 dark:border-neutral-800 dark:bg-neutral-900/60"
        >
            <div class="mx-auto max-w-7xl space-y-12 px-4 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-2xl space-y-2 text-center">
                    <h2
                        class="text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl dark:text-white"
                    >
                        ¿Por qué elegir la plataforma SIGC-CUSCO?
                    </h2>
                    <p
                        class="text-xs font-medium text-slate-600 sm:text-sm dark:text-neutral-400"
                    >
                        Tecnología moderna para entidades educativas,
                        instituciones y empresas capacitadoras.
                    </p>
                </div>

                <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                    <div
                        class="space-y-3 rounded-2xl border border-slate-200 bg-slate-50/50 p-6 dark:border-neutral-800 dark:bg-neutral-900/30"
                    >
                        <div
                            class="flex size-10 items-center justify-center rounded-xl bg-rose-100 font-bold text-rose-900 dark:bg-rose-950 dark:text-rose-300"
                        >
                            <QrCode class="size-5" />
                        </div>
                        <h3
                            class="text-base font-bold text-slate-900 dark:text-white"
                        >
                            Control de Asistencia con QR
                        </h3>
                        <p
                            class="text-xs leading-relaxed font-medium text-slate-600 dark:text-neutral-400"
                        >
                            El docente proyecta un código QR dinámico por sesión
                            de clase. Los alumnos escanean el código en segundos
                            desde sus celulares para marcar asistencia.
                        </p>
                    </div>

                    <div
                        class="space-y-3 rounded-2xl border border-slate-200 bg-slate-50/50 p-6 dark:border-neutral-800 dark:bg-neutral-900/30"
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
                            class="text-xs leading-relaxed font-medium text-slate-600 dark:text-neutral-400"
                        >
                            Integración en tiempo real con RENIEC Perú para
                            validar nombres legítimos y generar certificados
                            protegidos institucionalmente.
                        </p>
                    </div>

                    <div
                        class="space-y-3 rounded-2xl border border-slate-200 bg-slate-50/50 p-6 dark:border-neutral-800 dark:bg-neutral-900/30"
                    >
                        <div
                            class="flex size-10 items-center justify-center rounded-xl bg-indigo-100 font-bold text-indigo-800 dark:bg-indigo-950 dark:text-indigo-300"
                        >
                            <Award class="size-5" />
                        </div>
                        <h3
                            class="text-base font-bold text-slate-900 dark:text-white"
                        >
                            Certificación Digital Inmediata
                        </h3>
                        <p
                            class="text-xs leading-relaxed font-medium text-slate-600 dark:text-neutral-400"
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
            class="border-t border-slate-200/80 bg-slate-100/60 py-8 text-xs text-slate-600 dark:border-neutral-800 dark:bg-neutral-950 dark:text-neutral-400"
        >
            <div
                class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-4 px-4 sm:flex-row sm:px-6 lg:px-8"
            >
                <div class="flex items-center gap-2 font-medium">
                    <GraduationCap class="size-4 text-rose-900" />
                    <span
                        class="font-bold text-slate-900 dark:text-neutral-200"
                    >
                        SIGC-CUSCO
                    </span>
                    <span
                        >— Sistema Integral de Gestión de Capacitaciones •
                        Cusco</span
                    >
                </div>
                <div class="font-medium">
                    Gestión Académica e Institucional • Cusco, Perú 2026
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
