<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDateRange, formatHours } from '@/lib/formatters';
import { THEME_BUTTONS, THEME_MODAL } from '@/lib/theme';
import { notify } from '@/lib/notify';
import {
    Search,
    Loader2,
    CheckCircle2,
    AlertCircle,
    UserCheck,
    GraduationCap,
    Calendar,
    Clock,
    Users,
    Building2,
    ShieldCheck,
    Phone,
    Mail,
    IdCard,
    QrCode,
    Lock,
    RefreshCw,
    Award,
    Info,
} from '@lucide/vue';

interface CourseTarget {
    id: number;
    code: string;
    title: string;
    institution?: string;
    hours: number;
    capacity: number;
    start_date: string;
    end_date: string;
    enrollments_count?: number;
    available_spots?: number;
    instructor?: {
        name: string;
        paterno?: string;
    };
}

const props = defineProps<{
    course: CourseTarget | null;
    open: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'enrolled'): void;
}>();

const page = usePage();
const authUser = computed(() => page.props.auth?.user);

// Form state
const dni = ref('');
const nombres = ref('');
const paterno = ref('');
const materno = ref('');
const email = ref('');
const phone = ref('');
const termsAccepted = ref(true);

const isLookingUpDni = ref(false);
const dniLookupSuccess = ref(false);
const dniLookupError = ref<string | null>(null);
const manualEntry = ref(false);
const isSubmitting = ref(false);
const submitSuccess = ref(false);
const submitError = ref<string | null>(null);

// Validation computed properties
const isDniFormatValid = computed(() => /^[0-9]{8}$/.test(dni.value.trim()));
const isEmailValid = computed(() => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim()));
const isPhoneValid = computed(() => !phone.value || /^9[0-9]{8}$/.test(phone.value.trim()));

function onPhoneInput(e: Event) {
    const target = e.target as HTMLInputElement;
    phone.value = target.value.replace(/\D/g, '').slice(0, 9);
}

function onDniInput(e: Event) {
    const target = e.target as HTMLInputElement;
    dni.value = target.value.replace(/\D/g, '').slice(0, 8);
}

function allowOnlyNumbers(e: KeyboardEvent) {
    if (['Backspace', 'Delete', 'Tab', 'ArrowLeft', 'ArrowRight', 'Home', 'End', 'Enter'].includes(e.key)) {
        return;
    }
    if (e.ctrlKey || e.metaKey) return;
    if (!/^[0-9]$/.test(e.key)) {
        e.preventDefault();
    }
}

const canSubmit = computed(() => {
    return (
        props.course !== null &&
        (dniLookupSuccess.value || manualEntry.value) &&
        isDniFormatValid.value &&
        nombres.value.trim().length > 0 &&
        paterno.value.trim().length > 0 &&
        isEmailValid.value &&
        isPhoneValid.value &&
        termsAccepted.value &&
        !isSubmitting.value
    );
});

// Reset form when modal opens or course changes
watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            submitSuccess.value = false;
            submitError.value = null;
            dniLookupError.value = null;
            termsAccepted.value = true;

            // Pre-fill if user is logged in
            if (authUser.value) {
                dni.value = authUser.value.dni || '';
                nombres.value = authUser.value.name || '';
                paterno.value = authUser.value.paterno || '';
                materno.value = authUser.value.materno || '';
                email.value = authUser.value.email || '';
                phone.value = authUser.value.phone || '';
                if (authUser.value.dni && authUser.value.dni.length === 8) {
                    dniLookupSuccess.value = true;
                } else {
                    dniLookupSuccess.value = false;
                }
            } else {
                dni.value = '';
                nombres.value = '';
                paterno.value = '';
                materno.value = '';
                email.value = '';
                phone.value = '';
                dniLookupSuccess.value = false;
            }
        }
    }
);

// Consulta DNI en API RENIEC
async function lookupDni() {
    const cleanDni = dni.value.trim();
    if (!/^[0-9]{8}$/.test(cleanDni)) {
        dniLookupError.value = 'El DNI debe contener exactamente 8 dígitos numéricos.';
        dniLookupSuccess.value = false;
        return;
    }

    isLookingUpDni.value = true;
    dniLookupError.value = null;
    dniLookupSuccess.value = false;

    try {
        const response = await fetch(`/api/dni/${cleanDni}`, {
            headers: {
                Accept: 'application/json',
            },
        });

        const data = await response.json();
        const p = data.data || data.person || {};

        if (response.ok && data.success && (p.nombres || p.nombre_completo)) {
            nombres.value = (p.nombres || '').trim();
            paterno.value = (p.paterno || p.apellido_paterno || '').trim();
            materno.value = (p.materno || p.apellido_materno || '').trim();
            dniLookupSuccess.value = true;
            dniLookupError.value = null;
            manualEntry.value = false;
            notify.success('RENIEC Validado', `${nombres.value} ${paterno.value}`, 2000);
        } else {
            dniLookupError.value =
                data.message || 'No se encontraron datos en RENIEC. Puedes ingresar los nombres y apellidos manualmente.';
            manualEntry.value = true;
            dniLookupSuccess.value = false;
            notify.info('Ingreso manual habilitado', 'Ingrese sus nombres y apellidos manualmente.', 2500);
        }
    } catch {
        dniLookupError.value = 'Error al comunicarse con RENIEC. Puedes ingresar los datos manualmente.';
        manualEntry.value = true;
        dniLookupSuccess.value = false;
        notify.info('Ingreso manual habilitado', 'Ingrese sus nombres y apellidos manualmente.', 2500);
    } finally {
        isLookingUpDni.value = false;
    }
}

// Reset DNI to allow querying another citizen
function resetDni() {
    dniLookupSuccess.value = false;
    manualEntry.value = false;
    dniLookupError.value = null;
    nombres.value = '';
    paterno.value = '';
    materno.value = '';
    dni.value = '';
}

// Envío de matrícula con validaciones estrictas
function submitEnrollment() {
    if (!props.course) return;

    if (!dniLookupSuccess.value && !manualEntry.value) {
        submitError.value = 'Debe validar su DNI con RENIEC o habilitar ingreso manual.';
        notify.warning('Validación requerida', submitError.value, 2500);
        return;
    }

    if (!nombres.value || !paterno.value) {
        submitError.value = 'Los datos de nombres y apellidos son obligatorios.';
        notify.warning('Campos incompletos', submitError.value, 2500);
        return;
    }

    if (!isEmailValid.value) {
        submitError.value = 'Ingrese una dirección de correo electrónico válida para recibir su comprobante.';
        notify.warning('Correo inválido', submitError.value, 2500);
        return;
    }

    if (phone.value && !isPhoneValid.value) {
        submitError.value = 'El número de celular debe contener 9 dígitos numéricos.';
        notify.warning('Teléfono inválido', submitError.value, 2500);
        return;
    }

    if (!termsAccepted.value) {
        submitError.value = 'Debe aceptar la declaración jurada para confirmar su inscripción.';
        notify.warning('Términos requeridos', submitError.value, 2500);
        return;
    }

    isSubmitting.value = true;
    submitError.value = null;

    router.post(
        `/courses/${props.course.id}/enroll`,
        {
            dni: dni.value.trim(),
            nombres: nombres.value.trim(),
            paterno: paterno.value.trim(),
            materno: materno.value.trim() || null,
            email: email.value.trim(),
            phone: phone.value.trim() || null,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                isSubmitting.value = false;
                submitSuccess.value = true;
                notify.success('¡Inscripción Confirmada!', `Inscrito exitosamente en ${props.course?.title}`, 2500);
                emit('enrolled');
            },
            onError: (errors) => {
                isSubmitting.value = false;
                const firstKey = Object.keys(errors)[0];
                submitError.value = errors[firstKey] || 'Ocurrió un error al procesar la inscripción.';
                notify.error('Error de inscripción', submitError.value, 3000);
            },
        }
    );
}

function closeModal() {
    emit('update:open', false);
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <!-- MODAL AMPLIO Y ESPACIOSO (THEME_MODAL.enrollmentDialog) -->
        <DialogContent :class="THEME_MODAL.enrollmentDialog">
            <!-- Header Superior del Modal (FIJO / PINNED) -->
            <div class="p-5 sm:px-7 sm:py-5 border-b border-slate-200 dark:border-slate-800 shrink-0 bg-white dark:bg-slate-950 pr-12">
                <DialogHeader class="space-y-1.5 text-left">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <span class="font-mono text-xs font-black px-2.5 py-0.5 rounded bg-rose-100 text-rose-950 dark:bg-rose-950 dark:text-rose-200 border border-rose-300 dark:border-rose-800">
                                {{ course?.code }}
                            </span>
                            <span v-if="course?.institution" class="text-xs font-bold text-rose-900 bg-rose-50 dark:bg-rose-950/60 dark:text-rose-200 border border-rose-200 dark:border-rose-800 px-2.5 py-0.5 rounded">
                                {{ course.institution }}
                            </span>
                        </div>

                        <div class="flex items-center gap-1.5 text-xs font-black text-rose-900 dark:text-rose-300 bg-rose-50 dark:bg-rose-950 px-3 py-1 rounded-full border border-rose-200 dark:border-rose-800">
                            <ShieldCheck class="size-4 text-rose-800" />
                            <span>Inscripción Oficial con Validación RENIEC</span>
                        </div>
                    </div>

                    <DialogTitle class="text-xl sm:text-2xl font-black text-slate-950 dark:text-white pt-1 leading-snug">
                        Ficha de Inscripción: {{ course?.title }}
                    </DialogTitle>
                    <DialogDescription class="text-xs text-slate-600 dark:text-slate-400 font-medium">
                        Completa la verificación de tu DNI para reservar tu vacante oficial en el sistema académico.
                    </DialogDescription>
                </DialogHeader>
            </div>

            <!-- Contenedor Scrollable Interno con scrollbar redondeado y limpio -->
            <div class="flex-1 overflow-y-auto overscroll-contain custom-scrollbar p-5 sm:p-7">
                <!-- Success State -->
                <div v-if="submitSuccess" class="py-6 text-center space-y-6">
                <div class="size-20 bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300 rounded-full flex items-center justify-center mx-auto shadow-inner ring-8 ring-rose-50 dark:ring-rose-900/40">
                    <CheckCircle2 class="size-10" />
                </div>
                <div class="space-y-1.5">
                    <h3 class="text-2xl font-black text-slate-950 dark:text-white">¡Inscripción Confirmada Exitosamente!</h3>
                    <p class="text-sm font-semibold text-slate-700 dark:text-slate-300 max-w-lg mx-auto">
                        Has quedado registrado formalmente en la capacitación <strong class="text-rose-900 dark:text-rose-300">{{ course?.title }}</strong>.
                    </p>
                </div>

                <div class="max-w-lg mx-auto p-5 bg-rose-50/80 dark:bg-rose-950/40 rounded-2xl text-xs text-slate-900 dark:text-slate-200 space-y-2.5 text-left border border-rose-200 dark:border-rose-800 font-semibold shadow-xs">
                    <div class="flex justify-between border-b border-rose-200/80 pb-2">
                        <span class="text-slate-600 dark:text-slate-400 font-medium">Participante:</span>
                        <strong class="text-slate-950 dark:text-white text-sm">{{ nombres }} {{ paterno }} {{ materno }}</strong>
                    </div>
                    <div class="flex justify-between border-b border-rose-200/80 pb-2">
                        <span class="text-slate-600 dark:text-slate-400 font-medium">DNI Registrado:</span>
                        <strong class="font-mono text-slate-950 dark:text-white">{{ dni }}</strong>
                    </div>
                    <div class="flex justify-between border-b border-rose-200/80 pb-2">
                        <span class="text-slate-600 dark:text-slate-400 font-medium">Correo Electrónico:</span>
                        <span class="font-bold text-slate-950 dark:text-white">{{ email }}</span>
                    </div>
                    <div v-if="phone" class="flex justify-between border-b border-rose-200/80 pb-2">
                        <span class="text-slate-600 dark:text-slate-400 font-medium">Teléfono / WhatsApp:</span>
                        <span class="font-bold text-slate-950 dark:text-white">{{ phone }}</span>
                    </div>
                    <div class="flex items-center gap-2 pt-2 text-rose-950 dark:text-rose-200 font-bold text-xs">
                        <QrCode class="size-5 shrink-0 text-rose-800" />
                        <span>Podrás marcar tu asistencia con código QR en cada clase y descargar tu diploma digital.</span>
                    </div>
                </div>

                <div class="pt-2">
                    <Button @click="closeModal" class="bg-rose-900 hover:bg-rose-950 text-white font-black px-8 py-2.5 text-xs shadow-md">
                        Entendido / Cerrar Ventana
                    </Button>
                </div>
            </div>

            <!-- Form State: Distribución Amplia de 2 Columnas -->
            <form v-else @submit.prevent="submitEnrollment" class="py-2 space-y-5">
                <!-- Banner de Error General -->
                <div v-if="submitError" class="p-3 rounded-xl bg-rose-100 dark:bg-rose-950/60 border border-rose-300 dark:border-rose-900 text-rose-950 dark:text-rose-200 text-xs font-bold flex items-start gap-2.5">
                    <AlertCircle class="size-4 shrink-0 mt-0.5 text-rose-800" />
                    <span>{{ submitError }}</span>
                </div>

                <!-- Grilla Principal: Izquierda (Resumen) + Derecha (Formulario) -->
                <div class="grid grid-cols-1 md:grid-cols-12 gap-6 items-start">
                    <!-- Columna Izquierda (5 cols): Resumen del Programa -->
                    <div class="md:col-span-5 space-y-4 bg-slate-50 dark:bg-slate-900/60 p-4 sm:p-5 rounded-2xl border border-slate-200 dark:border-slate-800">
                        <div class="text-xs font-black uppercase tracking-wider text-rose-900 dark:text-rose-400 flex items-center gap-1.5 border-b pb-2">
                            <GraduationCap class="size-4 text-rose-800" />
                            <span>Detalles de la Capacitación</span>
                        </div>

                        <div class="space-y-3 text-xs">
                            <div>
                                <span class="text-slate-600 dark:text-slate-400 block text-[11px] font-medium">Programa Académico:</span>
                                <h4 class="font-bold text-slate-950 dark:text-white leading-tight">
                                    {{ course?.title }}
                                </h4>
                            </div>

                            <div v-if="course?.institution" class="flex items-center gap-2 text-slate-800 dark:text-slate-200">
                                <Building2 class="size-4 text-rose-800 shrink-0" />
                                <div>
                                    <span class="text-slate-600 dark:text-slate-400 block text-[10px]">Entidad Convocante:</span>
                                    <strong class="font-semibold">{{ course.institution }}</strong>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 text-slate-800 dark:text-slate-200">
                                <Calendar class="size-4 text-rose-800 shrink-0" />
                                <div>
                                    <span class="text-slate-600 dark:text-slate-400 block text-[10px]">Periodo de Clases:</span>
                                    <strong class="font-semibold">{{ formatDateRange(course?.start_date, course?.end_date, 'compact') }}</strong>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 text-slate-800 dark:text-slate-200">
                                <Clock class="size-4 text-amber-600 shrink-0" />
                                <div>
                                    <span class="text-slate-600 dark:text-slate-400 block text-[10px]">Carga Horaria:</span>
                                    <strong class="font-semibold">{{ formatHours(course?.hours) }}</strong>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 text-slate-800 dark:text-slate-200">
                                <Users class="size-4 text-blue-600 shrink-0" />
                                <div>
                                    <span class="text-slate-600 dark:text-slate-400 block text-[10px]">Vacantes Oficiales:</span>
                                    <strong class="font-bold text-rose-900 dark:text-rose-300">
                                        {{ course?.capacity ? `${course.capacity} vacantes programadas` : 'Disponibilidad abierta' }}
                                    </strong>
                                </div>
                            </div>
                        </div>

                        <!-- Garantías Institucionales -->
                        <div class="pt-3 border-t border-slate-200 dark:border-slate-800 space-y-2 text-[11px] text-slate-700 dark:text-slate-300">
                            <div class="flex items-start gap-2">
                                <QrCode class="size-3.5 text-rose-800 shrink-0 mt-0.5" />
                                <span>Asistencia controlada mediante QR individual.</span>
                            </div>
                            <div class="flex items-start gap-2">
                                <Award class="size-3.5 text-amber-600 shrink-0 mt-0.5" />
                                <span>Certificado digital con código de verificación web.</span>
                            </div>
                            <div class="flex items-start gap-2">
                                <ShieldCheck class="size-3.5 text-blue-600 shrink-0 mt-0.5" />
                                <span>Conexión en línea con RENIEC para validar nombres.</span>
                            </div>
                        </div>
                    </div>

                    <!-- Columna Derecha (7 cols): Formulario de Matrícula y Validación -->
                    <div class="md:col-span-7 space-y-5">
                        <!-- Paso 1: Verificación de DNI con RENIEC -->
                        <div class="space-y-2.5 p-4 rounded-xl border border-rose-200/90 dark:border-rose-900/60 bg-rose-50/40 dark:bg-rose-950/20">
                            <div class="flex items-center justify-between">
                                <Label for="dni-input" class="text-xs font-black text-slate-950 dark:text-white flex items-center gap-1.5">
                                    <IdCard class="size-4 text-rose-800" />
                                    <span>Paso 1: DNI del Participante</span>
                                    <span class="text-rose-600">*</span>
                                </Label>
                                <span class="text-[10px] font-black px-2 py-0.5 rounded bg-rose-900 text-white">
                                    RENIEC PERÚ
                                </span>
                            </div>

                            <div class="flex gap-2">
                                <Input
                                    id="dni-input"
                                    v-model="dni"
                                    type="text"
                                    inputmode="numeric"
                                    pattern="[0-9]*"
                                    maxlength="8"
                                    placeholder="Ingresa 8 dígitos numéricos"
                                    class="font-mono text-sm tracking-wider font-bold bg-white dark:bg-slate-950"
                                    :disabled="isSubmitting || dniLookupSuccess"
                                    @keypress="allowOnlyNumbers"
                                    @input="onDniInput"
                                    @keyup.enter.prevent="lookupDni"
                                />
                                <Button
                                    v-if="!dniLookupSuccess"
                                    type="button"
                                    :disabled="isLookingUpDni || !isDniFormatValid"
                                    @click="lookupDni"
                                    class="shrink-0 text-xs font-black bg-rose-900 hover:bg-rose-950 text-white px-4 shadow-xs"
                                >
                                    <Loader2 v-if="isLookingUpDni" class="size-3.5 mr-1.5 animate-spin" />
                                    <Search v-else class="size-3.5 mr-1.5" />
                                    Validar RENIEC
                                </Button>
                                <Button
                                    v-else
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    @click="resetDni"
                                    class="shrink-0 text-xs font-bold border-slate-300 hover:bg-rose-50 text-rose-900"
                                >
                                    <RefreshCw class="size-3.5 mr-1.5" />
                                    Cambiar DNI
                                </Button>
                            </div>

                            <!-- Estado: Error al buscar DNI -->
                            <div v-if="dniLookupError" class="text-xs font-bold text-rose-700 dark:text-rose-400 flex flex-col gap-1 pt-1">
                                <div class="flex items-center gap-1.5">
                                    <AlertCircle class="size-4 text-rose-600 shrink-0" />
                                    <span>{{ dniLookupError }}</span>
                                </div>
                                <button
                                    type="button"
                                    @click="manualEntry = true"
                                    class="text-left text-xs font-bold text-rose-900 dark:text-rose-300 underline pl-5 cursor-pointer hover:text-rose-950"
                                >
                                    ✍️ Ingresar nombres y apellidos manualmente para continuar
                                </button>
                            </div>

                            <!-- Estado: Mensaje cuando aún no se validó -->
                            <div v-if="!dniLookupSuccess && !dniLookupError" class="flex flex-col gap-1.5 pt-0.5">
                                <div class="text-[11px] font-medium text-slate-600 dark:text-slate-400 flex items-center gap-1.5">
                                    <Info class="size-3.5 text-slate-500 shrink-0" />
                                    <span>Ingresa los 8 dígitos y pulsa «Validar RENIEC». Los nombres se cargarán automáticamente.</span>
                                </div>
                                <button
                                    type="button"
                                    @click="manualEntry = !manualEntry"
                                    class="text-left text-[11px] font-bold text-rose-900 dark:text-rose-400 hover:underline cursor-pointer"
                                >
                                    {{ manualEntry ? 'Volver a validación automática RENIEC' : '¿Problemas con el servicio RENIEC? Habilitar ingreso manual' }}
                                </button>
                            </div>

                            <!-- Estado: Éxito en RENIEC (Tarjeta de confirmación) -->
                            <div
                                v-if="dniLookupSuccess"
                                class="p-3 bg-rose-100/90 dark:bg-rose-950/70 border border-rose-300 dark:border-rose-700 rounded-xl flex items-center gap-3 mt-2"
                            >
                                <div class="size-9 rounded-full bg-rose-900 text-white flex items-center justify-center font-black shrink-0 shadow-xs">
                                    <UserCheck class="size-5 text-amber-300" />
                                </div>
                                <div class="min-w-0 flex-1 text-xs">
                                    <div class="font-black text-slate-950 dark:text-white truncate text-sm">
                                        {{ nombres }} {{ paterno }} {{ materno }}
                                    </div>
                                    <div class="text-[11px] font-bold text-rose-900 dark:text-rose-300 flex items-center gap-1 mt-0.5">
                                        <ShieldCheck class="size-3.5" />
                                        <span>Identidad verificada exitosamente en la base de datos nacional.</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Paso 2: Datos de Identidad -->
                        <div class="space-y-3 p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900">
                            <div class="flex items-center justify-between border-b pb-2">
                                <div class="text-xs font-black text-slate-950 dark:text-white flex items-center gap-1.5">
                                    <Lock v-if="dniLookupSuccess" class="size-3.5 text-rose-900" />
                                    <UserCheck v-else class="size-3.5 text-amber-600" />
                                    <span>Paso 2: Datos de Identidad</span>
                                </div>
                                <span
                                    class="text-[10px] font-bold px-2 py-0.5 rounded"
                                    :class="dniLookupSuccess ? 'bg-rose-100 text-rose-900 dark:bg-rose-950 dark:text-rose-200' : (manualEntry ? 'bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-200' : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300')"
                                >
                                    {{ dniLookupSuccess ? '🔒 Verificado (RENIEC)' : (manualEntry ? '✍️ Ingreso Manual Habilitado' : 'Pendiente de DNI') }}
                                </span>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div class="space-y-1 sm:col-span-2">
                                    <Label for="nombres" class="text-xs font-bold text-slate-800 dark:text-slate-200 flex items-center justify-between">
                                        <span>Nombres Completos <span class="text-rose-600">*</span></span>
                                        <span v-if="dniLookupSuccess" class="text-[10px] text-rose-800 font-bold">Oficial RENIEC</span>
                                        <span v-else-if="manualEntry" class="text-[10px] text-amber-700 font-bold">Ingreso Manual</span>
                                    </Label>
                                    <div class="relative">
                                        <Input
                                            id="nombres"
                                            v-model="nombres"
                                            type="text"
                                            :readonly="dniLookupSuccess"
                                            :tabindex="dniLookupSuccess ? -1 : 0"
                                            :placeholder="dniLookupSuccess ? 'Nombres oficiales' : (manualEntry ? 'Ingresa los nombres del participante' : 'Pendiente de consulta DNI')"
                                            :class="[
                                                'text-xs font-bold pr-8',
                                                dniLookupSuccess
                                                    ? 'bg-slate-100/90 dark:bg-slate-950/80 cursor-not-allowed border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white'
                                                    : 'bg-white dark:bg-slate-950 border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-rose-800'
                                            ]"
                                        />
                                        <Lock v-if="dniLookupSuccess" class="size-3.5 text-slate-400 absolute right-2.5 top-1/2 -translate-y-1/2 pointer-events-none" />
                                    </div>
                                </div>

                                <div class="space-y-1">
                                    <Label for="paterno" class="text-xs font-bold text-slate-800 dark:text-slate-200 flex items-center justify-between">
                                        <span>Apellido Paterno <span class="text-rose-600">*</span></span>
                                        <span v-if="manualEntry" class="text-[10px] text-amber-700 font-bold">Manual</span>
                                    </Label>
                                    <div class="relative">
                                        <Input
                                            id="paterno"
                                            v-model="paterno"
                                            type="text"
                                            :readonly="dniLookupSuccess"
                                            :tabindex="dniLookupSuccess ? -1 : 0"
                                            :placeholder="dniLookupSuccess ? 'Apellido paterno' : (manualEntry ? 'Primer apellido' : 'Pendiente de DNI')"
                                            :class="[
                                                'text-xs font-bold pr-8',
                                                dniLookupSuccess
                                                    ? 'bg-slate-100/90 dark:bg-slate-950/80 cursor-not-allowed border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white'
                                                    : 'bg-white dark:bg-slate-950 border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-rose-800'
                                            ]"
                                        />
                                        <Lock v-if="dniLookupSuccess" class="size-3.5 text-slate-400 absolute right-2.5 top-1/2 -translate-y-1/2 pointer-events-none" />
                                    </div>
                                </div>

                                <div class="space-y-1">
                                    <Label for="materno" class="text-xs font-bold text-slate-800 dark:text-slate-200 flex items-center justify-between">
                                        <span>Apellido Materno</span>
                                        <span v-if="manualEntry" class="text-[10px] text-slate-500 font-normal">Opcional</span>
                                    </Label>
                                    <div class="relative">
                                        <Input
                                            id="materno"
                                            v-model="materno"
                                            type="text"
                                            :readonly="dniLookupSuccess"
                                            :tabindex="dniLookupSuccess ? -1 : 0"
                                            :placeholder="dniLookupSuccess ? 'Apellido materno' : (manualEntry ? 'Segundo apellido' : 'Pendiente de DNI')"
                                            :class="[
                                                'text-xs font-bold pr-8',
                                                dniLookupSuccess
                                                    ? 'bg-slate-100/90 dark:bg-slate-950/80 cursor-not-allowed border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white'
                                                    : 'bg-white dark:bg-slate-950 border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-rose-800'
                                            ]"
                                        />
                                        <Lock v-if="dniLookupSuccess" class="size-3.5 text-slate-400 absolute right-2.5 top-1/2 -translate-y-1/2 pointer-events-none" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Paso 3: Datos de Contacto (EDITABLES) -->
                        <div class="space-y-3 p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900">
                            <div class="text-xs font-black text-slate-950 dark:text-white border-b pb-2 flex items-center gap-1.5">
                                <Mail class="size-3.5 text-rose-900" />
                                <span>Paso 3: Información de Contacto</span>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div class="space-y-1 sm:col-span-2">
                                    <Label for="email" class="text-xs font-bold text-slate-800 dark:text-slate-200 flex items-center justify-between">
                                        <span class="flex items-center gap-1">
                                            <Mail class="size-3 text-rose-800" />
                                            Correo Electrónico <span class="text-rose-600">*</span>
                                        </span>
                                        <span class="text-[10px] text-slate-500 font-normal">Para envío de confirmación y diplomas</span>
                                    </Label>
                                    <Input
                                        id="email"
                                        v-model="email"
                                        type="email"
                                        placeholder="ejemplo@correo.com"
                                        class="text-xs font-medium text-slate-900 dark:text-white"
                                        :class="{ 'border-rose-500 ring-rose-500/20': email && !isEmailValid }"
                                        required
                                    />
                                    <span v-if="email && !isEmailValid" class="text-[11px] font-bold text-rose-600">
                                        Ingrese un correo válido con formato usuario@dominio.com
                                    </span>
                                </div>

                                <div class="space-y-1 sm:col-span-2">
                                    <Label for="phone" class="text-xs font-bold text-slate-800 dark:text-slate-200 flex items-center gap-1">
                                        <Phone class="size-3 text-rose-800" />
                                        <span>Celular / WhatsApp (Perú)</span>
                                    </Label>
                                    <Input
                                        id="phone"
                                        v-model="phone"
                                        type="tel"
                                        inputmode="numeric"
                                        pattern="[0-9]*"
                                        maxlength="9"
                                        placeholder="9XXXXXXXX (9 dígitos numéricos)"
                                        class="text-xs font-medium text-slate-900 dark:text-white font-mono"
                                        :class="{ 'border-rose-500 ring-rose-500/20': phone && !isPhoneValid }"
                                        @keypress="allowOnlyNumbers"
                                        @input="onPhoneInput"
                                    />
                                    <span v-if="phone && !isPhoneValid" class="text-[11px] font-bold text-rose-600">
                                        El número de celular debe contener 9 dígitos numéricos e iniciar con 9.
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Declaración Jurada Obligatoria -->
                        <div
                            class="p-3 bg-slate-50 dark:bg-slate-900/60 rounded-xl border transition-colors"
                            :class="!termsAccepted ? 'border-amber-300 dark:border-amber-800 bg-amber-50/50 dark:bg-amber-950/20' : 'border-slate-200 dark:border-slate-800'"
                        >
                            <label class="flex items-start gap-3 cursor-pointer text-xs text-slate-800 dark:text-slate-200 select-none">
                                <input
                                    type="checkbox"
                                    v-model="termsAccepted"
                                    class="mt-0.5 size-4 rounded text-rose-900 border-slate-300 focus:ring-rose-800 focus:ring-2 cursor-pointer accent-rose-900 shrink-0"
                                />
                                <span class="font-medium leading-relaxed">
                                    Declaro bajo juramento que los datos de DNI y contacto son legítimos y acepto participar cumpliendo con la asistencia por código QR y evaluaciones de la capacitación.
                                </span>
                            </label>
                        </div>

                        <!-- Banner de Error antes de enviar si existe -->
                        <div v-if="submitError" class="p-3 rounded-xl bg-rose-100 dark:bg-rose-950/60 border border-rose-300 dark:border-rose-900 text-rose-950 dark:text-rose-200 text-xs font-bold flex items-start gap-2.5 animate-in fade-in">
                            <AlertCircle class="size-4 shrink-0 mt-0.5 text-rose-800" />
                            <span>{{ submitError }}</span>
                        </div>

                        <!-- Botones de Acción -->
                        <div class="flex flex-col-reverse sm:flex-row items-center justify-end gap-3 pt-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="default"
                                @click="closeModal"
                                :disabled="isSubmitting"
                                class="w-full sm:w-auto text-xs font-bold border-slate-300"
                            >
                                Cancelar
                            </Button>
                            <Button
                                type="submit"
                                size="default"
                                :class="['w-full sm:w-auto h-10 px-6 text-xs font-black shadow-md cursor-pointer', THEME_BUTTONS.primary]"
                                :disabled="isSubmitting"
                            >
                                <Loader2 v-if="isSubmitting" class="size-4 mr-2 animate-spin" />
                                <CheckCircle2 v-else class="size-4 mr-2" />
                                Confirmar Inscripción Oficial
                            </Button>
                        </div>
                    </div>
                </div>
            </form>
            </div>
        </DialogContent>
    </Dialog>
</template>
