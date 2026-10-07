<script setup lang="ts">
import { computed, ref, onMounted, onUnmounted } from 'vue';
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
    Check,
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
    Copy,
    Search,
    X,
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
    instructors: Instructor[];
    statuses: Array<{ value: string; label: string }>;
    suggestedCode: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Panel Principal', href: '/dashboard' },
    { title: 'Capacitaciones', href: '/courses' },
    { title: 'Nueva Capacitación', href: '/courses/create' },
];

function instructorName(inst: Instructor): string {
    const parts = [inst.name, inst.paterno, inst.materno].filter(Boolean);
    return parts.length > 0 ? parts.join(' ') : inst.name;
}

const defaultInst = props.instructors[0];
const defaultName = defaultInst ? instructorName(defaultInst) : '';

const form = useForm({
    code: props.suggestedCode,
    title: '',
    institution: '',
    description: '',
    instructor_id: defaultInst ? defaultInst.id : ('' as string | number),
    instructor_name: defaultName,
    start_date: '',
    end_date: '',
    hours: 30,
    capacity: 30,
    status: 'abierto',
});

const instructorQuery = ref(defaultName);
const isDropdownOpen = ref(false);
const copiedInstructor = ref(false);

const filteredInstructors = computed(() => {
    const q = instructorQuery.value.trim().toLowerCase();
    if (!q) return props.instructors;
    return props.instructors.filter((inst) => {
        const full = instructorName(inst).toLowerCase();
        const email = inst.email.toLowerCase();
        return full.includes(q) || email.includes(q);
    });
});

const selectedInstructor = computed(() => {
    if (!form.instructor_id) return null;
    return props.instructors.find((i) => i.id === Number(form.instructor_id));
});

function onInstructorInput(e: Event) {
    const val = (e.target as HTMLInputElement).value;
    instructorQuery.value = val;
    form.instructor_name = val;
    isDropdownOpen.value = true;

    // Verificar si coincide exactamente con un docente registrado
    const match = props.instructors.find(
        (i) => instructorName(i).toLowerCase() === val.trim().toLowerCase()
    );
    if (match) {
        form.instructor_id = match.id;
    } else {
        form.instructor_id = '';
    }
}

function selectInstructor(inst: Instructor) {
    const name = instructorName(inst);
    form.instructor_id = inst.id;
    form.instructor_name = name;
    instructorQuery.value = name;
    isDropdownOpen.value = false;
}

function useAsCustomInstructor() {
    form.instructor_id = '';
    form.instructor_name = instructorQuery.value.trim();
    isDropdownOpen.value = false;
}

function clearInstructor() {
    form.instructor_id = '';
    form.instructor_name = '';
    instructorQuery.value = '';
    isDropdownOpen.value = false;
}

async function copyInstructorText() {
    const text = form.instructor_name || instructorQuery.value;
    if (!text) return;
    try {
        await navigator.clipboard.writeText(text);
        copiedInstructor.value = true;
        setTimeout(() => {
            copiedInstructor.value = false;
        }, 2000);
    } catch {
        // Fallback
    }
}

onMounted(() => {
    const handleClickOutside = (e: MouseEvent) => {
        const el = document.getElementById('instructor_container');
        if (el && !el.contains(e.target as Node)) {
            isDropdownOpen.value = false;
        }
    };
    window.addEventListener('click', handleClickOutside);
    onUnmounted(() => {
        window.removeEventListener('click', handleClickOutside);
    });
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

        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-10 space-y-8">
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
                        <Card id="instructor_container" class="border border-slate-200 dark:border-slate-800 shadow-xs bg-white dark:bg-slate-900 overflow-visible relative">
                            <CardHeader class="bg-slate-50/70 dark:bg-slate-900/80 border-b pb-3.5 rounded-t-xl">
                                <div class="flex items-center gap-2 text-xs font-black text-rose-900 dark:text-rose-400 uppercase tracking-wider">
                                    <Users class="size-4 text-rose-800" />
                                    <span>Cuerpo Académico</span>
                                </div>
                                <CardTitle class="text-base font-bold text-slate-950 dark:text-white">
                                    Ponente / Docente a Cargo
                                </CardTitle>
                                <CardDescription class="text-xs text-slate-600 dark:text-slate-400">
                                    Ponente o docente responsable de impartir la capacitación y firmar certificados.
                                </CardDescription>
                            </CardHeader>

                            <CardContent class="p-5 sm:p-6 space-y-4">
                                <div class="space-y-1.5 relative">
                                    <div class="flex items-center justify-between">
                                        <Label for="instructor_input" class="text-xs font-bold text-slate-800 dark:text-slate-200">
                                            Nombre del Ponente / Docente <span class="text-rose-700">*</span>
                                        </Label>
                                        <div class="flex items-center gap-1.5">
                                            <button
                                                v-if="instructorQuery"
                                                type="button"
                                                class="text-[11px] font-bold text-slate-700 dark:text-slate-300 hover:text-rose-900 flex items-center gap-1 transition-colors px-2 py-0.5 rounded-md hover:bg-rose-50 dark:hover:bg-rose-950/40 cursor-pointer border border-slate-200 dark:border-slate-800"
                                                @click="copyInstructorText"
                                                title="Copiar nombre al portapapeles"
                                            >
                                                <Check v-if="copiedInstructor" class="size-3 text-emerald-600" />
                                                <Copy v-else class="size-3 text-slate-500" />
                                                <span>{{ copiedInstructor ? '¡Copiado!' : 'Copiar' }}</span>
                                            </button>
                                            <button
                                                v-if="instructorQuery"
                                                type="button"
                                                class="text-[11px] text-slate-400 hover:text-red-600 p-1 rounded hover:bg-red-50 dark:hover:bg-red-950/40 cursor-pointer"
                                                @click="clearInstructor"
                                                title="Limpiar campo"
                                            >
                                                <X class="size-3.5" />
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Input con capacidad completa de tipeo, copiar y pegar (Ctrl+V) -->
                                    <div class="relative">
                                        <Input
                                            id="instructor_input"
                                            :value="instructorQuery"
                                            @input="onInstructorInput"
                                            @focus="isDropdownOpen = true"
                                            placeholder="Escribe o pega el nombre del ponente o docente..."
                                            class="h-10 text-xs sm:text-sm font-semibold border-slate-300 focus-visible:ring-rose-900 pr-10 bg-white dark:bg-slate-950 text-slate-950 dark:text-white"
                                            autocomplete="off"
                                            required
                                        />
                                        <div class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-slate-400">
                                            <Search class="size-4" />
                                        </div>
                                    </div>

                                    <p class="text-[11px] text-slate-500 dark:text-slate-400">
                                        Puedes escribir o pegar con <strong>Ctrl + V</strong>. Selecciona un docente del sistema o déjalo como ponente externo.
                                    </p>

                                    <span v-if="form.errors.instructor_name || form.errors.instructor_id" class="text-xs text-red-600 font-semibold block">
                                        {{ form.errors.instructor_name || form.errors.instructor_id }}
                                    </span>

                                    <!-- Dropdown / Sugerencias de Autocompletado -->
                                    <div
                                        v-if="isDropdownOpen"
                                        class="absolute left-0 right-0 z-50 mt-1 max-h-60 overflow-y-auto rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-xl p-1.5 space-y-1"
                                    >
                                        <div class="px-2 py-1 text-[10px] font-black uppercase tracking-wider text-rose-900 dark:text-rose-400 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                                            <span>Ponentes / Docentes del Sistema</span>
                                            <span class="text-[9px] text-slate-400">Clic para asignar</span>
                                        </div>

                                        <div v-if="filteredInstructors.length > 0">
                                            <button
                                                v-for="inst in filteredInstructors"
                                                :key="inst.id"
                                                type="button"
                                                class="w-full text-left p-2 rounded-lg hover:bg-rose-50 dark:hover:bg-rose-950/40 flex items-center justify-between gap-2 transition-colors cursor-pointer group"
                                                @mousedown="selectInstructor(inst)"
                                            >
                                                <div class="min-w-0">
                                                    <div class="text-xs font-bold text-slate-900 dark:text-white group-hover:text-rose-900 flex items-center gap-1.5">
                                                        <span>{{ instructorName(inst) }}</span>
                                                        <CheckCircle2 v-if="Number(form.instructor_id) === inst.id" class="size-3 text-emerald-600" />
                                                    </div>
                                                    <div class="text-[11px] text-slate-500 truncate">
                                                        {{ inst.email }}
                                                    </div>
                                                </div>
                                                <Badge variant="outline" class="text-[10px] uppercase font-bold border-rose-200 text-rose-900 shrink-0">
                                                    {{ inst.role }}
                                                </Badge>
                                            </button>
                                        </div>

                                        <!-- Opción de Ponente Externo si escribió o pegó texto -->
                                        <div v-if="instructorQuery.trim().length > 0" class="pt-1 border-t border-slate-100 dark:border-slate-800">
                                            <button
                                                type="button"
                                                class="w-full text-left p-2 rounded-lg bg-amber-50/80 hover:bg-amber-100/90 dark:bg-amber-950/30 text-amber-950 dark:text-amber-200 text-xs font-bold flex items-center justify-between gap-2 transition-colors cursor-pointer"
                                                @mousedown="useAsCustomInstructor"
                                            >
                                                <div class="flex items-center gap-1.5 truncate">
                                                    <Sparkles class="size-3.5 text-amber-600 shrink-0" />
                                                    <span class="truncate">Asignar como Ponente Externo: "<strong>{{ instructorQuery.trim() }}</strong>"</span>
                                                </div>
                                                <Badge class="bg-amber-700 text-white text-[9px] uppercase shrink-0">
                                                    Externo
                                                </Badge>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Ficha Resumen del Ponente / Docente Seleccionado o Escrito -->
                                <div v-if="selectedInstructor || form.instructor_name" class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 flex items-center justify-between gap-3">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div class="size-10 rounded-lg bg-gradient-to-br from-rose-100 to-rose-200 dark:from-rose-950 dark:to-slate-900 text-rose-900 dark:text-rose-300 flex items-center justify-center font-black text-sm shrink-0 border border-rose-300/50">
                                            {{ (form.instructor_name || selectedInstructor?.name || 'P')[0] }}
                                        </div>
                                        <div class="min-w-0 flex-1 text-xs">
                                            <div class="font-bold text-slate-950 dark:text-white truncate">
                                                {{ form.instructor_name || instructorName(selectedInstructor!) }}
                                            </div>
                                            <div v-if="selectedInstructor?.email" class="text-[11px] text-slate-500 truncate">
                                                {{ selectedInstructor.email }}
                                            </div>
                                            <div v-else class="text-[11px] text-amber-700 dark:text-amber-400 font-medium">
                                                Ponente / Docente externo registrado para este curso
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-2 shrink-0">
                                        <Badge
                                            variant="outline"
                                            class="text-[10px] font-black uppercase px-2 py-0.5"
                                            :class="selectedInstructor ? 'border-rose-300 text-rose-900 bg-rose-50' : 'border-amber-300 text-amber-800 bg-amber-50'"
                                        >
                                            {{ selectedInstructor ? selectedInstructor.role : 'Ponente / Docente' }}
                                        </Badge>
                                        <button
                                            type="button"
                                            class="p-1 rounded-md hover:bg-slate-200 dark:hover:bg-slate-800 text-slate-600 transition-colors cursor-pointer"
                                            @click="copyInstructorText"
                                            title="Copiar nombre"
                                        >
                                            <Check v-if="copiedInstructor" class="size-3.5 text-emerald-600" />
                                            <Copy v-else class="size-3.5" />
                                        </button>
                                    </div>
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

                            <div class="text-[11px] text-slate-600 dark:text-slate-400 font-medium flex items-center gap-1.5">
                                <Building2 class="size-3 text-slate-500 shrink-0" />
                                <span class="truncate">{{ form.institution || 'Entidad convocante oficial' }}</span>
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
