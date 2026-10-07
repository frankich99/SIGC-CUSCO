<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import EnrollmentModal from '@/components/EnrollmentModal.vue';
import { formatDate, formatDateRange, formatHours } from '@/lib/formatters';
import { THEME_BUTTONS, THEME_BADGES } from '@/lib/theme';
import {
    Calendar,
    Clock,
    Users,
    ArrowLeft,
    Pencil,
    Trash2,
    CheckCircle2,
    QrCode,
    Award,
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
    instructor_name?: string;
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

const isEnrollModalOpen = ref(false);

function handleEnrolled() {
    router.reload();
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
                class: THEME_BADGES.abierto,
            };
        case 'en_curso':
            return {
                label: 'En curso',
                class: THEME_BADGES.en_curso,
            };
        case 'concluido':
            return {
                label: 'Concluido',
                class: THEME_BADGES.concluido,
            };
        default:
            return {
                label: status,
                class: THEME_BADGES.concluido,
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
                        <span class="font-mono text-xs font-bold text-rose-950 dark:text-rose-200 bg-rose-100 dark:bg-rose-950 px-2 py-0.5 rounded border border-rose-200 dark:border-rose-800">
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
                        class="flex items-center gap-1.5 text-xs font-bold text-rose-900 dark:text-rose-300 bg-rose-50 dark:bg-rose-950/60 px-2.5 py-1 rounded-md border border-rose-200/80 dark:border-rose-800/60 w-fit"
                    >
                        <Building2 class="size-4 shrink-0 text-rose-800 dark:text-rose-400" />
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
                                        <Clock class="size-4 text-amber-600" />
                                        Horas Académicas
                                    </div>
                                    <div class="text-lg font-bold text-neutral-900 dark:text-neutral-100 mt-1">
                                        {{ course.hours }} hrs
                                    </div>
                                </div>

                                <div class="rounded-lg border p-3 dark:border-neutral-800 bg-neutral-50/50 dark:bg-neutral-900/50">
                                    <div class="flex items-center gap-1.5 text-xs text-neutral-500 font-medium">
                                        <Users class="size-4 text-rose-800" />
                                        Vacantes Máximas
                                    </div>
                                    <div class="text-lg font-bold text-neutral-900 dark:text-neutral-100 mt-1">
                                        {{ course.capacity }} cupos
                                    </div>
                                </div>

                                <div class="rounded-lg border p-3 border-slate-200 dark:border-slate-800 bg-slate-50/80 dark:bg-slate-900/60">
                                    <div class="flex items-center gap-1.5 text-xs text-slate-700 dark:text-slate-300 font-bold">
                                        <Calendar class="size-4 text-rose-800" />
                                        Cronograma Oficial
                                    </div>
                                    <div class="text-xs font-bold text-slate-900 dark:text-slate-100 mt-1.5">
                                        {{ formatDate(course.start_date, 'compact') }} al {{ formatDate(course.end_date, 'compact') }}
                                    </div>
                                    <div class="text-[11px] font-bold text-rose-900 dark:text-rose-400">
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
                                    <div class="flex items-center gap-1.5 font-bold text-rose-900 dark:text-rose-400">
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
                    <!-- Ponente / Docente Responsable -->
                    <Card>
                        <CardHeader class="pb-3">
                            <CardTitle class="text-xs font-semibold uppercase tracking-wider text-neutral-500">
                                Ponente / Docente
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-3 text-sm">
                            <div class="flex items-center gap-3">
                                <div class="size-11 rounded-full bg-rose-100 text-rose-900 dark:bg-rose-950 dark:text-rose-200 flex items-center justify-center font-bold text-base">
                                    {{ (course.instructor?.name || course.instructor_name || 'P')[0] }}
                                </div>
                                <div>
                                    <div class="font-semibold text-neutral-900 dark:text-neutral-100">
                                        {{ instructorName(course.instructor) || course.instructor_name || 'Ponente / Docente Asignado' }}
                                    </div>
                                    <div class="text-xs text-neutral-500">
                                        {{ course.instructor?.email || 'Ponente para esta capacitación' }}
                                    </div>
                                    <div v-if="course.instructor?.dni" class="text-[11px] text-neutral-400 font-mono mt-0.5">
                                        DNI: {{ course.instructor.dni }}
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Inscripción Oficial con DNI -->
                    <Card class="border-2 border-rose-200 dark:border-rose-900 bg-gradient-to-br from-rose-50/40 to-transparent dark:from-rose-950/20 shadow-sm rounded-xl">
                        <CardHeader class="pb-3">
                            <div class="flex items-center justify-between">
                                <CardTitle class="text-sm sm:text-base font-bold flex items-center gap-2 text-slate-900 dark:text-white">
                                    <UserCheck class="size-4 text-rose-800" />
                                    Inscripción en Línea
                                </CardTitle>
                                <Badge variant="outline" class="text-[10px] font-bold border-rose-300 text-rose-900 bg-rose-50 dark:bg-rose-950">
                                    RENIEC API
                                </Badge>
                            </div>
                            <CardDescription class="text-xs text-slate-600 dark:text-slate-400">
                                Reserva tu vacante con validación DNI y registro oficial.
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div class="p-3 bg-white dark:bg-slate-900 rounded-lg border border-slate-200 dark:border-slate-800 text-xs space-y-1.5">
                                <div class="flex justify-between font-medium text-slate-600 dark:text-slate-400">
                                    <span>Capacidad:</span>
                                    <span class="font-bold text-slate-900 dark:text-white">{{ course.capacity }} vacantes</span>
                                </div>
                                <div class="flex justify-between font-medium text-slate-600 dark:text-slate-400">
                                    <span>Horas:</span>
                                    <span class="font-bold text-slate-900 dark:text-white">{{ course.hours }} hrs académicas</span>
                                </div>
                            </div>
                            <Button
                                v-if="course.status === 'abierto'"
                                size="lg"
                                :class="['w-full h-10 text-xs sm:text-sm', THEME_BUTTONS.primary]"
                                @click="isEnrollModalOpen = true"
                            >
                                <CheckCircle2 class="size-4 mr-2" />
                                Inscribirme
                            </Button>
                            <div v-else class="text-center py-2 text-xs font-bold text-slate-500 bg-slate-100 dark:bg-slate-800 rounded-lg">
                                Convocatoria Cerrada
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>

        <!-- MODAL DE INSCRIPCIÓN OFICIAL -->
        <EnrollmentModal
            :course="course"
            :open="isEnrollModalOpen"
            @update:open="isEnrollModalOpen = $event"
            @enrolled="handleEnrolled"
        />
    </AppLayout>
</template>
