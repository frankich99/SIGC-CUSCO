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
import CoursePublicView, { type MyEnrollmentInfo } from '@/components/CoursePublicView.vue';
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
        };
    }>(),
    {
        isStaff: false,
        myEnrollment: null,
        modules: () => [],
    }
);

const staffViewMode = ref<'management' | 'public'>('management');

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
        notify.warning('Asistencia sin conexión', `Guardada localmente para ${id}. Se sincronizará al tener red.`, 3000);
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
                notify.success('Asistencia registrada', `Sesión ${selectedSession.value} para ${id}`, 1800);
            },
            onError: (errs) => {
                isSubmittingAttendance.value = false;
                const msg = Object.values(errs)[0] || 'No se pudo registrar la asistencia.';
                attendanceFeedback.value = {
                    type: 'error',
                    text: msg as string,
                };
                notify.error('Error al registrar', msg as string, 3000);
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
                notify.success('Sincronización completada', `Se sincronizaron ${count} registros guardados.`, 2500);
            },
            onError: () => {
                isSyncing.value = false;
                notify.error('Error de sincronización', 'Ocurrió un problema durante la sincronización.', 3000);
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
                notify.success('Acta oficial cerrada', 'Calificaciones y asistencias selladas con valor legal.', 2500);
            },
            onError: () => {
                isClosingActa.value = false;
                notify.error('Error al cerrar acta', 'No se pudo cerrar el acta del curso.', 3000);
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
                notify.success('Certificados emitidos', 'Certificados digitales oficiales generados para participantes aprobados.', 2500);
            },
            onError: () => {
                isIssuingCertificates.value = false;
                notify.error('Error en emisión', 'No se pudieron emitir los certificados masivos.', 3000);
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
                notify.success('Matrícula actualizada', 'Los datos del participante fueron guardados.', 1800);
            },
            onError: (errs) => {
                isSaving.value = false;
                editError.value = Object.values(errs)[0] as string || 'Error al actualizar la matrícula.';
                notify.error('Error al actualizar', editError.value, 3000);
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
            onSuccess: () => {
                notify.success('Asistencia rápida (+1)', `Registrada para ${enrollment.nombres} ${enrollment.paterno}`, 1500);
            },
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
            onSuccess: () => {
                notify.success('Certificado generado', `Emitido para ${enrollment.nombres} ${enrollment.paterno}`, 2000);
            },
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
                notify.success('Matrícula eliminada', 'El participante fue desmatriculado con éxito.', 2000);
            },
            onError: () => {
                isDeletingEnrollment.value = false;
                notify.error('Error al desmatricular', 'No se pudo eliminar la matrícula del participante.', 3000);
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

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8 space-y-6">
            <!-- Barra de alternancia de vista exclusiva para Docentes y Administradores (Staff) -->
            <div
                v-if="isStaff"
                class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 p-3.5 rounded-2xl bg-slate-900 text-white shadow-sm border border-slate-800"
            >
                <div class="flex items-center gap-2.5">
                    <div class="size-8 rounded-lg bg-rose-900 text-amber-300 flex items-center justify-center shrink-0">
                        <ShieldCheck class="size-4" />
                    </div>
                    <div>
                        <div class="text-xs font-black">
                            Personal Autorizado: Docente / Administrador
                        </div>
                        <div class="text-[11px] text-slate-300">
                            Alterna entre el panel de gestión académica confidencial y la vista pública del curso.
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <Button
                        type="button"
                        size="sm"
                        :variant="staffViewMode === 'management' ? 'default' : 'secondary'"
                        class="text-xs font-black cursor-pointer"
                        :class="staffViewMode === 'management' ? 'bg-rose-900 hover:bg-rose-950 text-white shadow-xs' : 'bg-slate-800 text-slate-200'"
                        @click="staffViewMode = 'management'"
                    >
                        <Users class="mr-1.5 size-3.5" />
                        Panel Académico (Staff)
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        :variant="staffViewMode === 'public' ? 'default' : 'secondary'"
                        class="text-xs font-black cursor-pointer"
                        :class="staffViewMode === 'public' ? 'bg-amber-600 hover:bg-amber-500 text-slate-950 shadow-xs' : 'bg-slate-800 text-slate-200'"
                        @click="staffViewMode = 'public'"
                    >
                        <Eye class="mr-1.5 size-3.5" />
                        Vista Pública Informativa
                    </Button>
                </div>
            </div>

            <!-- 1. VISTA PÚBLICA INFORMATIVA (Público General, Visitantes, Alumnos, o Staff en modo preview) -->
            <div v-if="!isStaff || staffViewMode === 'public'" class="space-y-6">
                <div class="flex items-center justify-between pb-1">
                    <Button as-child variant="ghost" size="sm" class="-ml-2 text-xs text-slate-600 dark:text-slate-400">
                        <Link href="/courses">
                            <ArrowLeft class="mr-1 size-3.5" />
                            Catálogo de Capacitaciones
                        </Link>
                    </Button>
                    <div v-if="can.update" class="flex items-center gap-2">
                        <Button as-child variant="outline" size="sm" class="text-xs font-bold">
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
            <div v-else-if="isStaff && staffViewMode === 'management'" class="space-y-6">
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

            <!-- 4 TARJETAS DE MÉTRICAS GLOBALES DEL CURSO (POSICIÓN FIJA Y ESTABLE PARA TODAS LAS PESTAÑAS) -->
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
                        Nota >= 11 y asistencia >= {{ course.min_attendance_percentage }}%
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

            <!-- NAVEGACIÓN POR 5 PESTAÑAS ACADÉMICAS (POSICIÓN ESTABLE Y FIJA) -->
            <div class="border-b border-slate-200 dark:border-slate-800 pb-1">
                <nav class="flex flex-wrap items-center gap-1.5 sm:gap-2" aria-label="Tabs">
                    <button
                        type="button"
                        @click="activeTab = 'matriculados'"
                        :class="[
                            'py-2.5 px-3.5 rounded-xl font-black text-xs flex items-center gap-2 cursor-pointer transition-all',
                            activeTab === 'matriculados'
                                ? 'bg-rose-900 text-white shadow-sm ring-1 ring-rose-950'
                                : 'bg-slate-100 hover:bg-slate-200 text-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800'
                        ]"
                    >
                        <Users class="size-3.5" />
                        <span>Matriculados</span>
                        <span
                            :class="[
                                'text-[10px] px-1.5 py-0.2 rounded-full font-bold',
                                activeTab === 'matriculados'
                                    ? 'bg-white/20 text-white'
                                    : 'bg-rose-100 text-rose-900 dark:bg-rose-950 dark:text-rose-200'
                            ]"
                        >
                            {{ totalEnrolled }}
                        </span>
                    </button>

                    <button
                        type="button"
                        @click="activeTab = 'asistencia'"
                        :class="[
                            'py-2.5 px-3.5 rounded-xl font-black text-xs flex items-center gap-2 cursor-pointer transition-all',
                            activeTab === 'asistencia'
                                ? 'bg-rose-900 text-white shadow-sm ring-1 ring-rose-950'
                                : 'bg-slate-100 hover:bg-slate-200 text-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800'
                        ]"
                    >
                        <QrCode class="size-3.5" />
                        <span>Asistencias</span>
                    </button>

                    <button
                        type="button"
                        @click="activeTab = 'notas'"
                        :class="[
                            'py-2.5 px-3.5 rounded-xl font-black text-xs flex items-center gap-2 cursor-pointer transition-all',
                            activeTab === 'notas'
                                ? 'bg-rose-900 text-white shadow-sm ring-1 ring-rose-950'
                                : 'bg-slate-100 hover:bg-slate-200 text-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800'
                        ]"
                    >
                        <FileText class="size-3.5" />
                        <span>Notas y Acta</span>
                        <span v-if="isActaClosed" class="size-2 rounded-full bg-emerald-400"></span>
                    </button>

                    <button
                        type="button"
                        @click="activeTab = 'certificados'"
                        :class="[
                            'py-2.5 px-3.5 rounded-xl font-black text-xs flex items-center gap-2 cursor-pointer transition-all',
                            activeTab === 'certificados'
                                ? 'bg-rose-900 text-white shadow-sm ring-1 ring-rose-950'
                                : 'bg-slate-100 hover:bg-slate-200 text-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800'
                        ]"
                    >
                        <Award class="size-3.5" />
                        <span>Certificados</span>
                        <span
                            :class="[
                                'text-[10px] px-1.5 py-0.2 rounded-full font-bold',
                                activeTab === 'certificados'
                                    ? 'bg-white/20 text-white'
                                    : 'bg-rose-100 text-rose-900 dark:bg-rose-950 dark:text-rose-200'
                            ]"
                        >
                            {{ approvedStudents.length }}
                        </span>
                    </button>

                    <button
                        type="button"
                        @click="activeTab = 'reportes'"
                        :class="[
                            'py-2.5 px-3.5 rounded-xl font-black text-xs flex items-center gap-2 cursor-pointer transition-all',
                            activeTab === 'reportes'
                                ? 'bg-rose-900 text-white shadow-sm ring-1 ring-rose-950'
                                : 'bg-slate-100 hover:bg-slate-200 text-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800'
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

                    <!-- Contenido de la Tabla Compacta (Sin Scroll Horizontal) -->
                    <CardContent class="p-0">
                        <div class="w-full">
                            <table class="w-full text-left text-xs border-collapse">
                                <thead>
                                    <tr class="bg-slate-100/90 dark:bg-slate-900 border-b text-[11px] font-black uppercase tracking-wider text-slate-700 dark:text-slate-300">
                                        <th class="py-2.5 px-3 text-left w-28">DNI</th>
                                        <th class="py-2.5 px-3 text-left">Participante y Contacto</th>
                                        <th class="py-2.5 px-2 text-center w-28">Asistencia</th>
                                        <th class="py-2.5 px-2 text-center w-16">Nota</th>
                                        <th class="py-2.5 px-2 text-center w-24">Estado</th>
                                        <th v-if="can.manage_enrollments" class="py-2.5 px-3 text-right w-28">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200 dark:divide-slate-800 bg-white dark:bg-slate-950">
                                    <tr
                                        v-for="enrollment in matriculadosFilteredEnrollments"
                                        :key="enrollment.id"
                                        class="hover:bg-rose-50/40 dark:hover:bg-rose-950/20 transition-colors"
                                    >
                                        <!-- DNI -->
                                        <td class="py-2.5 px-3 whitespace-nowrap">
                                            <div class="flex items-center gap-1.5">
                                                <span class="font-mono font-black text-xs px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-700">
                                                    {{ enrollment.dni }}
                                                </span>
                                                <button
                                                    type="button"
                                                    @click="viewCredential(enrollment)"
                                                    title="Ver Credencial con QR"
                                                    class="text-rose-900 dark:text-rose-400 hover:text-rose-700 p-0.5 rounded hover:bg-rose-100 dark:hover:bg-rose-950/60 cursor-pointer"
                                                >
                                                    <QrCode class="size-3.5" />
                                                </button>
                                            </div>
                                        </td>

                                        <!-- Participante y Contacto -->
                                        <td class="py-2.5 px-3">
                                            <div class="font-black text-slate-950 dark:text-white leading-tight">
                                                {{ enrollment.paterno }} {{ enrollment.materno || '' }}, {{ enrollment.nombres }}
                                            </div>
                                            <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                                                <a :href="`mailto:${enrollment.email}`" class="hover:underline hover:text-rose-900 truncate max-w-[190px]" :title="enrollment.email">
                                                    {{ enrollment.email }}
                                                </a>
                                                <span v-if="enrollment.phone" class="font-mono text-emerald-700 dark:text-emerald-400 font-bold">
                                                    · {{ enrollment.phone }}
                                                </span>
                                                <span v-if="enrollment.certificate_code" class="font-mono text-amber-700 dark:text-amber-400 font-bold">
                                                    · 📜 {{ enrollment.certificate_code }}
                                                </span>
                                            </div>
                                        </td>

                                        <!-- Asistencia -->
                                        <td class="py-2.5 px-2 text-center whitespace-nowrap">
                                            <span class="font-black text-slate-900 dark:text-white text-xs">
                                                {{ enrollment.attended_sessions }}/{{ totalSessions }}
                                            </span>
                                            <span class="text-[10px] font-bold font-mono text-slate-500 ml-1">
                                                ({{ enrollment.attendance_percentage }}%)
                                            </span>
                                        </td>

                                        <!-- Nota -->
                                        <td class="py-2.5 px-2 text-center whitespace-nowrap">
                                            <span
                                                v-if="enrollment.final_grade !== null && enrollment.final_grade !== undefined"
                                                :class="[
                                                    'font-mono font-black text-xs px-1.5 py-0.5 rounded border',
                                                    Number(enrollment.final_grade) >= 11
                                                        ? 'bg-rose-50 text-rose-950 border-rose-300 dark:bg-rose-950 dark:text-rose-200 dark:border-rose-800'
                                                        : 'bg-red-50 text-red-900 border-red-300 dark:bg-red-950 dark:text-red-200 dark:border-red-800'
                                                ]"
                                            >
                                                {{ Number(enrollment.final_grade).toFixed(1) }}
                                            </span>
                                            <span v-else class="text-slate-400 font-bold">-</span>
                                        </td>

                                        <!-- Estado -->
                                        <td class="py-2.5 px-2 text-center whitespace-nowrap">
                                            <Badge
                                                variant="outline"
                                                :class="['text-[10px] font-black uppercase px-2 py-0.5', getStatusBadge(enrollment.status).classes]"
                                            >
                                                {{ getStatusBadge(enrollment.status).label }}
                                            </Badge>
                                        </td>

                                        <!-- Acciones -->
                                        <td v-if="can.manage_enrollments" class="py-2.5 px-3 text-right whitespace-nowrap">
                                            <div class="flex items-center justify-end gap-1">
                                                <!-- Asistencia Rápida (+1) -->
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    class="h-7 w-7 p-0 text-emerald-800 hover:text-emerald-950 hover:bg-emerald-50 dark:text-emerald-400"
                                                    title="Registrar +1 asistencia rápida"
                                                    :disabled="isRecordingQuickAttendance === enrollment.id"
                                                    @click="quickRecordAttendance(enrollment)"
                                                >
                                                    <Loader2 v-if="isRecordingQuickAttendance === enrollment.id" class="size-3 animate-spin" />
                                                    <Plus v-else class="size-3.5" />
                                                </Button>

                                                <!-- Emitir Certificado si califica y no lo tiene -->
                                                <Button
                                                    v-if="enrollment.status === 'aprobado' && !enrollment.certificate_code"
                                                    size="sm"
                                                    variant="ghost"
                                                    class="h-7 w-7 p-0 text-amber-800 hover:text-amber-950 hover:bg-amber-50"
                                                    title="Emitir certificado oficial"
                                                    :disabled="isIssuingSingleCert === enrollment.id"
                                                    @click="issueSingleCertificate(enrollment)"
                                                >
                                                    <Loader2 v-if="isIssuingSingleCert === enrollment.id" class="size-3 animate-spin" />
                                                    <Award v-else class="size-3.5" />
                                                </Button>

                                                <!-- Editar Matrícula -->
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    class="h-7 w-7 p-0 text-slate-700 hover:text-rose-950 hover:bg-rose-50 dark:text-slate-300"
                                                    title="Editar matrícula"
                                                    @click="openEditModal(enrollment)"
                                                >
                                                    <Pencil class="size-3.5" />
                                                </Button>

                                                <!-- Eliminar Matrícula -->
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    class="h-7 w-7 p-0 text-rose-700 hover:text-rose-950 hover:bg-rose-100 dark:text-rose-400"
                                                    title="Desmatricular participante"
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

            <!-- ============================================================ -->
            <!-- CONTENIDO DE LA PESTAÑA 2: ASISTENCIA DE SESIONES            -->
            <!-- ============================================================ -->
            <div v-if="activeTab === 'asistencia'" class="space-y-6">
                <!-- Barra de Sesión Activa y Registro Manual Rápido -->
                <div class="p-4 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 shadow-xs space-y-3">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b pb-3 border-slate-100 dark:border-slate-800">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-xs font-black uppercase tracking-wider text-slate-800 dark:text-slate-200">
                                Sesión de Control Activa:
                            </span>
                            <div class="flex flex-wrap items-center gap-1.5">
                                <Button
                                    v-for="num in totalSessions"
                                    :key="num"
                                    size="sm"
                                    :variant="selectedSession === num ? 'default' : 'outline'"
                                    :class="selectedSession === num ? 'bg-rose-900 text-white font-black h-7 text-xs px-2.5' : 'font-bold text-xs h-7 px-2.5'"
                                    @click="selectedSession = num"
                                >
                                    S{{ num }}
                                </Button>
                            </div>
                        </div>

                        <Button
                            size="sm"
                            :class="[THEME_BUTTONS.primary, 'text-xs font-black shadow-xs shrink-0 h-8']"
                            @click="isQrProjectorOpen = true"
                        >
                            <Maximize2 class="mr-1.5 size-3.5" />
                            Proyectar QR (Sesión {{ selectedSession }})
                        </Button>
                    </div>

                    <!-- Formulario de Registro Manual Rápido -->
                    <form @submit.prevent="registerManualAttendance" class="flex flex-col sm:flex-row gap-2">
                        <div class="relative flex-1">
                            <Input
                                v-model="manualIdentifier"
                                type="text"
                                placeholder="Ingresar DNI (8 dígitos) o código de credencial para registrar..."
                                class="text-xs font-bold h-9 pl-3"
                                :disabled="isSubmittingAttendance"
                            />
                        </div>

                        <select
                            v-model="manualStatus"
                            class="h-9 px-3 rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-xs font-bold text-slate-800 dark:text-slate-200"
                        >
                            <option value="presente">Presente</option>
                            <option value="tardanza">Tardanza</option>
                        </select>

                        <Button
                            type="submit"
                            size="sm"
                            :disabled="!manualIdentifier.trim() || isSubmittingAttendance"
                            :class="[THEME_BUTTONS.primary, 'text-xs font-black h-9 px-4 shrink-0']"
                        >
                            <Loader2 v-if="isSubmittingAttendance" class="size-3.5 mr-1.5 animate-spin" />
                            <UserCheck v-else class="size-3.5 mr-1.5" />
                            Registrar en Sesión {{ selectedSession }}
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

                <!-- Card de la Matriz Integral de Asistencias (Misma Dimensión y Borde que las demás pestañas) -->
                <Card class="border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
                    <CardHeader class="p-4 sm:p-5 border-b bg-slate-50/80 dark:bg-slate-900/80 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                        <div>
                            <CardTitle class="text-base font-black flex items-center gap-2">
                                <QrCode class="size-4 text-rose-900" />
                                Matriz Integral de Asistencia por Participante
                            </CardTitle>
                            <CardDescription class="text-xs mt-0.5">
                                Registro histórico sesión por sesión (Mínimo exigido: {{ course.min_attendance_percentage }}% de asistencia).
                            </CardDescription>
                        </div>
                        <Button
                            as-child
                            variant="outline"
                            size="sm"
                            class="text-xs font-bold border-slate-300 hover:text-rose-900"
                        >
                            <a :href="`/courses/${course.id}/reports/attendance-csv`" target="_blank">
                                <FileSpreadsheet class="mr-1.5 size-3.5 text-emerald-700" />
                                Descargar Matriz CSV
                            </a>
                        </Button>
                    </CardHeader>

                    <!-- Barra de Búsqueda -->
                    <div class="p-4 bg-white dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between gap-3">
                        <div class="relative w-full sm:w-80">
                            <Search class="size-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
                            <Input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Buscar por DNI o nombres..."
                                class="h-9 pl-9 text-xs font-medium"
                            />
                        </div>
                        <span class="text-xs font-bold text-slate-500 whitespace-nowrap">
                            Total: {{ filteredEnrollments.length }} registros
                        </span>
                    </div>

                    <!-- Tabla de Matriz de Asistencias -->
                    <CardContent class="p-0">
                        <div class="w-full overflow-x-auto">
                            <table class="w-full text-left text-xs border-collapse">
                                <thead>
                                    <tr class="bg-slate-100/90 dark:bg-slate-900 border-b text-[11px] font-black uppercase tracking-wider text-slate-700 dark:text-slate-300">
                                        <th class="py-2.5 px-3 text-left w-28">DNI</th>
                                        <th class="py-2.5 px-3 text-left">Participante</th>
                                        <th v-for="s in totalSessions" :key="s" class="py-2.5 px-1.5 text-center w-9">
                                            S{{ s }}
                                        </th>
                                        <th class="py-2.5 px-2 text-center w-20">Asistidas</th>
                                        <th class="py-2.5 px-2 text-center w-16">% Asist.</th>
                                        <th class="py-2.5 px-3 text-right w-24">Condición</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200 dark:divide-slate-800 bg-white dark:bg-slate-950">
                                    <tr v-for="enrollment in filteredEnrollments" :key="enrollment.id" class="hover:bg-rose-50/40 dark:hover:bg-rose-950/20 transition-colors">
                                        <td class="py-2.5 px-3 whitespace-nowrap font-mono font-bold text-xs">
                                            {{ enrollment.dni }}
                                        </td>
                                        <td class="py-2.5 px-3 font-black text-slate-950 dark:text-white">
                                            {{ enrollment.paterno }} {{ enrollment.materno || '' }}, {{ enrollment.nombres }}
                                        </td>
                                        <td v-for="s in totalSessions" :key="s" class="py-2 px-1 text-center">
                                            <span
                                                v-if="enrollment.attendance_records?.some(r => r.session_number === s && r.status === 'presente')"
                                                class="size-5 inline-flex items-center justify-center rounded-full bg-emerald-100 text-emerald-800 font-black text-[11px]"
                                                title="Presente"
                                            >✓</span>
                                            <span
                                                v-else-if="enrollment.attendance_records?.some(r => r.session_number === s && r.status === 'tardanza')"
                                                class="size-5 inline-flex items-center justify-center rounded-full bg-amber-100 text-amber-800 font-black text-[11px]"
                                                title="Tardanza"
                                            >T</span>
                                            <span v-else class="size-5 inline-flex items-center justify-center rounded-full bg-slate-100 text-slate-400 text-[10px]">·</span>
                                        </td>
                                        <td class="py-2.5 px-2 text-center font-bold text-xs">
                                            {{ enrollment.attended_sessions }}/{{ totalSessions }}
                                        </td>
                                        <td class="py-2.5 px-2 text-center font-black font-mono text-xs">
                                            {{ enrollment.attendance_percentage }}%
                                        </td>
                                        <td class="py-2.5 px-3 text-right whitespace-nowrap">
                                            <Badge
                                                v-if="(enrollment.attendance_percentage || 0) >= (course.min_attendance_percentage || 75)"
                                                class="bg-emerald-600 text-white font-black text-[10px]"
                                            >
                                                CUMPLE
                                            </Badge>
                                            <Badge v-else variant="outline" class="text-rose-900 border-rose-300 font-bold text-[10px]">
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
                <Card class="border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
                    <CardHeader class="p-4 sm:p-5 border-b bg-slate-50/80 dark:bg-slate-900/80 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                        <div>
                            <CardTitle class="text-base font-black flex items-center gap-2">
                                <FileText class="size-4 text-rose-900" />
                                Nómina Oficial de Calificaciones Vigesimales (0 - 20)
                            </CardTitle>
                            <CardDescription class="text-xs mt-0.5">
                                Reglamento UNSAAC: Aprobación con Nota vigesimal >= 11.00 y Asistencia >= {{ course.min_attendance_percentage }}%.
                            </CardDescription>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <Button
                                v-if="can.manage_enrollments && !isActaClosed"
                                size="sm"
                                :class="[THEME_BUTTONS.primary, 'text-xs font-black shadow-xs']"
                                @click="isCloseActaModalOpen = true"
                            >
                                <Lock class="mr-1.5 size-3.5" />
                                Cerrar Acta Oficial del Curso
                            </Button>
                            <Badge v-else-if="isActaClosed" class="bg-emerald-700 text-white font-bold text-xs py-1 px-2.5">
                                <Lock class="size-3 mr-1" />
                                Acta Cerrada Oficialmente
                            </Badge>
                            <Button
                                as-child
                                variant="outline"
                                size="sm"
                                class="text-xs font-bold border-slate-300 hover:text-rose-900"
                            >
                                <a :href="`/courses/${course.id}/reports/acta-csv`" target="_blank">
                                    <FileSpreadsheet class="mr-1.5 size-3.5 text-emerald-700" />
                                    Descargar Acta CSV
                                </a>
                            </Button>
                        </div>
                    </CardHeader>

                    <!-- Barra de Búsqueda y Filtros -->
                    <div class="p-4 bg-white dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800 flex flex-col sm:flex-row items-center justify-between gap-3">
                        <div class="relative w-full sm:w-80">
                            <Search class="size-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
                            <Input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Buscar por DNI o nombres..."
                                class="h-9 pl-9 text-xs font-medium"
                            />
                        </div>
                        <div class="flex items-center gap-2 text-xs font-bold">
                            <span class="text-emerald-700">Aprobados: {{ approvedStudents.length }}</span>
                            <span class="text-slate-300">|</span>
                            <span class="text-rose-700">Reprobados: {{ failedStudents.length }}</span>
                        </div>
                    </div>

                    <!-- Tabla de Notas -->
                    <CardContent class="p-0">
                        <div class="w-full">
                            <table class="w-full text-left text-xs border-collapse">
                                <thead>
                                    <tr class="bg-slate-100/90 dark:bg-slate-900 border-b text-[11px] font-black uppercase tracking-wider text-slate-700 dark:text-slate-300">
                                        <th class="py-2.5 px-3 text-left w-28">DNI</th>
                                        <th class="py-2.5 px-3 text-left">Participante</th>
                                        <th class="py-2.5 px-2 text-center w-24">Asistencia %</th>
                                        <th class="py-2.5 px-2 text-center w-24">Nota (0-20)</th>
                                        <th class="py-2.5 px-2 text-center w-28">Condición</th>
                                        <th v-if="!isActaClosed && can.manage_enrollments" class="py-2.5 px-3 text-right w-20">Acción</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200 dark:divide-slate-800 bg-white dark:bg-slate-950">
                                    <tr v-for="enrollment in filteredEnrollments" :key="enrollment.id" class="hover:bg-rose-50/40 dark:hover:bg-rose-950/20 transition-colors">
                                        <td class="py-2.5 px-3 whitespace-nowrap font-mono font-bold text-xs">
                                            {{ enrollment.dni }}
                                        </td>
                                        <td class="py-2.5 px-3 font-black text-slate-950 dark:text-white">
                                            {{ enrollment.paterno }} {{ enrollment.materno || '' }}, {{ enrollment.nombres }}
                                        </td>
                                        <td class="py-2.5 px-2 text-center font-mono font-bold text-xs">
                                            {{ enrollment.attendance_percentage }}%
                                        </td>
                                        <td class="py-2.5 px-2 text-center whitespace-nowrap">
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
                                        <td class="py-2.5 px-2 text-center whitespace-nowrap">
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
                                                REPROBADO
                                            </Badge>
                                            <Badge v-else variant="outline" class="text-[10px] font-bold text-slate-600">
                                                {{ (enrollment.status || 'EN CURSO').toUpperCase() }}
                                            </Badge>
                                        </td>
                                        <td v-if="!isActaClosed && can.manage_enrollments" class="py-2.5 px-3 text-right whitespace-nowrap">
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                class="h-7 w-7 p-0 text-slate-700 hover:text-rose-950 hover:bg-rose-50"
                                                title="Editar calificación"
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

            <!-- ============================================================ -->
            <!-- CONTENIDO DE LA PESTAÑA 4: CERTIFICADOS DIGITALES OFICIALES  -->
            <!-- ============================================================ -->
            <div v-if="activeTab === 'certificados'" class="space-y-6">
                <Card class="border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
                    <CardHeader class="p-4 sm:p-5 border-b bg-slate-50/80 dark:bg-slate-900/80 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                        <div>
                            <CardTitle class="text-base font-black flex items-center gap-2">
                                <Award class="size-4 text-rose-900" />
                                Registro y Emisión de Certificados Digitales
                            </CardTitle>
                            <CardDescription class="text-xs mt-0.5">
                                Diplomas oficiales con código QR y verificación criptográfica SHA-256 única.
                            </CardDescription>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <Button
                                v-if="isActaClosed && can.manage_enrollments"
                                size="sm"
                                :disabled="isIssuingCertificates || approvedStudents.length === 0"
                                :class="[THEME_BUTTONS.primary, 'text-xs font-black shadow-xs']"
                                @click="bulkIssueCertificates"
                            >
                                <Loader2 v-if="isIssuingCertificates" class="size-3.5 mr-1.5 animate-spin" />
                                <Award v-else class="size-3.5 mr-1.5" />
                                Emitir Certificados ({{ approvedStudents.length }} Aprobados)
                            </Button>
                            <Badge v-else-if="!isActaClosed" variant="outline" class="text-amber-800 border-amber-300 bg-amber-50 font-bold text-xs py-1 px-2.5">
                                <Lock class="size-3 mr-1" />
                                Requiere Cierre de Acta
                            </Badge>
                        </div>
                    </CardHeader>

                    <!-- Si el acta NO está cerrada, mostramos mensaje explicativo ordenado -->
                    <div v-if="!isActaClosed" class="p-12 text-center space-y-3 bg-white dark:bg-slate-950">
                        <div class="size-14 rounded-full bg-amber-100 text-amber-900 flex items-center justify-center mx-auto">
                            <Lock class="size-7" />
                        </div>
                        <h4 class="text-base font-black text-slate-900 dark:text-white">
                            Emisión Bloqueada: El Acta Oficial aún no ha sido cerrada
                        </h4>
                        <p class="text-xs text-slate-600 dark:text-slate-400 max-w-md mx-auto leading-relaxed">
                            Para garantizar la validez legal y académica de los certificados oficiales UNSAAC, primero debe cerrar el acta oficial en la pestaña <strong>"Notas y Acta"</strong>.
                        </p>
                        <div class="pt-2">
                            <Button
                                size="sm"
                                variant="outline"
                                class="text-xs font-bold border-rose-900 text-rose-900 hover:bg-rose-50"
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
                        <div class="p-4 bg-white dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between gap-3">
                            <div class="relative w-full sm:w-80">
                                <Search class="size-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
                                <Input
                                    v-model="searchQuery"
                                    type="text"
                                    placeholder="Buscar participante aprobado..."
                                    class="h-9 pl-9 text-xs font-medium"
                                />
                            </div>
                            <span class="text-xs font-bold text-emerald-700 whitespace-nowrap">
                                {{ approvedStudents.length }} Participantes Certificados / Aptos
                            </span>
                        </div>

                        <!-- Tabla de Certificados -->
                        <CardContent class="p-0">
                            <div class="w-full">
                                <table class="w-full text-left text-xs border-collapse">
                                    <thead>
                                        <tr class="bg-slate-100/90 dark:bg-slate-900 border-b text-[11px] font-black uppercase tracking-wider text-slate-700 dark:text-slate-300">
                                            <th class="py-2.5 px-3 text-left w-28">DNI</th>
                                            <th class="py-2.5 px-3 text-left">Participante</th>
                                            <th class="py-2.5 px-2 text-center w-36">Código Oficial</th>
                                            <th class="py-2.5 px-2 text-left hidden sm:table-cell">Firma SHA-256</th>
                                            <th class="py-2.5 px-3 text-right w-28">Verificación</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800 bg-white dark:bg-slate-950">
                                        <tr v-for="enrollment in approvedStudents" :key="enrollment.id" class="hover:bg-rose-50/40 dark:hover:bg-rose-950/20 transition-colors">
                                            <td class="py-2.5 px-3 whitespace-nowrap font-mono font-bold text-xs">
                                                {{ enrollment.dni }}
                                            </td>
                                            <td class="py-2.5 px-3 font-black text-slate-950 dark:text-white">
                                                {{ enrollment.paterno }} {{ enrollment.materno || '' }}, {{ enrollment.nombres }}
                                            </td>
                                            <td class="py-2.5 px-2 text-center whitespace-nowrap font-mono font-black text-rose-950 dark:text-rose-200">
                                                {{ enrollment.certificate_code || 'En proceso' }}
                                            </td>
                                            <td class="py-2.5 px-2 hidden sm:table-cell font-mono text-[10px] text-slate-500 max-w-[200px] truncate">
                                                {{ enrollment.certificate_hash || 'Pendiente' }}
                                            </td>
                                            <td class="py-2.5 px-3 text-right whitespace-nowrap">
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
                    </div>
                </Card>
            </div>

            <!-- ============================================================ -->
            <!-- CONTENIDO DE LA PESTAÑA 5: REPORTES CSV Y ESTADÍSTICAS       -->
            <!-- ============================================================ -->
            <div v-if="activeTab === 'reportes'" class="space-y-6">
                <Card class="border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
                    <CardHeader class="p-4 sm:p-5 border-b bg-slate-50/80 dark:bg-slate-900/80 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                        <div>
                            <CardTitle class="text-base font-black flex items-center gap-2">
                                <FileSpreadsheet class="size-4 text-rose-900" />
                                Centro Oficial de Reportes Académicos CSV
                            </CardTitle>
                            <CardDescription class="text-xs mt-0.5">
                                Archivos descargables con codificación UTF-8 e inclusión de firmas para Microsoft Excel.
                            </CardDescription>
                        </div>
                        <span class="text-xs font-bold text-slate-500">
                            Total Matriculados: <strong>{{ totalEnrolled }}</strong>
                        </span>
                    </CardHeader>

                    <CardContent class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Reporte 1: Matriz de Asistencias -->
                            <div class="p-5 rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50 flex flex-col justify-between space-y-4">
                                <div class="space-y-1.5">
                                    <div class="flex items-center gap-2 font-black text-sm text-slate-900 dark:text-white">
                                        <QrCode class="size-4 text-rose-900" />
                                        Matriz Integral de Asistencia
                                    </div>
                                    <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
                                        Detalle de todas las sesiones programadas (S1..S{{ totalSessions }}), total de asistencias acumuladas y porcentaje oficial de asistencia.
                                    </p>
                                </div>
                                <Button as-child size="sm" :class="[THEME_BUTTONS.primary, 'text-xs font-black w-full']">
                                    <a :href="`/courses/${course.id}/reports/attendance-csv`" download>
                                        <FileSpreadsheet class="size-3.5 mr-1.5" />
                                        Descargar Matriz CSV
                                    </a>
                                </Button>
                            </div>

                            <!-- Reporte 2: Acta Oficial de Notas -->
                            <div class="p-5 rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50 flex flex-col justify-between space-y-4">
                                <div class="space-y-1.5">
                                    <div class="flex items-center gap-2 font-black text-sm text-slate-900 dark:text-white">
                                        <FileText class="size-4 text-rose-900" />
                                        Acta Oficial de Calificaciones Vigesimales
                                    </div>
                                    <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
                                        Nómina con DNI, nombres completos, notas vigesimales (0-20), condición final (Aprobado/Reprobado) y código de certificación emitido.
                                    </p>
                                </div>
                                <Button as-child size="sm" variant="outline" class="text-xs font-bold border-rose-300 text-rose-950 w-full hover:bg-rose-50">
                                    <a :href="`/courses/${course.id}/reports/acta-csv`" download>
                                        <FileText class="size-3.5 mr-1.5" />
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
                            <Input
                                id="edit-phone"
                                v-model="editForm.phone"
                                type="tel"
                                inputmode="numeric"
                                pattern="[0-9]*"
                                maxlength="9"
                                placeholder="9XXXXXXXX (9 dígitos)"
                                class="h-9 text-xs font-medium font-mono"
                                @keypress="(e: KeyboardEvent) => { if (!/[0-9]/.test(e.key) && !['Backspace', 'Delete', 'ArrowLeft', 'ArrowRight', 'Tab'].includes(e.key)) e.preventDefault(); }"
                                @input="(e: Event) => { editForm.phone = ((e.target as HTMLInputElement).value || '').replace(/\D/g, '').slice(0, 9); }"
                            />
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

                    <div class="pt-2 border-t flex flex-col sm:flex-row gap-2">
                        <Button
                            v-if="credentialEnrollment?.credential_code"
                            type="button"
                            variant="secondary"
                            size="sm"
                            class="text-xs font-bold flex-1 flex items-center justify-center gap-1.5 border border-slate-200 dark:border-slate-800"
                            @click="copyCredentialCode(credentialEnrollment.credential_code)"
                        >
                            <Copy class="size-3.5 text-rose-800" />
                            <span>Copiar Código</span>
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="text-xs font-bold flex-1"
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
