<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle, CardFooter } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Spinner } from '@/components/ui/spinner';
import {
    AlertCircle,
    ArrowLeft,
    Calendar,
    Clock,
    GraduationCap,
    BookOpen,
    FileText,
    Building2,
    Users,
    Eye,
    Sparkles,
    CheckCircle2,
    Info,
} from '@lucide/vue';
import { THEME_BUTTONS, THEME_BADGES } from '@/lib/theme';
import { formatDateRange, formatHours } from '@/lib/formatters';
import type { BreadcrumbItem } from '@/types';

interface Instructor {
    id: number;
    name: string;
    paterno?: string;
    materno?: string;
    email: string;
    role: string;
}

const props = defineProps<{
    instructors?: Instructor[];
    statuses: Array<{ value: string; label: string }>;
    suggestedCode: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Panel Principal', href: '/dashboard' },
    { title: 'Capacitaciones', href: '/courses' },
    { title: 'Nueva Capacitación', href: '/courses/create' },
];

const form = useForm({
    code: props.suggestedCode,
    title: '',
    institution: '',
    description: '',
    instructor_id: null as number | null,
    instructor_name: '',
    start_date: '',
    end_date: '',
    hours: 30,
    capacity: 30,
    status: 'abierto',
});

const isDateOrderInvalid = computed(() => {
    if (form.start_date && form.end_date) {
        return new Date(form.end_date) < new Date(form.start_date);
    }
    return false;
});

function submit() {
    form.post('/courses');
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Registrar Nueva Capacitación - SIGC-CUSCO" />

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8 space-y-8">
            <!-- HEADER DE PÁGINA INSTITUCIONAL -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200/90 dark:border-slate-800 pb-5">
                <div class="space-y-1">
                    <div class="flex items-center gap-2">
                        <div class="size-10 rounded-xl bg-gradient-to-tr from-[#701a31] to-[#800020] text-white flex items-center justify-center shadow-sm shrink-0">
                            <GraduationCap class="size-5 text-amber-300" />
                        </div>
                        <div>
                            <h1 class="text-xl sm:text-2xl font-black tracking-tight text-slate-950 dark:text-white">
                                Registrar Nueva Capacitación
                            </h1>
                            <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-400 font-medium">
                                Configuración integral del programa académico, calendario y aforo institucional.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2.5">
                    <Button as-child variant="outline" size="sm" class="text-xs font-bold border-slate-300 hover:text-rose-900 hover:bg-rose-50 cursor-pointer shadow-2xs">
                        <Link href="/courses">
                            <ArrowLeft class="mr-1.5 size-3.5 text-rose-800" />
                            Volver al Catálogo
                        </Link>
                    </Button>
                </div>
            </div>

            <!-- FORMULARIO EN 2 COLUMNAS BALANCEADAS -->
            <form @submit.prevent="submit" class="space-y-8">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-8 items-start">
                    
                    <!-- COLUMNA 1 (IZQUIERDA - 7 COLS): INFORMACIÓN ACADÉMICA Y CONTENIDO -->
                    <div class="lg:col-span-7 space-y-6">
                        
                        <!-- TARJETA: IDENTIFICACIÓN DEL PROGRAMA -->
                        <Card class="border border-slate-200 dark:border-slate-800 shadow-xs bg-white dark:bg-slate-900 overflow-hidden">
                            <CardHeader class="bg-slate-50/70 dark:bg-slate-900/80 border-b pb-3.5">
                                <div class="flex items-center gap-2 text-xs font-black text-rose-900 dark:text-rose-400 uppercase tracking-wider">
                                    <BookOpen class="size-4 text-rose-800" />
                                    <span>Identificación del Programa</span>
                                </div>
                                <CardTitle class="text-base font-bold text-slate-950 dark:text-white">
                                    Datos Principales de la Convocatoria
                                </CardTitle>
                                <CardDescription class="text-xs text-slate-600 dark:text-slate-400">
                                    El código y nombre oficial se reflejarán en los diplomas acreditados.
                                </CardDescription>
                            </CardHeader>

                            <CardContent class="p-5 sm:p-6 space-y-4">
                                <!-- Código y Estado Inicial en 2 columnas -->
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div class="space-y-1.5">
                                        <div class="flex items-center justify-between">
                                            <Label for="code" class="text-xs font-bold text-slate-800 dark:text-slate-200">
                                                Código del Curso <span class="text-rose-700">*</span>
                                            </Label>
                                            <span class="text-[10px] text-slate-500 font-mono">Único</span>
                                        </div>
                                        <Input
                                            id="code"
                                            v-model="form.code"
                                            class="font-mono text-sm uppercase font-bold tracking-wider h-10 border-slate-300 focus-visible:ring-rose-900 bg-slate-50/50 dark:bg-slate-950"
                                            placeholder="SIGC-2026-001"
                                            required
                                        />
                                        <span v-if="form.errors.code" class="text-xs text-red-600 font-semibold block">
                                            {{ form.errors.code }}
                                        </span>
                                    </div>

                                    <div class="space-y-1.5">
                                        <div class="flex items-center justify-between">
                                            <Label for="status" class="text-xs font-bold text-slate-800 dark:text-slate-200">
                                                Estado Inicial <span class="text-rose-700">*</span>
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

                                <!-- Nombre de la Capacitación -->
                                <div class="space-y-1.5">
                                    <Label for="title" class="text-xs font-bold text-slate-800 dark:text-slate-200">
                                        Nombre Completo de la Capacitación <span class="text-rose-700">*</span>
                                    </Label>
                                    <Input
                                        id="title"
                                        v-model="form.title"
                                        placeholder="Ej: Taller Especializado de Seguridad en Aplicaciones Web y Nube"
                                        class="h-10 text-sm font-medium border-slate-300 focus-visible:ring-rose-900"
                                        required
                                    />
                                    <span v-if="form.errors.title" class="text-xs text-red-600 font-semibold block">
                                        {{ form.errors.title }}
                                    </span>
                                </div>

                                <!-- Entidad u Organización Responsable -->
                                <div class="space-y-1.5">
                                    <div class="flex items-center justify-between">
                                        <Label for="institution" class="text-xs font-bold text-slate-800 dark:text-slate-200 flex items-center gap-1.5">
                                            <Building2 class="size-3.5 text-rose-800" />
                                            <span>Entidad u Organización Responsable</span>
                                        </Label>
                                        <span class="text-[10px] text-slate-500">Opcional</span>
                                    </div>
                                    <Input
                                        id="institution"
                                        v-model="form.institution"
                                        placeholder="Ej: Colegio de Ingenieros del Perú - CD Cusco, Cámara de Comercio, etc."
                                        class="h-10 text-xs sm:text-sm border-slate-300 focus-visible:ring-rose-900"
                                    />
                                    <span v-if="form.errors.institution" class="text-xs text-red-600 font-semibold block">
                                        {{ form.errors.institution }}
                                    </span>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- TARJETA: DESCRIPCIÓN Y TEMARIO -->
                        <Card class="border border-slate-200 dark:border-slate-800 shadow-xs bg-white dark:bg-slate-900 overflow-hidden">
                            <CardHeader class="bg-slate-50/70 dark:bg-slate-900/80 border-b pb-3.5">
                                <div class="flex items-center gap-2 text-xs font-black text-rose-900 dark:text-rose-400 uppercase tracking-wider">
                                    <FileText class="size-4 text-rose-800" />
                                    <span>Alcance Académico</span>
                                </div>
                                <CardTitle class="text-base font-bold text-slate-950 dark:text-white">
                                    Descripción y Temario del Programa
                                </CardTitle>
                                <CardDescription class="text-xs text-slate-600 dark:text-slate-400">
                                    Información visible para los participantes al momento de matricularse.
                                </CardDescription>
                            </CardHeader>

                            <CardContent class="p-5 sm:p-6 space-y-2">
                                <div class="space-y-1.5">
                                    <div class="flex items-center justify-between">
                                        <Label for="description" class="text-xs font-bold text-slate-800 dark:text-slate-200">
                                            Resumen de Objetivos y Temas a Tratar
                                        </Label>
                                        <span class="text-[10px] text-slate-500 font-mono">
                                            {{ form.description.length }} caracteres
                                        </span>
                                    </div>
                                    <textarea
                                        id="description"
                                        v-model="form.description"
                                        rows="4"
                                        class="w-full rounded-md border border-slate-300 bg-white p-3 text-xs sm:text-sm shadow-2xs focus:border-rose-900 focus:outline-hidden dark:border-slate-700 dark:bg-slate-950 leading-relaxed font-normal"
                                        placeholder="Describe las competencias profesionales, prerrequisitos, módulos temáticos y metodología de evaluación del curso..."
                                    ></textarea>
                                    <span v-if="form.errors.description" class="text-xs text-red-600 font-semibold block">
                                        {{ form.errors.description }}
                                    </span>
                                </div>
                            </CardContent>
                        </Card>

                    </div>

                    <!-- COLUMNA 2 (DERECHA - 5 COLS): GESTIÓN ACADÉMICA, AFORO Y VISTA PREVIA -->
                    <div class="lg:col-span-5 space-y-6">
                        
                        <!-- TARJETA: PONENTE / DOCENTE RESPONSABLE -->
                        <Card class="border border-slate-200 dark:border-slate-800 shadow-xs bg-white dark:bg-slate-900 overflow-hidden">
                            <CardHeader class="bg-slate-50/70 dark:bg-slate-900/80 border-b pb-3.5">
                                <div class="flex items-center gap-2 text-xs font-black text-rose-900 dark:text-rose-400 uppercase tracking-wider">
                                    <Users class="size-4 text-rose-800" />
                                    <span>Cuerpo Académico</span>
                                </div>
                                <CardTitle class="text-base font-bold text-slate-950 dark:text-white">
                                    Ponente / Docente a Cargo
                                </CardTitle>
                                <CardDescription class="text-xs text-slate-600 dark:text-slate-400">
                                    Nombre del ponente o docente que dictará la capacitación y figurará en las certificaciones oficiales.
                                </CardDescription>
                            </CardHeader>

                            <CardContent class="p-5 sm:p-6 space-y-3">
                                <div class="space-y-1.5">
                                    <Label for="instructor_name" class="text-xs font-bold text-slate-800 dark:text-slate-200">
                                        Nombre del Ponente / Docente <span class="text-rose-700">*</span>
                                    </Label>
                                    <Input
                                        id="instructor_name"
                                        v-model="form.instructor_name"
                                        placeholder="Ej: Ing. Carlos Alberto Quispe Pérez"
                                        class="h-10 text-xs sm:text-sm font-semibold border-slate-300 focus-visible:ring-rose-900 bg-white dark:bg-slate-950 text-slate-950 dark:text-white"
                                        required
                                    />
                                    <p class="text-[11px] text-slate-500 dark:text-slate-400">
                                        Ingresa directamente el nombre y apellidos del ponente o docente expositor.
                                    </p>
                                    <span v-if="form.errors.instructor_name" class="text-xs text-red-600 font-semibold block">
                                        {{ form.errors.instructor_name }}
                                    </span>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- TARJETA: CRONOGRAMA, HORAS Y AFORO -->
                        <Card class="border border-slate-200 dark:border-slate-800 shadow-xs bg-white dark:bg-slate-900 overflow-hidden">
                            <CardHeader class="bg-slate-50/70 dark:bg-slate-900/80 border-b pb-3.5">
                                <div class="flex items-center gap-2 text-xs font-black text-rose-900 dark:text-rose-400 uppercase tracking-wider">
                                    <Calendar class="size-4 text-rose-800" />
                                    <span>Planificación y Aforo</span>
                                </div>
                                <CardTitle class="text-base font-bold text-slate-950 dark:text-white">
                                    Cronograma y Capacidad
                                </CardTitle>
                                <CardDescription class="text-xs text-slate-600 dark:text-slate-400">
                                    Período oficial de clases, carga lectiva y vacantes máximas.
                                </CardDescription>
                            </CardHeader>

                            <CardContent class="p-5 sm:p-6 space-y-4">
                                <!-- Fechas Inicio y Fin -->
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
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
                                </div>

                                <!-- Alerta visual si las fechas son incongruentes -->
                                <div v-if="isDateOrderInvalid" class="flex items-center gap-2 rounded-xl bg-rose-50 p-3 text-xs text-rose-900 border border-rose-200 dark:bg-rose-950/40 dark:text-rose-200 dark:border-rose-900 font-bold">
                                    <AlertCircle class="size-4 shrink-0 text-rose-700" />
                                    <span>La fecha de fin no puede ser anterior a la fecha de inicio.</span>
                                </div>

                                <!-- Horas Lectivas y Límite de Vacantes -->
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 pt-1">
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
                            </CardContent>
                        </Card>

                        <!-- TARJETA: VISTA PREVIA EN VIVO DE LA CONVOCATORIA (MÁS VIDA Y FORMALIDAD) -->
                        <div class="p-5 rounded-2xl border-2 border-dashed border-rose-200 dark:border-rose-900/60 bg-gradient-to-b from-rose-50/40 via-white to-amber-50/20 dark:from-slate-900 dark:to-slate-950 space-y-3 shadow-2xs">
                            <div class="flex items-center justify-between text-xs font-black text-rose-900 dark:text-rose-300 uppercase tracking-wider">
                                <span class="flex items-center gap-1.5">
                                    <Eye class="size-3.5 text-rose-800" />
                                    Vista Previa en Vivo
                                </span>
                                <span class="font-mono text-[10px] bg-rose-100 text-rose-950 dark:bg-rose-950 dark:text-rose-200 px-2 py-0.5 rounded">
                                    {{ form.code || 'SIN CÓDIGO' }}
                                </span>
                            </div>

                            <h4 class="text-sm font-black text-slate-950 dark:text-white leading-snug line-clamp-2">
                                {{ form.title || 'Nombre de la capacitación a registrar...' }}
                            </h4>

                            <div class="text-[11px] text-slate-600 dark:text-slate-400 font-medium flex items-center justify-between gap-2">
                                <span class="truncate flex items-center gap-1.5">
                                    <Building2 class="size-3 text-slate-500 shrink-0" />
                                    {{ form.institution || 'Entidad convocante oficial' }}
                                </span>
                                <span v-if="form.instructor_name" class="text-rose-900 dark:text-rose-400 font-bold truncate text-right">
                                    {{ form.instructor_name }}
                                </span>
                            </div>

                            <div class="grid grid-cols-3 gap-2 pt-2 border-t border-slate-200/80 dark:border-slate-800 text-[11px] text-slate-700 dark:text-slate-300 font-bold">
                                <div>
                                    <span class="text-[9px] uppercase text-slate-500 block">Horas</span>
                                    {{ formatHours(form.hours || 0) }}
                                </div>
                                <div>
                                    <span class="text-[9px] uppercase text-slate-500 block">Vacantes</span>
                                    {{ form.capacity || 0 }} cupos
                                </div>
                                <div>
                                    <span class="text-[9px] uppercase text-slate-500 block">Estado</span>
                                    <span class="capitalize text-rose-900 dark:text-rose-400">{{ form.status }}</span>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- BARRA DE ACCIÓN Y GUARDADO (FULL WIDTH) -->
                <div class="flex flex-col sm:flex-row items-center justify-between gap-4 p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
                    <div class="text-xs text-slate-600 dark:text-slate-400 font-medium flex items-center gap-2">
                        <Info class="size-4 text-slate-400 shrink-0" />
                        <span>Verifica la información antes de guardar. El código se asignará de manera definitiva.</span>
                    </div>

                    <div class="flex items-center gap-3 w-full sm:w-auto justify-end">
                        <Button as-child variant="ghost" size="sm" class="text-xs font-bold text-slate-700 hover:text-rose-900 cursor-pointer">
                            <Link href="/courses">Cancelar y Volver</Link>
                        </Button>
                        <Button
                            type="submit"
                            :disabled="form.processing || isDateOrderInvalid"
                            class="bg-rose-900 hover:bg-rose-950 text-white font-bold text-xs h-11 px-7 shadow-md shadow-rose-900/20 cursor-pointer"
                        >
                            <Spinner v-if="form.processing" class="mr-2 size-4" />
                            <Check v-else class="mr-2 size-4" />
                            <span>Guardar Capacitación</span>
                        </Button>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
