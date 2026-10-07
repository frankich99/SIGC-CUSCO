<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { AlertCircle, ArrowLeft, Calendar, Check, GraduationCap, PlusCircle } from '@lucide/vue';
import { THEME_BUTTONS } from '@/lib/theme';
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

const form = useForm({
    code: props.suggestedCode,
    title: '',
    institution: '',
    description: '',
    instructor_id: props.instructors[0]?.id || '',
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

function instructorName(inst: Instructor): string {
    const parts = [inst.name, inst.paterno, inst.materno].filter(Boolean);
    return parts.length > 0 ? parts.join(' ') : inst.name;
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Nueva Capacitación - SIGC-CUSCO" />

        <div class="max-w-3xl mx-auto px-4 py-6 md:px-8 space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-bold tracking-tight text-neutral-900 dark:text-neutral-100 flex items-center gap-2">
                        <GraduationCap class="size-6 text-rose-900" />
                        Registrar Nueva Capacitación
                    </h1>
                    <p class="text-xs text-neutral-500 mt-1">
                        Define los parámetros, vacantes y el docente a cargo del programa.
                    </p>
                </div>
                <Button as-child variant="outline" size="sm" class="text-xs">
                    <Link href="/courses">
                        <ArrowLeft class="mr-1 size-3.5" />
                        Volver al Catálogo
                    </Link>
                </Button>
            </div>

            <!-- Form Card -->
            <Card>
                <CardHeader class="pb-4">
                    <CardTitle class="text-base font-semibold">Datos del Programa</CardTitle>
                    <CardDescription class="text-xs">
                        Todos los campos marcados con (*) son obligatorios para la auditoría y certificación.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submit" class="space-y-4">
                        <!-- Código y Estado -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <Label for="code" class="text-xs font-medium">Código del Curso *</Label>
                                <Input
                                    id="code"
                                    v-model="form.code"
                                    class="font-mono text-sm uppercase"
                                    placeholder="SIGC-2026-001"
                                    required
                                />
                                <span v-if="form.errors.code" class="text-xs text-red-600 font-medium">
                                    {{ form.errors.code }}
                                </span>
                            </div>

                            <div class="space-y-1.5">
                                <Label for="status" class="text-xs font-medium">Estado Inicial *</Label>
                                <select
                                    id="status"
                                    v-model="form.status"
                                    class="w-full h-9 rounded-md border border-neutral-300 bg-white px-3 py-1 text-sm shadow-xs focus:border-rose-900 focus:outline-hidden dark:border-neutral-700 dark:bg-neutral-900"
                                >
                                    <option v-for="st in statuses" :key="st.value" :value="st.value">
                                        {{ st.label }}
                                    </option>
                                </select>
                                <span v-if="form.errors.status" class="text-xs text-red-600 font-medium">
                                    {{ form.errors.status }}
                                </span>
                            </div>
                        </div>

                        <!-- Título -->
                        <div class="space-y-1.5">
                            <Label for="title" class="text-xs font-medium">Nombre de la Capacitación *</Label>
                            <Input
                                id="title"
                                v-model="form.title"
                                placeholder="Ej: Taller Especializado de Seguridad en Aplicaciones Web"
                                required
                            />
                            <span v-if="form.errors.title" class="text-xs text-red-600 font-medium">
                                {{ form.errors.title }}
                            </span>
                        </div>

                        <!-- Entidad Organizadora -->
                        <div class="space-y-1.5">
                            <Label for="institution" class="text-xs font-medium">Entidad u Organización Responsable</Label>
                            <Input
                                id="institution"
                                v-model="form.institution"
                                placeholder="Ej: Colegio de Ingenieros del Perú - CD Cusco, Cámara de Comercio, etc."
                            />
                            <span v-if="form.errors.institution" class="text-xs text-red-600 font-medium">
                                {{ form.errors.institution }}
                            </span>
                        </div>

                        <!-- Descripción -->
                        <div class="space-y-1.5">
                            <Label for="description" class="text-xs font-medium">Descripción y Contenido Temático</Label>
                            <textarea
                                id="description"
                                v-model="form.description"
                                rows="3"
                                class="w-full rounded-md border border-neutral-300 bg-white p-3 text-sm shadow-xs focus:border-rose-900 focus:outline-hidden dark:border-neutral-700 dark:bg-neutral-900"
                                placeholder="Resumen del temario, objetivos y competencias a desarrollar..."
                            ></textarea>
                            <span v-if="form.errors.description" class="text-xs text-red-600 font-medium">
                                {{ form.errors.description }}
                            </span>
                        </div>

                        <!-- Docente Responsable -->
                        <div class="space-y-1.5">
                            <Label for="instructor_id" class="text-xs font-medium">Docente / Instructor Responsable *</Label>
                            <select
                                id="instructor_id"
                                v-model="form.instructor_id"
                                class="w-full h-9 rounded-md border border-neutral-300 bg-white px-3 py-1 text-sm shadow-xs focus:border-rose-900 focus:outline-hidden dark:border-neutral-700 dark:bg-neutral-900"
                                required
                            >
                                <option disabled value="">Seleccione un docente...</option>
                                <option v-for="inst in instructors" :key="inst.id" :value="inst.id">
                                    {{ instructorName(inst) }} ({{ inst.email }})
                                </option>
                            </select>
                            <span v-if="form.errors.instructor_id" class="text-xs text-red-600 font-medium">
                                {{ form.errors.instructor_id }}
                            </span>
                        </div>

                        <!-- Fechas (Inicio y Fin) -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <Label for="start_date" class="text-xs font-medium">Fecha de Inicio *</Label>
                                <Input
                                    id="start_date"
                                    type="date"
                                    v-model="form.start_date"
                                    required
                                />
                                <span v-if="form.errors.start_date" class="text-xs text-red-600 font-medium">
                                    {{ form.errors.start_date }}
                                </span>
                            </div>

                            <div class="space-y-1.5">
                                <Label for="end_date" class="text-xs font-medium">Fecha de Fin *</Label>
                                <Input
                                    id="end_date"
                                    type="date"
                                    v-model="form.end_date"
                                    required
                                />
                                <span v-if="form.errors.end_date" class="text-xs text-red-600 font-medium">
                                    {{ form.errors.end_date }}
                                </span>
                            </div>
                        </div>

                        <!-- Alerta visual de fechas -->
                        <div v-if="isDateOrderInvalid" class="flex items-center gap-2 rounded-md bg-amber-50 p-2.5 text-xs text-amber-800 border border-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-800">
                            <AlertCircle class="size-4 shrink-0 text-amber-600" />
                            <span>La fecha de fin no puede ser anterior a la fecha de inicio.</span>
                        </div>

                        <!-- Horas Académicas y Límite de Vacantes -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <Label for="hours" class="text-xs font-medium">Horas Académicas *</Label>
                                <Input
                                    id="hours"
                                    type="number"
                                    min="1"
                                    v-model.number="form.hours"
                                    placeholder="40"
                                    required
                                />
                                <span v-if="form.errors.hours" class="text-xs text-red-600 font-medium">
                                    {{ form.errors.hours }}
                                </span>
                            </div>

                            <div class="space-y-1.5">
                                <Label for="capacity" class="text-xs font-medium">Límite de Vacantes / Cupo *</Label>
                                <Input
                                    id="capacity"
                                    type="number"
                                    min="1"
                                    v-model.number="form.capacity"
                                    placeholder="30"
                                    required
                                />
                                <span v-if="form.errors.capacity" class="text-xs text-red-600 font-medium">
                                    {{ form.errors.capacity }}
                                </span>
                            </div>
                        </div>

                        <!-- Buttons -->
                        <div class="flex items-center justify-end gap-3 pt-4 border-t dark:border-neutral-800">
                            <Button as-child variant="ghost" size="sm">
                                <Link href="/courses">Cancelar</Link>
                            </Button>
                            <Button
                                type="submit"
                                :disabled="form.processing || isDateOrderInvalid"
                                :class="['font-bold shadow-sm', THEME_BUTTONS.primary]"
                            >
                                <Spinner v-if="form.processing" class="mr-1.5 size-4" />
                                <Check v-else class="mr-1.5 size-4" />
                                Guardar Capacitación
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
