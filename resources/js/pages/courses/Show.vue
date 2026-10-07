<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
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
import { formatDate, formatDateRange } from '@/lib/formatters';
import { THEME_BUTTONS, THEME_BADGES } from '@/lib/theme';
import { generateQrSvg } from '@/lib/qr';
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
    ShieldCheck,
    Loader2,
    Check,
    X,
    Maximize2,
    Wifi,
    WifiOff,
    RefreshCw,
    FileSpreadsheet,
    Lock,
    Unlock,
    FileText,
    CheckCheck,
    Download,
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

interface AttendanceRecordItem {
    id: number;
    session_number: number;
    status: 'presente' | 'tardanza' | 'falta';
    recorded_at?: string;
    method?: string;
}

interface EnrollmentItem {
    id: number;
    course_id: number;
    user_id?: number | null;
    credential_code?: string | null;
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
    certificate_hash?: string | null;
    certificate_issued_at?: string | null;
    attendance_records?: AttendanceRecordItem[];
    attendance_percentage?: number;
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
    total_sessions: number;
    min_attendance_percentage: number;
    capacity: number;
    status: 'abierto' | 'en_curso' | 'concluido' | 'cancelado';
    acta_closed_at?: string | null;
    acta_closed_by?: number | null;
    acta_closer?: { name: string; paterno?: string } | null;
    instructor?: Instructor;
    instructor_name?: string;
    enrollments?: EnrollmentItem[];
}

const props = defineProps<{
    course: CourseDetail;
    can: {
        update: boolean;
        delete: boolean;
        manage_enrollments?: boolean;
        close_acta?: boolean;
        issue_certificates?: boolean;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Panel Principal', href: '/dashboard' },
    { title: 'Capacitaciones', href: '/courses' },
    { title: props.course.title, href: `/courses/${props.course.id}` },
];

type AcademicTab = 'asistencia' | 'notas' | 'certificados' | 'reportes';
const activeTab = ref<AcademicTab>('asistencia');

const selectedSession = ref<number>(1);
const totalSessions = computed(() => props.course.total_sessions || 4);

const isQrProjectorOpen = ref(false);
const currentTime = ref('');
let timeInterval: any = null;

function updateCuscoTime() {
    currentTime.value = new Intl.DateTimeFormat('es-PE', {
        timeZone: 'America/Lima',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: true,
    }).format(new Date());
}

const sessionQrPayload = computed(() => {
    return JSON.stringify({
        course: props.course.code,
        course_id: props.course.id,
        session: selectedSession.value,
        token: `SES-${props.course.id}-${selectedSession.value}-${props.course.code.slice(-3)}`,
        system: 'SIGC-CUSCO',
    });
});

const sessionQrSvg = computed(() => {
    return generateQrSvg(sessionQrPayload.value, 300, '#800020');
});

const isOnline = ref(typeof navigator !== 'undefined' ? navigator.onLine : true);
interface OfflineAttendanceItem {
    identifier: string;
    session_number: number;
    status: 'presente' | 'tardanza';
    recorded_at: string;
    timestamp: number;
}
const offlineQueueKey = computed(() => `sigc_offline_attendance_${props.course.id}`);
const offlineQueue = ref<OfflineAttendanceItem[]>([]);
const isSyncing = ref(false);
const syncSuccessMessage = ref<string | null>(null);

function loadOfflineQueue() {
    if (typeof localStorage === 'undefined') return;
    try {
        const data = localStorage.getItem(offlineQueueKey.value);
        offlineQueue.value = data ? JSON.parse(data) : [];
    } catch {
        offlineQueue.value = [];
    }
}

function saveOfflineQueue() {
    if (typeof localStorage === 'undefined') return;
    try {
        localStorage.setItem(offlineQueueKey.value, JSON.stringify(offlineQueue.value));
    } catch (e) {
        console.error('Error guardando cola offline', e);
    }
}

const manualIdentifier = ref('');
const manualStatus = ref<'presente' | 'tardanza'>('presente');
const isSubmittingAttendance = ref(false);
const attendanceFeedback = ref<{ type: 'success' | 'error' | 'warning'; text: string } | null>(null);

function registerManualAttendance() {
    const id = manualIdentifier.value.trim();
    if (!id) return;

    if (!isOnline.value) {
        offlineQueue.value.push({
            identifier: id,
            session_number: selectedSession.value,
            status: manualStatus.value,
            recorded_at: new Date().toISOString(),
            timestamp: Date.now(),
        });
        saveOfflineQueue();
        manualIdentifier.value = '';
        attendanceFeedback.value = {
            type: 'warning',
            text: `[Sin Conexión] Asistencia guardada localmente para ${id}. Se enviará al sincronizar.`,
        };
        return;
    }

    isSubmittingAttendance.value = true;
    attendanceFeedback.value = null;

    router.post(
        `/courses/${props.course.id}/sessions/${selectedSession.value}/attendance`,
        {
            identifier: id,
            status: manualStatus.value,
            method: 'manual',
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                isSubmittingAttendance.value = false;
                manualIdentifier.value = '';
                attendanceFeedback.value = {
                    type: 'success',
                    text: `✓ Asistencia registrada para la sesión ${selectedSession.value}.`,
                };
            },
            onError: (errs) => {
                isSubmittingAttendance.value = false;
                const msg = Object.values(errs)[0] || 'No se pudo registrar la asistencia.';
                attendanceFeedback.value = {
                    type: 'error',
                    text: msg as string,
                };
            },
        }
    );
}

function syncOfflineQueue() {
    if (offlineQueue.value.length === 0 || isSyncing.value) return;

    isSyncing.value = true;
    syncSuccessMessage.value = null;

    router.post(
        `/courses/${props.course.id}/attendance/sync`,
        {
            items: offlineQueue.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                const count = offlineQueue.value.length;
                offlineQueue.value = [];
                saveOfflineQueue();
                isSyncing.value = false;
                syncSuccessMessage.value = `¡Sincronización completada! Se registraron ${count} asistencias guardadas en el equipo.`;
            },
            onError: () => {
                isSyncing.value = false;
                alert('Ocurrió un problema durante la sincronización.');
            },
        }
    );
}

const isCloseActaModalOpen = ref(false);
const isClosingActa = ref(false);

function confirmCloseActa() {
    isClosingActa.value = true;
    router.post(
        `/courses/${props.course.id}/acta/close`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                isClosingActa.value = false;
                isCloseActaModalOpen.value = false;
            },
            onError: () => {
                isClosingActa.value = false;
            },
        }
    );
}

const isIssuingCertificates = ref(false);
function bulkIssueCertificates() {
    if (!confirm('¿Emitir los certificados digitales oficiales para todos los participantes aprobados?')) {
        return;
    }

    isIssuingCertificates.value = true;
    router.post(
        `/courses/${props.course.id}/certificates/bulk-issue`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                isIssuingCertificates.value = false;
            },
            onError: () => {
                isIssuingCertificates.value = false;
            },
        }
    );
}

const searchQuery = ref('');
const enrollmentsList = computed(() => props.course.enrollments || []);
const totalEnrolled = computed(() => enrollmentsList.value.length);
const approvedStudents = computed(() => enrollmentsList.value.filter((e) => e.status === 'aprobado'));
const failedStudents = computed(() => enrollmentsList.value.filter((e) => e.status === 'reprobado'));
const availableSpots = computed(() => Math.max(0, props.course.capacity - totalEnrolled.value));

const averageAttendance = computed(() => {
    if (enrollmentsList.value.length === 0) return 0;
    const total = enrollmentsList.value.reduce((acc, curr) => acc + (curr.attendance_percentage || 0), 0);
    return Math.round(total / enrollmentsList.value.length);
});

const isActaClosed = computed(() => Boolean(props.course.acta_closed_at));

const filteredEnrollments = computed(() => {
    if (!searchQuery.value.trim()) return enrollmentsList.value;
    const q = searchQuery.value.toLowerCase().trim();
    return enrollmentsList.value.filter((e) => {
        const full = `${e.nombres} ${e.paterno} ${e.materno || ''}`.toLowerCase();
        return full.includes(q) || (e.dni || '').includes(q) || (e.credential_code || '').toLowerCase().includes(q);
    });
});

const isEnrollModalOpen = ref(false);

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
                editError.value = Object.values(errs)[0] as string || 'Error al actualizar.';
            },
        }
    );
}

onMounted(() => {
    updateCuscoTime();
    timeInterval = setInterval(updateCuscoTime, 1000);
    loadOfflineQueue();

    if (typeof window !== 'undefined') {
        window.addEventListener('online', () => { isOnline.value = true; });
        window.addEventListener('offline', () => { isOnline.value = false; });
    }
});

onUnmounted(() => {
    if (timeInterval) clearInterval(timeInterval);
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`${course.title} - SIGC-CUSCO`" />

        <div class="max-w-7xl mx-auto px-4 py-6 sm:px-6 lg:px-8 space-y-6">
            <!-- Header Superior Oficial Granate Cusco -->
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 border-b pb-5 dark:border-slate-800">
                <div class="space-y-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <Button as-child variant="ghost" size="sm" class="-ml-2 text-xs text-slate-600 dark:text-slate-400">
                            <Link href="/courses">
                                <ArrowLeft class="mr-1 size-3.5" />
                                Catálogo de Capacitaciones
                            </Link>
                        </Button>
                        <span class="font-mono text-xs font-black text-rose-950 dark:text-rose-200 bg-rose-100 dark:bg-rose-950 px-2 py-0.5 rounded border border-rose-300 dark:border-rose-800">
                            {{ course.code }}
                        </span>
                        <Badge variant="outline" class="text-xs font-bold border-rose-800 text-rose-900 bg-rose-50 dark:bg-rose-950">
                            {{ course.status.toUpperCase() }}
                        </Badge>
                        <Badge v-if="isActaClosed" class="bg-emerald-700 text-white font-bold text-xs">
                            <Lock class="size-3 mr-1" />
                            Acta Cerrada Oficialmente
                        </Badge>
                        <Badge v-else variant="secondary" class="font-bold text-xs">
                            <Unlock class="size-3 mr-1" />
                            Acta Abierta (En Edición)
                        </Badge>
                    </div>

                    <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-slate-950 dark:text-white">
                        {{ course.title }}
                    </h1>

                    <div v-if="course.institution" class="flex items-center gap-1.5 text-xs font-bold text-rose-950 dark:text-rose-300">
                        <Building2 class="size-4 shrink-0 text-rose-800" />
                        <span>Entidad Organizadora: <strong>{{ course.institution }}</strong></span>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <Button
                        v-if="course.status === 'abierto' || can.manage_enrollments"
                        size="sm"
                        :class="[THEME_BUTTONS.primary, 'text-xs shadow-xs font-bold']"
                        @click="isEnrollModalOpen = true"
                    >
                        <Plus class="mr-1.5 size-3.5" />
                        Inscribir Participante
                    </Button>

                    <Button v-if="can.update" as-child variant="outline" size="sm" class="text-xs font-bold">
                        <Link :href="`/courses/${course.id}/edit`">
                            <Pencil class="mr-1.5 size-3.5" />
                            Editar Curso
                        </Link>
                    </Button>
                </div>
            </div>

            <!-- Banner Alerta Modo Sin Conexión / Offline (SIGC-12) -->
            <div
                v-if="!isOnline || offlineQueue.length > 0"
                class="rounded-xl border p-4 transition-all"
                :class="!isOnline ? 'bg-amber-50 border-amber-300 text-amber-950 dark:bg-amber-950/40 dark:border-amber-800 dark:text-amber-200' : 'bg-sky-50 border-sky-300 text-sky-950 dark:bg-sky-950/40 dark:border-sky-800 dark:text-sky-200'"
            >
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="size-9 rounded-full flex items-center justify-center shrink-0" :class="!isOnline ? 'bg-amber-200 dark:bg-amber-900 text-amber-900' : 'bg-sky-200 dark:bg-sky-900 text-sky-900'">
                            <WifiOff v-if="!isOnline" class="size-5" />
                            <Wifi v-else class="size-5" />
                        </div>
                        <div>
                            <div class="font-black text-sm">
                                <span v-if="!isOnline">Modo Sin Conexión Activo (Trabajo Offline)</span>
                                <span v-else>Conexión Restablecida · Sincronización Pendiente</span>
                            </div>
                            <div class="text-xs mt-0.5">
                                Hay <strong>{{ offlineQueue.length }}</strong> asistencia(s) guardadas localmente en este equipo.
                            </div>
                        </div>
                    </div>

                    <Button
                        v-if="offlineQueue.length > 0"
                        size="sm"
                        :disabled="!isOnline || isSyncing"
                        class="bg-amber-700 hover:bg-amber-800 text-white font-black text-xs shrink-0"
                        @click="syncOfflineQueue"
                    >
                        <RefreshCw :class="['mr-1.5 size-3.5', isSyncing ? 'animate-spin' : '']" />
                        {{ isSyncing ? 'Sincronizando...' : 'Sincronizar Ahora' }}
                    </Button>
                </div>
                <div v-if="syncSuccessMessage" class="mt-2 text-xs font-bold text-emerald-800 dark:text-emerald-300">
                    {{ syncSuccessMessage }}
                </div>
            </div>

            <!-- NAVEGACIÓN POR 4 PESTAÑAS ACADÉMICAS OFICIALES -->
            <div class="border-b border-slate-200 dark:border-slate-800">
                <nav class="flex space-x-2 sm:space-x-4 overflow-x-auto pb-px" aria-label="Tabs">
                    <button
                        type="button"
                        @click="activeTab = 'asistencia'"
                        :class="[
                            'whitespace-nowrap py-3 px-4 border-b-2 font-black text-xs sm:text-sm flex items-center gap-2 cursor-pointer transition-all',
                            activeTab === 'asistencia'
                                ? 'border-rose-900 text-rose-900 dark:text-rose-300 dark:border-rose-500'
                                : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'
                        ]"
                    >
                        <QrCode class="size-4" />
                        1. Asistencia de Sesiones
                    </button>

                    <button
                        type="button"
                        @click="activeTab = 'notas'"
                        :class="[
                            'whitespace-nowrap py-3 px-4 border-b-2 font-black text-xs sm:text-sm flex items-center gap-2 cursor-pointer transition-all',
                            activeTab === 'notas'
                                ? 'border-rose-900 text-rose-900 dark:text-rose-300 dark:border-rose-500'
                                : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'
                        ]"
                    >
                        <FileText class="size-4" />
                        2. Notas y Acta Oficial
                        <span v-if="isActaClosed" class="size-2 rounded-full bg-emerald-600"></span>
                    </button>

                    <button
                        type="button"
                        @click="activeTab = 'certificados'"
                        :class="[
                            'whitespace-nowrap py-3 px-4 border-b-2 font-black text-xs sm:text-sm flex items-center gap-2 cursor-pointer transition-all',
                            activeTab === 'certificados'
                                ? 'border-rose-900 text-rose-900 dark:text-rose-300 dark:border-rose-500'
                                : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'
                        ]"
                    >
                        <Award class="size-4" />
                        3. Emisión de Certificados
                        <span class="bg-rose-100 text-rose-950 text-[10px] px-1.5 py-0.2 rounded-full font-bold">
                            {{ approvedStudents.length }}
                        </span>
                    </button>

                    <button
                        type="button"
                        @click="activeTab = 'reportes'"
                        :class="[
                            'whitespace-nowrap py-3 px-4 border-b-2 font-black text-xs sm:text-sm flex items-center gap-2 cursor-pointer transition-all',
                            activeTab === 'reportes'
                                ? 'border-rose-900 text-rose-900 dark:text-rose-300 dark:border-rose-500'
                                : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'
                        ]"
                    >
                        <FileSpreadsheet class="size-4" />
                        4. Reportes y Estadísticas
                    </button>
                </nav>
            </div>

            <!-- CONTENIDO DE LA PESTAÑA 1: ASISTENCIA DE SESIONES (SIGC-4 / SIGC-12) -->
            <div v-if="activeTab === 'asistencia'" class="space-y-6">
                <Card class="border-slate-200 dark:border-slate-800 shadow-sm">
                    <CardHeader class="pb-3 border-b bg-slate-50/60 dark:bg-slate-900/60">
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                            <div>
                                <CardTitle class="text-base font-black flex items-center gap-2">
                                    <QrCode class="size-4 text-rose-900" />
                                    Control de Asistencia por Sesión
                                </CardTitle>
                                <CardDescription class="text-xs">
                                    Seleccione la sesión activa para proyectar el código QR o registrar asistencia manual por DNI.
                                </CardDescription>
                            </div>

                            <Button
                                size="sm"
                                :class="[THEME_BUTTONS.primary, 'text-xs font-black shadow-xs']"
                                @click="isQrProjectorOpen = true"
                            >
                                <Maximize2 class="mr-1.5 size-3.5" />
                                Proyectar QR de la Sesión {{ selectedSession }}
                            </Button>
                        </div>
                    </CardHeader>

                    <CardContent class="pt-4 space-y-4">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-xs font-bold text-slate-600 mr-2">Sesión Activa:</span>
                            <Button
                                v-for="num in totalSessions"
                                :key="num"
                                size="sm"
                                :variant="selectedSession === num ? 'default' : 'outline'"
                                :class="selectedSession === num ? 'bg-rose-900 text-white font-black' : 'font-bold text-xs'"
                                @click="selectedSession = num"
                            >
                                Sesión {{ num }}
                            </Button>
                        </div>

                        <div class="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/40 space-y-3">
                            <div class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-wider">
                                Registro Manual Rápido (Sesión {{ selectedSession }})
                            </div>

                            <form @submit.prevent="registerManualAttendance" class="flex flex-col sm:flex-row gap-2">
                                <div class="relative flex-1">
                                    <Input
                                        v-model="manualIdentifier"
                                        type="text"
                                        placeholder="Ingrese DNI (8 dígitos) o código de credencial INS-..."
                                        class="text-xs font-bold h-9 pl-3"
                                        :disabled="isSubmittingAttendance"
                                    />
                                </div>

                                <select
                                    v-model="manualStatus"
                                    class="h-9 px-3 rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-xs font-bold text-slate-800"
                                >
                                    <option value="presente">Presente</option>
                                    <option value="tardanza">Tardanza</option>
                                </select>

                                <Button
                                    type="submit"
                                    size="sm"
                                    :disabled="!manualIdentifier.trim() || isSubmittingAttendance"
                                    :class="[THEME_BUTTONS.primary, 'text-xs font-black h-9 px-4']"
                                >
                                    <Loader2 v-if="isSubmittingAttendance" class="size-3.5 mr-1.5 animate-spin" />
                                    <UserCheck v-else class="size-3.5 mr-1.5" />
                                    Registrar
                                </Button>
                            </form>

                            <div v-if="attendanceFeedback" :class="[
                                'p-2.5 rounded-lg text-xs font-bold flex items-center gap-2',
                                attendanceFeedback.type === 'success' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' :
                                attendanceFeedback.type === 'warning' ? 'bg-amber-50 text-amber-900 border border-amber-200' :
                                'bg-red-50 text-red-800 border border-red-200'
                            ]">
                                <AlertCircle class="size-4 shrink-0" />
                                <span>{{ attendanceFeedback.text }}</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- MATRIZ OFICIAL DE ASISTENCIAS (S1..SN) -->
                <Card class="border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
                    <CardHeader class="p-4 border-b bg-slate-50/80 dark:bg-slate-900/80 flex flex-row items-center justify-between">
                        <div>
                            <CardTitle class="text-sm font-black">Matriz Integral de Asistencia por Participante</CardTitle>
                            <CardDescription class="text-xs">Registro histórico sesión por sesión (Mínimo aprobatorio: {{ course.min_attendance_percentage }}%)</CardDescription>
                        </div>
                        <div class="relative w-64">
                            <Search class="size-3.5 text-slate-400 absolute left-2.5 top-1/2 -translate-y-1/2" />
                            <Input v-model="searchQuery" placeholder="Buscar por DNI o nombres..." class="h-8 pl-8 text-xs font-semibold" />
                        </div>
                    </CardHeader>

                    <CardContent class="p-0">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs border-collapse">
                                <thead>
                                    <tr class="bg-slate-100/80 dark:bg-slate-900/90 border-b text-[11px] font-black uppercase tracking-wider text-slate-700">
                                        <th class="py-3 px-3 w-10 text-center">#</th>
                                        <th class="py-3 px-3">DNI / Credencial</th>
                                        <th class="py-3 px-3">Participante</th>
                                        <th v-for="s in totalSessions" :key="s" class="py-3 px-2 text-center w-12">
                                            S{{ s }}
                                        </th>
                                        <th class="py-3 px-3 text-center">Asistidas</th>
                                        <th class="py-3 px-3 text-center">% Asist.</th>
                                        <th class="py-3 px-3 text-center">Cumple Req.</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200 dark:divide-slate-800 bg-white dark:bg-slate-950">
                                    <tr v-for="(enrollment, idx) in filteredEnrollments" :key="enrollment.id" class="hover:bg-rose-50/30">
                                        <td class="py-2.5 px-3 text-center font-bold text-slate-400">{{ idx + 1 }}</td>
                                        <td class="py-2.5 px-3 whitespace-nowrap">
                                            <span class="font-mono font-black text-xs px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-900 border">
                                                {{ enrollment.dni }}
                                            </span>
                                            <div v-if="enrollment.credential_code" class="text-[10px] text-slate-400 font-mono mt-0.5">
                                                {{ enrollment.credential_code }}
                                            </div>
                                        </td>
                                        <td class="py-2.5 px-3 font-black text-slate-950 dark:text-white">
                                            {{ enrollment.paterno }} {{ enrollment.materno || '' }}, {{ enrollment.nombres }}
                                        </td>
                                        <td v-for="s in totalSessions" :key="s" class="py-2.5 px-2 text-center">
                                            <span
                                                v-if="enrollment.attendance_records?.some(r => r.session_number === s && r.status === 'presente')"
                                                class="size-6 inline-flex items-center justify-center rounded-full bg-emerald-100 text-emerald-800 font-black text-xs"
                                                title="Presente"
                                            >
                                                ✓
                                            </span>
                                            <span
                                                v-else-if="enrollment.attendance_records?.some(r => r.session_number === s && r.status === 'tardanza')"
                                                class="size-6 inline-flex items-center justify-center rounded-full bg-amber-100 text-amber-800 font-black text-xs"
                                                title="Tardanza"
                                            >
                                                T
                                            </span>
                                            <span v-else class="size-6 inline-flex items-center justify-center rounded-full bg-slate-100 text-slate-400 text-xs font-bold">
                                                -
                                            </span>
                                        </td>
                                        <td class="py-2.5 px-3 text-center font-black">
                                            {{ enrollment.attended_sessions }} / {{ totalSessions }}
                                        </td>
                                        <td class="py-2.5 px-3 text-center font-black font-mono">
                                            {{ enrollment.attendance_percentage }}%
                                        </td>
                                        <td class="py-2.5 px-3 text-center">
                                            <Badge
                                                v-if="(enrollment.attendance_percentage || 0) >= (course.min_attendance_percentage || 75)"
                                                class="bg-emerald-100 text-emerald-900 border-emerald-300 font-black text-[10px]"
                                            >
                                                CUMPLE
                                            </Badge>
                                            <Badge v-else variant="outline" class="text-rose-900 border-rose-300 font-bold text-[10px]">
                                                INSUFICIENTE
                                            </Badge>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- CONTENIDO DE LA PESTAÑA 2: NOTAS Y ACTA OFICIAL (SIGC-5) -->
            <div v-if="activeTab === 'notas'" class="space-y-6">
                <div class="p-4 rounded-xl border border-rose-200 dark:border-rose-900 bg-rose-50/60 dark:bg-rose-950/30 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2 text-rose-950 dark:text-rose-200 font-black text-sm">
                            <ShieldCheck class="size-5 text-rose-800" />
                            Reglamento Oficial de Evaluación y Acreditación UNSAAC
                        </div>
                        <p class="text-xs text-rose-900/80 dark:text-rose-300">
                            Condición de Aprobación: <strong>Nota Final vigesimal >= 11.00</strong> Y <strong>Asistencia >= {{ course.min_attendance_percentage }}%</strong>.
                        </p>
                    </div>

                    <div v-if="can.manage_enrollments">
                        <Button
                            v-if="!isActaClosed"
                            size="sm"
                            :class="[THEME_BUTTONS.primary, 'text-xs font-black shadow-xs']"
                            @click="isCloseActaModalOpen = true"
                        >
                            <Lock class="mr-1.5 size-3.5" />
                            Cerrar Acta del Curso
                        </Button>
                        <div v-else class="text-xs font-bold text-emerald-800 flex items-center gap-1.5 bg-emerald-50 px-3 py-1.5 rounded-lg border border-emerald-200">
                            <CheckCheck class="size-4" />
                            <span>Acta cerrada oficialmente. Notas bloqueadas.</span>
                        </div>
                    </div>
                </div>

                <Card class="border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
                    <CardHeader class="p-4 border-b bg-slate-50/80 dark:bg-slate-900/80 flex flex-row items-center justify-between">
                        <div>
                            <CardTitle class="text-sm font-black">Nómina de Calificaciones Vigesimales (0 - 20)</CardTitle>
                            <CardDescription class="text-xs">Haga clic en el lápiz para ingresar o ajustar notas antes de cerrar el acta.</CardDescription>
                        </div>
                    </CardHeader>

                    <CardContent class="p-0">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs border-collapse">
                                <thead>
                                    <tr class="bg-slate-100/80 dark:bg-slate-900/90 border-b text-[11px] font-black uppercase tracking-wider text-slate-700">
                                        <th class="py-3 px-3 w-10 text-center">#</th>
                                        <th class="py-3 px-3">DNI</th>
                                        <th class="py-3 px-3">Apellidos y Nombres</th>
                                        <th class="py-3 px-3 text-center">Asistencia %</th>
                                        <th class="py-3 px-3 text-center">Nota Final (0-20)</th>
                                        <th class="py-3 px-3 text-center">Condición Oficial</th>
                                        <th v-if="!isActaClosed" class="py-3 px-3 text-right">Editar</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200 dark:divide-slate-800 bg-white dark:bg-slate-950">
                                    <tr v-for="(enrollment, idx) in filteredEnrollments" :key="enrollment.id" class="hover:bg-rose-50/30">
                                        <td class="py-2.5 px-3 text-center font-bold text-slate-400">{{ idx + 1 }}</td>
                                        <td class="py-2.5 px-3 font-mono font-black text-xs">{{ enrollment.dni }}</td>
                                        <td class="py-2.5 px-3 font-black text-slate-950 dark:text-white">
                                            {{ enrollment.paterno }} {{ enrollment.materno || '' }}, {{ enrollment.nombres }}
                                        </td>
                                        <td class="py-2.5 px-3 text-center font-black font-mono">
                                            {{ enrollment.attendance_percentage }}%
                                        </td>
                                        <td class="py-2.5 px-3 text-center whitespace-nowrap">
                                            <span
                                                v-if="enrollment.final_grade !== null && enrollment.final_grade !== undefined"
                                                :class="[
                                                    'font-mono font-black text-xs px-2.5 py-0.5 rounded border',
                                                    Number(enrollment.final_grade) >= 11
                                                        ? 'bg-rose-50 text-rose-950 border-rose-300'
                                                        : 'bg-red-50 text-red-900 border-red-300'
                                                ]"
                                            >
                                                {{ Number(enrollment.final_grade).toFixed(1) }}
                                            </span>
                                            <span v-else class="text-slate-400 font-bold">-</span>
                                        </td>
                                        <td class="py-2.5 px-3 text-center">
                                            <Badge
                                                v-if="enrollment.status === 'aprobado'"
                                                class="bg-emerald-600 text-white font-black text-[10px]"
                                            >
                                                APROBADO
                                            </Badge>
                                            <Badge
                                                v-else-if="enrollment.status === 'reprobado'"
                                                variant="destructive"
                                                class="font-black text-[10px]"
                                            >
                                                DESAPROBADO
                                            </Badge>
                                            <Badge v-else variant="outline" class="font-bold text-[10px]">
                                                {{ enrollment.status.toUpperCase() }}
                                            </Badge>
                                        </td>
                                        <td v-if="!isActaClosed" class="py-2.5 px-3 text-right">
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                class="h-7 w-7 p-0 text-slate-700 hover:text-rose-950"
                                                @click="openEditModal(enrollment)"
                                            >
                                                <Pencil class="size-3.5" />
                                            </Button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- CONTENIDO DE LA PESTAÑA 3: EMISIÓN DE CERTIFICADOS (SIGC-6) -->
            <div v-if="activeTab === 'certificados'" class="space-y-6">
                <div v-if="!isActaClosed" class="p-6 rounded-2xl border border-amber-300 bg-amber-50 dark:bg-amber-950/30 text-amber-950 dark:text-amber-200 space-y-2">
                    <div class="flex items-center gap-2 font-black text-sm">
                        <Lock class="size-5 text-amber-700" />
                        Emisión Bloqueada: El Acta Oficial aún no ha sido cerrada
                    </div>
                    <p class="text-xs">
                        Para garantizar la validez legal y académica de los certificados oficiales, primero debe cerrar el acta en la pestaña <strong>"2. Notas y Acta Oficial"</strong>.
                    </p>
                </div>

                <div v-else class="space-y-6">
                    <div class="p-5 rounded-2xl border border-emerald-300 bg-emerald-50 dark:bg-emerald-950/30 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div class="space-y-1">
                            <div class="font-black text-sm text-emerald-950 dark:text-emerald-200 flex items-center gap-2">
                                <Award class="size-5 text-emerald-700" />
                                {{ approvedStudents.length }} Participantes Aprobados Listos para Certificación Digital
                            </div>
                            <p class="text-xs text-emerald-800 dark:text-emerald-300">
                                Los certificados generados contienen código verificable QR y firma criptográfica SHA-256 única.
                            </p>
                        </div>

                        <Button
                            size="sm"
                            :disabled="isIssuingCertificates || approvedStudents.length === 0"
                            :class="[THEME_BUTTONS.primary, 'text-xs font-black shadow-xs shrink-0']"
                            @click="bulkIssueCertificates"
                        >
                            <Loader2 v-if="isIssuingCertificates" class="size-3.5 mr-1.5 animate-spin" />
                            <Award v-else class="size-3.5 mr-1.5" />
                            Emitir Certificados de {{ approvedStudents.length }} Aprobados
                        </Button>
                    </div>

                    <Card class="border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
                        <CardHeader class="p-4 border-b bg-slate-50/80">
                            <CardTitle class="text-sm font-black">Registro Oficial de Certificados Emitidos</CardTitle>
                        </CardHeader>
                        <CardContent class="p-0">
                            <div class="overflow-x-auto">
                                <table class="w-full text-left text-xs border-collapse">
                                    <thead>
                                        <tr class="bg-slate-100 border-b text-[11px] font-black uppercase text-slate-700">
                                            <th class="py-3 px-3 w-10 text-center">#</th>
                                            <th class="py-3 px-3">DNI</th>
                                            <th class="py-3 px-3">Participante</th>
                                            <th class="py-3 px-3">Código Oficial</th>
                                            <th class="py-3 px-3">Firma Digital SHA-256</th>
                                            <th class="py-3 px-3 text-right">Verificación</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-200 bg-white">
                                        <tr v-for="(enrollment, idx) in approvedStudents" :key="enrollment.id">
                                            <td class="py-2.5 px-3 text-center font-bold text-slate-400">{{ idx + 1 }}</td>
                                            <td class="py-2.5 px-3 font-mono font-bold">{{ enrollment.dni }}</td>
                                            <td class="py-2.5 px-3 font-black">{{ enrollment.paterno }} {{ enrollment.materno || '' }}, {{ enrollment.nombres }}</td>
                                            <td class="py-2.5 px-3 font-mono font-black text-rose-950">
                                                {{ enrollment.certificate_code || 'En proceso' }}
                                            </td>
                                            <td class="py-2.5 px-3 font-mono text-[10px] text-slate-500 max-w-xs truncate">
                                                {{ enrollment.certificate_hash || 'Pendiente de emisión' }}
                                            </td>
                                            <td class="py-2.5 px-3 text-right">
                                                <Button as-child size="sm" variant="ghost" class="h-7 text-xs font-bold text-rose-900">
                                                    <Link :href="`/certificates?dni=${enrollment.dni}`" target="_blank">
                                                        <ExternalLink class="size-3.5 mr-1" />
                                                        Verificar
                                                    </Link>
                                                </Button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <!-- CONTENIDO DE LA PESTAÑA 4: REPORTES Y ESTADÍSTICAS (SIGC-10) -->
            <div v-if="activeTab === 'reportes'" class="space-y-6">
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <div class="p-4 rounded-xl border bg-white dark:bg-slate-900 shadow-xs">
                        <div class="text-[11px] font-bold text-slate-500 uppercase">Matrícula Total</div>
                        <div class="text-2xl font-black text-slate-900 dark:text-white mt-1">{{ totalEnrolled }}</div>
                    </div>
                    <div class="p-4 rounded-xl border bg-white dark:bg-slate-900 shadow-xs">
                        <div class="text-[11px] font-bold text-slate-500 uppercase">Asistencia Promedio</div>
                        <div class="text-2xl font-black text-amber-700 mt-1">{{ averageAttendance }}%</div>
                    </div>
                    <div class="p-4 rounded-xl border bg-white dark:bg-slate-900 shadow-xs">
                        <div class="text-[11px] font-bold text-slate-500 uppercase">Aprobados</div>
                        <div class="text-2xl font-black text-rose-900 mt-1">{{ approvedStudents.length }}</div>
                    </div>
                    <div class="p-4 rounded-xl border bg-white dark:bg-slate-900 shadow-xs">
                        <div class="text-[11px] font-bold text-slate-500 uppercase">Desaprobados</div>
                        <div class="text-2xl font-black text-red-600 mt-1">{{ failedStudents.length }}</div>
                    </div>
                </div>

                <Card class="border-slate-200 dark:border-slate-800 shadow-sm">
                    <CardHeader class="pb-3 border-b">
                        <CardTitle class="text-base font-black flex items-center gap-2">
                            <FileSpreadsheet class="size-4 text-rose-900" />
                            Exportación de Archivos Oficiales para Excel / Trámites UNSAAC
                        </CardTitle>
                        <CardDescription class="text-xs">
                            Descargue las sábanas de datos en formato CSV con codificación UTF-8 compatible con Microsoft Excel.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="pt-4 flex flex-col sm:flex-row gap-3">
                        <Button as-child size="sm" variant="outline" class="text-xs font-bold border-rose-300 text-rose-950">
                            <a :href="`/courses/${course.id}/reports/attendance-csv`" download>
                                <FileSpreadsheet class="size-3.5 mr-1.5" />
                                Exportar Matriz de Asistencia (.CSV)
                            </a>
                        </Button>

                        <Button as-child size="sm" variant="outline" class="text-xs font-bold border-rose-300 text-rose-950">
                            <a :href="`/courses/${course.id}/reports/acta-csv`" download>
                                <FileText class="size-3.5 mr-1.5" />
                                Exportar Acta Oficial de Notas (.CSV)
                            </a>
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- MODAL DE PROYECCIÓN QR EN PANTALLA GIGANTE (SIGC-4) -->
        <Dialog :open="isQrProjectorOpen" @update:open="isQrProjectorOpen = $event">
            <DialogContent class="w-[95vw] sm:max-w-xl p-0 rounded-3xl border border-rose-200 bg-white dark:bg-slate-950 overflow-hidden shadow-2xl">
                <div class="p-6 bg-gradient-to-br from-rose-950 via-rose-900 to-rose-950 text-white text-center space-y-2">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/10 text-xs font-black uppercase tracking-wider text-amber-300 border border-white/15">
                        <Clock class="size-3.5" />
                        Hora Oficial Cusco: {{ currentTime }}
                    </div>
                    <h2 class="text-2xl font-black tracking-tight">
                        Asistencia en Vivo · Sesión {{ selectedSession }}
                    </h2>
                    <p class="text-xs text-rose-200 max-w-md mx-auto">
                        {{ course.title }} ({{ course.code }})
                    </p>
                </div>

                <div class="p-6 flex flex-col items-center justify-center space-y-4">
                    <div class="p-4 bg-white rounded-2xl border-4 border-rose-950/20 shadow-md" v-html="sessionQrSvg"></div>

                    <div class="text-center space-y-1">
                        <div class="text-[11px] font-bold text-slate-500 uppercase">Código Alternativo para Alumnos</div>
                        <div class="font-mono text-xl font-black text-rose-950 bg-rose-50 px-4 py-1.5 rounded-lg border border-rose-200 tracking-wider">
                            SES-{{ course.id }}-0{{ selectedSession }}-{{ course.code.slice(-3) }}
                        </div>
                        <p class="text-[11px] text-slate-500 pt-1">
                            Escanea con tu cámara o ingresa tu DNI con el docente.
                        </p>
                    </div>

                    <Button
                        size="sm"
                        variant="outline"
                        class="text-xs font-bold"
                        @click="isQrProjectorOpen = false"
                    >
                        Cerrar Proyector
                    </Button>
                </div>
            </DialogContent>
        </Dialog>

        <!-- MODAL CONFIRMACIÓN CIERRE DE ACTA (SIGC-5) -->
        <Dialog :open="isCloseActaModalOpen" @update:open="isCloseActaModalOpen = $event">
            <DialogContent class="w-[94vw] sm:max-w-md p-6 rounded-2xl bg-white dark:bg-slate-950 space-y-4">
                <DialogHeader class="space-y-2">
                    <div class="size-11 rounded-full bg-rose-100 text-rose-900 flex items-center justify-center mx-auto">
                        <Lock class="size-6" />
                    </div>
                    <DialogTitle class="text-lg font-black text-center text-slate-950 dark:text-white">
                        ¿Cerrar Oficialmente el Acta del Curso?
                    </DialogTitle>
                    <DialogDescription class="text-xs text-center text-slate-600 leading-relaxed">
                        Esta acción es <strong>irreversible</strong> conforme a las normativas de certificación UNSAAC. Se calcularán automáticamente las condiciones de los participantes (Aprobado si Asistencia >= {{ course.min_attendance_percentage }}% y Nota >= 11), se bloquearán futuras modificaciones y se habilitará la emisión de certificados.
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter class="flex items-center justify-end gap-2 pt-2">
                    <Button variant="outline" size="sm" class="text-xs font-bold" @click="isCloseActaModalOpen = false">
                        Cancelar
                    </Button>
                    <Button
                        size="sm"
                        :disabled="isClosingActa"
                        :class="[THEME_BUTTONS.primary, 'text-xs font-black shadow-xs']"
                        @click="confirmCloseActa"
                    >
                        <Loader2 v-if="isClosingActa" class="size-3.5 mr-1.5 animate-spin" />
                        <Lock v-else class="size-3.5 mr-1.5" />
                        Sí, Cerrar Acta Oficialmente
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- MODAL CRUD EDITAR MATRÍCULA -->
        <Dialog :open="isEditModalOpen" @update:open="isEditModalOpen = $event">
            <DialogContent class="w-[94vw] sm:max-w-lg p-0 rounded-2xl shadow-2xl border bg-white dark:bg-slate-950 overflow-hidden">
                <div class="p-5 border-b bg-slate-50 dark:bg-slate-900">
                    <DialogHeader class="text-left space-y-1">
                        <div class="flex items-center gap-2">
                            <span class="font-mono text-xs font-black px-2 py-0.5 rounded bg-rose-100 text-rose-950">
                                DNI: {{ editingEnrollment?.dni }}
                            </span>
                            <span v-if="editingEnrollment?.credential_code" class="font-mono text-xs font-bold text-slate-500">
                                {{ editingEnrollment.credential_code }}
                            </span>
                        </div>
                        <DialogTitle class="text-base font-black text-slate-950 pt-1">
                            Editar Matrícula: {{ editingEnrollment?.nombres }} {{ editingEnrollment?.paterno }}
                        </DialogTitle>
                    </DialogHeader>
                </div>

                <form @submit.prevent="saveEnrollment" class="p-5 space-y-4 text-xs">
                    <div v-if="editError" class="p-3 bg-red-50 border border-red-200 rounded-xl text-red-700 font-bold">
                        {{ editError }}
                    </div>

                    <div class="space-y-1.5">
                        <Label for="edit-status" class="font-bold">Estado</Label>
                        <select
                            id="edit-status"
                            v-model="editForm.status"
                            class="w-full h-9 rounded-md border border-slate-300 bg-white px-3 py-1 text-xs font-bold"
                        >
                            <option value="inscrito">Inscrito</option>
                            <option value="en_curso">En Curso</option>
                            <option value="aprobado">Aprobado</option>
                            <option value="reprobado">Reprobado</option>
                            <option value="cancelado">Cancelado</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <Label for="edit-sessions" class="font-bold">Sesiones Asistidas</Label>
                            <Input id="edit-sessions" v-model.number="editForm.attended_sessions" type="number" min="0" max="100" class="h-9 text-xs font-bold" />
                        </div>
                        <div class="space-y-1.5">
                            <Label for="edit-grade" class="font-bold">Nota Final (0 - 20)</Label>
                            <Input id="edit-grade" v-model="editForm.final_grade" type="number" step="0.1" min="0" max="20" placeholder="Ej: 16.5" class="h-9 text-xs font-bold" />
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="edit-cert" class="font-bold">Código de Certificado (Opcional)</Label>
                        <Input id="edit-cert" v-model="editForm.certificate_code" type="text" placeholder="Ej: CERT-2026-UNSAAC-..." class="h-9 font-mono text-xs font-bold" />
                    </div>

                    <DialogFooter class="pt-3 border-t flex justify-end gap-2">
                        <Button type="button" variant="outline" size="sm" class="text-xs font-bold" @click="isEditModalOpen = false">Cancelar</Button>
                        <Button type="submit" size="sm" :disabled="isSaving" :class="[THEME_BUTTONS.primary, 'text-xs font-black']">
                            <Loader2 v-if="isSaving" class="size-3.5 mr-1.5 animate-spin" />
                            <Check v-else class="size-3.5 mr-1.5" />
                            Guardar
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
            @enrolled="router.reload()"
        />
    </AppLayout>
</template>
