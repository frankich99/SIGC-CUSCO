<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
import { credentialQr } from '@/actions/App/Http/Controllers/EnrollmentController';
import { sessionQr } from '@/actions/App/Http/Controllers/CourseAcademicController';
import { notify } from '@/lib/notify';
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
    Copy,
    Eye,
} from '@lucide/vue';
import type { BreadcrumbItem } from '@/types';
import CoursePublicView, {
    type MyEnrollmentInfo,
} from '@/components/CoursePublicView.vue';
import type { CertificateModule } from '@/lib/certificateTemplate';

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
    enrollments_count?: number;
    acta_closed_at?: string | null;
    acta_closed_by?: number | null;
    acta_closer?: { name: string; paterno?: string } | null;
    instructor?: Instructor;
    instructor_name?: string;
    instructor_display_name?: string;
    enrollments?: EnrollmentItem[];
}

const props = withDefaults(
    defineProps<{
        course: CourseDetail;
        isStaff?: boolean;
        myEnrollment?: MyEnrollmentInfo | null;
        modules?: CertificateModule[];
        can: {
            update: boolean;
            delete: boolean;
            manage_enrollments?: boolean;
            close_acta?: boolean;
            issue_certificates?: boolean;
            reopen_acta?: boolean;
        };
    }>(),
    {
        isStaff: false,
        myEnrollment: null,
        modules: () => [],
    },
);

const staffViewMode = ref<'management' | 'public'>('management');

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Panel Principal', href: '/dashboard' },
    { title: 'Capacitaciones', href: '/courses' },
    { title: props.course.title, href: `/courses/${props.course.id}` },
];

type AcademicTab =
    | 'matriculados'
    | 'asistencia'
    | 'notas'
    | 'certificados'
    | 'reportes';
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

const sessionQrSvg = ref('');
const isLoadingQr = ref(false);
const qrLoadError = ref<string | null>(null);

async function loadSessionQr() {
    if (!isQrProjectorOpen.value) return;

    isLoadingQr.value = true;
    qrLoadError.value = null;
    sessionQrSvg.value = '';

    try {
        const response = await fetch(
            sessionQr.url({
                course: props.course.id,
                session: selectedSession.value,
            }),
            {
                headers: { Accept: 'application/json' },
            },
        );
        const data = await response.json();

        if (!response.ok || !data.svg) {
            throw new Error(
                data?.message ||
                    'No se pudo generar el código QR de la sesión.',
            );
        }

        sessionQrSvg.value = data.svg;
    } catch (e) {
        qrLoadError.value =
            e instanceof Error
                ? e.message
                : 'No se pudo generar el código QR de la sesión.';
    } finally {
        isLoadingQr.value = false;
    }
}

const isOnline = ref(
    typeof navigator !== 'undefined' ? navigator.onLine : true,
);
interface OfflineAttendanceItem {
    identifier: string;
    session_number: number;
    status: 'presente' | 'tardanza';
    recorded_at: string;
    timestamp: number;
}
const offlineQueueKey = computed(
    () => `sigc_offline_attendance_${props.course.id}`,
);
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
        localStorage.setItem(
            offlineQueueKey.value,
            JSON.stringify(offlineQueue.value),
        );
    } catch (e) {
        console.error('Error guardando cola offline', e);
    }
}

const manualIdentifier = ref('');
const manualStatus = ref<'presente' | 'tardanza'>('presente');
const isSubmittingAttendance = ref(false);
const attendanceFeedback = ref<{
    type: 'success' | 'error' | 'warning';
    text: string;
} | null>(null);

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
        notify.warning(
            'Asistencia sin conexión',
            `Guardada localmente para ${id}. Se sincronizará al tener red.`,
            3000,
        );
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
                notify.success(
                    'Asistencia registrada',
                    `Sesión ${selectedSession.value} para ${id}`,
                    1800,
                );
            },
            onError: (errs) => {
                isSubmittingAttendance.value = false;
                const msg =
                    Object.values(errs)[0] ||
                    'No se pudo registrar la asistencia.';
                attendanceFeedback.value = {
                    type: 'error',
                    text: msg as string,
                };
                notify.error('Error al registrar', msg as string, 3000);
            },
        },
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
                notify.success(
                    'Sincronización completada',
                    `Se sincronizaron ${count} registros guardados.`,
                    2500,
                );
            },
            onError: () => {
                isSyncing.value = false;
                notify.error(
                    'Error de sincronización',
                    'Ocurrió un problema durante la sincronización.',
                    3000,
                );
            },
        },
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
                notify.success(
                    'Acta oficial cerrada',
                    'Calificaciones y asistencias selladas con valor legal.',
                    2500,
                );
            },
            onError: () => {
                isClosingActa.value = false;
                notify.error(
                    'Error al cerrar acta',
                    'No se pudo cerrar el acta del curso.',
                    3000,
                );
            },
        },
    );
}

const isReopenActaModalOpen = ref(false);
const isReopeningActa = ref(false);

function confirmReopenActa() {
    isReopeningActa.value = true;
    router.post(
        `/courses/${props.course.id}/acta/reopen`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                isReopeningActa.value = false;
                isReopenActaModalOpen.value = false;
                notify.success(
                    'Acta oficial reactivada',
                    'El acta se encuentra en modo edición para realizar correcciones o modificaciones.',
                    3000,
                );
            },
            onError: () => {
                isReopeningActa.value = false;
                notify.error(
                    'Error al reabrir acta',
                    'No se pudo reactivar el acta del curso.',
                    3000,
                );
            },
        },
    );
}

const isIssuingCertificates = ref(false);
function bulkIssueCertificates() {
    if (
        !confirm(
            '¿Emitir los certificados digitales oficiales para todos los participantes aprobados?',
        )
    ) {
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
                notify.success(
                    'Certificados emitidos',
                    'Certificados digitales oficiales generados para participantes aprobados.',
                    2500,
                );
            },
            onError: () => {
                isIssuingCertificates.value = false;
                notify.error(
                    'Error en emisión',
                    'No se pudieron emitir los certificados masivos.',
                    3000,
                );
            },
        },
    );
}

const searchQuery = ref('');
const statusFilter = ref<
    'todos' | 'inscrito' | 'en_curso' | 'aprobado' | 'reprobado' | 'cancelado'
>('todos');

const enrollmentsList = computed(() => props.course.enrollments || []);
const totalEnrolled = computed(() => enrollmentsList.value.length);
const registeredStudents = computed(() =>
    enrollmentsList.value.filter((e) => e.status === 'inscrito'),
);
const inProgressStudents = computed(() =>
    enrollmentsList.value.filter((e) => e.status === 'en_curso'),
);
const approvedStudents = computed(() =>
    enrollmentsList.value.filter((e) => e.status === 'aprobado'),
);
const failedStudents = computed(() =>
    enrollmentsList.value.filter((e) => e.status === 'reprobado'),
);
const cancelledStudents = computed(() =>
    enrollmentsList.value.filter((e) => e.status === 'cancelado'),
);
const availableSpots = computed(() =>
    Math.max(0, props.course.capacity - totalEnrolled.value),
);

const averageAttendance = computed(() => {
    if (enrollmentsList.value.length === 0) return 0;
    const total = enrollmentsList.value.reduce(
        (acc, curr) => acc + (curr.attendance_percentage || 0),
        0,
    );
    return Math.round(total / enrollmentsList.value.length);
});

const isActaClosed = computed(() => Boolean(props.course.acta_closed_at));

// Filtro general por búsqueda (usado en matriz de asistencias y actas)
const filteredEnrollments = computed(() => {
    if (!searchQuery.value.trim()) return enrollmentsList.value;
    const q = searchQuery.value.toLowerCase().trim();
    return enrollmentsList.value.filter((e) => {
        const full =
            `${e.nombres} ${e.paterno} ${e.materno || ''}`.toLowerCase();
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
    status: 'inscrito' as
        | 'inscrito'
        | 'en_curso'
        | 'aprobado'
        | 'reprobado'
        | 'cancelado',
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
        final_grade:
            enrollment.final_grade !== null &&
            enrollment.final_grade !== undefined
                ? enrollment.final_grade
                : '',
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
            final_grade:
                editForm.value.final_grade === ''
                    ? null
                    : Number(editForm.value.final_grade),
            certificate_code: editForm.value.certificate_code.trim() || null,
            email: editForm.value.email.trim() || null,
            phone: editForm.value.phone.trim() || null,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                isSaving.value = false;
                isEditModalOpen.value = false;
                notify.success(
                    'Matrícula actualizada',
                    'Los datos del participante fueron guardados.',
                    1800,
                );
            },
            onError: (errs) => {
                isSaving.value = false;
                editError.value =
                    (Object.values(errs)[0] as string) ||
                    'Error al actualizar la matrícula.';
                notify.error('Error al actualizar', editError.value, 3000);
            },
        },
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
            onSuccess: () => {
                notify.success(
                    'Asistencia rápida (+1)',
                    `Registrada para ${enrollment.nombres} ${enrollment.paterno}`,
                    1500,
                );
            },
            onFinish: () => {
                isRecordingQuickAttendance.value = null;
            },
        },
    );
}

// Emitir Certificado Individual para participante aprobado
const isIssuingSingleCert = ref<number | null>(null);
function issueSingleCertificate(enrollment: EnrollmentItem) {
    if (
        !confirm(
            `¿Emitir certificado oficial con código único para ${enrollment.nombres} ${enrollment.paterno}?`,
        )
    )
        return;
    isIssuingSingleCert.value = enrollment.id;
    router.post(
        `/enrollments/${enrollment.id}/certificate`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                notify.success(
                    'Certificado generado',
                    `Emitido para ${enrollment.nombres} ${enrollment.paterno}`,
                    2000,
                );
            },
            onFinish: () => {
                isIssuingSingleCert.value = null;
            },
        },
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
    router.delete(`/enrollments/${enrollmentToDelete.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            isDeletingEnrollment.value = false;
            isDeleteModalOpen.value = false;
            enrollmentToDelete.value = null;
            notify.success(
                'Matrícula eliminada',
                'El participante fue desmatriculado con éxito.',
                2000,
            );
        },
        onError: () => {
            isDeletingEnrollment.value = false;
            notify.error(
                'Error al desmatricular',
                'No se pudo eliminar la matrícula del participante.',
                3000,
            );
        },
    });
}

// Modal: Ver Credencial Digital con Código QR
const isCredentialModalOpen = ref(false);
const credentialEnrollment = ref<EnrollmentItem | null>(null);

const credentialQrSvg = ref('');
const credentialQrCode = ref('');
const isCredentialQrLoading = ref(false);

function viewCredential(enrollment: EnrollmentItem) {
    credentialEnrollment.value = enrollment;
    isCredentialModalOpen.value = true;
    credentialQrSvg.value = '';
    credentialQrCode.value = '';
    isCredentialQrLoading.value = true;

    fetch(credentialQr.url(enrollment.id), {
        headers: { Accept: 'application/json' },
    })
        .then((response) => {
            if (!response.ok) {
                throw new Error('No se pudo generar el QR de la credencial.');
            }
            return response.json();
        })
        .then((data) => {
            credentialQrSvg.value = data.svg || '';
            credentialQrCode.value =
                data.code || enrollment.credential_code || '';
        })
        .catch(() => {
            credentialQrSvg.value = '';
        })
        .finally(() => {
            isCredentialQrLoading.value = false;
        });
}

async function copyCredentialCode(code: string) {
    try {
        await navigator.clipboard.writeText(code);
        notify.success('Código copiado al portapapeles', code, 1500);
    } catch {
        notify.info('Código de acreditación', code, 2500);
    }
}

// Estilo de Badges de Estado
function getStatusBadge(status: string) {
    switch (status) {
        case 'inscrito':
            return {
                label: 'Inscrito',
                classes:
                    'bg-blue-100 text-blue-900 border-blue-300 dark:bg-blue-950 dark:text-blue-200 dark:border-blue-800',
            };
        case 'en_curso':
            return {
                label: 'En Curso',
                classes:
                    'bg-amber-100 text-amber-950 border-amber-300 dark:bg-amber-950 dark:text-amber-200 dark:border-amber-800',
            };
        case 'aprobado':
            return {
                label: 'Aprobado',
                classes:
                    'bg-emerald-100 text-emerald-950 border-emerald-300 dark:bg-emerald-950 dark:text-emerald-200 dark:border-emerald-800',
            };
        case 'reprobado':
            return {
                label: 'Reprobado',
                classes:
                    'bg-rose-100 text-rose-950 border-rose-300 dark:bg-rose-950 dark:text-rose-200 dark:border-rose-800',
            };
        case 'cancelado':
            return {
                label: 'Cancelado',
                classes:
                    'bg-slate-100 text-slate-800 border-slate-300 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700',
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
        window.addEventListener('online', () => {
            isOnline.value = true;
        });
        window.addEventListener('offline', () => {
            isOnline.value = false;
        });
    }
});

watch([selectedSession, isQrProjectorOpen], () => {
    if (isQrProjectorOpen.value) {
        loadSessionQr();
    }
});

onUnmounted(() => {
    if (timeInterval) clearInterval(timeInterval);
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`${course.title} - SIGC-CUSCO`" />

        <div
            class="mx-auto w-full max-w-7xl min-w-0 space-y-6 overflow-x-hidden px-4 py-6 sm:px-6 sm:py-8 lg:px-8"
        >
            <!-- Barra de alternancia de vista exclusiva para Docentes y Administradores (Staff) -->
            <div
                v-if="isStaff"
                class="flex flex-col gap-3 rounded-2xl border border-slate-800 bg-slate-900 p-3.5 text-white shadow-sm sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="flex items-center gap-2.5">
                    <div
                        class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-rose-900 text-amber-300"
                    >
                        <ShieldCheck class="size-4" />
                    </div>
                    <div>
                        <div class="text-xs font-black">
                            Personal Autorizado: Docente / Administrador
                        </div>
                        <div class="text-[11px] text-slate-300">
                            Alterna entre el panel de gestión académica
                            confidencial y la vista pública del curso.
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <Button
                        type="button"
                        size="sm"
                        :variant="
                            staffViewMode === 'management'
                                ? 'default'
                                : 'secondary'
                        "
                        class="cursor-pointer text-xs font-black"
                        :class="
                            staffViewMode === 'management'
                                ? 'bg-rose-900 text-white shadow-xs hover:bg-rose-950'
                                : 'bg-slate-800 text-slate-200'
                        "
                        @click="staffViewMode = 'management'"
                    >
                        <Users class="mr-1.5 size-3.5" />
                        Panel Académico (Staff)
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        :variant="
                            staffViewMode === 'public' ? 'default' : 'secondary'
                        "
                        class="cursor-pointer text-xs font-black"
                        :class="
                            staffViewMode === 'public'
                                ? 'bg-amber-600 text-slate-950 shadow-xs hover:bg-amber-500'
                                : 'bg-slate-800 text-slate-200'
                        "
                        @click="staffViewMode = 'public'"
                    >
                        <Eye class="mr-1.5 size-3.5" />
                        Vista Pública Informativa
                    </Button>
                </div>
            </div>

            <!-- 1. VISTA PÚBLICA INFORMATIVA (Público General, Visitantes, Alumnos, o Staff en modo preview) -->
            <div
                v-if="!isStaff || staffViewMode === 'public'"
                class="space-y-6"
            >
                <div class="flex items-center justify-between pb-1">
                    <Button
                        as-child
                        variant="ghost"
                        size="sm"
                        class="-ml-2 text-xs text-slate-600 dark:text-slate-400"
                    >
                        <Link href="/courses">
                            <ArrowLeft class="mr-1 size-3.5" />
                            Catálogo de Capacitaciones
                        </Link>
                    </Button>
                    <div v-if="can.update" class="flex items-center gap-2">
                        <Button
                            as-child
                            variant="outline"
                            size="sm"
                            class="text-xs font-bold"
                        >
                            <Link :href="`/courses/${course.id}/edit`">
                                <Pencil class="mr-1.5 size-3.5" />
                                Editar Curso
                            </Link>
                        </Button>
                    </div>
                </div>

                <CoursePublicView
                    :course="course"
                    :modules="modules || []"
                    :my-enrollment="myEnrollment"
                    @open-enrollment="isEnrollModalOpen = true"
                />
            </div>

            <!-- 2. PANEL PRIVADO DE GESTIÓN ACADÉMICA (Solo para Staff en modo management) -->
            <div
                v-else-if="isStaff && staffViewMode === 'management'"
                class="space-y-6"
            >
                <!-- Header Superior Oficial Granate Cusco -->
                <div
                    class="flex flex-col gap-4 border-b pb-5 md:flex-row md:items-center md:justify-between dark:border-slate-800"
                >
                    <div class="space-y-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <Button
                                as-child
                                variant="ghost"
                                size="sm"
                                class="-ml-2 text-xs text-slate-600 dark:text-slate-400"
                            >
                                <Link href="/courses">
                                    <ArrowLeft class="mr-1 size-3.5" />
                                    Catálogo de Capacitaciones
                                </Link>
                            </Button>
                            <span
                                class="rounded border border-rose-300 bg-rose-100 px-2 py-0.5 font-mono text-xs font-black text-rose-950 dark:border-rose-800 dark:bg-rose-950 dark:text-rose-200"
                            >
                                {{ course.code }}
                            </span>
                            <Badge
                                variant="outline"
                                class="border-rose-800 bg-rose-50 text-xs font-bold text-rose-900 dark:bg-rose-950"
                            >
                                {{ course.status.toUpperCase() }}
                            </Badge>
                            <template v-if="isActaClosed">
                                <Badge
                                    class="bg-emerald-700 text-xs font-bold text-white"
                                >
                                    <Lock class="mr-1 size-3" />
                                    Acta Cerrada Oficialmente
                                </Badge>
                                <Button
                                    v-if="can.reopen_acta"
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    class="h-6 cursor-pointer border-amber-600 bg-amber-50 px-2 text-[11px] font-bold text-amber-950 shadow-2xs hover:bg-amber-100 dark:bg-amber-950 dark:text-amber-200"
                                    title="Reabrir acta oficial para corregir calificaciones o asistencias"
                                    @click="isReopenActaModalOpen = true"
                                >
                                    <Unlock
                                        class="mr-1 size-3 text-amber-700"
                                    />
                                    Reabrir / Modificar Acta
                                </Button>
                            </template>
                            <Badge
                                v-else
                                variant="secondary"
                                class="text-xs font-bold"
                            >
                                <Unlock class="mr-1 size-3" />
                                Acta Abierta (En Edición)
                            </Badge>
                        </div>

                        <h1
                            class="text-2xl font-black tracking-tight text-slate-950 sm:text-3xl dark:text-white"
                        >
                            {{ course.title }}
                        </h1>

                        <div
                            v-if="course.institution"
                            class="flex items-center gap-1.5 text-xs font-bold text-rose-950 dark:text-rose-300"
                        >
                            <Building2 class="size-4 shrink-0 text-rose-800" />
                            <span
                                >Entidad Organizadora:
                                <strong>{{ course.institution }}</strong></span
                            >
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <Button
                            v-if="
                                course.status === 'abierto' ||
                                can.manage_enrollments
                            "
                            size="sm"
                            :class="[
                                THEME_BUTTONS.primary,
                                'text-xs font-bold shadow-xs',
                            ]"
                            @click="isEnrollModalOpen = true"
                        >
                            <Plus class="mr-1.5 size-3.5" />
                            Inscribir Participante
                        </Button>

                        <Button
                            v-if="can.update"
                            as-child
                            variant="outline"
                            size="sm"
                            class="text-xs font-bold"
                        >
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
                    :class="
                        !isOnline
                            ? 'border-amber-300 bg-amber-50 text-amber-950 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200'
                            : 'border-sky-300 bg-sky-50 text-sky-950 dark:border-sky-800 dark:bg-sky-950/40 dark:text-sky-200'
                    "
                >
                    <div
                        class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="flex items-center gap-3">
                            <div
                                class="flex size-9 shrink-0 items-center justify-center rounded-full"
                                :class="
                                    !isOnline
                                        ? 'bg-amber-200 text-amber-900 dark:bg-amber-900'
                                        : 'bg-sky-200 text-sky-900 dark:bg-sky-900'
                                "
                            >
                                <WifiOff v-if="!isOnline" class="size-5" />
                                <Wifi v-else class="size-5" />
                            </div>
                            <div>
                                <div class="text-sm font-black">
                                    <span v-if="!isOnline"
                                        >Modo Sin Conexión Activo (Trabajo
                                        Offline)</span
                                    >
                                    <span v-else
                                        >Conexión Restablecida · Sincronización
                                        Pendiente</span
                                    >
                                </div>
                                <div class="mt-0.5 text-xs">
                                    Hay
                                    <strong>{{ offlineQueue.length }}</strong>
                                    asistencia(s) guardadas localmente en este
                                    equipo.
                                </div>
                            </div>
                        </div>

                        <Button
                            v-if="offlineQueue.length > 0"
                            size="sm"
                            :disabled="!isOnline || isSyncing"
                            class="shrink-0 bg-amber-700 text-xs font-black text-white hover:bg-amber-800"
                            @click="syncOfflineQueue"
                        >
                            <RefreshCw
                                :class="[
                                    'mr-1.5 size-3.5',
                                    isSyncing ? 'animate-spin' : '',
                                ]"
                            />
                            {{
                                isSyncing
                                    ? 'Sincronizando...'
                                    : 'Sincronizar Ahora'
                            }}
                        </Button>
                    </div>
                    <div
                        v-if="syncSuccessMessage"
                        class="mt-2 text-xs font-bold text-emerald-800 dark:text-emerald-300"
                    >
                        {{ syncSuccessMessage }}
                    </div>
                </div>

                <!-- 4 TARJETAS DE MÉTRICAS GLOBALES DEL CURSO (POSICIÓN FIJA Y ESTABLE PARA TODAS LAS PESTAÑAS) -->
                <div
                    class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4"
                >
                    <!-- Total Matriculados -->
                    <Card
                        class="border-2 border-rose-300 bg-rose-50/50 shadow-xs dark:border-rose-900 dark:bg-rose-950/20"
                    >
                        <CardHeader class="pb-2">
                            <CardDescription
                                class="flex items-center justify-between text-xs font-bold text-rose-950 dark:text-rose-300"
                            >
                                <span>Total Inscritos</span>
                                <div
                                    class="flex size-7 items-center justify-center rounded-lg bg-rose-900 text-white shadow-xs"
                                >
                                    <Users class="size-3.5" />
                                </div>
                            </CardDescription>
                            <CardTitle
                                class="flex items-baseline gap-2 pt-1 text-2xl font-black text-slate-900 dark:text-white"
                            >
                                <span>{{ totalEnrolled }}</span>
                                <span
                                    class="text-xs font-semibold text-slate-500"
                                    >/ {{ course.capacity }} vacantes</span
                                >
                            </CardTitle>
                        </CardHeader>
                        <CardContent
                            class="text-xs text-slate-600 dark:text-slate-400"
                        >
                            <div
                                class="mt-1 h-1.5 w-full overflow-hidden rounded-full bg-slate-200 dark:bg-slate-800"
                            >
                                <div
                                    class="h-full rounded-full bg-rose-900 transition-all"
                                    :style="{
                                        width: `${Math.min(100, (totalEnrolled / (course.capacity || 1)) * 100)}%`,
                                    }"
                                />
                            </div>
                        </CardContent>
                    </Card>

                    <!-- En Curso -->
                    <Card
                        class="border-2 border-amber-300 bg-amber-50/50 shadow-xs dark:border-amber-900 dark:bg-amber-950/20"
                    >
                        <CardHeader class="pb-2">
                            <CardDescription
                                class="flex items-center justify-between text-xs font-bold text-amber-950 dark:text-amber-300"
                            >
                                <span>Alumnos En Curso</span>
                                <div
                                    class="flex size-7 items-center justify-center rounded-lg bg-amber-600 text-white shadow-xs"
                                >
                                    <Calendar class="size-3.5" />
                                </div>
                            </CardDescription>
                            <CardTitle
                                class="pt-1 text-2xl font-black text-slate-900 dark:text-white"
                            >
                                {{ inProgressStudents.length }}
                            </CardTitle>
                        </CardHeader>
                        <CardContent
                            class="text-xs font-semibold text-amber-800 dark:text-amber-300"
                        >
                            Asistiendo regularmente a clases
                        </CardContent>
                    </Card>

                    <!-- Aprobados -->
                    <Card
                        class="border-2 border-emerald-300 bg-emerald-50/50 shadow-xs dark:border-emerald-900 dark:bg-emerald-950/20"
                    >
                        <CardHeader class="pb-2">
                            <CardDescription
                                class="flex items-center justify-between text-xs font-bold text-emerald-950 dark:text-emerald-300"
                            >
                                <span>Aprobados / Aptos</span>
                                <div
                                    class="flex size-7 items-center justify-center rounded-lg bg-emerald-700 text-white shadow-xs"
                                >
                                    <Award class="size-3.5" />
                                </div>
                            </CardDescription>
                            <CardTitle
                                class="pt-1 text-2xl font-black text-slate-900 dark:text-white"
                            >
                                {{ approvedStudents.length }}
                            </CardTitle>
                        </CardHeader>
                        <CardContent
                            class="text-xs font-semibold text-emerald-800 dark:text-emerald-300"
                        >
                            Nota >= 11 y asistencia >=
                            {{ course.min_attendance_percentage }}%
                        </CardContent>
                    </Card>

                    <!-- Vacantes Disponibles -->
                    <Card
                        class="border-2 border-slate-200 bg-white shadow-xs dark:border-slate-800 dark:bg-slate-900"
                    >
                        <CardHeader class="pb-2">
                            <CardDescription
                                class="flex items-center justify-between text-xs font-bold text-slate-700 dark:text-slate-300"
                            >
                                <span>Vacantes Libres</span>
                                <div
                                    class="flex size-7 items-center justify-center rounded-lg bg-slate-800 text-white shadow-xs"
                                >
                                    <CheckCircle2 class="size-3.5" />
                                </div>
                            </CardDescription>
                            <CardTitle
                                class="pt-1 text-2xl font-black text-rose-900 dark:text-rose-400"
                            >
                                {{ availableSpots }}
                            </CardTitle>
                        </CardHeader>
                        <CardContent
                            class="text-xs font-semibold text-slate-600 dark:text-slate-400"
                        >
                            Cupos disponibles para inscripción
                        </CardContent>
                    </Card>
                </div>

                <!-- NAVEGACIÓN POR 5 PESTAÑAS ACADÉMICAS (POSICIÓN ESTABLE Y FIJA) -->
                <div
                    class="border-b border-slate-200 pb-1 dark:border-slate-800"
                >
                    <nav
                        class="flex flex-wrap items-center gap-1.5 sm:gap-2"
                        aria-label="Tabs"
                    >
                        <button
                            type="button"
                            @click="activeTab = 'matriculados'"
                            :class="[
                                'flex cursor-pointer items-center gap-2 rounded-xl px-3.5 py-2.5 text-xs font-black transition-all',
                                activeTab === 'matriculados'
                                    ? 'bg-rose-900 text-white shadow-sm ring-1 ring-rose-950'
                                    : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800',
                            ]"
                        >
                            <Users class="size-3.5" />
                            <span>Matriculados</span>
                            <span
                                :class="[
                                    'py-0.2 rounded-full px-1.5 text-[10px] font-bold',
                                    activeTab === 'matriculados'
                                        ? 'bg-white/20 text-white'
                                        : 'bg-rose-100 text-rose-900 dark:bg-rose-950 dark:text-rose-200',
                                ]"
                            >
                                {{ totalEnrolled }}
                            </span>
                        </button>

                        <button
                            type="button"
                            @click="activeTab = 'asistencia'"
                            :class="[
                                'flex cursor-pointer items-center gap-2 rounded-xl px-3.5 py-2.5 text-xs font-black transition-all',
                                activeTab === 'asistencia'
                                    ? 'bg-rose-900 text-white shadow-sm ring-1 ring-rose-950'
                                    : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800',
                            ]"
                        >
                            <QrCode class="size-3.5" />
                            <span>Asistencias</span>
                        </button>

                        <button
                            type="button"
                            @click="activeTab = 'notas'"
                            :class="[
                                'flex cursor-pointer items-center gap-2 rounded-xl px-3.5 py-2.5 text-xs font-black transition-all',
                                activeTab === 'notas'
                                    ? 'bg-rose-900 text-white shadow-sm ring-1 ring-rose-950'
                                    : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800',
                            ]"
                        >
                            <FileText class="size-3.5" />
                            <span>Notas y Acta</span>
                            <span
                                v-if="isActaClosed"
                                class="size-2 rounded-full bg-emerald-400"
                            ></span>
                        </button>

                        <button
                            type="button"
                            @click="activeTab = 'certificados'"
                            :class="[
                                'flex cursor-pointer items-center gap-2 rounded-xl px-3.5 py-2.5 text-xs font-black transition-all',
                                activeTab === 'certificados'
                                    ? 'bg-rose-900 text-white shadow-sm ring-1 ring-rose-950'
                                    : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800',
                            ]"
                        >
                            <Award class="size-3.5" />
                            <span>Certificados</span>
                            <span
                                :class="[
                                    'py-0.2 rounded-full px-1.5 text-[10px] font-bold',
                                    activeTab === 'certificados'
                                        ? 'bg-white/20 text-white'
                                        : 'bg-rose-100 text-rose-900 dark:bg-rose-950 dark:text-rose-200',
                                ]"
                            >
                                {{ approvedStudents.length }}
                            </span>
                        </button>

                        <button
                            type="button"
                            @click="activeTab = 'reportes'"
                            :class="[
                                'flex cursor-pointer items-center gap-2 rounded-xl px-3.5 py-2.5 text-xs font-black transition-all',
                                activeTab === 'reportes'
                                    ? 'bg-rose-900 text-white shadow-sm ring-1 ring-rose-950'
                                    : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800',
                            ]"
                        >
                            <FileSpreadsheet class="size-3.5" />
                            <span>Reportes CSV</span>
                        </button>
                    </nav>
                </div>

                <!-- ============================================================ -->
                <!-- CONTENIDO DE LA PESTAÑA 1: PADRÓN DE MATRICULADOS (CRUD)     -->
                <!-- ============================================================ -->
                <div v-if="activeTab === 'matriculados'" class="space-y-6">
                    <!-- Card Principal: Tabla Padrón de Matriculados (Misma Dimensión y Borde) -->
                    <Card
                        class="overflow-hidden border-slate-200 shadow-sm dark:border-slate-800"
                    >
                        <CardHeader
                            class="flex flex-col gap-4 border-b bg-slate-50/80 p-4 sm:p-5 lg:flex-row lg:items-center lg:justify-between dark:bg-slate-900/80"
                        >
                            <div>
                                <CardTitle
                                    class="flex items-center gap-2 text-base font-black"
                                >
                                    <Users class="size-4 text-rose-900" />
                                    Padrón de Participantes Inscritos (CRUD
                                    Oficial)
                                </CardTitle>
                                <CardDescription class="mt-0.5 text-xs">
                                    Lista nominal de personas matriculadas con
                                    control de asistencia, notas y acciones de
                                    gestión académica.
                                </CardDescription>
                            </div>

                            <!-- Botones de Acción de Cabecera -->
                            <div class="flex flex-wrap items-center gap-2">
                                <Button
                                    v-if="can.manage_enrollments"
                                    size="sm"
                                    :class="[
                                        THEME_BUTTONS.primary,
                                        'text-xs font-black shadow-xs',
                                    ]"
                                    @click="isEnrollModalOpen = true"
                                >
                                    <UserPlus class="mr-1.5 size-3.5" />
                                    + Inscribir Participante
                                </Button>
                                <Button
                                    as-child
                                    variant="outline"
                                    size="sm"
                                    class="border-slate-300 text-xs font-bold hover:text-rose-900"
                                >
                                    <a
                                        :href="`/courses/${course.id}/reports/attendance-csv`"
                                        target="_blank"
                                    >
                                        <FileSpreadsheet
                                            class="mr-1.5 size-3.5 text-emerald-700"
                                        />
                                        Descargar CSV
                                    </a>
                                </Button>
                            </div>
                        </CardHeader>

                        <!-- Barra de Búsqueda y Filtros de Estado -->
                        <div
                            class="flex flex-col items-center justify-between gap-3 border-b border-slate-200 bg-white p-4 sm:flex-row dark:border-slate-800 dark:bg-slate-950"
                        >
                            <div class="relative w-full sm:w-80">
                                <Search
                                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400"
                                />
                                <Input
                                    v-model="searchQuery"
                                    type="text"
                                    placeholder="Buscar por DNI, nombres, email o teléfono..."
                                    class="h-9 pl-9 text-xs font-medium"
                                />
                            </div>

                            <!-- Selector de Filtro de Estado -->
                            <div
                                class="flex w-full items-center gap-2 sm:w-auto"
                            >
                                <span
                                    class="flex items-center gap-1 text-xs font-bold whitespace-nowrap text-slate-600 dark:text-slate-400"
                                >
                                    <Filter class="size-3.5" />
                                    Estado:
                                </span>
                                <select
                                    v-model="statusFilter"
                                    class="h-9 w-full cursor-pointer rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800 sm:w-auto dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                                >
                                    <option value="todos">
                                        Todos los Estados ({{ totalEnrolled }})
                                    </option>
                                    <option value="inscrito">
                                        Inscritos ({{
                                            registeredStudents.length
                                        }})
                                    </option>
                                    <option value="en_curso">
                                        En Curso ({{
                                            inProgressStudents.length
                                        }})
                                    </option>
                                    <option value="aprobado">
                                        Aprobados ({{
                                            approvedStudents.length
                                        }})
                                    </option>
                                    <option value="reprobado">
                                        Reprobados ({{ failedStudents.length }})
                                    </option>
                                    <option value="cancelado">
                                        Cancelados ({{
                                            cancelledStudents.length
                                        }})
                                    </option>
                                </select>
                            </div>
                        </div>

                        <!-- Contenido de la Tabla Compacta (Con Scroll Horizontal Suave en Pantallas Estrechas) -->
                        <CardContent class="p-0">
                            <div class="w-full overflow-x-auto">
                                <table
                                    class="w-full border-collapse text-left text-xs"
                                >
                                    <thead>
                                        <tr
                                            class="border-b bg-slate-100/90 text-[11px] font-black tracking-wider text-slate-700 uppercase dark:bg-slate-900 dark:text-slate-300"
                                        >
                                            <th
                                                class="w-28 px-3 py-2.5 text-left"
                                            >
                                                DNI
                                            </th>
                                            <th class="px-3 py-2.5 text-left">
                                                Participante y Contacto
                                            </th>
                                            <th
                                                class="w-28 px-2 py-2.5 text-center"
                                            >
                                                Asistencia
                                            </th>
                                            <th
                                                class="w-16 px-2 py-2.5 text-center"
                                            >
                                                Nota
                                            </th>
                                            <th
                                                class="w-24 px-2 py-2.5 text-center"
                                            >
                                                Estado
                                            </th>
                                            <th
                                                v-if="can.manage_enrollments"
                                                class="w-28 px-3 py-2.5 text-right"
                                            >
                                                Acciones
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody
                                        class="divide-y divide-slate-200 bg-white dark:divide-slate-800 dark:bg-slate-950"
                                    >
                                        <tr
                                            v-for="enrollment in matriculadosFilteredEnrollments"
                                            :key="enrollment.id"
                                            class="transition-colors hover:bg-rose-50/40 dark:hover:bg-rose-950/20"
                                        >
                                            <!-- DNI -->
                                            <td
                                                class="px-3 py-2.5 whitespace-nowrap"
                                            >
                                                <div
                                                    class="flex items-center gap-1.5"
                                                >
                                                    <span
                                                        class="rounded border border-slate-200 bg-slate-100 px-2 py-0.5 font-mono text-xs font-black text-slate-900 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                                                    >
                                                        {{ enrollment.dni }}
                                                    </span>
                                                    <button
                                                        type="button"
                                                        @click="
                                                            viewCredential(
                                                                enrollment,
                                                            )
                                                        "
                                                        title="Ver Credencial con QR"
                                                        class="cursor-pointer rounded p-0.5 text-rose-900 hover:bg-rose-100 hover:text-rose-700 dark:text-rose-400 dark:hover:bg-rose-950/60"
                                                    >
                                                        <QrCode
                                                            class="size-3.5"
                                                        />
                                                    </button>
                                                </div>
                                            </td>

                                            <!-- Participante y Contacto -->
                                            <td class="px-3 py-2.5">
                                                <div
                                                    class="leading-tight font-black text-slate-950 dark:text-white"
                                                >
                                                    {{ enrollment.paterno }}
                                                    {{
                                                        enrollment.materno ||
                                                        ''
                                                    }}, {{ enrollment.nombres }}
                                                </div>
                                                <div
                                                    class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[11px] text-slate-500 dark:text-slate-400"
                                                >
                                                    <a
                                                        :href="`mailto:${enrollment.email}`"
                                                        class="max-w-[190px] truncate hover:text-rose-900 hover:underline"
                                                        :title="
                                                            enrollment.email
                                                        "
                                                    >
                                                        {{ enrollment.email }}
                                                    </a>
                                                    <span
                                                        v-if="enrollment.phone"
                                                        class="font-mono font-bold text-emerald-700 dark:text-emerald-400"
                                                    >
                                                        · {{ enrollment.phone }}
                                                    </span>
                                                    <span
                                                        v-if="
                                                            enrollment.certificate_code
                                                        "
                                                        class="font-mono font-bold text-amber-700 dark:text-amber-400"
                                                    >
                                                        · 📜
                                                        {{
                                                            enrollment.certificate_code
                                                        }}
                                                    </span>
                                                </div>
                                            </td>

                                            <!-- Asistencia -->
                                            <td
                                                class="px-2 py-2.5 text-center whitespace-nowrap"
                                            >
                                                <span
                                                    class="text-xs font-black text-slate-900 dark:text-white"
                                                >
                                                    {{
                                                        enrollment.attended_sessions
                                                    }}/{{ totalSessions }}
                                                </span>
                                                <span
                                                    class="ml-1 font-mono text-[10px] font-bold text-slate-500"
                                                >
                                                    ({{
                                                        enrollment.attendance_percentage
                                                    }}%)
                                                </span>
                                            </td>

                                            <!-- Nota -->
                                            <td
                                                class="px-2 py-2.5 text-center whitespace-nowrap"
                                            >
                                                <span
                                                    v-if="
                                                        enrollment.final_grade !==
                                                            null &&
                                                        enrollment.final_grade !==
                                                            undefined
                                                    "
                                                    :class="[
                                                        'rounded border px-1.5 py-0.5 font-mono text-xs font-black',
                                                        Number(
                                                            enrollment.final_grade,
                                                        ) >= 11
                                                            ? 'border-rose-300 bg-rose-50 text-rose-950 dark:border-rose-800 dark:bg-rose-950 dark:text-rose-200'
                                                            : 'border-red-300 bg-red-50 text-red-900 dark:border-red-800 dark:bg-red-950 dark:text-red-200',
                                                    ]"
                                                >
                                                    {{
                                                        Number(
                                                            enrollment.final_grade,
                                                        ).toFixed(1)
                                                    }}
                                                </span>
                                                <span
                                                    v-else
                                                    class="font-bold text-slate-400"
                                                    >-</span
                                                >
                                            </td>

                                            <!-- Estado -->
                                            <td
                                                class="px-2 py-2.5 text-center whitespace-nowrap"
                                            >
                                                <Badge
                                                    variant="outline"
                                                    :class="[
                                                        'px-2 py-0.5 text-[10px] font-black uppercase',
                                                        getStatusBadge(
                                                            enrollment.status,
                                                        ).classes,
                                                    ]"
                                                >
                                                    {{
                                                        getStatusBadge(
                                                            enrollment.status,
                                                        ).label
                                                    }}
                                                </Badge>
                                            </td>

                                            <!-- Acciones -->
                                            <td
                                                v-if="can.manage_enrollments"
                                                class="px-3 py-2.5 text-right whitespace-nowrap"
                                            >
                                                <div
                                                    class="flex items-center justify-end gap-1"
                                                >
                                                    <!-- Asistencia Rápida (+1) -->
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        class="h-7 w-7 p-0 text-emerald-800 hover:bg-emerald-50 hover:text-emerald-950 dark:text-emerald-400"
                                                        title="Registrar +1 asistencia rápida"
                                                        :disabled="
                                                            isRecordingQuickAttendance ===
                                                            enrollment.id
                                                        "
                                                        @click="
                                                            quickRecordAttendance(
                                                                enrollment,
                                                            )
                                                        "
                                                    >
                                                        <Loader2
                                                            v-if="
                                                                isRecordingQuickAttendance ===
                                                                enrollment.id
                                                            "
                                                            class="size-3 animate-spin"
                                                        />
                                                        <Plus
                                                            v-else
                                                            class="size-3.5"
                                                        />
                                                    </Button>

                                                    <!-- Emitir Certificado si califica y no lo tiene -->
                                                    <Button
                                                        v-if="
                                                            enrollment.status ===
                                                                'aprobado' &&
                                                            !enrollment.certificate_code
                                                        "
                                                        size="sm"
                                                        variant="ghost"
                                                        class="h-7 w-7 p-0 text-amber-800 hover:bg-amber-50 hover:text-amber-950"
                                                        title="Emitir certificado oficial"
                                                        :disabled="
                                                            isIssuingSingleCert ===
                                                            enrollment.id
                                                        "
                                                        @click="
                                                            issueSingleCertificate(
                                                                enrollment,
                                                            )
                                                        "
                                                    >
                                                        <Loader2
                                                            v-if="
                                                                isIssuingSingleCert ===
                                                                enrollment.id
                                                            "
                                                            class="size-3 animate-spin"
                                                        />
                                                        <Award
                                                            v-else
                                                            class="size-3.5"
                                                        />
                                                    </Button>

                                                    <!-- Editar Matrícula -->
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        class="h-7 w-7 p-0 text-slate-700 hover:bg-rose-50 hover:text-rose-950 dark:text-slate-300"
                                                        title="Editar matrícula"
                                                        @click="
                                                            openEditModal(
                                                                enrollment,
                                                            )
                                                        "
                                                    >
                                                        <Pencil
                                                            class="size-3.5"
                                                        />
                                                    </Button>

                                                    <!-- Eliminar Matrícula -->
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        class="h-7 w-7 p-0 text-rose-700 hover:bg-rose-100 hover:text-rose-950 dark:text-rose-400"
                                                        title="Desmatricular participante"
                                                        @click="
                                                            openDeleteModal(
                                                                enrollment,
                                                            )
                                                        "
                                                    >
                                                        <Trash2
                                                            class="size-3.5"
                                                        />
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>

                                <!-- Estado Vacío Si no hay participantes filtrados -->
                                <div
                                    v-if="
                                        matriculadosFilteredEnrollments.length ===
                                        0
                                    "
                                    class="space-y-3 bg-white p-10 text-center dark:bg-slate-950"
                                >
                                    <div
                                        class="mx-auto flex size-14 items-center justify-center rounded-full bg-rose-50 text-rose-900 dark:bg-rose-950 dark:text-rose-300"
                                    >
                                        <Users class="size-7" />
                                    </div>
                                    <h4
                                        class="text-sm font-black text-slate-900 dark:text-white"
                                    >
                                        No se encontraron participantes
                                        inscritos
                                    </h4>
                                    <p
                                        class="mx-auto max-w-sm text-xs text-slate-600 dark:text-slate-400"
                                    >
                                        <span
                                            v-if="
                                                searchQuery ||
                                                statusFilter !== 'todos'
                                            "
                                        >
                                            No hay participantes que coincidan
                                            con la búsqueda o el filtro
                                            seleccionado.
                                        </span>
                                        <span v-else>
                                            Aún no hay inscripciones registradas
                                            en esta capacitación. Puedes
                                            inscribir al primer alumno haciendo
                                            clic abajo.
                                        </span>
                                    </p>
                                    <div
                                        class="flex items-center justify-center gap-2 pt-2"
                                    >
                                        <Button
                                            v-if="
                                                searchQuery ||
                                                statusFilter !== 'todos'
                                            "
                                            variant="outline"
                                            size="sm"
                                            class="text-xs font-bold"
                                            @click="
                                                searchQuery = '';
                                                statusFilter = 'todos';
                                            "
                                        >
                                            Limpiar Filtros
                                        </Button>
                                        <Button
                                            v-if="can.manage_enrollments"
                                            size="sm"
                                            :class="[
                                                THEME_BUTTONS.primary,
                                                'text-xs font-black',
                                            ]"
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

                <!-- ============================================================ -->
                <!-- CONTENIDO DE LA PESTAÑA 2: ASISTENCIA DE SESIONES            -->
                <!-- ============================================================ -->
                <div v-if="activeTab === 'asistencia'" class="space-y-6">
                    <!-- Barra de Sesión Activa y Registro Manual Rápido -->
                    <div
                        class="space-y-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-xs dark:border-slate-800 dark:bg-slate-950"
                    >
                        <div
                            class="flex flex-col gap-3 border-b border-slate-100 pb-3 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800"
                        >
                            <div class="flex flex-wrap items-center gap-2">
                                <span
                                    class="text-xs font-black tracking-wider text-slate-800 uppercase dark:text-slate-200"
                                >
                                    Sesión de Control Activa:
                                </span>
                                <div
                                    class="flex flex-wrap items-center gap-1.5"
                                >
                                    <Button
                                        v-for="num in totalSessions"
                                        :key="num"
                                        size="sm"
                                        :variant="
                                            selectedSession === num
                                                ? 'default'
                                                : 'outline'
                                        "
                                        :class="
                                            selectedSession === num
                                                ? 'h-7 bg-rose-900 px-2.5 text-xs font-black text-white'
                                                : 'h-7 px-2.5 text-xs font-bold'
                                        "
                                        @click="selectedSession = num"
                                    >
                                        S{{ num }}
                                    </Button>
                                </div>
                            </div>

                            <Button
                                size="sm"
                                :class="[
                                    THEME_BUTTONS.primary,
                                    'h-8 shrink-0 text-xs font-black shadow-xs',
                                ]"
                                @click="isQrProjectorOpen = true"
                            >
                                <Maximize2 class="mr-1.5 size-3.5" />
                                Proyectar QR (Sesión {{ selectedSession }})
                            </Button>
                        </div>

                        <!-- Formulario de Registro Manual Rápido -->
                        <form
                            @submit.prevent="registerManualAttendance"
                            class="flex flex-col gap-2 sm:flex-row"
                        >
                            <div class="relative flex-1">
                                <Input
                                    v-model="manualIdentifier"
                                    type="text"
                                    placeholder="Ingresar DNI (8 dígitos) o código de credencial para registrar..."
                                    class="h-9 pl-3 text-xs font-bold"
                                    :disabled="isSubmittingAttendance"
                                />
                            </div>

                            <select
                                v-model="manualStatus"
                                class="h-9 cursor-pointer rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                            >
                                <option value="presente">Presente</option>
                                <option value="tardanza">Tardanza</option>
                            </select>

                            <Button
                                type="submit"
                                size="sm"
                                :disabled="
                                    !manualIdentifier.trim() ||
                                    isSubmittingAttendance
                                "
                                :class="[
                                    THEME_BUTTONS.primary,
                                    'h-9 shrink-0 px-4 text-xs font-black',
                                ]"
                            >
                                <Loader2
                                    v-if="isSubmittingAttendance"
                                    class="mr-1.5 size-3.5 animate-spin"
                                />
                                <UserCheck v-else class="mr-1.5 size-3.5" />
                                Registrar en Sesión {{ selectedSession }}
                            </Button>
                        </form>

                        <div
                            v-if="attendanceFeedback"
                            :class="[
                                'flex items-center gap-2 rounded-lg p-2.5 text-xs font-bold',
                                attendanceFeedback.type === 'success'
                                    ? 'border border-emerald-200 bg-emerald-50 text-emerald-800'
                                    : attendanceFeedback.type === 'warning'
                                      ? 'border border-amber-200 bg-amber-50 text-amber-900'
                                      : 'border border-red-200 bg-red-50 text-red-800',
                            ]"
                        >
                            <AlertCircle class="size-4 shrink-0" />
                            <span>{{ attendanceFeedback.text }}</span>
                        </div>
                    </div>

                    <!-- Card de la Matriz Integral de Asistencias (Misma Dimensión y Borde que las demás pestañas) -->
                    <Card
                        class="overflow-hidden border-slate-200 shadow-sm dark:border-slate-800"
                    >
                        <CardHeader
                            class="flex flex-col gap-4 border-b bg-slate-50/80 p-4 sm:p-5 lg:flex-row lg:items-center lg:justify-between dark:bg-slate-900/80"
                        >
                            <div>
                                <CardTitle
                                    class="flex items-center gap-2 text-base font-black"
                                >
                                    <QrCode class="size-4 text-rose-900" />
                                    Matriz Integral de Asistencia por
                                    Participante
                                </CardTitle>
                                <CardDescription class="mt-0.5 text-xs">
                                    Registro histórico sesión por sesión (Mínimo
                                    exigido:
                                    {{ course.min_attendance_percentage }}% de
                                    asistencia).
                                </CardDescription>
                            </div>
                            <Button
                                as-child
                                variant="outline"
                                size="sm"
                                class="border-slate-300 text-xs font-bold hover:text-rose-900"
                            >
                                <a
                                    :href="`/courses/${course.id}/reports/attendance-csv`"
                                    target="_blank"
                                >
                                    <FileSpreadsheet
                                        class="mr-1.5 size-3.5 text-emerald-700"
                                    />
                                    Descargar Matriz CSV
                                </a>
                            </Button>
                        </CardHeader>

                        <!-- Barra de Búsqueda -->
                        <div
                            class="flex items-center justify-between gap-3 border-b border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-950"
                        >
                            <div class="relative w-full sm:w-80">
                                <Search
                                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400"
                                />
                                <Input
                                    v-model="searchQuery"
                                    type="text"
                                    placeholder="Buscar por DNI o nombres..."
                                    class="h-9 pl-9 text-xs font-medium"
                                />
                            </div>
                            <span
                                class="text-xs font-bold whitespace-nowrap text-slate-500"
                            >
                                Total:
                                {{ filteredEnrollments.length }} registros
                            </span>
                        </div>

                        <!-- Tabla de Matriz de Asistencias -->
                        <CardContent class="p-0">
                            <div class="w-full overflow-x-auto">
                                <table
                                    class="w-full border-collapse text-left text-xs"
                                >
                                    <thead>
                                        <tr
                                            class="border-b bg-slate-100/90 text-[11px] font-black tracking-wider text-slate-700 uppercase dark:bg-slate-900 dark:text-slate-300"
                                        >
                                            <th
                                                class="w-28 px-3 py-2.5 text-left"
                                            >
                                                DNI
                                            </th>
                                            <th class="px-3 py-2.5 text-left">
                                                Participante
                                            </th>
                                            <th
                                                v-for="s in totalSessions"
                                                :key="s"
                                                class="w-9 px-1.5 py-2.5 text-center"
                                            >
                                                S{{ s }}
                                            </th>
                                            <th
                                                class="w-20 px-2 py-2.5 text-center"
                                            >
                                                Asistidas
                                            </th>
                                            <th
                                                class="w-16 px-2 py-2.5 text-center"
                                            >
                                                % Asist.
                                            </th>
                                            <th
                                                class="w-24 px-3 py-2.5 text-right"
                                            >
                                                Condición
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody
                                        class="divide-y divide-slate-200 bg-white dark:divide-slate-800 dark:bg-slate-950"
                                    >
                                        <tr
                                            v-for="enrollment in filteredEnrollments"
                                            :key="enrollment.id"
                                            class="transition-colors hover:bg-rose-50/40 dark:hover:bg-rose-950/20"
                                        >
                                            <td
                                                class="px-3 py-2.5 font-mono text-xs font-bold whitespace-nowrap"
                                            >
                                                {{ enrollment.dni }}
                                            </td>
                                            <td
                                                class="px-3 py-2.5 font-black text-slate-950 dark:text-white"
                                            >
                                                {{ enrollment.paterno }}
                                                {{ enrollment.materno || '' }},
                                                {{ enrollment.nombres }}
                                            </td>
                                            <td
                                                v-for="s in totalSessions"
                                                :key="s"
                                                class="px-1 py-2 text-center"
                                            >
                                                <span
                                                    v-if="
                                                        enrollment.attendance_records?.some(
                                                            (r) =>
                                                                r.session_number ===
                                                                    s &&
                                                                r.status ===
                                                                    'presente',
                                                        )
                                                    "
                                                    class="inline-flex size-5 items-center justify-center rounded-full bg-emerald-100 text-[11px] font-black text-emerald-800"
                                                    title="Presente"
                                                    >✓</span
                                                >
                                                <span
                                                    v-else-if="
                                                        enrollment.attendance_records?.some(
                                                            (r) =>
                                                                r.session_number ===
                                                                    s &&
                                                                r.status ===
                                                                    'tardanza',
                                                        )
                                                    "
                                                    class="inline-flex size-5 items-center justify-center rounded-full bg-amber-100 text-[11px] font-black text-amber-800"
                                                    title="Tardanza"
                                                    >T</span
                                                >
                                                <span
                                                    v-else
                                                    class="inline-flex size-5 items-center justify-center rounded-full bg-slate-100 text-[10px] text-slate-400"
                                                    >·</span
                                                >
                                            </td>
                                            <td
                                                class="px-2 py-2.5 text-center text-xs font-bold"
                                            >
                                                {{
                                                    enrollment.attended_sessions
                                                }}/{{ totalSessions }}
                                            </td>
                                            <td
                                                class="px-2 py-2.5 text-center font-mono text-xs font-black"
                                            >
                                                {{
                                                    enrollment.attendance_percentage
                                                }}%
                                            </td>
                                            <td
                                                class="px-3 py-2.5 text-right whitespace-nowrap"
                                            >
                                                <Badge
                                                    v-if="
                                                        (enrollment.attendance_percentage ||
                                                            0) >=
                                                        (course.min_attendance_percentage ||
                                                            75)
                                                    "
                                                    class="bg-emerald-600 text-[10px] font-black text-white"
                                                >
                                                    CUMPLE
                                                </Badge>
                                                <Badge
                                                    v-else
                                                    variant="outline"
                                                    class="border-rose-300 text-[10px] font-bold text-rose-900"
                                                >
                                                    FALTA
                                                </Badge>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- ============================================================ -->
                <!-- CONTENIDO DE LA PESTAÑA 3: NOTAS Y ACTA OFICIAL              -->
                <!-- ============================================================ -->
                <div v-if="activeTab === 'notas'" class="space-y-6">
                    <!-- Card Principal con Misma Dimensión y Borde -->
                    <Card
                        class="overflow-hidden border-slate-200 shadow-sm dark:border-slate-800"
                    >
                        <CardHeader
                            class="flex flex-col gap-4 border-b bg-slate-50/80 p-4 sm:p-5 lg:flex-row lg:items-center lg:justify-between dark:bg-slate-900/80"
                        >
                            <div>
                                <CardTitle
                                    class="flex items-center gap-2 text-base font-black"
                                >
                                    <FileText class="size-4 text-rose-900" />
                                    Nómina Oficial de Calificaciones Vigesimales
                                    (0 - 20)
                                </CardTitle>
                                <CardDescription class="mt-0.5 text-xs">
                                    Reglamento UNSAAC: Aprobación con Nota
                                    vigesimal >= 11.00 y Asistencia >=
                                    {{ course.min_attendance_percentage }}%.
                                </CardDescription>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <Button
                                    v-if="
                                        can.manage_enrollments && !isActaClosed
                                    "
                                    size="sm"
                                    :class="[
                                        THEME_BUTTONS.primary,
                                        'text-xs font-black shadow-xs',
                                    ]"
                                    @click="isCloseActaModalOpen = true"
                                >
                                    <Lock class="mr-1.5 size-3.5" />
                                    Cerrar Acta Oficial del Curso
                                </Button>
                                <template v-else-if="isActaClosed">
                                    <Badge
                                        class="bg-emerald-700 px-2.5 py-1 text-xs font-bold text-white"
                                    >
                                        <Lock class="mr-1 size-3" />
                                        Acta Cerrada Oficialmente
                                    </Badge>
                                    <Button
                                        v-if="can.reopen_acta"
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        class="cursor-pointer border-amber-600 bg-amber-50 text-xs font-bold text-amber-950 shadow-xs hover:bg-amber-100 dark:bg-amber-950 dark:text-amber-200"
                                        title="Reabrir acta oficial para corregir o modificar notas y calificaciones"
                                        @click="isReopenActaModalOpen = true"
                                    >
                                        <Unlock
                                            class="mr-1.5 size-3.5 text-amber-700"
                                        />
                                        Reabrir / Modificar Acta
                                    </Button>
                                </template>
                                <Button
                                    as-child
                                    variant="outline"
                                    size="sm"
                                    class="border-slate-300 text-xs font-bold hover:text-rose-900"
                                >
                                    <a
                                        :href="`/courses/${course.id}/reports/acta-csv`"
                                        target="_blank"
                                    >
                                        <FileSpreadsheet
                                            class="mr-1.5 size-3.5 text-emerald-700"
                                        />
                                        Descargar Acta CSV
                                    </a>
                                </Button>
                            </div>
                        </CardHeader>

                        <!-- Barra de Búsqueda y Filtros -->
                        <div
                            class="flex flex-col items-center justify-between gap-3 border-b border-slate-200 bg-white p-4 sm:flex-row dark:border-slate-800 dark:bg-slate-950"
                        >
                            <div class="relative w-full sm:w-80">
                                <Search
                                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400"
                                />
                                <Input
                                    v-model="searchQuery"
                                    type="text"
                                    placeholder="Buscar por DNI o nombres..."
                                    class="h-9 pl-9 text-xs font-medium"
                                />
                            </div>
                            <div
                                class="flex items-center gap-2 text-xs font-bold"
                            >
                                <span class="text-emerald-700"
                                    >Aprobados:
                                    {{ approvedStudents.length }}</span
                                >
                                <span class="text-slate-300">|</span>
                                <span class="text-rose-700"
                                    >Reprobados:
                                    {{ failedStudents.length }}</span
                                >
                            </div>
                        </div>

                        <!-- Tabla de Notas -->
                        <CardContent class="p-0">
                            <div class="w-full overflow-x-auto">
                                <table
                                    class="w-full border-collapse text-left text-xs"
                                >
                                    <thead>
                                        <tr
                                            class="border-b bg-slate-100/90 text-[11px] font-black tracking-wider text-slate-700 uppercase dark:bg-slate-900 dark:text-slate-300"
                                        >
                                            <th
                                                class="w-28 px-3 py-2.5 text-left"
                                            >
                                                DNI
                                            </th>
                                            <th class="px-3 py-2.5 text-left">
                                                Participante
                                            </th>
                                            <th
                                                class="w-24 px-2 py-2.5 text-center"
                                            >
                                                Asistencia %
                                            </th>
                                            <th
                                                class="w-24 px-2 py-2.5 text-center"
                                            >
                                                Nota (0-20)
                                            </th>
                                            <th
                                                class="w-28 px-2 py-2.5 text-center"
                                            >
                                                Condición
                                            </th>
                                            <th
                                                v-if="can.manage_enrollments"
                                                class="w-20 px-3 py-2.5 text-right"
                                            >
                                                Acción
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody
                                        class="divide-y divide-slate-200 bg-white dark:divide-slate-800 dark:bg-slate-950"
                                    >
                                        <tr
                                            v-for="enrollment in filteredEnrollments"
                                            :key="enrollment.id"
                                            class="transition-colors hover:bg-rose-50/40 dark:hover:bg-rose-950/20"
                                        >
                                            <td
                                                class="px-3 py-2.5 font-mono text-xs font-bold whitespace-nowrap"
                                            >
                                                {{ enrollment.dni }}
                                            </td>
                                            <td
                                                class="px-3 py-2.5 font-black text-slate-950 dark:text-white"
                                            >
                                                {{ enrollment.paterno }}
                                                {{ enrollment.materno || '' }},
                                                {{ enrollment.nombres }}
                                            </td>
                                            <td
                                                class="px-2 py-2.5 text-center font-mono text-xs font-bold"
                                            >
                                                {{
                                                    enrollment.attendance_percentage
                                                }}%
                                            </td>
                                            <td
                                                class="px-2 py-2.5 text-center whitespace-nowrap"
                                            >
                                                <span
                                                    v-if="
                                                        enrollment.final_grade !==
                                                            null &&
                                                        enrollment.final_grade !==
                                                            undefined
                                                    "
                                                    :class="[
                                                        'rounded border px-2 py-0.5 font-mono text-xs font-black',
                                                        Number(
                                                            enrollment.final_grade,
                                                        ) >= 11
                                                            ? 'border-rose-300 bg-rose-50 text-rose-950 dark:border-rose-800 dark:bg-rose-950 dark:text-rose-200'
                                                            : 'border-red-300 bg-red-50 text-red-900 dark:border-red-800 dark:bg-red-950 dark:text-red-200',
                                                    ]"
                                                >
                                                    {{
                                                        Number(
                                                            enrollment.final_grade,
                                                        ).toFixed(1)
                                                    }}
                                                </span>
                                                <span
                                                    v-else
                                                    class="font-bold text-slate-400"
                                                    >-</span
                                                >
                                            </td>
                                            <td
                                                class="px-2 py-2.5 text-center whitespace-nowrap"
                                            >
                                                <Badge
                                                    v-if="
                                                        enrollment.status ===
                                                        'aprobado'
                                                    "
                                                    class="bg-emerald-600 text-[10px] font-black text-white"
                                                >
                                                    APROBADO
                                                </Badge>
                                                <Badge
                                                    v-else-if="
                                                        enrollment.status ===
                                                        'reprobado'
                                                    "
                                                    variant="destructive"
                                                    class="text-[10px] font-black"
                                                >
                                                    REPROBADO
                                                </Badge>
                                                <Badge
                                                    v-else
                                                    variant="outline"
                                                    class="text-[10px] font-bold text-slate-600"
                                                >
                                                    {{
                                                        (
                                                            enrollment.status ||
                                                            'EN CURSO'
                                                        ).toUpperCase()
                                                    }}
                                                </Badge>
                                            </td>
                                            <td
                                                v-if="can.manage_enrollments"
                                                class="px-3 py-2.5 text-right whitespace-nowrap"
                                            >
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    class="h-7 w-7 cursor-pointer p-0 text-slate-700 hover:bg-rose-50 hover:text-rose-950"
                                                    :title="
                                                        isActaClosed
                                                            ? 'Modificar calificación o condición'
                                                            : 'Editar calificación'
                                                    "
                                                    @click="
                                                        openEditModal(
                                                            enrollment,
                                                        )
                                                    "
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

                <!-- ============================================================ -->
                <!-- CONTENIDO DE LA PESTAÑA 4: CERTIFICADOS DIGITALES OFICIALES  -->
                <!-- ============================================================ -->
                <div v-if="activeTab === 'certificados'" class="space-y-6">
                    <Card
                        class="overflow-hidden border-slate-200 shadow-sm dark:border-slate-800"
                    >
                        <CardHeader
                            class="flex flex-col gap-4 border-b bg-slate-50/80 p-4 sm:p-5 lg:flex-row lg:items-center lg:justify-between dark:bg-slate-900/80"
                        >
                            <div>
                                <CardTitle
                                    class="flex items-center gap-2 text-base font-black"
                                >
                                    <Award class="size-4 text-rose-900" />
                                    Registro y Emisión de Certificados Digitales
                                </CardTitle>
                                <CardDescription class="mt-0.5 text-xs">
                                    Diplomas oficiales con código QR y
                                    verificación criptográfica SHA-256 única.
                                </CardDescription>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <Button
                                    v-if="
                                        isActaClosed && can.manage_enrollments
                                    "
                                    size="sm"
                                    :disabled="
                                        isIssuingCertificates ||
                                        approvedStudents.length === 0
                                    "
                                    :class="[
                                        THEME_BUTTONS.primary,
                                        'text-xs font-black shadow-xs',
                                    ]"
                                    @click="bulkIssueCertificates"
                                >
                                    <Loader2
                                        v-if="isIssuingCertificates"
                                        class="mr-1.5 size-3.5 animate-spin"
                                    />
                                    <Award v-else class="mr-1.5 size-3.5" />
                                    Emitir Certificados ({{
                                        approvedStudents.length
                                    }}
                                    Aprobados)
                                </Button>
                                <Badge
                                    v-else-if="!isActaClosed"
                                    variant="outline"
                                    class="border-amber-300 bg-amber-50 px-2.5 py-1 text-xs font-bold text-amber-800"
                                >
                                    <Lock class="mr-1 size-3" />
                                    Requiere Cierre de Acta
                                </Badge>
                            </div>
                        </CardHeader>

                        <!-- Si el acta NO está cerrada, mostramos mensaje explicativo ordenado -->
                        <div
                            v-if="!isActaClosed"
                            class="space-y-3 bg-white p-12 text-center dark:bg-slate-950"
                        >
                            <div
                                class="mx-auto flex size-14 items-center justify-center rounded-full bg-amber-100 text-amber-900"
                            >
                                <Lock class="size-7" />
                            </div>
                            <h4
                                class="text-base font-black text-slate-900 dark:text-white"
                            >
                                Emisión Bloqueada: El Acta Oficial aún no ha
                                sido cerrada
                            </h4>
                            <p
                                class="mx-auto max-w-md text-xs leading-relaxed text-slate-600 dark:text-slate-400"
                            >
                                Para garantizar la validez legal y académica de
                                los certificados oficiales UNSAAC, primero debe
                                cerrar el acta oficial en la pestaña
                                <strong>"Notas y Acta"</strong>.
                            </p>
                            <div class="pt-2">
                                <Button
                                    size="sm"
                                    variant="outline"
                                    class="border-rose-900 text-xs font-bold text-rose-900 hover:bg-rose-50"
                                    @click="activeTab = 'notas'"
                                >
                                    <FileText class="mr-1.5 size-3.5" />
                                    Ir a Notas y Acta Oficial
                                </Button>
                            </div>
                        </div>

                        <!-- Si el acta ESTÁ cerrada, mostramos la tabla de certificados -->
                        <div v-else>
                            <!-- Barra de Búsqueda -->
                            <div
                                class="flex items-center justify-between gap-3 border-b border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-950"
                            >
                                <div class="relative w-full sm:w-80">
                                    <Search
                                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400"
                                    />
                                    <Input
                                        v-model="searchQuery"
                                        type="text"
                                        placeholder="Buscar participante aprobado..."
                                        class="h-9 pl-9 text-xs font-medium"
                                    />
                                </div>
                                <span
                                    class="text-xs font-bold whitespace-nowrap text-emerald-700"
                                >
                                    {{ approvedStudents.length }} Participantes
                                    Certificados / Aptos
                                </span>
                            </div>

                            <!-- Tabla de Certificados -->
                            <CardContent class="p-0">
                                <div class="w-full overflow-x-auto">
                                    <table
                                        class="w-full border-collapse text-left text-xs"
                                    >
                                        <thead>
                                            <tr
                                                class="border-b bg-slate-100/90 text-[11px] font-black tracking-wider text-slate-700 uppercase dark:bg-slate-900 dark:text-slate-300"
                                            >
                                                <th
                                                    class="w-28 px-3 py-2.5 text-left"
                                                >
                                                    DNI
                                                </th>
                                                <th
                                                    class="px-3 py-2.5 text-left"
                                                >
                                                    Participante
                                                </th>
                                                <th
                                                    class="w-36 px-2 py-2.5 text-center"
                                                >
                                                    Código Oficial
                                                </th>
                                                <th
                                                    class="hidden px-2 py-2.5 text-left sm:table-cell"
                                                >
                                                    Firma SHA-256
                                                </th>
                                                <th
                                                    class="w-28 px-3 py-2.5 text-right"
                                                >
                                                    Verificación
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody
                                            class="divide-y divide-slate-200 bg-white dark:divide-slate-800 dark:bg-slate-950"
                                        >
                                            <tr
                                                v-for="enrollment in approvedStudents"
                                                :key="enrollment.id"
                                                class="transition-colors hover:bg-rose-50/40 dark:hover:bg-rose-950/20"
                                            >
                                                <td
                                                    class="px-3 py-2.5 font-mono text-xs font-bold whitespace-nowrap"
                                                >
                                                    {{ enrollment.dni }}
                                                </td>
                                                <td
                                                    class="px-3 py-2.5 font-black text-slate-950 dark:text-white"
                                                >
                                                    {{ enrollment.paterno }}
                                                    {{
                                                        enrollment.materno ||
                                                        ''
                                                    }}, {{ enrollment.nombres }}
                                                </td>
                                                <td
                                                    class="px-2 py-2.5 text-center font-mono font-black whitespace-nowrap text-rose-950 dark:text-rose-200"
                                                >
                                                    {{
                                                        enrollment.certificate_code ||
                                                        'En proceso'
                                                    }}
                                                </td>
                                                <td
                                                    class="hidden max-w-[200px] truncate px-2 py-2.5 font-mono text-[10px] text-slate-500 sm:table-cell"
                                                >
                                                    {{
                                                        enrollment.certificate_hash ||
                                                        'Pendiente'
                                                    }}
                                                </td>
                                                <td
                                                    class="px-3 py-2.5 text-right whitespace-nowrap"
                                                >
                                                    <Button
                                                        as-child
                                                        size="sm"
                                                        variant="ghost"
                                                        class="h-7 text-xs font-bold text-rose-900"
                                                    >
                                                        <Link
                                                            :href="`/certificates?dni=${enrollment.dni}`"
                                                            target="_blank"
                                                        >
                                                            <ExternalLink
                                                                class="mr-1 size-3.5"
                                                            />
                                                            Verificar
                                                        </Link>
                                                    </Button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </CardContent>
                        </div>
                    </Card>
                </div>

                <!-- ============================================================ -->
                <!-- CONTENIDO DE LA PESTAÑA 5: REPORTES CSV Y ESTADÍSTICAS       -->
                <!-- ============================================================ -->
                <div v-if="activeTab === 'reportes'" class="space-y-6">
                    <Card
                        class="overflow-hidden border-slate-200 shadow-sm dark:border-slate-800"
                    >
                        <CardHeader
                            class="flex flex-col gap-4 border-b bg-slate-50/80 p-4 sm:p-5 lg:flex-row lg:items-center lg:justify-between dark:bg-slate-900/80"
                        >
                            <div>
                                <CardTitle
                                    class="flex items-center gap-2 text-base font-black"
                                >
                                    <FileSpreadsheet
                                        class="size-4 text-rose-900"
                                    />
                                    Centro Oficial de Reportes Académicos CSV
                                </CardTitle>
                                <CardDescription class="mt-0.5 text-xs">
                                    Archivos descargables con codificación UTF-8
                                    e inclusión de firmas para Microsoft Excel.
                                </CardDescription>
                            </div>
                            <span class="text-xs font-bold text-slate-500">
                                Total Matriculados:
                                <strong>{{ totalEnrolled }}</strong>
                            </span>
                        </CardHeader>

                        <CardContent class="p-6">
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <!-- Reporte 1: Matriz de Asistencias -->
                                <div
                                    class="flex flex-col justify-between space-y-4 rounded-2xl border border-slate-200 bg-slate-50/50 p-5 dark:border-slate-800 dark:bg-slate-900/50"
                                >
                                    <div class="space-y-1.5">
                                        <div
                                            class="flex items-center gap-2 text-sm font-black text-slate-900 dark:text-white"
                                        >
                                            <QrCode
                                                class="size-4 text-rose-900"
                                            />
                                            Matriz Integral de Asistencia
                                        </div>
                                        <p
                                            class="text-xs leading-relaxed text-slate-600 dark:text-slate-400"
                                        >
                                            Detalle de todas las sesiones
                                            programadas (S1..S{{
                                                totalSessions
                                            }}), total de asistencias acumuladas
                                            y porcentaje oficial de asistencia.
                                        </p>
                                    </div>
                                    <Button
                                        as-child
                                        size="sm"
                                        :class="[
                                            THEME_BUTTONS.primary,
                                            'w-full text-xs font-black',
                                        ]"
                                    >
                                        <a
                                            :href="`/courses/${course.id}/reports/attendance-csv`"
                                            download
                                        >
                                            <FileSpreadsheet
                                                class="mr-1.5 size-3.5"
                                            />
                                            Descargar Matriz CSV
                                        </a>
                                    </Button>
                                </div>

                                <!-- Reporte 2: Acta Oficial de Notas -->
                                <div
                                    class="flex flex-col justify-between space-y-4 rounded-2xl border border-slate-200 bg-slate-50/50 p-5 dark:border-slate-800 dark:bg-slate-900/50"
                                >
                                    <div class="space-y-1.5">
                                        <div
                                            class="flex items-center gap-2 text-sm font-black text-slate-900 dark:text-white"
                                        >
                                            <FileText
                                                class="size-4 text-rose-900"
                                            />
                                            Acta Oficial de Calificaciones
                                            Vigesimales
                                        </div>
                                        <p
                                            class="text-xs leading-relaxed text-slate-600 dark:text-slate-400"
                                        >
                                            Nómina con DNI, nombres completos,
                                            notas vigesimales (0-20), condición
                                            final (Aprobado/Reprobado) y código
                                            de certificación emitido.
                                        </p>
                                    </div>
                                    <Button
                                        as-child
                                        size="sm"
                                        variant="outline"
                                        class="w-full border-rose-300 text-xs font-bold text-rose-950 hover:bg-rose-50"
                                    >
                                        <a
                                            :href="`/courses/${course.id}/reports/acta-csv`"
                                            download
                                        >
                                            <FileText class="mr-1.5 size-3.5" />
                                            Descargar Acta Oficial CSV
                                        </a>
                                    </Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>

        <!-- MODAL DE PROYECCIÓN QR EN PANTALLA GIGANTE (SIGC-4) -->
        <Dialog
            :open="isQrProjectorOpen"
            @update:open="isQrProjectorOpen = $event"
        >
            <DialogContent
                class="w-[95vw] overflow-hidden rounded-3xl border border-rose-200 bg-white p-0 shadow-2xl sm:max-w-xl dark:bg-slate-950"
            >
                <div
                    class="space-y-2 bg-gradient-to-br from-rose-950 via-rose-900 to-rose-950 p-6 text-center text-white"
                >
                    <div
                        class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-3 py-1 text-xs font-black tracking-wider text-amber-300 uppercase"
                    >
                        <Clock class="size-3.5" />
                        Hora Oficial Cusco: {{ currentTime }}
                    </div>
                    <h2 class="text-2xl font-black tracking-tight">
                        Asistencia en Vivo · Sesión {{ selectedSession }}
                    </h2>
                    <p class="mx-auto max-w-md text-xs text-rose-200">
                        {{ course.title }} ({{ course.code }})
                    </p>
                </div>

                <div
                    class="flex flex-col items-center justify-center space-y-4 p-6"
                >
                    <div
                        v-if="isLoadingQr"
                        class="rounded-2xl border-4 border-rose-950/20 bg-white p-4 shadow-md"
                    >
                        <Loader2 class="size-8 animate-spin text-rose-900" />
                    </div>
                    <div
                        v-else-if="qrLoadError"
                        class="max-w-sm rounded-2xl border border-rose-200 bg-rose-50 p-4 text-xs font-bold text-rose-900"
                    >
                        {{ qrLoadError }}
                    </div>
                    <div
                        v-else-if="sessionQrSvg"
                        class="rounded-2xl border-4 border-rose-950/20 bg-white p-4 shadow-md"
                        v-html="sessionQrSvg"
                    ></div>

                    <div class="space-y-1 text-center">
                        <p class="max-w-sm text-[11px] text-slate-500">
                            Escanea con tu cámara para confirmar tu asistencia
                            desde tu cuenta verificada. El enlace expira
                            automáticamente.
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
        <Dialog
            :open="isCloseActaModalOpen"
            @update:open="isCloseActaModalOpen = $event"
        >
            <DialogContent
                class="w-[94vw] space-y-4 rounded-2xl bg-white p-6 sm:max-w-md dark:bg-slate-950"
            >
                <DialogHeader class="space-y-2">
                    <div
                        class="mx-auto flex size-11 items-center justify-center rounded-full bg-rose-100 text-rose-900"
                    >
                        <Lock class="size-6" />
                    </div>
                    <DialogTitle
                        class="text-center text-lg font-black text-slate-950 dark:text-white"
                    >
                        ¿Cerrar Oficialmente el Acta del Curso?
                    </DialogTitle>
                    <DialogDescription
                        class="text-center text-xs leading-relaxed text-slate-600"
                    >
                        Esta acción es <strong>irreversible</strong> conforme a
                        las normativas de certificación UNSAAC. Se calcularán
                        automáticamente las condiciones de los participantes
                        (Aprobado si Asistencia >=
                        {{ course.min_attendance_percentage }}% y Nota >= 11),
                        se bloquearán futuras modificaciones y se habilitará la
                        emisión de certificados.
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter class="flex items-center justify-end gap-2 pt-2">
                    <Button
                        variant="outline"
                        size="sm"
                        class="text-xs font-bold"
                        @click="isCloseActaModalOpen = false"
                    >
                        Cancelar
                    </Button>
                    <Button
                        size="sm"
                        :disabled="isClosingActa"
                        :class="[
                            THEME_BUTTONS.primary,
                            'text-xs font-black shadow-xs',
                        ]"
                        @click="confirmCloseActa"
                    >
                        <Loader2
                            v-if="isClosingActa"
                            class="mr-1.5 size-3.5 animate-spin"
                        />
                        <Lock v-else class="mr-1.5 size-3.5" />
                        Sí, Cerrar Acta Oficialmente
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- MODAL CONFIRMACIÓN REAPERTURA / ACTIVACIÓN DE ACTA -->
        <Dialog
            :open="isReopenActaModalOpen"
            @update:open="isReopenActaModalOpen = $event"
        >
            <DialogContent
                class="w-[94vw] space-y-4 rounded-2xl bg-white p-6 sm:max-w-md dark:bg-slate-950"
            >
                <DialogHeader class="space-y-2">
                    <div
                        class="mx-auto flex size-11 items-center justify-center rounded-full bg-amber-100 text-amber-900 dark:bg-amber-950/60"
                    >
                        <Unlock
                            class="size-6 text-amber-700 dark:text-amber-400"
                        />
                    </div>
                    <DialogTitle
                        class="text-center text-lg font-black text-slate-950 dark:text-white"
                    >
                        ¿Reabrir o Activar el Acta del Curso?
                    </DialogTitle>
                    <DialogDescription
                        class="text-center text-xs leading-relaxed text-slate-600 dark:text-slate-400"
                    >
                        Esta acción devolverá el acta al estado
                        <strong>En Edición</strong>. Podrás corregir o modificar
                        notas, registrar asistencias pendientes y actualizar los
                        participantes. Una vez concluidas las correcciones,
                        podrás volver a cerrar el acta oficialmente.
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter class="flex items-center justify-end gap-2 pt-2">
                    <Button
                        variant="outline"
                        size="sm"
                        class="text-xs font-bold"
                        @click="isReopenActaModalOpen = false"
                    >
                        Cancelar
                    </Button>
                    <Button
                        size="sm"
                        :disabled="isReopeningActa"
                        class="cursor-pointer bg-amber-600 text-xs font-black text-white shadow-xs hover:bg-amber-700"
                        @click="confirmReopenActa"
                    >
                        <Loader2
                            v-if="isReopeningActa"
                            class="mr-1.5 size-3.5 animate-spin"
                        />
                        <Unlock v-else class="mr-1.5 size-3.5" />
                        Sí, Reabrir y Activar Edición
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- MODAL CRUD EDITAR MATRÍCULA -->
        <Dialog :open="isEditModalOpen" @update:open="isEditModalOpen = $event">
            <DialogContent
                class="w-[94vw] overflow-hidden rounded-2xl border bg-white p-0 shadow-2xl sm:max-w-lg dark:bg-slate-950"
            >
                <div class="border-b bg-slate-50 p-5 dark:bg-slate-900">
                    <DialogHeader class="space-y-1 text-left">
                        <div class="flex items-center gap-2">
                            <span
                                class="rounded bg-rose-100 px-2 py-0.5 font-mono text-xs font-black text-rose-950"
                            >
                                DNI: {{ editingEnrollment?.dni }}
                            </span>
                            <span
                                v-if="editingEnrollment?.credential_code"
                                class="font-mono text-xs font-bold text-slate-500"
                            >
                                {{ editingEnrollment.credential_code }}
                            </span>
                        </div>
                        <DialogTitle
                            class="pt-1 text-base font-black text-slate-950"
                        >
                            Editar Matrícula: {{ editingEnrollment?.nombres }}
                            {{ editingEnrollment?.paterno }}
                        </DialogTitle>
                    </DialogHeader>
                </div>

                <form
                    @submit.prevent="saveEnrollment"
                    class="space-y-4 p-5 text-xs"
                >
                    <div
                        v-if="editError"
                        class="rounded-xl border border-red-200 bg-red-50 p-3 font-bold text-red-700"
                    >
                        {{ editError }}
                    </div>

                    <div
                        v-if="isActaClosed"
                        class="flex items-center justify-between gap-2 rounded-lg border border-amber-200 bg-amber-50 p-2.5 text-[11px] text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200"
                    >
                        <div class="flex items-center gap-1.5">
                            <Lock class="size-3.5 shrink-0 text-amber-700" />
                            <span
                                >Acta cerrada: puedes ajustar la calificación o
                                reabrir el acta para edición global.</span
                            >
                        </div>
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            class="h-6 shrink-0 cursor-pointer px-1.5 text-[10px] font-black text-amber-900 underline hover:bg-amber-100 dark:text-amber-300 dark:hover:bg-amber-900/60"
                            @click="
                                isEditModalOpen = false;
                                isReopenActaModalOpen = true;
                            "
                        >
                            Reabrir acta
                        </Button>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="edit-status" class="font-bold"
                            >Estado</Label
                        >
                        <select
                            id="edit-status"
                            v-model="editForm.status"
                            class="h-9 w-full cursor-pointer rounded-xl border border-slate-300 bg-white px-3 py-1 text-xs font-bold"
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
                            <Label for="edit-email" class="font-bold"
                                >Correo Electrónico</Label
                            >
                            <Input
                                id="edit-email"
                                v-model="editForm.email"
                                type="email"
                                placeholder="correo@ejemplo.com"
                                class="h-9 text-xs font-medium"
                            />
                        </div>
                        <div class="space-y-1.5">
                            <Label for="edit-phone" class="font-bold"
                                >Teléfono / WhatsApp</Label
                            >
                            <Input
                                id="edit-phone"
                                v-model="editForm.phone"
                                type="tel"
                                inputmode="numeric"
                                pattern="[0-9]*"
                                maxlength="9"
                                placeholder="9XXXXXXXX (9 dígitos)"
                                class="h-9 font-mono text-xs font-medium"
                                @keypress="
                                    (e: KeyboardEvent) => {
                                        if (
                                            !/[0-9]/.test(e.key) &&
                                            ![
                                                'Backspace',
                                                'Delete',
                                                'ArrowLeft',
                                                'ArrowRight',
                                                'Tab',
                                            ].includes(e.key)
                                        )
                                            e.preventDefault();
                                    }
                                "
                                @input="
                                    (e: Event) => {
                                        editForm.phone = (
                                            (e.target as HTMLInputElement)
                                                .value || ''
                                        )
                                            .replace(/\D/g, '')
                                            .slice(0, 9);
                                    }
                                "
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <Label for="edit-sessions" class="font-bold"
                                >Sesiones Asistidas</Label
                            >
                            <Input
                                id="edit-sessions"
                                v-model.number="editForm.attended_sessions"
                                type="number"
                                min="0"
                                max="100"
                                class="h-9 text-xs font-bold"
                            />
                        </div>
                        <div class="space-y-1.5">
                            <Label for="edit-grade" class="font-bold"
                                >Nota Final (0 - 20)</Label
                            >
                            <Input
                                id="edit-grade"
                                v-model="editForm.final_grade"
                                type="number"
                                step="0.1"
                                min="0"
                                max="20"
                                placeholder="Ej: 16.5"
                                class="h-9 text-xs font-bold"
                            />
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="edit-cert" class="font-bold"
                            >Código de Certificado (Opcional)</Label
                        >
                        <Input
                            id="edit-cert"
                            v-model="editForm.certificate_code"
                            type="text"
                            placeholder="Ej: CERT-2026-UNSAAC-..."
                            class="h-9 font-mono text-xs font-bold"
                        />
                    </div>

                    <DialogFooter class="flex justify-end gap-2 border-t pt-3">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="text-xs font-bold"
                            @click="isEditModalOpen = false"
                            >Cancelar</Button
                        >
                        <Button
                            type="submit"
                            size="sm"
                            :disabled="isSaving"
                            :class="[
                                THEME_BUTTONS.primary,
                                'text-xs font-black',
                            ]"
                        >
                            <Loader2
                                v-if="isSaving"
                                class="mr-1.5 size-3.5 animate-spin"
                            />
                            <Check v-else class="mr-1.5 size-3.5" />
                            Guardar
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- MODAL CRUD ELIMINAR / DESMATRICULAR PARTICIPANTE -->
        <Dialog
            :open="isDeleteModalOpen"
            @update:open="isDeleteModalOpen = $event"
        >
            <DialogContent
                class="w-[94vw] rounded-2xl border bg-white p-6 shadow-2xl sm:max-w-md dark:bg-slate-950"
            >
                <DialogHeader class="space-y-2 text-left">
                    <div
                        class="mx-auto flex size-12 items-center justify-center rounded-full bg-rose-100 text-rose-900 shadow-inner dark:bg-rose-950 dark:text-rose-200"
                    >
                        <Trash2 class="size-6" />
                    </div>
                    <DialogTitle
                        class="text-center text-lg font-black text-slate-950 dark:text-white"
                    >
                        ¿Desmatricular Participante?
                    </DialogTitle>
                    <DialogDescription
                        class="text-center text-xs leading-relaxed text-slate-600 dark:text-slate-400"
                    >
                        Se dará de baja la matrícula de
                        <strong class="text-slate-950 dark:text-white"
                            >{{ enrollmentToDelete?.nombres }}
                            {{ enrollmentToDelete?.paterno }}</strong
                        >
                        (DNI: {{ enrollmentToDelete?.dni }}). Esta acción
                        liberará una vacante en el aforo oficial.
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter
                    class="flex flex-col items-center justify-end gap-2 pt-4 sm:flex-row"
                >
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="w-full text-xs font-bold sm:w-auto"
                        @click="isDeleteModalOpen = false"
                        :disabled="isDeletingEnrollment"
                    >
                        Cancelar
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        size="sm"
                        class="w-full bg-rose-900 text-xs font-black text-white shadow-md hover:bg-rose-950 sm:w-auto"
                        :disabled="isDeletingEnrollment"
                        @click="confirmDeleteEnrollment"
                    >
                        <Loader2
                            v-if="isDeletingEnrollment"
                            class="mr-1.5 size-3.5 animate-spin"
                        />
                        <Trash2 v-else class="mr-1.5 size-3.5" />
                        Sí, Desmatricular
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- MODAL VER CREDENCIAL DIGITAL / QR DEL PARTICIPANTE -->
        <Dialog
            :open="isCredentialModalOpen"
            @update:open="isCredentialModalOpen = $event"
        >
            <DialogContent
                class="w-[94vw] overflow-hidden rounded-3xl border border-rose-200 bg-white p-0 shadow-2xl sm:max-w-md dark:bg-slate-950"
            >
                <div
                    class="space-y-1.5 bg-gradient-to-br from-rose-950 via-rose-900 to-rose-950 p-6 text-center text-white"
                >
                    <span
                        class="inline-block rounded-full bg-white/20 px-3 py-0.5 text-[10px] font-black tracking-wider uppercase"
                    >
                        SIGC-CUSCO • Acreditación Digital
                    </span>
                    <h3 class="pt-1 text-lg font-black">
                        Credencial Oficial de Alumno
                    </h3>
                    <p class="line-clamp-1 text-xs text-rose-200">
                        {{ course.title }}
                    </p>
                </div>

                <div class="space-y-4 p-6 text-center">
                    <div
                        class="mx-auto inline-block rounded-2xl border-2 border-dashed border-rose-200 bg-white p-4 shadow-inner"
                    >
                        <div
                            v-if="isCredentialQrLoading"
                            class="flex min-h-[260px] min-w-[260px] items-center justify-center text-xs font-bold text-slate-500"
                        >
                            Generando QR...
                        </div>
                        <div
                            v-else-if="credentialQrSvg"
                            v-html="credentialQrSvg"
                            class="flex justify-center"
                        />
                        <div v-else class="p-3 text-xs font-bold text-rose-900">
                            No se pudo generar el QR de la credencial.
                        </div>
                        <div
                            class="mt-2 font-mono text-xs font-black text-rose-950"
                        >
                            {{
                                credentialQrCode ||
                                credentialEnrollment?.credential_code
                            }}
                        </div>
                    </div>

                    <div
                        class="space-y-1 text-xs text-slate-800 dark:text-slate-200"
                    >
                        <div
                            class="text-base font-black text-slate-950 dark:text-white"
                        >
                            {{ credentialEnrollment?.nombres }}
                            {{ credentialEnrollment?.paterno }}
                            {{ credentialEnrollment?.materno || '' }}
                        </div>
                        <div>
                            DNI:
                            <strong class="font-mono text-sm">{{
                                credentialEnrollment?.dni
                            }}</strong>
                        </div>
                        <div
                            v-if="credentialEnrollment?.email"
                            class="font-medium text-slate-500"
                        >
                            {{ credentialEnrollment?.email }}
                        </div>
                    </div>

                    <div class="flex flex-col gap-2 border-t pt-2 sm:flex-row">
                        <Button
                            v-if="
                                credentialQrCode ||
                                credentialEnrollment?.credential_code
                            "
                            type="button"
                            variant="secondary"
                            size="sm"
                            class="flex flex-1 items-center justify-center gap-1.5 border border-slate-200 text-xs font-bold dark:border-slate-800"
                            @click="
                                copyCredentialCode(
                                    credentialQrCode ||
                                        credentialEnrollment?.credential_code ||
                                        '',
                                )
                            "
                        >
                            <Copy class="size-3.5 text-rose-800" />
                            <span>Copiar Código</span>
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="flex-1 text-xs font-bold"
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
