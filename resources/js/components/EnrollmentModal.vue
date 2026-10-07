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
import { Badge } from '@/components/ui/badge';
import { formatDate, formatDateRange, formatHours } from '@/lib/formatters';
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

// Status state
const isLookingUpDni = ref(false);
const dniLookupSuccess = ref(false);
const dniLookupError = ref<string | null>(null);
const isSubmitting = ref(false);
const submitSuccess = ref(false);
const submitError = ref<string | null>(null);

// Reset form when modal opens or course changes
watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            submitSuccess.value = false;
            submitError.value = null;
            dniLookupError.value = null;

            // Pre-fill if user logged in
            if (authUser.value) {
                dni.value = authUser.value.dni || '';
                nombres.value = authUser.value.name || '';
                paterno.value = authUser.value.paterno || '';
                materno.value = authUser.value.materno || '';
                email.value = authUser.value.email || '';
                phone.value = authUser.value.phone || '';
                if (authUser.value.dni && authUser.value.dni.length === 8) {
                    dniLookupSuccess.value = true;
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

// Consulta DNI en API PeruDevs
async function lookupDni() {
    const cleanDni = dni.value.trim();
    if (!/^[0-9]{8}$/.test(cleanDni)) {
        dniLookupError.value = 'Ingrese un DNI válido de 8 dígitos numéricos.';
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
            nombres.value = p.nombres || '';
            paterno.value = p.paterno || p.apellido_paterno || '';
            materno.value = p.materno || p.apellido_materno || '';
            dniLookupSuccess.value = true;
        } else {
            dniLookupError.value =
                data.message || 'No se encontraron datos para este DNI en RENIEC.';
        }
    } catch (err) {
        dniLookupError.value = 'Error al comunicarse con el servicio de DNI. Intente nuevamente.';
    } finally {
        isLookingUpDni.value = false;
    }
}

// Envío de matrícula
function submitEnrollment() {
    if (!props.course) return;

    if (!dni.value || dni.value.length !== 8) {
        submitError.value = 'Debe ingresar y validar un DNI de 8 dígitos.';
        return;
    }

    if (!nombres.value || !paterno.value) {
        submitError.value = 'Nombres y apellidos son requeridos.';
        return;
    }

    if (!email.value || !email.value.includes('@')) {
        submitError.value = 'Debe proporcionar un correo electrónico válido para recibir su confirmación.';
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
                emit('enrolled');
            },
            onError: (errors) => {
                isSubmitting.value = false;
                const firstKey = Object.keys(errors)[0];
                submitError.value = errors[firstKey] || 'Ocurrió un error al procesar la inscripción.';
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
        <!-- MODAL AMPLIO Y ESPACIOSO (max-w-4xl) CON DIMENSIONES CÓMODAS -->
        <DialogContent class="w-full sm:max-w-2xl md:max-w-3xl lg:max-w-4xl max-h-[92vh] overflow-y-auto p-6 sm:p-8 rounded-2xl shadow-2xl border border-slate-300 dark:border-slate-800">
            <!-- Header con colores vivos -->
            <DialogHeader class="space-y-2 border-b border-slate-200 dark:border-slate-800 pb-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-100 text-emerald-900 border border-emerald-300 dark:bg-emerald-950 dark:text-emerald-200 text-xs font-bold uppercase tracking-wider">
                        <GraduationCap class="size-4 text-emerald-700 dark:text-emerald-400" />
                        <span>Ficha Oficial de Inscripción</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="font-mono text-xs font-bold px-2.5 py-1 rounded bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-200 border border-slate-300 dark:border-slate-700">
                            Cód: {{ course?.code }}
                        </span>
                        <span v-if="course?.institution" class="text-xs font-bold text-blue-800 bg-blue-50 border border-blue-200 px-2.5 py-1 rounded">
                            {{ course.institution }}
                        </span>
                    </div>
                </div>

                <DialogTitle class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white leading-tight">
                    {{ course?.title }}
                </DialogTitle>

                <DialogDescription class="text-xs sm:text-sm font-medium text-slate-700 dark:text-slate-300">
                    Completa tus datos personales para asegurar tu vacante oficial y habilitar tu asistencia por código QR.
                </DialogDescription>
            </DialogHeader>

            <!-- Success State -->
            <div v-if="submitSuccess" class="py-8 text-center space-y-6">
                <div class="size-20 bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300 rounded-full flex items-center justify-center mx-auto shadow-inner ring-8 ring-emerald-50 dark:ring-emerald-900/30">
                    <CheckCircle2 class="size-10" />
                </div>
                <div class="space-y-2">
                    <h3 class="text-2xl font-extrabold text-slate-900 dark:text-white">¡Inscripción Confirmada con Éxito!</h3>
                    <p class="text-sm font-medium text-slate-700 dark:text-slate-300 max-w-lg mx-auto">
                        Has quedado registrado formalmente en <strong class="text-emerald-800 dark:text-emerald-300">{{ course?.title }}</strong>.
                    </p>
                </div>

                <div class="max-w-xl mx-auto p-5 bg-emerald-50/80 dark:bg-emerald-950/40 rounded-xl text-xs sm:text-sm text-slate-800 dark:text-slate-200 space-y-2 text-left border-2 border-emerald-200 dark:border-emerald-800 shadow-sm">
                    <div class="flex justify-between border-b border-emerald-200 pb-2">
                        <span class="font-semibold text-emerald-950 dark:text-emerald-200">Participante Registrado:</span>
                        <span class="font-bold text-slate-900 dark:text-white">{{ nombres }} {{ paterno }} {{ materno }}</span>
                    </div>
                    <div class="flex justify-between border-b border-emerald-200 pb-2">
                        <span class="font-semibold text-emerald-950 dark:text-emerald-200">Documento de Identidad (DNI):</span>
                        <span class="font-mono font-bold text-slate-900 dark:text-white">{{ dni }}</span>
                    </div>
                    <div class="flex justify-between border-b border-emerald-200 pb-2">
                        <span class="font-semibold text-emerald-950 dark:text-emerald-200">Correo Electrónico:</span>
                        <span class="font-bold text-slate-900 dark:text-white">{{ email }}</span>
                    </div>
                    <div class="flex items-center gap-2 pt-2 text-emerald-800 dark:text-emerald-300 font-bold">
                        <QrCode class="size-5 shrink-0 text-emerald-600" />
                        <span>¡Tu cuenta ya está lista para escanear el QR del docente en cada sesión de clase!</span>
                    </div>
                </div>

                <div class="pt-2">
                    <Button @click="closeModal" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-8 py-2.5 text-sm shadow-md rounded-lg">
                        Cerrar y Regresar al Panel
                    </Button>
                </div>
            </div>

            <!-- Form State (Grid de 2 Columnas Espacioso) -->
            <form v-else @submit.prevent="submitEnrollment" class="space-y-6 pt-4">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                    <!-- Columna Izquierda: Información de la Capacitación (4 cols) -->
                    <div class="lg:col-span-5 space-y-4 bg-slate-50 dark:bg-slate-900/80 p-5 rounded-xl border border-slate-200 dark:border-slate-800 text-xs text-slate-700 dark:text-slate-300">
                        <h4 class="text-sm font-extrabold text-slate-900 dark:text-white uppercase tracking-wider flex items-center gap-1.5">
                            <ShieldCheck class="size-4 text-emerald-600" />
                            Detalles del Programa
                        </h4>

                        <div class="space-y-3">
                            <div class="flex items-start gap-2.5">
                                <Calendar class="size-4 text-emerald-600 shrink-0 mt-0.5" />
                                <div>
                                    <span class="block font-bold text-slate-900 dark:text-white">Período de Clases:</span>
                                    <span class="font-medium">{{ formatDateRange(course?.start_date, course?.end_date, 'compact') }}</span>
                                </div>
                            </div>

                            <div class="flex items-start gap-2.5">
                                <Clock class="size-4 text-blue-600 shrink-0 mt-0.5" />
                                <div>
                                    <span class="block font-bold text-slate-900 dark:text-white">Horas Académicas:</span>
                                    <span class="font-medium">{{ formatHours(course?.hours) }}</span>
                                </div>
                            </div>

                            <div v-if="course?.instructor" class="flex items-start gap-2.5">
                                <GraduationCap class="size-4 text-indigo-600 shrink-0 mt-0.5" />
                                <div>
                                    <span class="block font-bold text-slate-900 dark:text-white">Docente / Expositor:</span>
                                    <span class="font-medium">{{ course.instructor.name }} {{ course.instructor.paterno }}</span>
                                </div>
                            </div>

                            <div class="flex items-start gap-2.5">
                                <Users class="size-4 text-teal-600 shrink-0 mt-0.5" />
                                <div>
                                    <span class="block font-bold text-slate-900 dark:text-white">Disponibilidad de Aforo:</span>
                                    <span class="font-bold text-emerald-700 dark:text-emerald-400">
                                        {{ course?.capacity ? `${course.capacity} vacantes máximas` : 'Abierto' }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="pt-3 border-t border-slate-200 dark:border-slate-700 space-y-2">
                            <div class="flex items-center gap-2 text-[11px] font-bold text-slate-800 dark:text-slate-200">
                                <span class="size-2 rounded-full bg-emerald-500"></span>
                                Certificación digital con validación web inmediata.
                            </div>
                            <div class="flex items-center gap-2 text-[11px] font-bold text-slate-800 dark:text-slate-200">
                                <span class="size-2 rounded-full bg-blue-500"></span>
                                Control de asistencia en línea con código QR.
                            </div>
                        </div>
                    </div>

                    <!-- Columna Derecha: Formulario de Registro (7 cols) -->
                    <div class="lg:col-span-7 space-y-4">
                        <!-- Error Alert -->
                        <div v-if="submitError" class="p-3 rounded-lg bg-rose-100 dark:bg-rose-950/60 border border-rose-300 dark:border-rose-800 text-rose-900 dark:text-rose-200 text-xs font-semibold flex items-start gap-2">
                            <AlertCircle class="size-4 shrink-0 mt-0.5 text-rose-600" />
                            <span>{{ submitError }}</span>
                        </div>

                        <!-- Sección 1: DNI y RENIEC -->
                        <div class="space-y-2 bg-white dark:bg-slate-950 p-4 rounded-xl border border-slate-300 dark:border-slate-800 shadow-xs">
                            <div class="flex items-center justify-between">
                                <Label for="dni-input" class="text-xs font-bold text-slate-900 dark:text-white flex items-center gap-1.5">
                                    <IdCard class="size-4 text-emerald-600" />
                                    Número de DNI del Participante <span class="text-rose-600">*</span>
                                </Label>
                                <span class="text-[11px] font-semibold text-emerald-800 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/50 px-2 py-0.5 rounded border border-emerald-200">
                                    Conexión RENIEC Activa
                                </span>
                            </div>

                            <div class="flex gap-2">
                                <Input
                                    id="dni-input"
                                    v-model="dni"
                                    type="text"
                                    maxlength="8"
                                    placeholder="8 dígitos numéricos"
                                    class="font-mono text-sm tracking-wider font-bold border-slate-300 focus:border-emerald-600 focus:ring-emerald-500 text-slate-900 dark:text-white"
                                    :disabled="isSubmitting || dniLookupSuccess"
                                    @keyup.enter.prevent="lookupDni"
                                />
                                <Button
                                    type="button"
                                    :disabled="isLookingUpDni || dni.length !== 8 || dniLookupSuccess"
                                    @click="lookupDni"
                                    class="shrink-0 text-xs font-bold bg-emerald-700 hover:bg-emerald-800 text-white shadow-xs"
                                >
                                    <Loader2 v-if="isLookingUpDni" class="size-3.5 mr-1.5 animate-spin" />
                                    <Search v-else class="size-3.5 mr-1.5" />
                                    Consultar RENIEC
                                </Button>
                                <Button
                                    v-if="dniLookupSuccess && !authUser"
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    @click="dniLookupSuccess = false; nombres = ''; paterno = ''; materno = ''"
                                    class="text-xs font-semibold text-slate-700 hover:text-slate-900"
                                >
                                    Cambiar
                                </Button>
                            </div>

                            <!-- DNI Lookup Feedback -->
                            <div v-if="dniLookupError" class="text-xs font-bold text-rose-700 dark:text-rose-400 flex items-center gap-1 pt-1">
                                <AlertCircle class="size-3.5 text-rose-600" />
                                <span>{{ dniLookupError }}</span>
                            </div>

                            <!-- Citizen Data Preview con colores vivos -->
                            <div
                                v-if="dniLookupSuccess"
                                class="p-3 bg-emerald-100/90 dark:bg-emerald-950/60 border-2 border-emerald-400 dark:border-emerald-700 rounded-lg flex items-center gap-3 mt-2 shadow-xs"
                            >
                                <div class="size-10 rounded-full bg-emerald-700 text-white flex items-center justify-center font-bold text-sm shrink-0 shadow-xs">
                                    <UserCheck class="size-5" />
                                </div>
                                <div class="min-w-0 flex-1 text-xs">
                                    <div class="font-extrabold text-slate-900 dark:text-white text-sm truncate">
                                        {{ nombres }} {{ paterno }} {{ materno }}
                                    </div>
                                    <div class="text-[11px] font-bold text-emerald-900 dark:text-emerald-200">
                                        ✓ Identidad ciudadana validada oficialmente
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Sección 2: Nombres y Apellidos en Grilla -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div class="space-y-1.5">
                                <Label for="nombres" class="text-xs font-bold text-slate-900 dark:text-white">
                                    Nombres Completos <span class="text-rose-600">*</span>
                                </Label>
                                <Input
                                    id="nombres"
                                    v-model="nombres"
                                    type="text"
                                    placeholder="Nombres de pila"
                                    class="text-xs font-semibold text-slate-900 dark:text-white border-slate-300"
                                    required
                                />
                            </div>
                            <div class="space-y-1.5">
                                <Label for="paterno" class="text-xs font-bold text-slate-900 dark:text-white">
                                    Apellido Paterno <span class="text-rose-600">*</span>
                                </Label>
                                <Input
                                    id="paterno"
                                    v-model="paterno"
                                    type="text"
                                    placeholder="Primer apellido"
                                    class="text-xs font-semibold text-slate-900 dark:text-white border-slate-300"
                                    required
                                />
                            </div>
                            <div class="space-y-1.5 sm:col-span-2">
                                <Label for="materno" class="text-xs font-bold text-slate-900 dark:text-white">
                                    Apellido Materno
                                </Label>
                                <Input
                                    id="materno"
                                    v-model="materno"
                                    type="text"
                                    placeholder="Segundo apellido"
                                    class="text-xs font-semibold text-slate-900 dark:text-white border-slate-300"
                                />
                            </div>
                        </div>

                        <!-- Sección 3: Contacto -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-3 border-t border-slate-200 dark:border-slate-800">
                            <div class="space-y-1.5">
                                <Label for="email" class="text-xs font-bold text-slate-900 dark:text-white flex items-center gap-1">
                                    <Mail class="size-3.5 text-blue-600" />
                                    Correo Electrónico <span class="text-rose-600">*</span>
                                </Label>
                                <Input
                                    id="email"
                                    v-model="email"
                                    type="email"
                                    placeholder="ejemplo@correo.com"
                                    class="text-xs font-semibold text-slate-900 dark:text-white border-slate-300"
                                    required
                                />
                            </div>
                            <div class="space-y-1.5">
                                <Label for="phone" class="text-xs font-bold text-slate-900 dark:text-white flex items-center gap-1">
                                    <Phone class="size-3.5 text-emerald-600" />
                                    Celular / WhatsApp
                                </Label>
                                <Input
                                    id="phone"
                                    v-model="phone"
                                    type="tel"
                                    placeholder="987654321"
                                    class="text-xs font-semibold text-slate-900 dark:text-white border-slate-300"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Botones de Acción Espaciosos y Vivos -->
                <div class="flex flex-col sm:flex-row items-center justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-800">
                    <Button type="button" variant="outline" size="default" @click="closeModal" :disabled="isSubmitting" class="w-full sm:w-auto font-bold border-slate-300 text-slate-700 hover:text-slate-900">
                        Cancelar
                    </Button>
                    <Button
                        type="submit"
                        size="default"
                        class="w-full sm:w-auto bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-sm px-6 py-2.5 shadow-md transition-all"
                        :disabled="isSubmitting"
                    >
                        <Loader2 v-if="isSubmitting" class="size-4 mr-2 animate-spin" />
                        <CheckCircle2 v-else class="size-4 mr-2" />
                        Confirmar Inscripción Oficial
                    </Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>
