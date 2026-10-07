<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formatDate, formatDateRange, formatHours } from '@/lib/formatters';
import {
    Calendar,
    Clock,
    GraduationCap,
    Users,
    User,
    ArrowLeft,
    Pencil,
    Trash2,
    CheckCircle2,
    QrCode,
    Award,
    FileCheck,
    Search,
    AlertCircle,
    UserCheck,
    Building2,
} from '@lucide/vue';
import type { BreadcrumbItem } from '@/types';

interface Instructor {
    id: number;
    name: string;
    paterno?: string;
    materno?: string;
    email: string;
    role: string;
}

interface CourseDetail {
    id: number;
    code: string;
    title: string;
    institution?: string;
    description?: string;
    start_date: string;
    end_date: string;
    hours: number;
    capacity: number;
    status: 'abierto' | 'en_curso' | 'concluido' | 'cancelado';
    instructor?: Instructor;
}

const props = defineProps<{
    course: CourseDetail;
    can: {
        update: boolean;
        delete: boolean;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Panel Principal', href: '/dashboard' },
    { title: 'Capacitaciones', href: '/courses' },
    { title: props.course.title, href: `/courses/${props.course.id}` },
];

// Interactive DNI enrollment simulation
const enrollDni = ref('');
const validatingDni = ref(false);
const dniError = ref<string | null>(null);
const enrolledPerson = ref<{
    dni: string;
    nombre_completo: string;
    codigo_verificacion?: string;
} | null>(null);
const enrollmentSuccess = ref(false);

async function consultAndEnroll() {
    dniError.value = null;
    enrolledPerson.value = null;
    enrollmentSuccess.value = false;

    const clean = enrollDni.value.trim();
    if (!/^\d{8}$/.test(clean)) {
        dniError.value = 'El DNI debe tener exactamente 8 dígitos numéricos.';
        return;
    }

    validatingDni.value = true;
    try {
        const response = await fetch(`/api/dni/${clean}`, {
            headers: { Accept: 'application/json' },
        });
        const data = await response.json();

        if (response.ok && data.success && data.data) {
            enrolledPerson.value = data.data;
        } else {
            dniError.value = data.message || 'No se encontró el DNI en RENIEC.';
        }
    } catch {
        dniError.value = 'Error al consultar servicio de DNI.';
    } finally {
        validatingDni.value = false;
    }
}

function confirmEnrollment() {
    enrollmentSuccess.value = true;
}

function deleteCourse() {
    if (confirm(`¿Estás seguro de eliminar "${props.course.title}"?`)) {
        router.delete(`/courses/${props.course.id}`);
    }
}

function getStatusBadge(status: string) {
    switch (status) {
        case 'abierto':
            return {
                label: 'Convocatoria Abierta',
                class: 'bg-emerald-50 text-emerald-700 border-emerald-300 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800',
            };
        case 'en_curso':
            return {
                label: 'En curso',
                class: 'bg-blue-50 text-blue-700 border-blue-300 dark:bg-blue-950/40 dark:text-blue-300 dark:border-blue-800',
            };
        case 'concluido':
            return {
                label: 'Concluido',
                class: 'bg-neutral-100 text-neutral-700 border-neutral-300 dark:bg-neutral-800 dark:text-neutral-300 dark:border-neutral-700',
            };
        default:
            return {
                label: status,
                class: 'bg-neutral-100 text-neutral-600 border-neutral-200',
            };
    }
}

function instructorName(inst?: Instructor): string {
    if (!inst) return 'Docente no asignado';
    const parts = [inst.name, inst.paterno, inst.materno].filter(Boolean);
    return parts.length > 0 ? parts.join(' ') : inst.name;
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`${course.title} - SIGC-CUSCO`" />

        <div class="max-w-5xl mx-auto px-4 py-6 md:px-8 space-y-6">
            <!-- Header with actions -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b pb-5 dark:border-neutral-800">
                <div class="space-y-2">
                    <div class="flex items-center gap-2">
                        <Button as-child variant="ghost" size="sm" class="-ml-2 text-xs text-neutral-500">
                            <Link href="/courses">
                                <ArrowLeft class="mr-1 size-3.5" />
                                Catálogo
                            </Link>
                        </Button>
                        <span class="font-mono text-xs font-semibold text-emerald-700 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/60 px-2 py-0.5 rounded border border-emerald-200 dark:border-emerald-800">
                            {{ course.code }}
                        </span>
                        <span :class="['text-xs font-medium px-2.5 py-0.5 rounded-full border', getStatusBadge(course.status).class]">
                            {{ getStatusBadge(course.status).label }}
                        </span>
                    </div>
                    <h1 class="text-2xl font-bold tracking-tight text-neutral-900 dark:text-neutral-100">
                        {{ course.title }}
                    </h1>
                    <div
                        v-if="course.institution"
                        class="flex items-center gap-1.5 text-xs font-medium text-emerald-800 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/60 px-2.5 py-1 rounded-md border border-emerald-200/80 dark:border-emerald-800/60 w-fit"
                    >
                        <Building2 class="size-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
                        <span>Entidad Organizadora: <strong>{{ course.institution }}</strong></span>
                    </div>
                </div>

                <div v-if="can.update || can.delete" class="flex items-center gap-2">
                    <Button v-if="can.update" as-child variant="outline" size="sm" class="text-xs">
                        <Link :href="`/courses/${course.id}/edit`">
                            <Pencil class="mr-1.5 size-3.5" />
                            Editar
                        </Link>
                    </Button>
                    <Button
                        v-if="can.delete"
                        variant="outline"
                        size="sm"
                        class="text-xs text-red-600 hover:text-red-700 hover:bg-red-50 dark:hover:bg-red-950/40"
                        @click="deleteCourse"
                    >
                        <Trash2 class="mr-1.5 size-3.5" />
                        Eliminar
                    </Button>
                </div>
            </div>

            <!-- Content Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left 2 Cols: Details & Description -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- General Details Card -->
                    <Card>
                        <CardHeader class="pb-3">
                            <CardTitle class="text-base font-semibold">Información General del Programa</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4 text-sm">
                            <p class="text-neutral-700 dark:text-neutral-300 leading-relaxed whitespace-pre-line">
                                {{ course.description || 'No hay descripción detallada registrada para esta capacitación.' }}
                            </p>

                            <!-- Key Metrics -->
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 pt-2">
                                <div class="rounded-lg border p-3 dark:border-neutral-800 bg-neutral-50/50 dark:bg-neutral-900/50">
                                    <div class="flex items-center gap-1.5 text-xs text-neutral-500 font-medium">
                                        <Clock class="size-4 text-emerald-600" />
                                        Horas Académicas
                                    </div>
                                    <div class="text-lg font-bold text-neutral-900 dark:text-neutral-100 mt-1">
                                        {{ course.hours }} hrs
                                    </div>
                                </div>

                                <div class="rounded-lg border p-3 dark:border-neutral-800 bg-neutral-50/50 dark:bg-neutral-900/50">
                                    <div class="flex items-center gap-1.5 text-xs text-neutral-500 font-medium">
                                        <Users class="size-4 text-emerald-600" />
                                        Vacantes Máximas
                                    </div>
                                    <div class="text-lg font-bold text-neutral-900 dark:text-neutral-100 mt-1">
                                        {{ course.capacity }} cupos
                                    </div>
                                </div>

                                <div class="rounded-lg border p-3 border-slate-200 dark:border-slate-800 bg-slate-50/80 dark:bg-slate-900/60">
                                    <div class="flex items-center gap-1.5 text-xs text-slate-700 dark:text-slate-300 font-bold">
                                        <Calendar class="size-4 text-emerald-600" />
                                        Cronograma Oficial
                                    </div>
                                    <div class="text-xs font-bold text-slate-900 dark:text-slate-100 mt-1.5">
                                        {{ formatDate(course.start_date, 'compact') }} al {{ formatDate(course.end_date, 'compact') }}
                                    </div>
                                    <div class="text-[11px] font-semibold text-emerald-700 dark:text-emerald-400">
                                        {{ formatDateRange(course.start_date, course.end_date, 'medium') }}
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Ciclo del Sistema (Scope SIGC-CUSCO) -->
                    <Card>
                        <CardHeader class="pb-3">
                            <CardTitle class="text-base font-semibold">Ciclo Formativo SIGC-CUSCO</CardTitle>
                            <CardDescription class="text-xs">
                                Funcionalidades activas para este curso según la especificación del informe
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                                <div class="p-3 rounded-lg border dark:border-neutral-800 space-y-1">
                                    <div class="flex items-center gap-1.5 font-semibold text-emerald-700 dark:text-emerald-400">
                                        <CheckCircle2 class="size-4" />
                                        1. Matrícula DNI
                                    </div>
                                    <p class="text-neutral-500">Validación de identidad con RENIEC en tiempo real y cupo protegido.</p>
                                </div>

                                <div class="p-3 rounded-lg border dark:border-neutral-800 space-y-1">
                                    <div class="flex items-center gap-1.5 font-semibold text-blue-700 dark:text-blue-400">
                                        <QrCode class="size-4" />
                                        2. Asistencia QR
                                    </div>
                                    <p class="text-neutral-500">Escaneo de código QR por sesión proyectado por el docente.</p>
                                </div>

                                <div class="p-3 rounded-lg border dark:border-neutral-800 space-y-1">
                                    <div class="flex items-center gap-1.5 font-semibold text-purple-700 dark:text-purple-400">
                                        <Award class="size-4" />
                                        3. Certificado PDF
                                    </div>
                                    <p class="text-neutral-500">Emisión digital con QR de validación pública institucional.</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Right Col: Instructor & DNI Enrollment Tool -->
                <div class="space-y-6">
                    <!-- Docente Responsable -->
                    <Card>
                        <CardHeader class="pb-3">
                            <CardTitle class="text-xs font-semibold uppercase tracking-wider text-neutral-500">
                                Docente / Instructor
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-3 text-sm">
                            <div class="flex items-center gap-3">
                                <div class="size-11 rounded-full bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 flex items-center justify-center font-bold text-base">
                                    {{ (course.instructor?.name || 'D')[0] }}
                                </div>
                                <div>
                                    <div class="font-semibold text-neutral-900 dark:text-neutral-100">
                                        {{ instructorName(course.instructor) }}
                                    </div>
                                    <div class="text-xs text-neutral-500">
                                        {{ course.instructor?.email || 'Sin correo' }}
                                    </div>
                                    <div v-if="course.instructor?.dni" class="text-[11px] text-neutral-400 font-mono mt-0.5">
                                        DNI: {{ course.instructor.dni }}
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Inscripción con DNI (RENIEC API) -->
                    <Card class="border-emerald-300 dark:border-emerald-800 bg-gradient-to-br from-emerald-50/50 to-transparent dark:from-emerald-950/20">
                        <CardHeader class="pb-3">
                            <div class="flex items-center justify-between">
                                <CardTitle class="text-base font-semibold flex items-center gap-2">
                                    <UserCheck class="size-5 text-emerald-600" />
                                    Inscripción con DNI
                                </CardTitle>
                                <Badge variant="outline" class="text-[10px] border-emerald-400 text-emerald-700">
                                    RENIEC API
                                </Badge>
                            </div>
                            <CardDescription class="text-xs">
                                Ingresa tu DNI para validar tus datos y reservar una vacante.
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div class="flex gap-2">
                                <Input
                                    v-model="enrollDni"
                                    placeholder="DNI (8 dígitos)..."
                                    maxlength="8"
                                    class="font-mono text-sm tracking-wider"
                                    @keydown.enter.prevent="consultAndEnroll"
                                />
                                <Button
                                    size="sm"
                                    :disabled="validatingDni || enrollDni.trim().length !== 8"
                                    @click="consultAndEnroll"
                                    class="bg-emerald-600 hover:bg-emerald-700 text-white shrink-0"
                                >
                                    <Spinner v-if="validatingDni" class="size-3.5 mr-1" />
                                    <Search v-else class="size-3.5 mr-1" />
                                    Validar
                                </Button>
                            </div>

                            <div v-if="dniError" class="flex items-center gap-1.5 p-2 rounded-md bg-red-50 text-xs text-red-700 border border-red-200 dark:bg-red-950/40 dark:text-red-300 dark:border-red-800">
                                <AlertCircle class="size-3.5 shrink-0" />
                                <span>{{ dniError }}</span>
                            </div>

                            <!-- Validated Citizen Result -->
                            <div v-if="enrolledPerson && !enrollmentSuccess" class="rounded-lg border border-emerald-200 bg-white p-3 dark:border-emerald-800 dark:bg-neutral-900 space-y-2 text-xs">
                                <div class="flex items-center gap-1.5 text-emerald-600 font-medium">
                                    <CheckCircle2 class="size-4" />
                                    <span>Identidad confirmada en RENIEC</span>
                                </div>
                                <div class="font-semibold text-neutral-900 dark:text-neutral-100 text-sm">
                                    {{ enrolledPerson.nombre_completo }}
                                </div>
                                <div class="text-neutral-500">
                                    DNI: <span class="font-mono font-medium">{{ enrolledPerson.dni }}</span>
                                </div>
                                <Button
                                    size="sm"
                                    @click="confirmEnrollment"
                                    class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-medium mt-2"
                                >
                                    Confirmar Matrícula en este Curso
                                </Button>
                            </div>

                            <!-- Enrollment Success State -->
                            <div v-if="enrollmentSuccess" class="rounded-lg border border-emerald-300 bg-emerald-50 p-3.5 text-center dark:border-emerald-800 dark:bg-emerald-950/50 space-y-1">
                                <CheckCircle2 class="size-6 text-emerald-600 mx-auto" />
                                <div class="text-xs font-bold text-emerald-800 dark:text-emerald-300">
                                    ¡Matrícula Registrada con Éxito!
                                </div>
                                <p class="text-[11px] text-emerald-700 dark:text-emerald-400">
                                    Tu vacante ha sido asegurada. Podrás marcar asistencia en cada clase con tu código QR.
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
