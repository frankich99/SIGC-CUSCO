<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
    Search,
    Plus,
    ExternalLink,
    AlertCircle,
    UserX,
    Filter,
    ShieldCheck,
    Loader2,
    Check,
    X,
} from '@lucide/vue';
import type { BreadcrumbItem } from '@/types';

interface Instructor {
    id: number;
    name: string;
    paterno?: string;
    materno?: string;
    email: string;
    role: string;
    dni?: string;
}

interface EnrollmentUser {
    id: number;
    name: string;
    paterno?: string;
    materno?: string;
    email: string;
    dni?: string;
    phone?: string;
}

interface EnrollmentItem {
    id: number;
    course_id: number;
    user_id?: number | null;
    dni: string;
    nombres: string;
    paterno: string;
    materno?: string | null;
    email: string;
    phone?: string | null;
    status: 'inscrito' | 'en_curso' | 'aprobado' | 'reprobado' | 'cancelado';
    attended_sessions: number;
    final_grade?: number | string | null;
    certificate_code?: string | null;
    created_at?: string;
    user?: EnrollmentUser | null;
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
    enrollments?: EnrollmentItem[];
    enrollments_count?: number;
}

const props = defineProps<{
    course: CourseDetail;
    can: {
        update: boolean;
        delete: boolean;
        manage_enrollments?: boolean;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Panel Principal', href: '/dashboard' },
    { title: 'Capacitaciones', href: '/courses' },
    { title: props.course.title, href: `/courses/${props.course.id}` },
];

const isEnrollModalOpen = ref(false);

// State for Participants Filter & Search
const searchQuery = ref('');
const statusFilter = ref<string>('all');

// Edit Enrollment Modal State
const isEditModalOpen = ref(false);
const editingEnrollment = ref<EnrollmentItem | null>(null);
const editForm = ref({
    status: 'inscrito' as 'inscrito' | 'en_curso' | 'aprobado' | 'reprobado' | 'cancelado',
    attended_sessions: 0,
    final_grade: '' as string | number,
    certificate_code: '',
});
const isSaving = ref(false);
const editError = ref<string | null>(null);

// Statistics computed
const enrollmentsList = computed(() => props.course.enrollments || []);
const totalEnrolled = computed(() => enrollmentsList.value.length);
const inProgressCount = computed(() => enrollmentsList.value.filter((e) => e.status === 'en_curso').length);
const approvedCount = computed(() => enrollmentsList.value.filter((e) => e.status === 'aprobado').length);
const availableSpots = computed(() => Math.max(0, props.course.capacity - totalEnrolled.value));

// Filtered enrollments list
const filteredEnrollments = computed(() => {
    return enrollmentsList.value.filter((e) => {
        if (statusFilter.value !== 'all' && e.status !== statusFilter.value) {
            return false;
        }

        if (!searchQuery.value.trim()) return true;

        const q = searchQuery.value.toLowerCase().trim();
        const fullName = `${e.nombres} ${e.paterno} ${e.materno || ''}`.toLowerCase();
        const dni = (e.dni || '').toLowerCase();
        const email = (e.email || '').toLowerCase();
        const cert = (e.certificate_code || '').toLowerCase();

        return fullName.includes(q) || dni.includes(q) || email.includes(q) || cert.includes(q);
    });
});

function handleEnrolled() {
    router.reload();
}

function deleteCourse() {
    if (confirm(`¿Estás seguro de eliminar "${props.course.title}"?`)) {
        router.delete(`/courses/${props.course.id}`);
    }
}

function openEditModal(enrollment: EnrollmentItem) {
    editingEnrollment.value = enrollment;
    editForm.value = {
        status: enrollment.status,
        attended_sessions: enrollment.attended_sessions,
        final_grade: enrollment.final_grade !== null && enrollment.final_grade !== undefined ? enrollment.final_grade : '',
        certificate_code: enrollment.certificate_code || '',
    };
    editError.value = null;
    isEditModalOpen.value = true;
}

function saveEnrollment() {
    if (!editingEnrollment.value) return;

    isSaving.value = true;
    editError.value = null;

    router.put(
        `/enrollments/${editingEnrollment.value.id}`,
        {
            status: editForm.value.status,
            attended_sessions: Number(editForm.value.attended_sessions) || 0,
            final_grade: editForm.value.final_grade === '' ? null : Number(editForm.value.final_grade),
            certificate_code: editForm.value.certificate_code.trim() || null,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                isSaving.value = false;
                isEditModalOpen.value = false;
            },
            onError: (errs) => {
                isSaving.value = false;
                const first = Object.keys(errs)[0];
                editError.value = errs[first] || 'Error al actualizar los datos de la matrícula.';
            },
        }
    );
}

function handleAttendance(enrollment: EnrollmentItem) {
    router.post(`/enrollments/${enrollment.id}/attendance`, {}, {
        preserveScroll: true,
    });
}

function handleGenerateCertificate(enrollment: EnrollmentItem) {
    if (confirm(`¿Emitir certificado oficial para ${enrollment.nombres} ${enrollment.paterno} (DNI: ${enrollment.dni})?`)) {
        router.post(`/enrollments/${enrollment.id}/certificate`, {}, {
            preserveScroll: true,
        });
    }
}

function handleDeleteEnrollment(enrollment: EnrollmentItem) {
    if (confirm(`¿Estás seguro de eliminar la matrícula de ${enrollment.nombres} ${enrollment.paterno} (DNI: ${enrollment.dni})? Esta acción desmatriculará al participante.`)) {
        router.delete(`/enrollments/${enrollment.id}`, {
            preserveScroll: true,
        });
    }
}

function getCourseStatusBadge(status: string) {
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

function getEnrollmentStatusBadge(status: string) {
    switch (status) {
        case 'inscrito':
            return {
                label: 'Inscrito',
                class: 'bg-sky-100 text-sky-950 border border-sky-300 dark:bg-sky-950/60 dark:text-sky-200 dark:border-sky-800',
            };
        case 'en_curso':
            return {
                label: 'En Curso',
                class: 'bg-amber-100 text-amber-950 border border-amber-300 dark:bg-amber-950/60 dark:text-amber-200 dark:border-amber-800',
            };
        case 'aprobado':
            return {
                label: 'Aprobado',
                class: 'bg-rose-100 text-rose-950 border border-rose-300 dark:bg-rose-950 dark:text-rose-200 dark:border-rose-800 font-black',
            };
        case 'reprobado':
            return {
                label: 'Reprobado',
                class: 'bg-red-100 text-red-950 border border-red-300 dark:bg-red-950/60 dark:text-red-300 dark:border-red-800',
            };
        case 'cancelado':
            return {
                label: 'Cancelado',
                class: 'bg-slate-100 text-slate-800 border border-slate-300 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700',
            };
        default:
            return {
                label: status,
                class: 'bg-slate-100 text-slate-800 border border-slate-300 dark:bg-slate-800 dark:text-slate-300',
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

        <div class="max-w-7xl mx-auto px-4 py-6 sm:px-6 lg:px-8 space-y-8">
            <!-- Header Superior con Navegación y Acciones -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b pb-5 dark:border-slate-800">
                <div class="space-y-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <Button as-child variant="ghost" size="sm" class="-ml-2 text-xs text-slate-600 dark:text-slate-400">
                            <Link href="/courses">
                                <ArrowLeft class="mr-1 size-3.5" />
                                Catálogo de Capacitaciones
                            </Link>
                        </Button>
                        <span class="font-mono text-xs font-bold text-rose-950 dark:text-rose-200 bg-rose-100 dark:bg-rose-950 px-2 py-0.5 rounded border border-rose-200 dark:border-rose-800">
                            {{ course.code }}
                        </span>
                        <span :class="['text-xs font-medium px-2.5 py-0.5 rounded-full border', getCourseStatusBadge(course.status).class]">
                            {{ getCourseStatusBadge(course.status).label }}
                        </span>
                    </div>

                    <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-slate-950 dark:text-white">
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

                <div class="flex flex-wrap items-center gap-2">
                    <Button
                        v-if="course.status === 'abierto' || can.manage_enrollments"
                        size="sm"
                        :class="[THEME_BUTTONS.primary, 'text-xs shadow-xs']"
                        @click="isEnrollModalOpen = true"
                    >
                        <Plus class="mr-1.5 size-3.5" />
                        Inscribir Participante
                    </Button>

                    <Button v-if="can.update" as-child variant="outline" size="sm" class="text-xs">
                        <Link :href="`/courses/${course.id}/edit`">
                            <Pencil class="mr-1.5 size-3.5" />
                            Editar Curso
                        </Link>
                    </Button>

                    <Button
                        v-if="can.delete"
                        variant="outline"
                        size="sm"
                        class="text-xs text-rose-700 hover:text-rose-800 hover:bg-rose-50 dark:hover:bg-rose-950/40"
                        @click="deleteCourse"
                    >
                        <Trash2 class="mr-1.5 size-3.5" />
                        Eliminar
                    </Button>
                </div>
            </div>

            <!-- Panel de Información General y Ponente -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Información General (2 Cols) -->
                <div class="lg:col-span-2 space-y-6">
                    <Card class="border-slate-200 dark:border-slate-800 shadow-xs">
                        <CardHeader class="pb-3 border-b dark:border-slate-800">
                            <CardTitle class="text-base font-black text-slate-950 dark:text-white flex items-center gap-2">
                                <Building2 class="size-4 text-rose-900 dark:text-rose-400" />
                                Información General del Programa
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="pt-4 space-y-4 text-sm">
                            <p class="text-slate-700 dark:text-slate-300 leading-relaxed whitespace-pre-line">
                                {{ course.description || 'No hay descripción detallada registrada para esta capacitación.' }}
                            </p>

                            <!-- Métricas del curso -->
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 pt-2">
                                <div class="rounded-xl border p-3 border-slate-200 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-900/60">
                                    <div class="flex items-center gap-1.5 text-xs text-slate-600 dark:text-slate-400 font-bold">
                                        <Clock class="size-4 text-amber-600" />
                                        Horas Académicas
                                    </div>
                                    <div class="text-lg font-black text-slate-950 dark:text-white mt-1">
                                        {{ course.hours }} hrs
                                    </div>
                                </div>

                                <div class="rounded-xl border p-3 border-slate-200 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-900/60">
                                    <div class="flex items-center gap-1.5 text-xs text-slate-600 dark:text-slate-400 font-bold">
                                        <Users class="size-4 text-rose-800" />
                                        Capacidad Máxima
                                    </div>
                                    <div class="text-lg font-black text-slate-950 dark:text-white mt-1">
                                        {{ course.capacity }} vacantes
                                    </div>
                                </div>

                                <div class="rounded-xl border p-3 border-slate-200 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-900/60">
                                    <div class="flex items-center gap-1.5 text-xs text-slate-600 dark:text-slate-400 font-bold">
                                        <Calendar class="size-4 text-rose-800" />
                                        Cronograma Oficial
                                    </div>
                                    <div class="text-xs font-bold text-slate-900 dark:text-white mt-1.5">
                                        {{ formatDate(course.start_date, 'compact') }} al {{ formatDate(course.end_date, 'compact') }}
                                    </div>
                                    <div class="text-[11px] font-bold text-rose-900 dark:text-rose-400">
                                        {{ formatDateRange(course.start_date, course.end_date, 'medium') }}
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Ciclo Formativo SIGC-CUSCO -->
                    <Card class="border-slate-200 dark:border-slate-800 shadow-xs">
                        <CardHeader class="pb-3 border-b dark:border-slate-800">
                            <CardTitle class="text-base font-black text-slate-950 dark:text-white">
                                Ciclo Formativo y Acreditación Digital
                            </CardTitle>
                            <CardDescription class="text-xs text-slate-600 dark:text-slate-400">
                                Etapas integradas del sistema oficial SIGC-CUSCO
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="pt-4">
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                                <div class="p-3 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 space-y-1">
                                    <div class="flex items-center gap-1.5 font-bold text-rose-900 dark:text-rose-400">
                                        <CheckCircle2 class="size-4" />
                                        1. Matrícula DNI
                                    </div>
                                    <p class="text-slate-600 dark:text-slate-400">Validación de identidad con RENIEC y reserva en base de datos.</p>
                                </div>

                                <div class="p-3 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 space-y-1">
                                    <div class="flex items-center gap-1.5 font-bold text-amber-700 dark:text-amber-400">
                                        <QrCode class="size-4" />
                                        2. Asistencia QR
                                    </div>
                                    <p class="text-slate-600 dark:text-slate-400">Registro de sesiones presenciales o virtuales por clase.</p>
                                </div>

                                <div class="p-3 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 space-y-1">
                                    <div class="flex items-center gap-1.5 font-bold text-rose-900 dark:text-rose-400">
                                        <Award class="size-4" />
                                        3. Certificado Oficial
                                    </div>
                                    <p class="text-slate-600 dark:text-slate-400">Emisión digital con código verificable públicamente en línea.</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Columna Lateral: Docente y Matrícula Rápida -->
                <div class="space-y-6">
                    <!-- Docente Responsable -->
                    <Card class="border-slate-200 dark:border-slate-800 shadow-xs">
                        <CardHeader class="pb-3 border-b dark:border-slate-800">
                            <CardTitle class="text-xs font-black uppercase tracking-wider text-slate-500">
                                Docente / Ponente Asignado
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="pt-4 space-y-3 text-sm">
                            <div class="flex items-center gap-3">
                                <div class="size-11 rounded-full bg-rose-900 text-white flex items-center justify-center font-black text-base shadow-xs">
                                    {{ (course.instructor?.name || course.instructor_name || 'D')[0] }}
                                </div>
                                <div class="min-w-0">
                                    <div class="font-black text-slate-950 dark:text-white truncate">
                                        {{ instructorName(course.instructor) || course.instructor_name || 'Docente Asignado' }}
                                    </div>
                                    <div class="text-xs text-slate-600 dark:text-slate-400 truncate">
                                        {{ course.instructor?.email || 'Docente titular' }}
                                    </div>
                                    <div v-if="course.instructor?.dni" class="text-[11px] text-slate-500 font-mono mt-0.5">
                                        DNI: {{ course.instructor.dni }}
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Tarjeta de Estado de Inscripción -->
                    <Card class="border-2 border-rose-200 dark:border-rose-900 bg-gradient-to-br from-rose-50/50 to-white dark:from-rose-950/30 dark:to-slate-900 shadow-sm rounded-2xl">
                        <CardHeader class="pb-3 border-b border-rose-100 dark:border-rose-900/60">
                            <div class="flex items-center justify-between">
                                <CardTitle class="text-sm font-black flex items-center gap-2 text-slate-950 dark:text-white">
                                    <UserCheck class="size-4 text-rose-800" />
                                    Inscripción y Aforo
                                </CardTitle>
                                <Badge variant="outline" class="text-[10px] font-bold border-rose-300 text-rose-900 bg-rose-50 dark:bg-rose-950">
                                    {{ course.status === 'abierto' ? 'Abierto' : 'Convocatoria Cerrada' }}
                                </Badge>
                            </div>
                        </CardHeader>
                        <CardContent class="pt-4 space-y-3 text-xs">
                            <div class="p-3 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 space-y-2">
                                <div class="flex justify-between items-center text-slate-600 dark:text-slate-400">
                                    <span>Matriculados:</span>
                                    <span class="font-black text-slate-950 dark:text-white text-sm">{{ totalEnrolled }} / {{ course.capacity }}</span>
                                </div>
                                <div class="w-full bg-slate-100 dark:bg-slate-800 rounded-full h-2 overflow-hidden">
                                    <div
                                        class="bg-rose-800 h-2 rounded-full transition-all duration-500"
                                        :style="{ width: `${Math.min(100, Math.round((totalEnrolled / (course.capacity || 1)) * 100))}%` }"
                                    ></div>
                                </div>
                                <div class="flex justify-between items-center text-[11px] text-slate-500">
                                    <span>Vacantes disponibles:</span>
                                    <span class="font-bold text-rose-900 dark:text-rose-300">{{ availableSpots }} cupos</span>
                                </div>
                            </div>

                            <Button
                                v-if="course.status === 'abierto' || can.manage_enrollments"
                                size="lg"
                                :class="['w-full h-10 text-xs sm:text-sm font-black', THEME_BUTTONS.primary]"
                                @click="isEnrollModalOpen = true"
                            >
                                <CheckCircle2 class="size-4 mr-2" />
                                Inscribirme en la Capacitación
                            </Button>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <!-- SECCIÓN PRINCIPAL: TABLA DE PARTICIPANTES MATRICULADOS Y GESTIÓN ACADÉMICA (CRUD) -->
            <Card class="border border-slate-200 dark:border-slate-800 shadow-sm rounded-2xl overflow-hidden">
                <CardHeader class="border-b border-slate-200 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-900/60 p-5 sm:p-6">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <div class="size-8 rounded-lg bg-rose-900 text-white flex items-center justify-center font-black shadow-xs">
                                    <Users class="size-4" />
                                </div>
                                <CardTitle class="text-lg sm:text-xl font-black text-slate-950 dark:text-white">
                                    Nómina Oficial de Participantes Matriculados
                                </CardTitle>
                            </div>
                            <CardDescription class="text-xs text-slate-600 dark:text-slate-400 pl-10">
                                Gestión completa de estudiantes registrados en la base de datos, control de sesiones, calificaciones y emisión de certificados oficiales.
                            </CardDescription>
                        </div>

                        <!-- Botón añadir matrícula -->
                        <div class="flex items-center gap-2">
                            <Button
                                size="sm"
                                :class="[THEME_BUTTONS.primary, 'text-xs shrink-0 shadow-xs']"
                                @click="isEnrollModalOpen = true"
                            >
                                <Plus class="mr-1.5 size-3.5" />
                                Inscribir Nuevo Participante
                            </Button>
                        </div>
                    </div>

                    <!-- Resumen Métrico de Matrículas (4 Tarjetas) -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-4">
                        <div class="p-3 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800">
                            <div class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Total Inscritos</div>
                            <div class="text-2xl font-black text-slate-950 dark:text-white mt-0.5">{{ totalEnrolled }}</div>
                        </div>

                        <div class="p-3 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800">
                            <div class="text-[11px] font-bold text-amber-700 dark:text-amber-400 uppercase tracking-wider">En Curso</div>
                            <div class="text-2xl font-black text-amber-700 dark:text-amber-300 mt-0.5">{{ inProgressCount }}</div>
                        </div>

                        <div class="p-3 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800">
                            <div class="text-[11px] font-bold text-rose-800 dark:text-rose-400 uppercase tracking-wider">Aprobados / Certif.</div>
                            <div class="text-2xl font-black text-rose-900 dark:text-rose-300 mt-0.5">{{ approvedCount }}</div>
                        </div>

                        <div class="p-3 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800">
                            <div class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Vacantes Libres</div>
                            <div class="text-2xl font-black text-slate-700 dark:text-slate-300 mt-0.5">{{ availableSpots }}</div>
                        </div>
                    </div>

                    <!-- Barra de Búsqueda y Filtros de Estado -->
                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 pt-4">
                        <!-- Input de Búsqueda -->
                        <div class="relative flex-1 max-w-md">
                            <Search class="size-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
                            <Input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Buscar por DNI, nombres, apellidos o certificado..."
                                class="pl-9 text-xs font-semibold bg-white dark:bg-slate-900 border-slate-300 dark:border-slate-700 h-9"
                            />
                            <button
                                v-if="searchQuery"
                                type="button"
                                @click="searchQuery = ''"
                                class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 p-0.5"
                            >
                                <X class="size-3.5" />
                            </button>
                        </div>

                        <!-- Filtro por Estado (Tabs) -->
                        <div class="flex flex-wrap items-center gap-1.5 p-1 bg-slate-200/70 dark:bg-slate-800/80 rounded-xl text-xs">
                            <button
                                type="button"
                                @click="statusFilter = 'all'"
                                :class="[
                                    'px-2.5 py-1 rounded-lg font-bold transition-all text-xs cursor-pointer',
                                    statusFilter === 'all'
                                        ? 'bg-rose-900 text-white shadow-xs'
                                        : 'text-slate-700 dark:text-slate-300 hover:text-slate-950'
                                ]"
                            >
                                Todos ({{ enrollmentsList.length }})
                            </button>
                            <button
                                type="button"
                                @click="statusFilter = 'inscrito'"
                                :class="[
                                    'px-2.5 py-1 rounded-lg font-bold transition-all text-xs cursor-pointer',
                                    statusFilter === 'inscrito'
                                        ? 'bg-rose-900 text-white shadow-xs'
                                        : 'text-slate-700 dark:text-slate-300 hover:text-slate-950'
                                ]"
                            >
                                Inscritos
                            </button>
                            <button
                                type="button"
                                @click="statusFilter = 'en_curso'"
                                :class="[
                                    'px-2.5 py-1 rounded-lg font-bold transition-all text-xs cursor-pointer',
                                    statusFilter === 'en_curso'
                                        ? 'bg-rose-900 text-white shadow-xs'
                                        : 'text-slate-700 dark:text-slate-300 hover:text-slate-950'
                                ]"
                            >
                                En Curso
                            </button>
                            <button
                                type="button"
                                @click="statusFilter = 'aprobado'"
                                :class="[
                                    'px-2.5 py-1 rounded-lg font-bold transition-all text-xs cursor-pointer',
                                    statusFilter === 'aprobado'
                                        ? 'bg-rose-900 text-white shadow-xs'
                                        : 'text-slate-700 dark:text-slate-300 hover:text-slate-950'
                                ]"
                            >
                                Aprobados
                            </button>
                        </div>
                    </div>
                </CardHeader>

                <CardContent class="p-0">
                    <!-- Vista de Tabla para Escritorio y Tablet -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="border-b border-slate-200 dark:border-slate-800 bg-slate-100/70 dark:bg-slate-900/90 text-[11px] font-black uppercase tracking-wider text-slate-700 dark:text-slate-300">
                                    <th class="py-3.5 px-4 w-12 text-center">#</th>
                                    <th class="py-3.5 px-4">DNI</th>
                                    <th class="py-3.5 px-4">Participante</th>
                                    <th class="py-3.5 px-4 text-center">Asistencia</th>
                                    <th class="py-3.5 px-4 text-center">Nota</th>
                                    <th class="py-3.5 px-4 text-center">Estado</th>
                                    <th class="py-3.5 px-4">Certificado Digital</th>
                                    <th class="py-3.5 px-4 text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 dark:divide-slate-800 bg-white dark:bg-slate-950">
                                <tr
                                    v-for="(enrollment, index) in filteredEnrollments"
                                    :key="enrollment.id"
                                    class="hover:bg-rose-50/40 dark:hover:bg-rose-950/20 transition-colors"
                                >
                                    <!-- Nro -->
                                    <td class="py-3 px-4 text-center font-bold text-slate-400">
                                        {{ index + 1 }}
                                    </td>

                                    <!-- DNI -->
                                    <td class="py-3 px-4 whitespace-nowrap">
                                        <span class="font-mono font-black text-xs px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-900 dark:text-white border border-slate-300 dark:border-slate-700">
                                            {{ enrollment.dni }}
                                        </span>
                                    </td>

                                    <!-- Participante (Nombre Completo y Contacto) -->
                                    <td class="py-3 px-4 min-w-[200px]">
                                        <div class="font-black text-slate-950 dark:text-white text-xs">
                                            {{ enrollment.paterno }} {{ enrollment.materno || '' }}, {{ enrollment.nombres }}
                                        </div>
                                        <div class="text-[11px] text-slate-500 flex items-center gap-2 mt-0.5">
                                            <span>{{ enrollment.email }}</span>
                                            <span v-if="enrollment.phone" class="font-mono text-[10px]">· Tel: {{ enrollment.phone }}</span>
                                        </div>
                                    </td>

                                    <!-- Asistencia (+1 botón rápido) -->
                                    <td class="py-3 px-4 text-center whitespace-nowrap">
                                        <div class="inline-flex items-center gap-1.5">
                                            <span class="font-black text-slate-900 dark:text-white px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700">
                                                {{ enrollment.attended_sessions }} ses.
                                            </span>
                                            <Button
                                                v-if="can.manage_enrollments"
                                                size="sm"
                                                variant="outline"
                                                title="Sumar 1 sesión de asistencia"
                                                class="h-6 px-1.5 text-[10px] font-bold border-rose-300 hover:bg-rose-100 hover:text-rose-950"
                                                @click="handleAttendance(enrollment)"
                                            >
                                                +1
                                            </Button>
                                        </div>
                                    </td>

                                    <!-- Nota Final -->
                                    <td class="py-3 px-4 text-center whitespace-nowrap">
                                        <span
                                            v-if="enrollment.final_grade !== null && enrollment.final_grade !== undefined"
                                            :class="[
                                                'font-mono font-black text-xs px-2 py-0.5 rounded border',
                                                Number(enrollment.final_grade) >= 14
                                                    ? 'bg-rose-50 text-rose-900 border-rose-300 dark:bg-rose-950 dark:text-rose-200'
                                                    : 'bg-amber-50 text-amber-900 border-amber-300 dark:bg-amber-950 dark:text-amber-200'
                                            ]"
                                        >
                                            {{ Number(enrollment.final_grade).toFixed(1) }}
                                        </span>
                                        <span v-else class="text-slate-400 text-xs font-semibold">
                                            -
                                        </span>
                                    </td>

                                    <!-- Estado de Matrícula -->
                                    <td class="py-3 px-4 text-center whitespace-nowrap">
                                        <span :class="['text-[11px] font-bold px-2.5 py-0.5 rounded-full border', getEnrollmentStatusBadge(enrollment.status).class]">
                                            {{ getEnrollmentStatusBadge(enrollment.status).label }}
                                        </span>
                                    </td>

                                    <!-- Certificado Digital Oficial -->
                                    <td class="py-3 px-4 whitespace-nowrap">
                                        <div v-if="enrollment.certificate_code" class="flex items-center gap-1.5">
                                            <span class="font-mono text-[11px] font-black text-rose-950 dark:text-rose-200 bg-rose-50 dark:bg-rose-950/70 border border-rose-200 dark:border-rose-800 px-2 py-0.5 rounded">
                                                {{ enrollment.certificate_code }}
                                            </span>
                                            <Link
                                                :href="`/certificates?dni=${enrollment.dni}`"
                                                title="Verificar certificado en línea por DNI"
                                                class="text-rose-900 hover:text-rose-950 dark:text-rose-300 p-1 hover:bg-rose-100 rounded"
                                            >
                                                <ExternalLink class="size-3.5" />
                                            </Link>
                                        </div>
                                        <div v-else-if="can.manage_enrollments">
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                class="h-7 text-[11px] font-bold border-amber-300 text-amber-800 hover:bg-amber-50"
                                                @click="handleGenerateCertificate(enrollment)"
                                            >
                                                <Award class="size-3 mr-1 text-amber-600" />
                                                Emitir Certificado
                                            </Button>
                                        </div>
                                        <span v-else class="text-[11px] text-slate-400 font-medium">
                                            Sin emitir
                                        </span>
                                    </td>

                                    <!-- Acciones del CRUD -->
                                    <td class="py-3 px-4 text-right whitespace-nowrap">
                                        <div v-if="can.manage_enrollments" class="inline-flex items-center gap-1">
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                title="Editar estado, asistencia o calificación"
                                                class="h-7 w-7 p-0 text-slate-700 hover:text-rose-950 hover:bg-rose-100"
                                                @click="openEditModal(enrollment)"
                                            >
                                                <Pencil class="size-3.5" />
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                title="Eliminar matrícula"
                                                class="h-7 w-7 p-0 text-red-600 hover:text-red-800 hover:bg-red-50"
                                                @click="handleDeleteEnrollment(enrollment)"
                                            >
                                                <Trash2 class="size-3.5" />
                                            </Button>
                                        </div>
                                        <div v-else class="text-[11px] font-bold text-slate-400">
                                            Registrado
                                        </div>
                                    </td>
                                </tr>

                                <!-- Empty State -->
                                <tr v-if="filteredEnrollments.length === 0">
                                    <td colspan="8" class="py-12 text-center space-y-3">
                                        <div class="size-12 rounded-full bg-slate-100 dark:bg-slate-900 text-slate-400 flex items-center justify-center mx-auto">
                                            <UserX class="size-6" />
                                        </div>
                                        <div class="space-y-1">
                                            <p class="font-black text-slate-900 dark:text-white text-sm">
                                                No se encontraron participantes
                                            </p>
                                            <p class="text-xs text-slate-500 max-w-sm mx-auto">
                                                {{ searchQuery ? 'No hay resultados que coincidan con el término de búsqueda.' : 'Aún no hay inscripciones registradas para esta capacitación.' }}
                                            </p>
                                        </div>
                                        <Button
                                            v-if="!searchQuery"
                                            size="sm"
                                            :class="[THEME_BUTTONS.primary, 'text-xs mt-2']"
                                            @click="isEnrollModalOpen = true"
                                        >
                                            <Plus class="size-3.5 mr-1" />
                                            Inscribir al Primer Participante
                                        </Button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- MODAL CRUD: EDITAR MATRÍCULA, ASISTENCIA Y NOTA -->
        <Dialog :open="isEditModalOpen" @update:open="isEditModalOpen = $event">
            <DialogContent class="w-[94vw] sm:max-w-lg p-0 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 overflow-hidden">
                <div class="p-5 border-b border-slate-200 dark:border-slate-800 bg-slate-50/80 dark:bg-slate-900/60">
                    <DialogHeader class="text-left space-y-1">
                        <div class="flex items-center gap-2">
                            <span class="font-mono text-xs font-black px-2 py-0.5 rounded bg-rose-100 text-rose-950 border border-rose-300">
                                DNI: {{ editingEnrollment?.dni }}
                            </span>
                            <Badge variant="outline" class="text-[10px] font-bold">
                                Matrícula #{{ editingEnrollment?.id }}
                            </Badge>
                        </div>
                        <DialogTitle class="text-lg font-black text-slate-950 dark:text-white pt-1">
                            Editar Matrícula: {{ editingEnrollment?.nombres }} {{ editingEnrollment?.paterno }}
                        </DialogTitle>
                        <DialogDescription class="text-xs text-slate-600 dark:text-slate-400">
                            Modifica el estado académico, registro de sesiones, nota vigesimal y código del certificado.
                        </DialogDescription>
                    </DialogHeader>
                </div>

                <form @submit.prevent="saveEnrollment" class="p-5 space-y-4 text-xs">
                    <div v-if="editError" class="p-3 bg-red-50 border border-red-200 rounded-xl text-red-700 font-bold flex items-center gap-2">
                        <AlertCircle class="size-4 shrink-0" />
                        <span>{{ editError }}</span>
                    </div>

                    <!-- Estado del Participante -->
                    <div class="space-y-1.5">
                        <Label for="edit-status" class="text-xs font-bold text-slate-800 dark:text-slate-200">
                            Estado del Participante <span class="text-rose-600">*</span>
                        </Label>
                        <select
                            id="edit-status"
                            v-model="editForm.status"
                            class="w-full h-9 rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-1 text-xs font-bold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-rose-800"
                        >
                            <option value="inscrito">Inscrito (Pendiente de inicio)</option>
                            <option value="en_curso">En Curso (Asistiendo a clases)</option>
                            <option value="aprobado">Aprobado (Cumplió requisitos)</option>
                            <option value="reprobado">Reprobado</option>
                            <option value="cancelado">Cancelado / Desistió</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <!-- Sesiones Asistidas -->
                        <div class="space-y-1.5">
                            <Label for="edit-sessions" class="text-xs font-bold text-slate-800 dark:text-slate-200">
                                Sesiones Asistidas
                            </Label>
                            <Input
                                id="edit-sessions"
                                v-model.number="editForm.attended_sessions"
                                type="number"
                                min="0"
                                max="100"
                                class="h-9 text-xs font-bold"
                            />
                        </div>

                        <!-- Calificación Vigesimal -->
                        <div class="space-y-1.5">
                            <Label for="edit-grade" class="text-xs font-bold text-slate-800 dark:text-slate-200">
                                Nota Final (0 - 20)
                            </Label>
                            <Input
                                id="edit-grade"
                                v-model="editForm.final_grade"
                                type="number"
                                step="0.1"
                                min="0"
                                max="20"
                                placeholder="Ej: 18.5"
                                class="h-9 text-xs font-bold"
                            />
                        </div>
                    </div>

                    <!-- Código de Certificado -->
                    <div class="space-y-1.5">
                        <Label for="edit-cert" class="text-xs font-bold text-slate-800 dark:text-slate-200 flex items-center justify-between">
                            <span>Código de Certificado Oficial</span>
                            <span class="text-[10px] text-slate-500 font-normal">Opcional / Autogenerado</span>
                        </Label>
                        <Input
                            id="edit-cert"
                            v-model="editForm.certificate_code"
                            type="text"
                            placeholder="Ej: CERT-2026-13396200"
                            class="h-9 font-mono text-xs font-bold"
                        />
                        <p class="text-[11px] text-slate-500">
                            Si se aprueba y este campo está vacío, el sistema generará automáticamente CERT-{{ new Date().getFullYear() }}-{{ editingEnrollment?.dni }}.
                        </p>
                    </div>

                    <DialogFooter class="pt-3 border-t border-slate-200 dark:border-slate-800 flex items-center justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="text-xs font-bold"
                            @click="isEditModalOpen = false"
                        >
                            Cancelar
                        </Button>
                        <Button
                            type="submit"
                            size="sm"
                            :disabled="isSaving"
                            :class="[THEME_BUTTONS.primary, 'text-xs font-black shadow-xs']"
                        >
                            <Loader2 v-if="isSaving" class="size-3.5 mr-1.5 animate-spin" />
                            <Check v-else class="size-3.5 mr-1.5" />
                            Guardar Cambios
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- MODAL DE INSCRIPCIÓN OFICIAL -->
        <EnrollmentModal
            :course="course"
            :open="isEnrollModalOpen"
            @update:open="isEnrollModalOpen = $event"
            @enrolled="handleEnrolled"
        />
    </AppLayout>
</template>
