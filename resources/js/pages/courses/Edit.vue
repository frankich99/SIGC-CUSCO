<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Spinner } from '@/components/ui/spinner';
import {
    AlertCircle,
    ArrowLeft,
    Calendar,
    Clock,
    GraduationCap,
    BookOpen,
    Building2,
    Users,
    CheckCircle2,
    Lock,
    Check,
    ShieldCheck,
} from '@lucide/vue';
import { THEME_BADGES } from '@/lib/theme';
import { notify } from '@/lib/notify';
import type { BreadcrumbItem } from '@/types';

interface Instructor {
    id: number;
    name: string;
    paterno?: string;
    materno?: string;
    email: string;
    role: string;
}

interface CourseData {
    id: number;
    code: string;
    title: string;
    institution?: string;
    description?: string;
    instructor_id?: number | null;
    instructor_name?: string | null;
    start_date: string;
    end_date: string;
    hours: number;
    total_sessions?: number;
    min_attendance_percentage?: number;
    capacity: number;
    status: string;
}

const props = defineProps<{
    course: CourseData;
    instructors: Instructor[];
    statuses: Array<{ value: string; label: string }>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Panel Principal', href: '/dashboard' },
    { title: 'Capacitaciones', href: '/courses' },
    { title: props.course.title, href: `/courses/${props.course.id}` },
    { title: 'Editar', href: `/courses/${props.course.id}/edit` },
];

const form = useForm({
    code: props.course.code,
    title: props.course.title,
    institution: props.course.institution || '',
    description: props.course.description || '',
    instructor_id: props.course.instructor_id || null,
    instructor_name:
        props.course.instructor_name ||
        (props.course as any).instructor_display_name ||
        '',
    start_date: props.course.start_date,
    end_date: props.course.end_date,
    hours: props.course.hours,
    total_sessions: props.course.total_sessions ?? 4,
    min_attendance_percentage: props.course.min_attendance_percentage ?? 75,
    capacity: props.course.capacity,
    status: props.course.status,
});

const statusOptions = [
    {
        value: 'abierto',
        label: 'Abierto',
        dotClass: 'bg-emerald-500 shadow-xs shadow-emerald-500/50',
        activeClass:
            'bg-emerald-50/90 dark:bg-emerald-950/60 border-emerald-500 text-emerald-950 dark:text-emerald-100 ring-2 ring-emerald-500/20 shadow-xs',
        checkBadgeClass: 'bg-emerald-600 text-white',
    },
    {
        value: 'en_curso',
        label: 'En Curso',
        dotClass: 'bg-blue-500 shadow-xs shadow-blue-500/50',
        activeClass:
            'bg-blue-50/90 dark:bg-blue-950/60 border-blue-500 text-blue-950 dark:text-blue-100 ring-2 ring-blue-500/20 shadow-xs',
        checkBadgeClass: 'bg-blue-600 text-white',
    },
    {
        value: 'concluido',
        label: 'Concluido',
        dotClass: 'bg-purple-500 shadow-xs shadow-purple-500/50',
        activeClass:
            'bg-purple-50/90 dark:bg-purple-950/60 border-purple-500 text-purple-950 dark:text-purple-100 ring-2 ring-purple-500/20 shadow-xs',
        checkBadgeClass: 'bg-purple-600 text-white',
    },
    {
        value: 'cancelado',
        label: 'Cancelado',
        dotClass: 'bg-rose-500 shadow-xs shadow-rose-500/50',
        activeClass:
            'bg-rose-50/90 dark:bg-rose-950/60 border-rose-500 text-rose-950 dark:text-rose-100 ring-2 ring-rose-500/20 shadow-xs',
        checkBadgeClass: 'bg-rose-600 text-white',
    },
];

const isDateOrderInvalid = computed(() => {
    if (form.start_date && form.end_date) {
        return new Date(form.end_date) < new Date(form.start_date);
    }
    return false;
});

function submit() {
    form.put(`/courses/${props.course.id}`, {
        onError: () => {
            notify.error(
                'Errores al actualizar',
                'Revise los campos requeridos marcados en rojo.',
            );
        },
    });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`Editar Capacitación: ${course.code} - SIGC-CUSCO`" />

        <div
            class="mx-auto max-w-4xl space-y-6 px-4 py-6 sm:px-6 sm:py-8 lg:px-8"
        >
            <!-- CABECERA INSTITUCIONAL GRANATE CUSCO -->
            <div
                class="flex flex-col justify-between gap-4 border-b border-slate-200/90 pb-5 sm:flex-row sm:items-center dark:border-slate-800"
            >
                <div class="space-y-1">
                    <div class="flex items-center gap-2">
                        <div
                            class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-tr from-[#701a31] to-[#800020] text-white shadow-xs ring-1 ring-rose-950/20"
                        >
                            <GraduationCap class="size-5 text-amber-300" />
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h1
                                    class="text-xl font-black tracking-tight text-slate-950 sm:text-2xl dark:text-white"
                                >
                                    Editar Capacitación
                                </h1>
                                <span
                                    class="rounded border border-rose-300 bg-rose-100 px-2 py-0.5 font-mono text-xs font-black text-rose-950 dark:border-rose-800 dark:bg-rose-950 dark:text-rose-200"
                                >
                                    {{ course.code }}
                                </span>
                            </div>
                            <p
                                class="text-xs font-medium text-slate-600 sm:text-sm dark:text-slate-400"
                            >
                                Modificación de parámetros académicos, fechas y
                                aforo del programa.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2.5">
                    <Button
                        as-child
                        variant="outline"
                        size="sm"
                        class="cursor-pointer border-slate-300 text-xs font-bold shadow-2xs hover:bg-rose-50 hover:text-rose-900"
                    >
                        <Link :href="`/courses/${course.id}`">
                            <ArrowLeft class="mr-1.5 size-3.5 text-rose-800" />
                            Ver Detalles del Curso
                        </Link>
                    </Button>
                </div>
            </div>

            <!-- FORMULARIO INSTITUCIONAL -->
            <form @submit.prevent="submit" class="space-y-6">
                <!-- SECCIÓN 1: IDENTIFICACIÓN Y CONVOCATORIA -->
                <Card
                    class="overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-xs dark:border-slate-800 dark:bg-slate-900"
                >
                    <CardHeader
                        class="border-b bg-slate-50/70 px-5 pb-3.5 sm:px-6 dark:bg-slate-900/80"
                    >
                        <div
                            class="flex items-center gap-2 text-xs font-black tracking-wider text-rose-900 uppercase dark:text-rose-400"
                        >
                            <BookOpen class="size-4 text-rose-800" />
                            <span>1. Identificación y Convocatoria</span>
                        </div>
                        <CardTitle
                            class="text-base font-bold text-slate-950 dark:text-white"
                        >
                            Datos Generales del Programa
                        </CardTitle>
                        <CardDescription
                            class="text-xs text-slate-600 dark:text-slate-400"
                        >
                            El código oficial es inmutable para preservar la
                            validez legal de las actas y certificados emitidos.
                        </CardDescription>
                    </CardHeader>

                    <CardContent class="space-y-5 p-5 sm:p-6">
                        <!-- Fila 1: Código Oficial (Inmutable) + Estado -->
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="space-y-1.5">
                                <div class="flex items-center justify-between">
                                    <Label
                                        for="code"
                                        class="flex items-center gap-1.5 text-xs font-bold text-slate-800 dark:text-slate-200"
                                    >
                                        <Lock class="size-3.5 text-rose-800" />
                                        <span>Código Oficial</span>
                                    </Label>
                                    <span
                                        class="flex items-center gap-1 rounded-full border border-slate-300 bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                                    >
                                        <Lock class="size-2.5" />
                                        Inmutable
                                    </span>
                                </div>
                                <div class="relative">
                                    <Input
                                        id="code"
                                        :value="form.code"
                                        readonly
                                        tabindex="-1"
                                        class="h-10 cursor-not-allowed border-slate-300 bg-slate-100/90 pr-10 pl-3 font-mono text-sm font-black tracking-wider text-rose-950 select-none dark:border-slate-700 dark:bg-slate-800/80 dark:text-rose-200"
                                    />
                                    <div
                                        class="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-slate-400"
                                    >
                                        <Lock class="size-4" />
                                    </div>
                                </div>
                                <p
                                    class="text-[11px] font-medium text-slate-500"
                                >
                                    Código institucional único registrado.
                                </p>
                            </div>

                            <div class="space-y-1.5">
                                <div class="flex items-center justify-between">
                                    <Label
                                        class="text-xs font-bold text-slate-800 dark:text-slate-200"
                                    >
                                        Estado de la Convocatoria
                                        <span class="text-rose-700">*</span>
                                    </Label>
                                    <Badge
                                        class="rounded-full px-2.5 py-0.5 text-[10px] font-bold capitalize"
                                        :class="
                                            THEME_BADGES[
                                                form.status as keyof typeof THEME_BADGES
                                            ] || 'bg-slate-100 text-slate-800'
                                        "
                                    >
                                        {{ form.status.replace('_', ' ') }}
                                    </Badge>
                                </div>
                                <div
                                    class="grid grid-cols-2 gap-2"
                                    role="radiogroup"
                                    aria-label="Estado de la convocatoria"
                                >
                                    <button
                                        v-for="st in statusOptions"
                                        :key="st.value"
                                        type="button"
                                        role="radio"
                                        :aria-checked="form.status === st.value"
                                        @click="form.status = st.value"
                                        class="flex h-10 cursor-pointer items-center justify-between rounded-xl border px-3 text-xs font-bold shadow-2xs transition-all duration-150 select-none focus:ring-2 focus:ring-rose-800/30 focus:outline-hidden"
                                        :class="
                                            form.status === st.value
                                                ? st.activeClass
                                                : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-900/60 dark:text-slate-400 dark:hover:border-slate-700 dark:hover:bg-slate-800/50'
                                        "
                                    >
                                        <div
                                            class="flex items-center gap-2 truncate"
                                        >
                                            <span
                                                class="size-2 shrink-0 rounded-full"
                                                :class="st.dotClass"
                                            />
                                            <span
                                                class="truncate font-semibold"
                                                >{{ st.label }}</span
                                            >
                                        </div>
                                        <div
                                            v-if="form.status === st.value"
                                            class="flex size-4.5 shrink-0 items-center justify-center rounded-full"
                                            :class="st.checkBadgeClass"
                                        >
                                            <Check class="size-3 stroke-[3]" />
                                        </div>
                                    </button>
                                </div>
                                <p
                                    class="text-[11px] font-medium text-slate-500"
                                >
                                    Selección táctil directa. Define el estado
                                    operativo del curso.
                                </p>
                                <span
                                    v-if="form.errors.status"
                                    class="block text-xs font-semibold text-red-600"
                                >
                                    {{ form.errors.status }}
                                </span>
                            </div>
                        </div>

                        <!-- Fila 2: Nombre Completo de la Capacitación -->
                        <div class="space-y-1.5">
                            <Label
                                for="title"
                                class="text-xs font-bold text-slate-800 dark:text-slate-200"
                            >
                                Nombre Oficial de la Capacitación
                                <span class="text-rose-700">*</span>
                            </Label>
                            <Input
                                id="title"
                                v-model="form.title"
                                placeholder="Ej: Especialización en Desarrollo Web Fullstack e Inteligencia Artificial"
                                class="h-10 rounded-xl border-slate-300 text-sm font-bold focus-visible:ring-rose-900"
                                required
                            />
                            <span
                                v-if="form.errors.title"
                                class="block text-xs font-semibold text-red-600"
                            >
                                {{ form.errors.title }}
                            </span>
                        </div>

                        <!-- Fila 3: Entidad Responsable + Ponente / Docente a Cargo -->
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="space-y-1.5">
                                <Label
                                    for="institution"
                                    class="flex items-center gap-1.5 text-xs font-bold text-slate-800 dark:text-slate-200"
                                >
                                    <Building2 class="size-3.5 text-rose-800" />
                                    <span
                                        >Entidad u Organización
                                        Responsable</span
                                    >
                                </Label>
                                <Input
                                    id="institution"
                                    v-model="form.institution"
                                    placeholder="Ej: Colegio de Ingenieros del Perú - CD Cusco"
                                    class="h-10 rounded-xl border-slate-300 text-xs focus-visible:ring-rose-900 sm:text-sm"
                                />
                                <span
                                    v-if="form.errors.institution"
                                    class="block text-xs font-semibold text-red-600"
                                >
                                    {{ form.errors.institution }}
                                </span>
                            </div>

                            <div class="space-y-1.5">
                                <Label
                                    for="instructor_name"
                                    class="flex items-center gap-1.5 text-xs font-bold text-slate-800 dark:text-slate-200"
                                >
                                    <GraduationCap
                                        class="size-3.5 text-rose-800"
                                    />
                                    <span
                                        >Ponente / Docente a Cargo
                                        <span class="text-rose-700"
                                            >*</span
                                        ></span
                                    >
                                </Label>
                                <Input
                                    id="instructor_name"
                                    v-model="form.instructor_name"
                                    placeholder="Ej: Ing. Carlos Alberto Quispe Pérez"
                                    class="h-10 rounded-xl border-slate-300 text-xs font-bold focus-visible:ring-rose-900 sm:text-sm"
                                    required
                                />
                                <span
                                    v-if="form.errors.instructor_name"
                                    class="block text-xs font-semibold text-red-600"
                                >
                                    {{ form.errors.instructor_name }}
                                </span>
                            </div>
                        </div>

                        <!-- Fila 4: Descripción y Temario -->
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between">
                                <Label
                                    for="description"
                                    class="text-xs font-bold text-slate-800 dark:text-slate-200"
                                >
                                    Descripción y Temario del Programa
                                </Label>
                                <span
                                    class="font-mono text-[10px] text-slate-500"
                                >
                                    {{ form.description.length }} caracteres
                                </span>
                            </div>
                            <textarea
                                id="description"
                                v-model="form.description"
                                rows="3"
                                class="w-full rounded-xl border border-slate-300 bg-white p-3 text-xs leading-relaxed font-normal shadow-2xs focus:border-rose-900 focus:ring-2 focus:ring-rose-900/20 focus:outline-hidden sm:text-sm dark:border-slate-700 dark:bg-slate-950"
                                placeholder="Describe brevemente los módulos temáticos, competencias a desarrollar y prerrequisitos del curso..."
                            ></textarea>
                            <span
                                v-if="form.errors.description"
                                class="block text-xs font-semibold text-red-600"
                            >
                                {{ form.errors.description }}
                            </span>
                        </div>
                    </CardContent>
                </Card>

                <!-- SECCIÓN 2: PLANIFICACIÓN, AFORO Y ACREDITACIÓN (REGLAMENTO UNSAAC) -->
                <Card
                    class="overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-xs dark:border-slate-800 dark:bg-slate-900"
                >
                    <CardHeader
                        class="border-b bg-slate-50/70 px-5 pb-3.5 sm:px-6 dark:bg-slate-900/80"
                    >
                        <div
                            class="flex items-center gap-2 text-xs font-black tracking-wider text-rose-900 uppercase dark:text-rose-400"
                        >
                            <Calendar class="size-4 text-rose-800" />
                            <span>2. Cronograma, Aforo y Acreditación</span>
                        </div>
                        <CardTitle
                            class="text-base font-bold text-slate-950 dark:text-white"
                        >
                            Parámetros Lectivos y Reglas de Evaluación
                        </CardTitle>
                        <CardDescription
                            class="text-xs text-slate-600 dark:text-slate-400"
                        >
                            Determinan la vigencia de la convocatoria, la matriz
                            de asistencia y el criterio de certificación.
                        </CardDescription>
                    </CardHeader>

                    <CardContent class="space-y-5 p-5 sm:p-6">
                        <!-- Fechas, Horas y Capacidad en 4 columnas -->
                        <div
                            class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4"
                        >
                            <div class="space-y-1.5">
                                <Label
                                    for="start_date"
                                    class="flex items-center gap-1 text-xs font-bold text-slate-800 dark:text-slate-200"
                                >
                                    <span>Fecha de Inicio</span>
                                    <span class="text-rose-700">*</span>
                                </Label>
                                <Input
                                    id="start_date"
                                    type="date"
                                    v-model="form.start_date"
                                    class="h-10 cursor-pointer rounded-xl border-slate-300 text-xs font-medium focus-visible:ring-rose-900 sm:text-sm"
                                    required
                                />
                                <span
                                    v-if="form.errors.start_date"
                                    class="block text-xs font-semibold text-red-600"
                                >
                                    {{ form.errors.start_date }}
                                </span>
                            </div>

                            <div class="space-y-1.5">
                                <Label
                                    for="end_date"
                                    class="flex items-center gap-1 text-xs font-bold text-slate-800 dark:text-slate-200"
                                >
                                    <span>Fecha de Fin</span>
                                    <span class="text-rose-700">*</span>
                                </Label>
                                <Input
                                    id="end_date"
                                    type="date"
                                    v-model="form.end_date"
                                    class="h-10 cursor-pointer rounded-xl border-slate-300 text-xs font-medium focus-visible:ring-rose-900 sm:text-sm"
                                    required
                                />
                                <span
                                    v-if="form.errors.end_date"
                                    class="block text-xs font-semibold text-red-600"
                                >
                                    {{ form.errors.end_date }}
                                </span>
                            </div>

                            <div class="space-y-1.5">
                                <Label
                                    for="hours"
                                    class="flex items-center gap-1 text-xs font-bold text-slate-800 dark:text-slate-200"
                                >
                                    <Clock class="size-3.5 text-amber-700" />
                                    <span>Horas Lectivas</span>
                                    <span class="text-rose-700">*</span>
                                </Label>
                                <Input
                                    id="hours"
                                    type="number"
                                    min="1"
                                    v-model.number="form.hours"
                                    placeholder="30"
                                    class="h-10 border-slate-300 font-bold focus-visible:ring-rose-900"
                                    required
                                />
                                <span
                                    v-if="form.errors.hours"
                                    class="block text-xs font-semibold text-red-600"
                                >
                                    {{ form.errors.hours }}
                                </span>
                            </div>

                            <div class="space-y-1.5">
                                <Label
                                    for="capacity"
                                    class="flex items-center gap-1 text-xs font-bold text-slate-800 dark:text-slate-200"
                                >
                                    <Users class="size-3.5 text-rose-800" />
                                    <span>Aforo / Vacantes</span>
                                    <span class="text-rose-700">*</span>
                                </Label>
                                <Input
                                    id="capacity"
                                    type="number"
                                    min="1"
                                    v-model.number="form.capacity"
                                    placeholder="30"
                                    class="h-10 border-slate-300 font-bold focus-visible:ring-rose-900"
                                    required
                                />
                                <span
                                    v-if="form.errors.capacity"
                                    class="block text-xs font-semibold text-red-600"
                                >
                                    {{ form.errors.capacity }}
                                </span>
                            </div>
                        </div>

                        <!-- Alerta visual si las fechas son incongruentes -->
                        <div
                            v-if="isDateOrderInvalid"
                            class="flex items-center gap-2 rounded-xl border border-rose-200 bg-rose-50 p-3 text-xs font-bold text-rose-900 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-200"
                        >
                            <AlertCircle
                                class="size-4 shrink-0 text-rose-700"
                            />
                            <span
                                >La fecha de fin no puede ser anterior a la
                                fecha de inicio.</span
                            >
                        </div>

                        <!-- Sesiones Programadas y Asistencia Mínima (2 columnas) -->
                        <div
                            class="grid grid-cols-1 gap-4 border-t border-slate-100 pt-3 sm:grid-cols-2 dark:border-slate-800"
                        >
                            <div class="space-y-1.5">
                                <Label
                                    for="total_sessions"
                                    class="flex items-center gap-1 text-xs font-bold text-slate-800 dark:text-slate-200"
                                >
                                    <BookOpen class="size-3.5 text-rose-800" />
                                    <span>N° Sesiones Programadas</span>
                                    <span class="text-rose-700">*</span>
                                </Label>
                                <Input
                                    id="total_sessions"
                                    type="number"
                                    min="1"
                                    max="200"
                                    v-model.number="form.total_sessions"
                                    placeholder="4"
                                    class="h-10 border-slate-300 font-bold focus-visible:ring-rose-900"
                                    required
                                />
                                <p class="text-[11px] text-slate-500">
                                    Total de clases para la matriz de asistencia
                                    y control QR.
                                </p>
                                <span
                                    v-if="form.errors.total_sessions"
                                    class="block text-xs font-semibold text-red-600"
                                >
                                    {{ form.errors.total_sessions }}
                                </span>
                            </div>

                            <div class="space-y-1.5">
                                <Label
                                    for="min_attendance_percentage"
                                    class="flex items-center gap-1 text-xs font-bold text-slate-800 dark:text-slate-200"
                                >
                                    <CheckCircle2
                                        class="size-3.5 text-emerald-700"
                                    />
                                    <span>Asistencia Mínima Exigida (%)</span>
                                    <span class="text-rose-700">*</span>
                                </Label>
                                <Input
                                    id="min_attendance_percentage"
                                    type="number"
                                    min="0"
                                    max="100"
                                    v-model.number="
                                        form.min_attendance_percentage
                                    "
                                    placeholder="75"
                                    class="h-10 border-slate-300 font-bold focus-visible:ring-rose-900"
                                    required
                                />
                                <p class="text-[11px] text-slate-500">
                                    Mínimo reglamentario UNSAAC: 75% de
                                    asistencia.
                                </p>
                                <span
                                    v-if="form.errors.min_attendance_percentage"
                                    class="block text-xs font-semibold text-red-600"
                                >
                                    {{ form.errors.min_attendance_percentage }}
                                </span>
                            </div>
                        </div>

                        <!-- Nota institucional reglamentaria -->
                        <div
                            class="flex items-start gap-2.5 rounded-xl border border-slate-200 bg-slate-50 p-3.5 text-xs text-slate-700 dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-300"
                        >
                            <ShieldCheck
                                class="mt-0.5 size-4.5 shrink-0 text-emerald-700 dark:text-emerald-400"
                            />
                            <div class="leading-relaxed">
                                <strong class="text-slate-900 dark:text-white"
                                    >Criterio Oficial de Acreditación
                                    UNSAAC:</strong
                                >
                                Al cerrar el acta académica, calificarán a
                                certificación únicamente los participantes con
                                asistencia ≥
                                {{ form.min_attendance_percentage || 75 }}% y
                                nota vigesimal ≥ 11.00.
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- BARRA DE ACCIÓN Y GUARDADO -->
                <div
                    class="flex flex-col items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:flex-row dark:border-slate-800 dark:bg-slate-900"
                >
                    <div
                        class="text-xs font-medium text-slate-600 dark:text-slate-400"
                    >
                        Capacitación oficial
                        <strong
                            class="font-mono text-rose-900 dark:text-rose-300"
                            >{{ course.code }}</strong
                        >
                    </div>

                    <div
                        class="flex w-full items-center justify-end gap-3 sm:w-auto"
                    >
                        <Button
                            as-child
                            variant="ghost"
                            size="sm"
                            class="cursor-pointer text-xs font-bold text-slate-700 hover:text-rose-900"
                        >
                            <Link :href="`/courses/${course.id}`"
                                >Cancelar</Link
                            >
                        </Button>
                        <Button
                            type="submit"
                            :disabled="form.processing || isDateOrderInvalid"
                            class="h-10 cursor-pointer bg-rose-900 px-6 text-xs font-bold text-white shadow-xs hover:bg-rose-950"
                        >
                            <Spinner
                                v-if="form.processing"
                                class="mr-2 size-4"
                            />
                            <Check v-else class="mr-2 size-4" />
                            <span>Actualizar Capacitación</span>
                        </Button>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
