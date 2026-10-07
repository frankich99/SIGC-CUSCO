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
    UserPlus,
    Phone,
    Mail,
    Filter,
    Sparkles,
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

type AcademicTab = 'matriculados' | 'asistencia' | 'notas' | 'certificados' | 'reportes';
const activeTab = ref<AcademicTab>('matriculados');

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
const statusFilter = ref<'todos' | 'inscrito' | 'en_curso' | 'aprobado' | 'reprobado' | 'cancelado'>('todos');

const enrollmentsList = computed(() => props.course.enrollments || []);
const totalEnrolled = computed(() => enrollmentsList.value.length);
const registeredStudents = computed(() => enrollmentsList.value.filter((e) => e.status === 'inscrito'));
const inProgressStudents = computed(() => enrollmentsList.value.filter((e) => e.status === 'en_curso'));
const approvedStudents = computed(() => enrollmentsList.value.filter((e) => e.status === 'aprobado'));
const failedStudents = computed(() => enrollmentsList.value.filter((e) => e.status === 'reprobado'));
const cancelledStudents = computed(() => enrollmentsList.value.filter((e) => e.status === 'cancelado'));
const availableSpots = computed(() => Math.max(0, props.course.capacity - totalEnrolled.value));

const averageAttendance = computed(() => {
    if (enrollmentsList.value.length === 0) return 0;
    const total = enrollmentsList.value.reduce((acc, curr) => acc + (curr.attendance_percentage || 0), 0);
    return Math.round(total / enrollmentsList.value.length);
});

const isActaClosed = computed(() => Boolean(props.course.acta_closed_at));

// Filtro general por búsqueda (usado en matriz de asistencias y actas)
const filteredEnrollments = computed(() => {
    if (!searchQuery.value.trim()) return enrollmentsList.value;
    const q = searchQuery.value.toLowerCase().trim();
    return enrollmentsList.value.filter((e) => {
        const full = `${e.nombres} ${e.paterno} ${e.materno || ''}`.toLowerCase();
        return (
            full.includes(q) ||
            (e.dni || '').includes(q) ||
            (e.email || '').toLowerCase().includes(q) ||
            (e.phone || '').includes(q) ||
            (e.credential_code || '').toLowerCase().includes(q)
        );
    });
});

// Filtro específico para el Padrón de Matriculados con Selector de Estado
const matriculadosFilteredEnrollments = computed(() => {
    let list = filteredEnrollments.value;
    if (statusFilter.value !== 'todos') {
        list = list.filter((e) => e.status === statusFilter.value);
    }
    return list;
});

// Modal de Inscripción Rápida
const isEnrollModalOpen = ref(false);

// Modal y CRUD: Editar Matrícula
const isEditModalOpen = ref(false);
const editingEnrollment = ref<EnrollmentItem | null>(null);
const editForm = ref({
    status: 'inscrito' as 'inscrito' | 'en_curso' | 'aprobado' | 'reprobado' | 'cancelado',
    attended_sessions: 0,
    final_grade: '' as string | number,
    certificate_code: '',
    email: '',
    phone: '',
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
        email: enrollment.email || '',
        phone: enrollment.phone || '',
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
            email: editForm.value.email.trim() || null,
            phone: editForm.value.phone.trim() || null,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                isSaving.value = false;
                isEditModalOpen.value = false;
            },
            onError: (errs) => {
                isSaving.value = false;
                editError.value = Object.values(errs)[0] as string || 'Error al actualizar la matrícula.';
            },
        }
    );
}

// Asistencia Rápida (+1 sesión)
const isRecordingQuickAttendance = ref<number | null>(null);
function quickRecordAttendance(enrollment: EnrollmentItem) {
    isRecordingQuickAttendance.value = enrollment.id;
    router.post(
        `/enrollments/${enrollment.id}/attendance`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                isRecordingQuickAttendance.value = null;
            },
        }
    );
}

// Emitir Certificado Individual para participante aprobado
const isIssuingSingleCert = ref<number | null>(null);
function issueSingleCertificate(enrollment: EnrollmentItem) {
    if (!confirm(`¿Emitir certificado oficial con código único para ${enrollment.nombres} ${enrollment.paterno}?`)) return;
    isIssuingSingleCert.value = enrollment.id;
    router.post(
        `/enrollments/${enrollment.id}/certificate`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                isIssuingSingleCert.value = null;
            },
        }
    );
}

// Modal y CRUD: Eliminar / Desmatricular Participante
const isDeleteModalOpen = ref(false);
const enrollmentToDelete = ref<EnrollmentItem | null>(null);
const isDeletingEnrollment = ref(false);

function openDeleteModal(enrollment: EnrollmentItem) {
    enrollmentToDelete.value = enrollment;
    isDeleteModalOpen.value = true;
}

function confirmDeleteEnrollment() {
    if (!enrollmentToDelete.value) return;
    isDeletingEnrollment.value = true;
    router.delete(
        `/enrollments/${enrollmentToDelete.value.id}`,
        {
            preserveScroll: true,
            onSuccess: () => {
                isDeletingEnrollment.value = false;
                isDeleteModalOpen.value = false;
                enrollmentToDelete.value = null;
            },
            onError: () => {
                isDeletingEnrollment.value = false;
                alert('No se pudo eliminar la matrícula.');
            },
        }
    );
}

// Modal: Ver Credencial Digital con Código QR
const isCredentialModalOpen = ref(false);
const credentialEnrollment = ref<EnrollmentItem | null>(null);

function viewCredential(enrollment: EnrollmentItem) {
    credentialEnrollment.value = enrollment;
    isCredentialModalOpen.value = true;
}

const credentialQrSvg = computed(() => {
    if (!credentialEnrollment.value) return '';
    const code = credentialEnrollment.value.credential_code || `INS-${credentialEnrollment.value.course_id}-${credentialEnrollment.value.dni.slice(-4)}`;
    return generateQrSvg(code, 260, '#800020');
});

// Estilo de Badges de Estado
function getStatusBadge(status: string) {
    switch (status) {
        case 'inscrito':
            return {
                label: 'Inscrito',
                classes: 'bg-blue-100 text-blue-900 border-blue-300 dark:bg-blue-950 dark:text-blue-200 dark:border-blue-800',
            };
        case 'en_curso':
            return {
                label: 'En Curso',
                classes: 'bg-amber-100 text-amber-950 border-amber-300 dark:bg-amber-950 dark:text-amber-200 dark:border-amber-800',
            };
        case 'aprobado':
            return {
                label: 'Aprobado',
                classes: 'bg-emerald-100 text-emerald-950 border-emerald-300 dark:bg-emerald-950 dark:text-emerald-200 dark:border-emerald-800',
            };
        case 'reprobado':
            return {
                label: 'Reprobado',
                classes: 'bg-rose-100 text-rose-950 border-rose-300 dark:bg-rose-950 dark:text-rose-200 dark:border-rose-800',
            };
        case 'cancelado':
            return {
                label: 'Cancelado',
                classes: 'bg-slate-100 text-slate-800 border-slate-300 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700',
            };
        default:
            return {
                label: (status || '').toUpperCase(),
                classes: 'bg-slate-100 text-slate-800 border-slate-300',
            };
    }
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

            <!-- NAVEGACIÓN POR 5 PESTAÑAS ACADÉMICAS OFICIALES -->
            <div class="border-b border-slate-200 dark:border-slate-800">
                <nav class="flex space-x-2 sm:space-x-4 overflow-x-auto pb-px" aria-label="Tabs">
                    <button
                        type="button"
                        @click="activeTab = 'matriculados'"
                        :class="[
                            'whitespace-nowrap py-3 px-4 border-b-2 font-black text-xs sm:text-sm flex items-center gap-2 cursor-pointer transition-all',
                            activeTab === 'matriculados'
                                ? 'border-rose-900 text-rose-900 dark:text-rose-300 dark:border-rose-500'
                                : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'
                        ]"
                    >
                        <Users class="size-4" />
                        1. Padrón de Matriculados
                        <span class="bg-rose-100 text-rose-950 dark:bg-rose-950 dark:text-rose-200 text-[10px] px-2 py-0.5 rounded-full font-bold">
                            {{ totalEnrolled }}
                        </span>
                    </button>

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
                        2. Asistencia de Sesiones
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
                        3. Notas y Acta Oficial
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
                        4. Emisión de Certificados
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
                        5. Reportes y Estadísticas
                    </button>
                </nav>
            </div>

            <!-- ============================================================ -->
            <!-- CONTENIDO DE LA PESTAÑA 1: PADRÓN DE MATRICULADOS (CRUD)     -->
            <!-- ============================================================ -->
            <div v-if="activeTab === 'matriculados'" class="space-y-6">
                <!-- 4 Tarjetas de Métricas del Padrón -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Total Matriculados -->
                    <Card class="border-2 border-rose-300 dark:border-rose-900 bg-rose-50/50 dark:bg-rose-950/20 shadow-xs">
                        <CardHeader class="pb-2">
                            <CardDescription class="text-xs font-bold text-rose-950 dark:text-rose-300 flex items-center justify-between">
                                <span>Total Inscritos</span>
                                <div class="size-7 rounded-lg bg-rose-900 text-white flex items-center justify-center shadow-xs">
                                    <Users class="size-3.5" />
                                </div>
                            </CardDescription>
                            <CardTitle class="text-2xl font-black text-slate-900 dark:text-white pt-1 flex items-baseline gap-2">
                                <span>{{ totalEnrolled }}</span>
                                <span class="text-xs font-semibold text-slate-500">/ {{ course.capacity }} vacantes</span>
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="text-xs text-slate-600 dark:text-slate-400">
                            <div class="w-full bg-slate-200 dark:bg-slate-800 h-1.5 rounded-full overflow-hidden mt-1">
                                <div
                                    class="h-full bg-rose-900 rounded-full transition-all"
                                    :style="{ width: `${Math.min(100, (totalEnrolled / (course.capacity || 1)) * 100)}%` }"
                                />
                            </div>
                        </CardContent>
                    </Card>

                    <!-- En Curso -->
                    <Card class="border-2 border-amber-300 dark:border-amber-900 bg-amber-50/50 dark:bg-amber-950/20 shadow-xs">
                        <CardHeader class="pb-2">
                            <CardDescription class="text-xs font-bold text-amber-950 dark:text-amber-300 flex items-center justify-between">
                                <span>Alumnos En Curso</span>
                                <div class="size-7 rounded-lg bg-amber-600 text-white flex items-center justify-center shadow-xs">
                                    <Calendar class="size-3.5" />
                                </div>
                            </CardDescription>
                            <CardTitle class="text-2xl font-black text-slate-900 dark:text-white pt-1">
                                {{ inProgressStudents.length }}
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="text-xs font-semibold text-amber-800 dark:text-amber-300">
                            Asistiendo regularmente a clases
                        </CardContent>
                    </Card>

                    <!-- Aprobados -->
                    <Card class="border-2 border-emerald-300 dark:border-emerald-900 bg-emerald-50/50 dark:bg-emerald-950/20 shadow-xs">
                        <CardHeader class="pb-2">
                            <CardDescription class="text-xs font-bold text-emerald-950 dark:text-emerald-300 flex items-center justify-between">
                                <span>Aprobados / Aptos</span>
                                <div class="size-7 rounded-lg bg-emerald-700 text-white flex items-center justify-center shadow-xs">
                                    <Award class="size-3.5" />
                                </div>
                            </CardDescription>
                            <CardTitle class="text-2xl font-black text-slate-900 dark:text-white pt-1">
                                {{ approvedStudents.length }}
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="text-xs font-semibold text-emerald-800 dark:text-emerald-300">
                            Cumplen nota >= 11 y asistencia >= {{ course.min_attendance_percentage }}%
                        </CardContent>
                    </Card>

                    <!-- Vacantes Disponibles -->
                    <Card class="border-2 border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-xs">
                        <CardHeader class="pb-2">
                            <CardDescription class="text-xs font-bold text-slate-700 dark:text-slate-300 flex items-center justify-between">
                                <span>Vacantes Libres</span>
                                <div class="size-7 rounded-lg bg-slate-800 text-white flex items-center justify-center shadow-xs">
                                    <CheckCircle2 class="size-3.5" />
                                </div>
                            </CardDescription>
                            <CardTitle class="text-2xl font-black text-rose-900 dark:text-rose-400 pt-1">
                                {{ availableSpots }}
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="text-xs font-semibold text-slate-600 dark:text-slate-400">
                            Cupos disponibles para inscripción
                        </CardContent>
                    </Card>
                </div>

                <!-- Card Principal: Tabla Padrón de Matriculados -->
                <Card class="border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
                    <CardHeader class="p-4 sm:p-5 border-b bg-slate-50/80 dark:bg-slate-900/80 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                        <div>
                            <CardTitle class="text-base font-black flex items-center gap-2">
                                <Users class="size-4 text-rose-900" />
                                Padrón de Participantes Inscritos (CRUD Oficial)
                            </CardTitle>
                            <CardDescription class="text-xs mt-0.5">
                                Lista nominal de personas matriculadas con control de asistencia, notas y acciones de gestión académica.
                            </CardDescription>
                        </div>

                        <!-- Botones de Acción de Cabecera -->
                        <div class="flex flex-wrap items-center gap-2">
                            <Button
                                v-if="can.manage_enrollments"
                                size="sm"
                                :class="[THEME_BUTTONS.primary, 'text-xs font-black shadow-xs']"
                                @click="isEnrollModalOpen = true"
                            >
                                <UserPlus class="mr-1.5 size-3.5" />
                                + Inscribir Participante
                            </Button>
                            <Button
                                as-child
                                variant="outline"
                                size="sm"
                                class="text-xs font-bold border-slate-300 hover:text-rose-900"
                            >
                                <a :href="`/courses/${course.id}/reports/attendance-csv`" target="_blank">
                                    <FileSpreadsheet class="mr-1.5 size-3.5 text-emerald-700" />
                                    Descargar CSV
                                </a>
                            </Button>
                        </div>
                    </CardHeader>

                    <!-- Barra de Búsqueda y Filtros de Estado -->
                    <div class="p-4 bg-white dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800 flex flex-col sm:flex-row items-center justify-between gap-3">
                        <div class="relative w-full sm:w-80">
                            <Search class="size-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
                            <Input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Buscar por DNI, nombres, email o teléfono..."
                                class="h-9 pl-9 text-xs font-medium"
                            />
                        </div>

                        <!-- Selector de Filtro de Estado -->
                        <div class="flex items-center gap-2 w-full sm:w-auto">
                            <span class="text-xs font-bold text-slate-600 dark:text-slate-400 whitespace-nowrap flex items-center gap-1">
                                <Filter class="size-3.5" />
                                Estado:
                            </span>
                            <select
                                v-model="statusFilter"
                                class="h-9 px-3 rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-xs font-bold text-slate-800 dark:text-slate-200 cursor-pointer w-full sm:w-auto"
                            >
                                <option value="todos">Todos los Estados ({{ totalEnrolled }})</option>
                                <option value="inscrito">Inscritos ({{ registeredStudents.length }})</option>
                                <option value="en_curso">En Curso ({{ inProgressStudents.length }})</option>
                                <option value="aprobado">Aprobados ({{ approvedStudents.length }})</option>
                                <option value="reprobado">Reprobados ({{ failedStudents.length }})</option>
                                <option value="cancelado">Cancelados ({{ cancelledStudents.length }})</option>
                            </select>
                        </div>
                    </div>

                    <!-- Contenido de la Tabla -->
                    <CardContent class="p-0">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs border-collapse">
                                <thead>
                                    <tr class="bg-slate-100/90 dark:bg-slate-900 border-b text-[11px] font-black uppercase tracking-wider text-slate-700 dark:text-slate-300">
                                        <th class="py-3 px-3 w-10 text-center">#</th>
                                        <th class="py-3 px-3">DNI / Credencial</th>
                                        <th class="py-3 px-3">Participante</th>
                                        <th class="py-3 px-3">Contacto</th>
                                        <th class="py-3 px-3 text-center">Asistencias</th>
                                        <th class="py-3 px-3 text-center">Nota Final</th>
                                        <th class="py-3 px-3 text-center">Estado</th>
                                        <th v-if="can.manage_enrollments" class="py-3 px-3 text-right">Acciones (CRUD)</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200 dark:divide-slate-800 bg-white dark:bg-slate-950">
                                    <tr
                                        v-for="(enrollment, idx) in matriculadosFilteredEnrollments"
                                        :key="enrollment.id"
                                        class="hover:bg-rose-50/40 dark:hover:bg-rose-950/20 transition-colors"
                                    >
                                        <!-- # -->
                                        <td class="py-3 px-3 text-center font-bold text-slate-400">
                                            {{ idx + 1 }}
                                        </td>

                                        <!-- DNI / Credencial -->
                                        <td class="py-3 px-3 whitespace-nowrap space-y-1">
                                            <div class="flex items-center gap-1.5">
                                                <span class="font-mono font-black text-xs px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-700">
                                                    {{ enrollment.dni }}
                                                </span>
                                                <button
                                                    type="button"
                                                    @click="viewCredential(enrollment)"
                                                    title="Ver Credencial Digital y QR"
                                                    class="text-rose-900 dark:text-rose-400 hover:text-rose-700 p-0.5 rounded hover:bg-rose-100 dark:hover:bg-rose-950/60 cursor-pointer"
                                                >
                                                    <QrCode class="size-3.5" />
                                                </button>
                                            </div>
                                            <div v-if="enrollment.credential_code" class="text-[10px] text-slate-400 font-mono">
                                                {{ enrollment.credential_code }}
                                            </div>
                                        </td>

                                        <!-- Participante -->
                                        <td class="py-3 px-3">
                                            <div class="font-black text-slate-950 dark:text-white leading-snug">
                                                {{ enrollment.paterno }} {{ enrollment.materno || '' }}, {{ enrollment.nombres }}
                                            </div>
                                            <div v-if="enrollment.certificate_code" class="text-[10px] font-mono text-emerald-700 dark:text-emerald-400 font-bold flex items-center gap-1 mt-0.5">
                                                <Award class="size-3" />
                                                <span>{{ enrollment.certificate_code }}</span>
                                            </div>
                                        </td>

                                        <!-- Contacto -->
                                        <td class="py-3 px-3 whitespace-nowrap text-xs space-y-0.5">
                                            <div class="flex items-center gap-1.5 text-slate-700 dark:text-slate-300">
                                                <Mail class="size-3 text-slate-400 shrink-0" />
                                                <a :href="`mailto:${enrollment.email}`" class="hover:underline hover:text-rose-900 font-medium truncate max-w-[180px] inline-block">
                                                    {{ enrollment.email }}
                                                </a>
                                            </div>
                                            <div v-if="enrollment.phone" class="flex items-center gap-1.5 text-slate-600 dark:text-slate-400 text-[11px]">
                                                <Phone class="size-3 text-slate-400 shrink-0" />
                                                <a :href="`https://wa.me/51${enrollment.phone}`" target="_blank" class="hover:underline hover:text-emerald-700 font-mono font-bold">
                                                    {{ enrollment.phone }}
                                                </a>
                                            </div>
                                        </td>

                                        <!-- Asistencias -->
                                        <td class="py-3 px-3 text-center whitespace-nowrap">
                                            <div class="font-black text-slate-900 dark:text-white">
                                                {{ enrollment.attended_sessions }} / {{ totalSessions }}
                                            </div>
                                            <div class="text-[10px] font-bold font-mono text-slate-500">
                                                {{ enrollment.attendance_percentage }}%
                                            </div>
                                        </td>

                                        <!-- Nota Final -->
                                        <td class="py-3 px-3 text-center whitespace-nowrap">
                                            <span
                                                v-if="enrollment.final_grade !== null && enrollment.final_grade !== undefined"
                                                :class="[
                                                    'font-mono font-black text-xs px-2 py-0.5 rounded border',
                                                    Number(enrollment.final_grade) >= 11
                                                        ? 'bg-rose-50 text-rose-950 border-rose-300 dark:bg-rose-950 dark:text-rose-200 dark:border-rose-800'
                                                        : 'bg-red-50 text-red-900 border-red-300 dark:bg-red-950 dark:text-red-200 dark:border-red-800'
                                                ]"
                                            >
                                                {{ Number(enrollment.final_grade).toFixed(1) }}
                                            </span>
                                            <span v-else class="text-slate-400 font-bold">-</span>
                                        </td>

                                        <!-- Estado Oficial -->
                                        <td class="py-3 px-3 text-center whitespace-nowrap">
                                            <Badge
                                                variant="outline"
                                                :class="['text-[11px] font-black uppercase px-2 py-0.5', getStatusBadge(enrollment.status).classes]"
                                            >
                                                {{ getStatusBadge(enrollment.status).label }}
                                            </Badge>
                                        </td>

                                        <!-- Acciones (CRUD) -->
                                        <td v-if="can.manage_enrollments" class="py-3 px-3 text-right whitespace-nowrap">
                                            <div class="flex items-center justify-end gap-1.5">
                                                <!-- Asistencia Rápida (+1) -->
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    class="h-8 px-2 text-xs font-bold text-emerald-800 hover:text-emerald-950 hover:bg-emerald-50 dark:text-emerald-400"
                                                    title="Registrar +1 asistencia rápida"
                                                    :disabled="isRecordingQuickAttendance === enrollment.id"
                                                    @click="quickRecordAttendance(enrollment)"
                                                >
                                                    <Loader2 v-if="isRecordingQuickAttendance === enrollment.id" class="size-3.5 animate-spin" />
                                                    <span v-else class="flex items-center gap-1">
                                                        <Plus class="size-3" />
                                                        <span class="hidden md:inline">Asist.</span>
                                                    </span>
                                                </Button>

                                                <!-- Emitir Certificado si califica y no lo tiene -->
                                                <Button
                                                    v-if="enrollment.status === 'aprobado' && !enrollment.certificate_code"
                                                    size="sm"
                                                    variant="ghost"
                                                    class="h-8 px-2 text-xs font-bold text-amber-800 hover:text-amber-950 hover:bg-amber-50"
                                                    title="Emitir certificado oficial"
                                                    :disabled="isIssuingSingleCert === enrollment.id"
                                                    @click="issueSingleCertificate(enrollment)"
                                                >
                                                    <Loader2 v-if="isIssuingSingleCert === enrollment.id" class="size-3.5 animate-spin" />
                                                    <Award v-else class="size-3.5" />
                                                </Button>

                                                <!-- Editar Matrícula -->
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    class="h-8 w-8 p-0 text-slate-700 hover:text-rose-950 hover:bg-rose-50 dark:text-slate-300"
                                                    title="Editar datos de matrícula"
                                                    @click="openEditModal(enrollment)"
                                                >
                                                    <Pencil class="size-3.5" />
                                                </Button>

                                                <!-- Eliminar Matrícula -->
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    class="h-8 w-8 p-0 text-rose-700 hover:text-rose-950 hover:bg-rose-100 dark:text-rose-400"
                                                    title="Eliminar o desmatricular participante"
                                                    @click="openDeleteModal(enrollment)"
                                                >
                                                    <Trash2 class="size-3.5" />
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>

                            <!-- Estado Vacío Si no hay participantes filtrados -->
                            <div
                                v-if="matriculadosFilteredEnrollments.length === 0"
                                class="p-10 text-center space-y-3 bg-white dark:bg-slate-950"
                            >
                                <div class="size-14 rounded-full bg-rose-50 dark:bg-rose-950 text-rose-900 dark:text-rose-300 flex items-center justify-center mx-auto">
                                    <Users class="size-7" />
                                </div>
                                <h4 class="text-sm font-black text-slate-900 dark:text-white">
                                    No se encontraron participantes inscritos
                                </h4>
                                <p class="text-xs text-slate-600 dark:text-slate-400 max-w-sm mx-auto">
                                    <span v-if="searchQuery || statusFilter !== 'todos'">
                                        No hay participantes que coincidan con la búsqueda o el filtro seleccionado.
                                    </span>
                                    <span v-else>
                                        Aún no hay inscripciones registradas en esta capacitación. Puedes inscribir al primer alumno haciendo clic abajo.
                                    </span>
                                </p>
                                <div class="pt-2 flex items-center justify-center gap-2">
                                    <Button
                                        v-if="searchQuery || statusFilter !== 'todos'"
                                        variant="outline"
                                        size="sm"
                                        class="text-xs font-bold"
                                        @click="searchQuery = ''; statusFilter = 'todos'"
                                    >
                                        Limpiar Filtros
                                    </Button>
                                    <Button
                                        v-if="can.manage_enrollments"
                                        size="sm"
                                        :class="[THEME_BUTTONS.primary, 'text-xs font-black']"
                                        @click="isEnrollModalOpen = true"
                                    >
                                        <UserPlus class="mr-1.5 size-3.5" />
                                        Inscribir Primer Alumno
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- CONTENIDO DE LA PESTAÑA 2: ASISTENCIA DE SESIONES (SIGC-4 / SIGC-12) -->
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
                            <Label for="edit-email" class="font-bold">Correo Electrónico</Label>
                            <Input id="edit-email" v-model="editForm.email" type="email" placeholder="correo@ejemplo.com" class="h-9 text-xs font-medium" />
                        </div>
                        <div class="space-y-1.5">
                            <Label for="edit-phone" class="font-bold">Teléfono / WhatsApp</Label>
                            <Input id="edit-phone" v-model="editForm.phone" type="text" maxlength="9" placeholder="9XXXXXXXX" class="h-9 text-xs font-medium font-mono" />
                        </div>
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

        <!-- MODAL CRUD ELIMINAR / DESMATRICULAR PARTICIPANTE -->
        <Dialog :open="isDeleteModalOpen" @update:open="isDeleteModalOpen = $event">
            <DialogContent class="w-[94vw] sm:max-w-md p-6 rounded-2xl shadow-2xl border bg-white dark:bg-slate-950">
                <DialogHeader class="text-left space-y-2">
                    <div class="size-12 rounded-full bg-rose-100 dark:bg-rose-950 text-rose-900 dark:text-rose-200 flex items-center justify-center mx-auto shadow-inner">
                        <Trash2 class="size-6" />
                    </div>
                    <DialogTitle class="text-lg font-black text-center text-slate-950 dark:text-white">
                        ¿Desmatricular Participante?
                    </DialogTitle>
                    <DialogDescription class="text-xs text-center text-slate-600 dark:text-slate-400 leading-relaxed">
                        Se dará de baja la matrícula de <strong class="text-slate-950 dark:text-white">{{ enrollmentToDelete?.nombres }} {{ enrollmentToDelete?.paterno }}</strong> (DNI: {{ enrollmentToDelete?.dni }}). Esta acción liberará una vacante en el aforo oficial.
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter class="pt-4 flex flex-col sm:flex-row items-center justify-end gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="w-full sm:w-auto text-xs font-bold"
                        @click="isDeleteModalOpen = false"
                        :disabled="isDeletingEnrollment"
                    >
                        Cancelar
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        size="sm"
                        class="w-full sm:w-auto text-xs font-black bg-rose-900 hover:bg-rose-950 text-white shadow-md"
                        :disabled="isDeletingEnrollment"
                        @click="confirmDeleteEnrollment"
                    >
                        <Loader2 v-if="isDeletingEnrollment" class="size-3.5 mr-1.5 animate-spin" />
                        <Trash2 v-else class="size-3.5 mr-1.5" />
                        Sí, Desmatricular
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- MODAL VER CREDENCIAL DIGITAL / QR DEL PARTICIPANTE -->
        <Dialog :open="isCredentialModalOpen" @update:open="isCredentialModalOpen = $event">
            <DialogContent class="w-[94vw] sm:max-w-md p-0 rounded-3xl shadow-2xl border border-rose-200 bg-white dark:bg-slate-950 overflow-hidden">
                <div class="p-6 bg-gradient-to-br from-rose-950 via-rose-900 to-rose-950 text-white text-center space-y-1.5">
                    <span class="text-[10px] font-black uppercase tracking-wider bg-white/20 px-3 py-0.5 rounded-full inline-block">
                        SIGC-CUSCO • Acreditación Digital
                    </span>
                    <h3 class="text-lg font-black pt-1">Credencial Oficial de Alumno</h3>
                    <p class="text-xs text-rose-200 line-clamp-1">
                        {{ course.title }}
                    </p>
                </div>

                <div class="p-6 text-center space-y-4">
                    <div class="p-4 bg-white rounded-2xl border-2 border-dashed border-rose-200 inline-block shadow-inner mx-auto">
                        <div v-html="credentialQrSvg" class="flex justify-center" />
                        <div class="font-mono text-xs font-black text-rose-950 mt-2">
                            {{ credentialEnrollment?.credential_code }}
                        </div>
                    </div>

                    <div class="text-xs space-y-1 text-slate-800 dark:text-slate-200">
                        <div class="font-black text-base text-slate-950 dark:text-white">
                            {{ credentialEnrollment?.nombres }} {{ credentialEnrollment?.paterno }} {{ credentialEnrollment?.materno || '' }}
                        </div>
                        <div>
                            DNI: <strong class="font-mono text-sm">{{ credentialEnrollment?.dni }}</strong>
                        </div>
                        <div v-if="credentialEnrollment?.email" class="text-slate-500 font-medium">
                            {{ credentialEnrollment?.email }}
                        </div>
                    </div>

                    <div class="pt-2 border-t">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="text-xs font-bold w-full"
                            @click="isCredentialModalOpen = false"
                        >
                            Cerrar Credencial
                        </Button>
                    </div>
                </div>
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
