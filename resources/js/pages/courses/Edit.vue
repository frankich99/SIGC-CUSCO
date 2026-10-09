<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
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
    instructor_name: props.course.instructor_name || (props.course as any).instructor_display_name || '',
    start_date: props.course.start_date,
    end_date: props.course.end_date,
    hours: props.course.hours,
    total_sessions: props.course.total_sessions ?? 4,
    min_attendance_percentage: props.course.min_attendance_percentage ?? 75,
    capacity: props.course.capacity,
    status: props.course.status,
});

const isDateOrderInvalid = computed(() => {
    if (form.start_date && form.end_date) {
        return new Date(form.end_date) < new Date(form.start_date);
    }
    return false;
});

function submit() {
    form.put(`/courses/${props.course.id}`, {
        onError: () => {
            notify.error('Errores al actualizar', 'Revise los campos requeridos marcados en rojo.');
        },
    });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`Editar Capacitación: ${course.code} - SIGC-CUSCO`" />

        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8 space-y-6">
            <!-- CABECERA INSTITUCIONAL GRANATE CUSCO -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200/90 dark:border-slate-800 pb-5">
                <div class="space-y-1">
                    <div class="flex items-center gap-2">
                        <div class="size-10 rounded-xl bg-gradient-to-tr from-[#701a31] to-[#800020] text-white flex items-center justify-center shadow-xs shrink-0 ring-1 ring-rose-950/20">
                            <GraduationCap class="size-5 text-amber-300" />
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h1 class="text-xl sm:text-2xl font-black tracking-tight text-slate-950 dark:text-white">
                                    Editar Capacitación
                                </h1>
                                <span class="font-mono text-xs font-black px-2 py-0.5 rounded bg-rose-100 text-rose-950 dark:bg-rose-950 dark:text-rose-200 border border-rose-300 dark:border-rose-800">
                                    {{ course.code }}
                                </span>
                            </div>
                            <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-400 font-medium">
                                Modificación de parámetros académicos, fechas y aforo del programa.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2.5">
                    <Button as-child variant="outline" size="sm" class="text-xs font-bold border-slate-300 hover:text-rose-900 hover:bg-rose-50 cursor-pointer shadow-2xs">
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
                <Card class="border border-slate-200/90 dark:border-slate-800 shadow-xs bg-white dark:bg-slate-900 rounded-2xl overflow-hidden">
                    <CardHeader class="bg-slate-50/70 dark:bg-slate-900/80 border-b pb-3.5 px-5 sm:px-6">
                        <div class="flex items-center gap-2 text-xs font-black text-rose-900 dark:text-rose-400 uppercase tracking-wider">
                            <BookOpen class="size-4 text-rose-800" />
                            <span>1. Identificación y Convocatoria</span>
                        </div>
                        <CardTitle class="text-base font-bold text-slate-950 dark:text-white">
                            Datos Generales del Programa
                        </CardTitle>
                        <CardDescription class="text-xs text-slate-600 dark:text-slate-400">
                            El código oficial es inmutable para preservar la validez legal de las actas y certificados emitidos.
                        </CardDescription>
                    </CardHeader>

                    <CardContent class="p-5 sm:p-6 space-y-5">
                        <!-- Fila 1: Código Oficial (Inmutable) + Estado -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <div class="flex items-center justify-between">
                                    <Label for="code" class="text-xs font-bold text-slate-800 dark:text-slate-200 flex items-center gap-1.5">
                                        <Lock class="size-3.5 text-rose-800" />
                                        <span>Código Oficial</span>
                                    </Label>
                                    <span class="text-[10px] font-bold text-slate-600 dark:text-slate-300 bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded-full border border-slate-300 dark:border-slate-700 flex items-center gap-1">
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
                                        class="font-mono text-sm font-black tracking-wider h-10 border-slate-300 dark:border-slate-700 bg-slate-100/90 dark:bg-slate-800/80 text-rose-950 dark:text-rose-200 cursor-not-allowed select-none pl-3 pr-10"
                                    />
                                    <div class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">
                                        <Lock class="size-4" />
                                    </div>
                                </div>
                                <p class="text-[11px] text-slate-500 font-medium">
                                    Código institucional único registrado.
                                </p>
                            </div>

                            <div class="space-y-1.5">
                                <div class="flex items-center justify-between">
                                    <Label for="status" class="text-xs font-bold text-slate-800 dark:text-slate-200">
                                        Estado de la Convocatoria <span class="text-rose-700">*</span>
                                    </Label>
                                    <Badge
                                        class="text-[10px] font-bold capitalize py-0.5 px-2"
                                        :class="THEME_BADGES[form.status as keyof typeof THEME_BADGES] || 'bg-slate-100 text-slate-800'"
                                    >
                                        {{ form.status }}
                                    </Badge>
                                </div>
                                <select
                                    id="status"
                                    v-model="form.status"
                                    class="w-full h-10 rounded-md border border-slate-300 bg-white px-3 py-1 text-xs sm:text-sm font-semibold shadow-2xs focus:border-rose-900 focus:outline-hidden dark:border-slate-700 dark:bg-slate-950"
                                >
                                    <option v-for="st in statuses" :key="st.value" :value="st.value">
                                        {{ st.label }}
                                    </option>
                                </select>
                                <span v-if="form.errors.status" class="text-xs text-red-600 font-semibold block">
                                    {{ form.errors.status }}
                                </span>
                            </div>
                        </div>

                        <!-- Fila 2: Nombre Completo de la Capacitación -->
                        <div class="space-y-1.5">
                            <Label for="title" class="text-xs font-bold text-slate-800 dark:text-slate-200">
                                Nombre Oficial de la Capacitación <span class="text-rose-700">*</span>
                            </Label>
                            <Input
                                id="title"
                                v-model="form.title"
                                placeholder="Ej: Especialización en Desarrollo Web Fullstack e Inteligencia Artificial"
                                class="h-10 text-sm font-bold border-slate-300 focus-visible:ring-rose-900"
                                required
                            />
                            <span v-if="form.errors.title" class="text-xs text-red-600 font-semibold block">
                                {{ form.errors.title }}
                            </span>
                        </div>

                        <!-- Fila 3: Entidad Responsable + Ponente / Docente a Cargo -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <Label for="institution" class="text-xs font-bold text-slate-800 dark:text-slate-200 flex items-center gap-1.5">
                                    <Building2 class="size-3.5 text-rose-800" />
                                    <span>Entidad u Organización Responsable</span>
                                </Label>
                                <Input
                                    id="institution"
                                    v-model="form.institution"
                                    placeholder="Ej: Colegio de Ingenieros del Perú - CD Cusco"
                                    class="h-10 text-xs sm:text-sm border-slate-300 focus-visible:ring-rose-900"
                                />
                                <span v-if="form.errors.institution" class="text-xs text-red-600 font-semibold block">
                                    {{ form.errors.institution }}
                                </span>
                            </div>

                            <div class="space-y-1.5">
                                <Label for="instructor_name" class="text-xs font-bold text-slate-800 dark:text-slate-200 flex items-center gap-1.5">
                                    <GraduationCap class="size-3.5 text-rose-800" />
                                    <span>Ponente / Docente a Cargo <span class="text-rose-700">*</span></span>
                                </Label>
                                <Input
                                    id="instructor_name"
                                    v-model="form.instructor_name"
                                    placeholder="Ej: Ing. Carlos Alberto Quispe Pérez"
                                    class="h-10 text-xs sm:text-sm font-bold border-slate-300 focus-visible:ring-rose-900"
                                    required
                                />
                                <span v-if="form.errors.instructor_name" class="text-xs text-red-600 font-semibold block">
                                    {{ form.errors.instructor_name }}
                                </span>
                            </div>
                        </div>

                        <!-- Fila 4: Descripción y Temario -->
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between">
                                <Label for="description" class="text-xs font-bold text-slate-800 dark:text-slate-200">
                                    Descripción y Temario del Programa
                                </Label>
                                <span class="text-[10px] text-slate-500 font-mono">
                                    {{ form.description.length }} caracteres
                                </span>
                            </div>
                            <textarea
                                id="description"
                                v-model="form.description"
                                rows="3"
                                class="w-full rounded-md border border-slate-300 bg-white p-3 text-xs sm:text-sm shadow-2xs focus:border-rose-900 focus:outline-hidden dark:border-slate-700 dark:bg-slate-950 leading-relaxed font-normal"
                                placeholder="Describe brevemente los módulos temáticos, competencias a desarrollar y prerrequisitos del curso..."
                            ></textarea>
                            <span v-if="form.errors.description" class="text-xs text-red-600 font-semibold block">
                                {{ form.errors.description }}
                            </span>
                        </div>
                    </CardContent>
                </Card>

                <!-- SECCIÓN 2: PLANIFICACIÓN, AFORO Y ACREDITACIÓN (REGLAMENTO UNSAAC) -->
                <Card class="border border-slate-200/90 dark:border-slate-800 shadow-xs bg-white dark:bg-slate-900 rounded-2xl overflow-hidden">
                    <CardHeader class="bg-slate-50/70 dark:bg-slate-900/80 border-b pb-3.5 px-5 sm:px-6">
                        <div class="flex items-center gap-2 text-xs font-black text-rose-900 dark:text-rose-400 uppercase tracking-wider">
                            <Calendar class="size-4 text-rose-800" />
                            <span>2. Cronograma, Aforo y Acreditación</span>
                        </div>
                        <CardTitle class="text-base font-bold text-slate-950 dark:text-white">
                            Parámetros Lectivos y Reglas de Evaluación
                        </CardTitle>
                        <CardDescription class="text-xs text-slate-600 dark:text-slate-400">
                            Determinan la vigencia de la convocatoria, la matriz de asistencia y el criterio de certificación.
                        </CardDescription>
                    </CardHeader>

                    <CardContent class="p-5 sm:p-6 space-y-5">
                        <!-- Fechas, Horas y Capacidad en 4 columnas -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                            <div class="space-y-1.5">
                                <Label for="start_date" class="text-xs font-bold text-slate-800 dark:text-slate-200 flex items-center gap-1">
                                    <span>Fecha de Inicio</span> <span class="text-rose-700">*</span>
                                </Label>
                                <Input
                                    id="start_date"
                                    type="date"
                                    v-model="form.start_date"
                                    class="h-10 text-xs sm:text-sm font-medium border-slate-300 focus-visible:ring-rose-900"
                                    required
                                />
                                <span v-if="form.errors.start_date" class="text-xs text-red-600 font-semibold block">
                                    {{ form.errors.start_date }}
                                </span>
                            </div>

                            <div class="space-y-1.5">
                                <Label for="end_date" class="text-xs font-bold text-slate-800 dark:text-slate-200 flex items-center gap-1">
                                    <span>Fecha de Fin</span> <span class="text-rose-700">*</span>
                                </Label>
                                <Input
                                    id="end_date"
                                    type="date"
                                    v-model="form.end_date"
                                    class="h-10 text-xs sm:text-sm font-medium border-slate-300 focus-visible:ring-rose-900"
                                    required
                                />
                                <span v-if="form.errors.end_date" class="text-xs text-red-600 font-semibold block">
                                    {{ form.errors.end_date }}
                                </span>
                            </div>

                            <div class="space-y-1.5">
                                <Label for="hours" class="text-xs font-bold text-slate-800 dark:text-slate-200 flex items-center gap-1">
                                    <Clock class="size-3.5 text-amber-700" />
                                    <span>Horas Lectivas</span> <span class="text-rose-700">*</span>
                                </Label>
                                <Input
                                    id="hours"
                                    type="number"
                                    min="1"
                                    v-model.number="form.hours"
                                    placeholder="30"
                                    class="h-10 font-bold border-slate-300 focus-visible:ring-rose-900"
                                    required
                                />
                                <span v-if="form.errors.hours" class="text-xs text-red-600 font-semibold block">
                                    {{ form.errors.hours }}
                                </span>
                            </div>

                            <div class="space-y-1.5">
                                <Label for="capacity" class="text-xs font-bold text-slate-800 dark:text-slate-200 flex items-center gap-1">
                                    <Users class="size-3.5 text-rose-800" />
                                    <span>Aforo / Vacantes</span> <span class="text-rose-700">*</span>
                                </Label>
                                <Input
                                    id="capacity"
                                    type="number"
                                    min="1"
                                    v-model.number="form.capacity"
                                    placeholder="30"
                                    class="h-10 font-bold border-slate-300 focus-visible:ring-rose-900"
                                    required
                                />
                                <span v-if="form.errors.capacity" class="text-xs text-red-600 font-semibold block">
                                    {{ form.errors.capacity }}
                                </span>
                            </div>
                        </div>

                        <!-- Alerta visual si las fechas son incongruentes -->
                        <div v-if="isDateOrderInvalid" class="flex items-center gap-2 rounded-xl bg-rose-50 p-3 text-xs text-rose-900 border border-rose-200 dark:bg-rose-950/40 dark:text-rose-200 dark:border-rose-900 font-bold">
                            <AlertCircle class="size-4 shrink-0 text-rose-700" />
                            <span>La fecha de fin no puede ser anterior a la fecha de inicio.</span>
                        </div>

                        <!-- Sesiones Programadas y Asistencia Mínima (2 columnas) -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-3 border-t border-slate-100 dark:border-slate-800">
                            <div class="space-y-1.5">
                                <Label for="total_sessions" class="text-xs font-bold text-slate-800 dark:text-slate-200 flex items-center gap-1">
                                    <BookOpen class="size-3.5 text-rose-800" />
                                    <span>N° Sesiones Programadas</span> <span class="text-rose-700">*</span>
                                </Label>
                                <Input
                                    id="total_sessions"
                                    type="number"
                                    min="1"
                                    max="200"
                                    v-model.number="form.total_sessions"
                                    placeholder="4"
                                    class="h-10 font-bold border-slate-300 focus-visible:ring-rose-900"
                                    required
                                />
                                <p class="text-[11px] text-slate-500">
                                    Total de clases para la matriz de asistencia y control QR.
                                </p>
                                <span v-if="form.errors.total_sessions" class="text-xs text-red-600 font-semibold block">
                                    {{ form.errors.total_sessions }}
                                </span>
                            </div>

                            <div class="space-y-1.5">
                                <Label for="min_attendance_percentage" class="text-xs font-bold text-slate-800 dark:text-slate-200 flex items-center gap-1">
                                    <CheckCircle2 class="size-3.5 text-emerald-700" />
                                    <span>Asistencia Mínima Exigida (%)</span> <span class="text-rose-700">*</span>
                                </Label>
                                <Input
                                    id="min_attendance_percentage"
                                    type="number"
                                    min="0"
                                    max="100"
                                    v-model.number="form.min_attendance_percentage"
                                    placeholder="75"
                                    class="h-10 font-bold border-slate-300 focus-visible:ring-rose-900"
                                    required
                                />
                                <p class="text-[11px] text-slate-500">
                                    Mínimo reglamentario UNSAAC: 75% de asistencia.
                                </p>
                                <span v-if="form.errors.min_attendance_percentage" class="text-xs text-red-600 font-semibold block">
                                    {{ form.errors.min_attendance_percentage }}
                                </span>
                            </div>
                        </div>

                        <!-- Nota institucional reglamentaria -->
                        <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 text-xs text-slate-700 dark:text-slate-300 flex items-start gap-2.5">
                            <ShieldCheck class="size-4.5 text-emerald-700 dark:text-emerald-400 shrink-0 mt-0.5" />
                            <div class="leading-relaxed">
                                <strong class="text-slate-900 dark:text-white">Criterio Oficial de Acreditación UNSAAC:</strong>
                                Al cerrar el acta académica, calificarán a certificación únicamente los participantes con asistencia $\ge {{ form.min_attendance_percentage || 75 }}%$ y nota vigesimal $\ge 11.00$.
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- BARRA DE ACCIÓN Y GUARDADO -->
                <div class="flex flex-col sm:flex-row items-center justify-between gap-4 p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
                    <div class="text-xs text-slate-600 dark:text-slate-400 font-medium">
                        Capacitación oficial <strong class="font-mono text-rose-900 dark:text-rose-300">{{ course.code }}</strong>
                    </div>

                    <div class="flex items-center gap-3 w-full sm:w-auto justify-end">
                        <Button as-child variant="ghost" size="sm" class="text-xs font-bold text-slate-700 hover:text-rose-900 cursor-pointer">
                            <Link :href="`/courses/${course.id}`">Cancelar</Link>
                        </Button>
                        <Button
                            type="submit"
                            :disabled="form.processing || isDateOrderInvalid"
                            class="bg-rose-900 hover:bg-rose-950 text-white font-bold text-xs h-10 px-6 shadow-xs cursor-pointer"
                        >
                            <Spinner v-if="form.processing" class="mr-2 size-4" />
                            <Check v-else class="mr-2 size-4" />
                            <span>Actualizar Capacitación</span>
                        </Button>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
