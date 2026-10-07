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
    Menu,
    X,
    ExternalLink,
    Building2,
    LogIn,
    UserPlus,
} from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import EnrollmentModal from '@/components/EnrollmentModal.vue';
import LoginModal from '@/components/auth/LoginModal.vue';
import RegisterModal from '@/components/auth/RegisterModal.vue';
import PeruGeoBadge from '@/components/PeruGeoBadge.vue';
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

// Search & Filter state
const searchQuery = ref('');
const selectedStatus = ref<string>('all');
const mobileMenuOpen = ref(false);

// Modals state
const isLoginModalOpen = ref(false);
const isRegisterModalOpen = ref(false);

const selectedCourseForEnrollment = ref<CourseItem | null>(null);
const isEnrollmentModalOpen = ref(false);

function openEnrollment(course: CourseItem) {
    selectedCourseForEnrollment.value = course;
    isEnrollmentModalOpen.value = true;
}

function switchToRegister() {
    isLoginModalOpen.value = false;
    isRegisterModalOpen.value = true;
}

function switchToLogin() {
    isRegisterModalOpen.value = false;
    isLoginModalOpen.value = true;
}

// Filtered courses
const filteredCourses = computed(() => {
    return props.courses.filter((course) => {
        const matchesStatus =
            selectedStatus.value === 'all' || course.status === selectedStatus.value;

        const q = searchQuery.value.toLowerCase().trim();
        const matchesQuery =
            !q ||
            course.title.toLowerCase().includes(q) ||
            course.code.toLowerCase().includes(q) ||
            (course.institution && course.institution.toLowerCase().includes(q)) ||
            (course.description && course.description.toLowerCase().includes(q)) ||
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

    <div class="min-h-screen bg-slate-50/50 dark:bg-neutral-950 text-slate-900 dark:text-neutral-100 antialiased selection:bg-rose-900 selection:text-white">
        <!-- TOP NAVBAR (PÚBLICA) -->
        <header class="sticky top-0 z-40 w-full border-b border-slate-200/80 dark:border-neutral-800 bg-white/95 dark:bg-neutral-900/90 backdrop-blur-md">
            <div class="max-w-7xl mx-auto flex h-16 items-center justify-between px-4 sm:px-6 lg:px-8">
                <!-- Brand Logo -->
                <Link href="/" class="flex items-center gap-3 group">
                    <div class="size-10 rounded-xl bg-gradient-to-tr from-[#701a31] to-[#800020] flex items-center justify-center text-white shadow-sm shadow-rose-900/20 group-hover:scale-105 transition-transform">
                        <GraduationCap class="size-5 text-amber-300" />
                    </div>
                    <div>
                        <div class="flex items-center gap-1.5 font-bold text-base tracking-tight text-slate-900 dark:text-white">
                            <span>SIGC-CUSCO</span>
                        </div>
                        <p class="text-[11px] text-slate-600 dark:text-neutral-400 font-medium hidden sm:block">
                            Plataforma Oficial de Capacitaciones • Cusco
                        </p>
                    </div>
                </Link>

                <!-- Desktop Navigation Links -->
                <nav class="hidden md:flex items-center gap-6 text-sm font-semibold text-slate-700 dark:text-neutral-300">
                    <a href="#cursos" class="hover:text-rose-900 dark:hover:text-rose-400 transition-colors">
                        Capacitaciones
                    </a>
                    <Link href="/certificates" class="hover:text-rose-900 dark:hover:text-rose-400 transition-colors">
                        Certificados Digitales
                    </Link>
                    <a href="#beneficios" class="hover:text-rose-900 dark:hover:text-rose-400 transition-colors">
                        Características
                    </a>
                </nav>

                <!-- Auth Actions (MODALS) -->
                <div class="hidden sm:flex items-center gap-2">
                    <template v-if="authUser">
                        <Button as-child size="sm" class="bg-rose-900 hover:bg-rose-950 text-white font-bold shadow-xs text-xs">
                            <Link href="/dashboard">
                                <span>Mi Panel</span>
                                <span class="ml-1.5 text-[10px] bg-rose-950 text-amber-300 px-1.5 py-0.5 rounded uppercase font-bold tracking-wider">
                                    {{ authUser.role }}
                                </span>
                            </Link>
                        </Button>
                    </template>
                    <template v-else>
                        <Button variant="ghost" size="sm" class="text-xs font-bold text-slate-800 hover:text-rose-900" @click="isLoginModalOpen = true">
                            <LogIn class="size-3.5 mr-1 text-rose-900" />
                            Iniciar Sesión
                        </Button>
                        <Button size="sm" class="bg-rose-900 hover:bg-rose-950 text-white font-bold text-xs shadow-xs" @click="isRegisterModalOpen = true">
                            <UserPlus class="size-3.5 mr-1" />
                            Registrarse
                        </Button>
                    </template>
                </div>

                <!-- Mobile Menu Button -->
                <div class="flex md:hidden">
                    <Button variant="ghost" size="icon" @click="mobileMenuOpen = !mobileMenuOpen">
                        <X v-if="mobileMenuOpen" class="size-5" />
                        <Menu v-else class="size-5" />
                    </Button>
                </div>
            </div>

            <!-- Mobile Dropdown -->
            <div v-if="mobileMenuOpen" class="md:hidden border-b border-slate-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 px-4 pt-2 pb-4 space-y-3">
                <a href="#cursos" @click="mobileMenuOpen = false" class="block py-1.5 text-sm font-semibold text-slate-800 dark:text-neutral-200">
                    Capacitaciones Disponibles
                </a>
                <Link href="/certificates" @click="mobileMenuOpen = false" class="block py-1.5 text-sm font-semibold text-slate-800 dark:text-neutral-200">
                    Consultar Certificados por DNI
                </Link>
                <a href="#beneficios" @click="mobileMenuOpen = false" class="block py-1.5 text-sm font-semibold text-slate-800 dark:text-neutral-200">
                    Características del Sistema
                </a>
                <div class="pt-2 border-t border-slate-200 dark:border-neutral-800 flex gap-2">
                    <template v-if="authUser">
                        <Button as-child class="w-full bg-rose-900 text-white text-xs font-bold">
                            <Link href="/dashboard">Ir a mi Panel ({{ authUser.role }})</Link>
                        </Button>
                    </template>
                    <template v-else>
                        <Button variant="outline" size="sm" class="flex-1 text-xs font-semibold" @click="isLoginModalOpen = true; mobileMenuOpen = false">
                            Ingresar
                        </Button>
                        <Button size="sm" class="flex-1 bg-rose-900 text-white text-xs font-bold" @click="isRegisterModalOpen = true; mobileMenuOpen = false">
                            Registrarse
                        </Button>
                    </template>
                </div>
            </div>
        </header>

        <!-- HERO SECTION -->
        <section class="relative overflow-hidden pt-12 pb-16 md:pt-20 md:pb-24 bg-gradient-to-b from-rose-50/70 via-transparent to-transparent dark:from-rose-950/20">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center space-y-6">
                <!-- Pill tags -->
                <div class="flex flex-wrap items-center justify-center gap-2">
                    <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-rose-100/90 dark:bg-rose-950/60 border border-rose-300 dark:border-rose-800 text-rose-950 dark:text-rose-200 text-xs font-bold shadow-xs">
                        <Sparkles class="size-3.5 text-rose-800" />
                        <span>Convocatorias 2026 • Cusco</span>
                    </div>
                    <PeruGeoBadge />
                </div>

                <!-- Headline Ampliado, Proporcional y de Alto Impacto -->
                <h1 class="text-4xl sm:text-5xl md:text-6xl lg:text-7xl font-black tracking-tight text-slate-950 dark:text-white max-w-4xl mx-auto leading-[1.12]">
                    Capacitaciones con <span class="text-rose-900 dark:text-rose-400 underline decoration-amber-500 decoration-4 underline-offset-8">Asistencia QR</span> y Certificación Oficial
                </h1>

                <!-- Subtitle -->
                <p class="text-base sm:text-lg md:text-xl text-slate-700 dark:text-neutral-300 max-w-3xl mx-auto leading-relaxed font-semibold">
                    Inscripción inmediata con DNI, control de asistencia por código QR y diplomas digitales con validación web institucional.
                </p>

                <!-- CTA Buttons -->
                <div class="flex flex-col sm:flex-row items-center justify-center gap-3 pt-3">
                    <Button as-child size="lg" class="w-full sm:w-auto bg-rose-900 hover:bg-rose-950 text-white font-bold shadow-md shadow-rose-900/20 px-7 py-6 text-sm sm:text-base">
                        <a href="#cursos">
                            <GraduationCap class="mr-2 size-5 text-amber-300" />
                            Ver Cursos Disponibles
                        </a>
                    </Button>
                    <Button as-child variant="outline" size="lg" class="w-full sm:w-auto text-sm sm:text-base font-bold text-slate-900 hover:text-rose-900 hover:bg-rose-50 border-slate-300 px-7 py-6">
                        <Link href="/certificates">
                            <Award class="mr-2 size-5 text-rose-800" />
                            Consultar Certificado por DNI
                        </Link>
                    </Button>
                </div>
            </div>
        </section>

        <!-- CATALOG SECTION ("EN AFUERA") -->
        <section id="cursos" class="py-12 md:py-20 border-t border-slate-200/80 dark:border-neutral-800 bg-white dark:bg-neutral-900/50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
                <!-- Section Header -->
                <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
                    <div class="space-y-1">
                        <div class="text-xs font-bold text-rose-900 dark:text-rose-400 uppercase tracking-wider">
                            Convocatorias Abiertas
                        </div>
                        <h2 class="text-2xl sm:text-3xl font-bold tracking-tight text-slate-950 dark:text-white">
                            Cursos y Capacitaciones
                        </h2>
                        <p class="text-xs sm:text-sm text-slate-600 dark:text-neutral-400 max-w-xl font-medium">
                            Capacitaciones con entidad convocante, aforo oficial e inscripción directa con DNI.
                        </p>
                    </div>

                    <!-- Filter Chips -->
                    <div class="flex flex-wrap items-center gap-1.5 bg-slate-100 dark:bg-neutral-800 p-1 rounded-lg self-start">
                        <button
                            type="button"
                            @click="selectedStatus = 'all'"
                            class="px-3 py-1 rounded-md text-xs font-bold transition-all"
                            :class="selectedStatus === 'all' ? 'bg-white dark:bg-neutral-700 text-slate-900 dark:text-white shadow-xs' : 'text-slate-600 dark:text-neutral-400 hover:text-slate-900'"
                        >
                            Todos ({{ courses.length }})
                        </button>
                        <button
                            type="button"
                            @click="selectedStatus = 'abierto'"
                            class="px-3 py-1 rounded-md text-xs font-bold transition-all"
                            :class="selectedStatus === 'abierto' ? 'bg-rose-900 text-white shadow-xs' : 'text-slate-600 dark:text-neutral-400 hover:text-slate-900'"
                        >
                            Abiertos
                        </button>
                        <button
                            type="button"
                            @click="selectedStatus = 'en_curso'"
                            class="px-3 py-1 rounded-md text-xs font-bold transition-all"
                            :class="selectedStatus === 'en_curso' ? 'bg-amber-700 text-white shadow-xs' : 'text-slate-600 dark:text-neutral-400 hover:text-slate-900'"
                        >
                            En Curso
                        </button>
                    </div>
                </div>

                <!-- Search Bar -->
                <div class="relative max-w-md">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-neutral-400" />
                    <Input
                        v-model="searchQuery"
                        type="search"
                        placeholder="Buscar por título, entidad organizadora, código o docente..."
                        class="pl-9 text-xs bg-neutral-50/60 dark:bg-neutral-900"
                    />
                </div>

                <!-- Empty State -->
                <div v-if="filteredCourses.length === 0" class="py-16 text-center rounded-2xl border border-dashed border-neutral-300 dark:border-neutral-800 p-8 space-y-3">
                    <GraduationCap class="size-12 text-neutral-400 mx-auto" />
                    <div class="space-y-1">
                        <h3 class="text-base font-semibold">No se encontraron capacitaciones</h3>
                        <p class="text-xs text-neutral-500 max-w-sm mx-auto">
                            No hay cursos que coincidan con el término de búsqueda o el filtro seleccionado.
                        </p>
                    </div>
                    <Button variant="outline" size="sm" @click="searchQuery = ''; selectedStatus = 'all'" class="text-xs">
                        Restablecer filtros
                    </Button>
                </div>

                <!-- Course Cards Grid -->
                <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <Card
                        v-for="course in filteredCourses"
                        :key="course.id"
                        class="flex flex-col border border-slate-200 dark:border-neutral-800 hover:border-rose-400/80 hover:shadow-md transition-all duration-200 overflow-hidden bg-white dark:bg-neutral-900"
                    >
                        <!-- Card Header -->
                        <CardHeader class="pb-3 space-y-2">
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-mono text-[11px] font-bold text-rose-900 dark:text-rose-300 bg-rose-50 dark:bg-rose-950/60 px-2 py-0.5 rounded border border-rose-200 dark:border-rose-800">
                                    {{ course.code }}
                                </span>
                                <span
                                    class="text-[11px] font-bold px-2 py-0.5 rounded-full border"
                                    :class="statusBadgeInfo(course.status).class"
                                >
                                    {{ statusBadgeInfo(course.status).label }}
                                </span>
                            </div>

                            <CardTitle class="text-lg font-bold line-clamp-2 leading-snug text-slate-900 dark:text-white">
                                {{ course.title }}
                            </CardTitle>

                            <!-- Organizing Entity / Institución -->
                            <div class="flex items-center gap-1.5 text-xs text-rose-950 dark:text-rose-200 bg-rose-50/80 dark:bg-rose-950/40 px-2.5 py-1 rounded-md border border-rose-200/80 dark:border-rose-800/60 font-semibold">
                                <Building2 class="size-3.5 shrink-0 text-rose-800 dark:text-rose-400" />
                                <span class="truncate">{{ course.institution || 'Dirección Académica • Cusco' }}</span>
                            </div>

                            <CardDescription class="text-xs line-clamp-2 text-slate-600 dark:text-neutral-400 font-medium">
                                {{ course.description || 'Capacitación profesional oficial con asistencia controlada por código QR y certificación digital.' }}
                            </CardDescription>
                        </CardHeader>

                        <!-- Card Body Details -->
                        <CardContent class="flex-1 space-y-3 text-xs">
                            <!-- Instructor snippet -->
                            <div class="flex items-center gap-2 pt-2 border-t border-slate-100 dark:border-neutral-800 text-slate-800 dark:text-neutral-200">
                                <div class="size-6 rounded-full bg-rose-100 text-rose-900 dark:bg-rose-950 dark:text-rose-300 flex items-center justify-center font-bold text-[10px]">
                                    {{ (course.instructor?.name || course.instructor_name || 'P')[0] }}
                                </div>
                                <span class="truncate">
                                    Ponente / Docente: <strong>{{ course.instructor ? `${course.instructor.name} ${course.instructor.paterno || ''}` : (course.instructor_name || 'Por asignar') }}</strong>
                                </span>
                            </div>

                            <!-- Meta info: dates & hours -->
                            <div class="grid grid-cols-2 gap-2 text-slate-800 dark:text-slate-200 text-xs font-semibold">
                                <div class="flex items-center gap-1.5">
                                    <Calendar class="size-3.5 text-rose-800 dark:text-rose-400 shrink-0" />
                                    <span>Inicio: <strong>{{ formatDate(course.start_date, 'compact') }}</strong></span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <Clock class="size-3.5 text-amber-700 dark:text-amber-400 shrink-0" />
                                    <span><strong>{{ formatHours(course.hours) }}</strong></span>
                                </div>
                            </div>

                            <!-- Vacantes / Capacity Bar -->
                            <div class="space-y-1.5 pt-1">
                                <div class="flex items-center justify-between text-[11px]">
                                    <span class="text-slate-600 dark:text-neutral-400 font-medium">Vacantes Disponibles:</span>
                                    <span class="font-bold" :class="course.capacity - course.enrollments_count > 0 ? 'text-rose-900 dark:text-rose-300' : 'text-rose-600'">
                                        {{ Math.max(0, course.capacity - course.enrollments_count) }} de {{ course.capacity }}
                                    </span>
                                </div>
                                <div class="w-full bg-slate-100 dark:bg-neutral-800 h-1.5 rounded-full overflow-hidden">
                                    <div
                                        class="h-full bg-rose-900 rounded-full transition-all"
                                        :style="{ width: `${Math.min(100, (course.enrollments_count / course.capacity) * 100)}%` }"
                                    />
                                </div>
                            </div>
                        </CardContent>

                        <!-- Card Footer Action -->
                        <CardFooter class="pt-3 border-t border-slate-100 dark:border-neutral-800 flex items-center justify-between gap-2 bg-slate-50/50 dark:bg-neutral-900/50">
                            <Button as-child variant="ghost" size="sm" class="text-xs flex-1 font-semibold text-slate-700 hover:text-rose-900">
                                <Link :href="`/courses/${course.id}`">
                                    Detalles
                                </Link>
                            </Button>

                            <Button
                                v-if="course.status === 'abierto' && course.capacity - course.enrollments_count > 0"
                                size="sm"
                                class="bg-rose-900 hover:bg-rose-950 text-white font-bold text-xs shadow-xs flex-1"
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
                                class="text-xs opacity-75 flex-1 font-medium"
                            >
                                {{ course.status !== 'abierto' ? 'Cerrado' : 'Agotado' }}
                            </Button>
                        </CardFooter>
                    </Card>
                </div>
            </div>
        </section>


        <!-- SYSTEM FEATURES / BENEFITS -->
        <section id="beneficios" class="py-12 md:py-20 border-t border-slate-200/80 dark:border-neutral-800 bg-white dark:bg-neutral-900/60">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-12">
                <div class="text-center space-y-2 max-w-2xl mx-auto">
                    <h2 class="text-2xl sm:text-3xl font-bold tracking-tight text-slate-950 dark:text-white">
                        ¿Por qué elegir la plataforma SIGC-CUSCO?
                    </h2>
                    <p class="text-xs sm:text-sm text-slate-600 dark:text-neutral-400 font-medium">
                        Tecnología moderna para entidades educativas, instituciones y empresas capacitadoras.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="p-6 rounded-2xl border border-slate-200 dark:border-neutral-800 bg-slate-50/50 dark:bg-neutral-900/30 space-y-3">
                        <div class="size-10 rounded-xl bg-rose-100 dark:bg-rose-950 text-rose-900 dark:text-rose-300 flex items-center justify-center font-bold">
                            <QrCode class="size-5" />
                        </div>
                        <h3 class="text-base font-bold text-slate-900 dark:text-white">Control de Asistencia con QR</h3>
                        <p class="text-xs text-slate-600 dark:text-neutral-400 leading-relaxed font-medium">
                            El docente proyecta un código QR dinámico por sesión de clase. Los alumnos escanean el código en segundos desde sus celulares para marcar asistencia.
                        </p>
                    </div>

                    <div class="p-6 rounded-2xl border border-slate-200 dark:border-neutral-800 bg-slate-50/50 dark:bg-neutral-900/30 space-y-3">
                        <div class="size-10 rounded-xl bg-amber-100 dark:bg-amber-950 text-amber-800 dark:text-amber-300 flex items-center justify-center font-bold">
                            <ShieldCheck class="size-5" />
                        </div>
                        <h3 class="text-base font-bold text-slate-900 dark:text-white">Cero Falsificaciones RENIEC</h3>
                        <p class="text-xs text-slate-600 dark:text-neutral-400 leading-relaxed font-medium">
                            Integración en tiempo real con RENIEC Perú para validar nombres legítimos y generar certificados protegidos institucionalmente.
                        </p>
                    </div>

                    <div class="p-6 rounded-2xl border border-slate-200 dark:border-neutral-800 bg-slate-50/50 dark:bg-neutral-900/30 space-y-3">
                        <div class="size-10 rounded-xl bg-indigo-100 dark:bg-indigo-950 text-indigo-800 dark:text-indigo-300 flex items-center justify-center font-bold">
                            <Award class="size-5" />
                        </div>
                        <h3 class="text-base font-bold text-slate-900 dark:text-white">Certificación Digital Inmediata</h3>
                        <p class="text-xs text-slate-600 dark:text-neutral-400 leading-relaxed font-medium">
                            Al completar los criterios de asistencia y evaluación, el participante descarga su certificado en formato PDF con código de verificación permanente.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- FOOTER -->
        <footer class="border-t border-slate-200/80 dark:border-neutral-800 bg-slate-100/60 dark:bg-neutral-950 py-8 text-xs text-slate-600 dark:text-neutral-400">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-2 font-medium">
                    <GraduationCap class="size-4 text-rose-900" />
                    <span class="font-bold text-slate-900 dark:text-neutral-200">
                        SIGC-CUSCO
                    </span>
                    <span>— Sistema Integral de Gestión de Capacitaciones • Cusco</span>
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
    </div>
</template>
