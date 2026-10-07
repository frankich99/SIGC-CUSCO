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
import { THEME_BUTTONS, THEME_MODAL } from '@/lib/theme';
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
        <DialogContent :class="THEME_MODAL.formDialog">
            <!-- Header Compacto y Responsivo - Granate Cusco -->
            <DialogHeader class="space-y-1.5 border-b pb-3">
                <div class="flex flex-wrap items-center justify-between gap-1.5">
                    <span class="font-mono text-xs font-bold px-2 py-0.5 rounded bg-rose-100 text-rose-950 dark:bg-rose-950 dark:text-rose-200 border border-rose-200">
                        {{ course?.code }}
                    </span>
                    <span v-if="course?.institution" class="text-xs font-bold text-rose-900 bg-rose-50 dark:bg-rose-950/60 dark:text-rose-200 border border-rose-200 dark:border-rose-800 px-2 py-0.5 rounded">
                        {{ course.institution }}
                    </span>
                </div>

                <DialogTitle class="text-lg sm:text-xl font-black text-slate-950 dark:text-white leading-snug">
                    {{ course?.title }}
                </DialogTitle>

                <!-- Resumen de Detalles en Fila Compacta -->
                <div class="flex flex-wrap items-center gap-2 pt-1 text-[11px] text-slate-700 dark:text-slate-300 font-semibold">
                    <span class="flex items-center gap-1">
                        <Calendar class="size-3 text-rose-800" />
                        {{ formatDateRange(course?.start_date, course?.end_date, 'compact') }}
                    </span>
                    <span>•</span>
                    <span class="flex items-center gap-1">
                        <Clock class="size-3 text-amber-600" />
                        {{ formatHours(course?.hours) }}
                    </span>
                    <span>•</span>
                    <span class="flex items-center gap-1 font-bold text-rose-900 dark:text-rose-400">
                        <Users class="size-3" />
                        {{ course?.capacity ? `${course.capacity} vacantes` : 'Abierto' }}
                    </span>
                </div>
            </DialogHeader>

            <!-- Success State -->
            <div v-if="submitSuccess" class="py-6 text-center space-y-4">
                <div class="size-16 bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300 rounded-full flex items-center justify-center mx-auto shadow-inner ring-4 ring-rose-50 dark:ring-rose-900/30">
                    <CheckCircle2 class="size-8" />
                </div>
                <div class="space-y-1">
                    <h3 class="text-xl font-black text-slate-950 dark:text-white">¡Inscripción Confirmada!</h3>
                    <p class="text-xs font-semibold text-slate-700 dark:text-slate-300 max-w-md mx-auto">
                        Has quedado registrado en <strong class="text-rose-900 dark:text-rose-300">{{ course?.title }}</strong>.
                    </p>
                </div>

                <div class="max-w-md mx-auto p-4 bg-rose-50/80 dark:bg-rose-950/40 rounded-xl text-xs text-slate-900 dark:text-slate-200 space-y-1.5 text-left border border-rose-200 dark:border-rose-800 font-semibold">
                    <div class="flex justify-between border-b border-rose-200/60 pb-1.5">
                        <span class="text-slate-600">Participante:</span>
                        <strong class="text-slate-950 dark:text-white">{{ nombres }} {{ paterno }} {{ materno }}</strong>
                    </div>
                    <div class="flex justify-between border-b border-rose-200/60 pb-1.5">
                        <span class="text-slate-600">DNI:</span>
                        <strong class="font-mono text-slate-950 dark:text-white">{{ dni }}</strong>
                    </div>
                    <div class="flex justify-between border-b border-rose-200/60 pb-1.5">
                        <span class="text-slate-600">Correo:</span>
                        <span class="font-bold text-slate-950 dark:text-white">{{ email }}</span>
                    </div>
                    <div class="flex items-center gap-1.5 pt-1.5 text-rose-900 dark:text-rose-300 font-bold text-[11px]">
                        <QrCode class="size-4 shrink-0 text-rose-800" />
                        <span>Podrás marcar tu asistencia con código QR en cada clase.</span>
                    </div>
                </div>

                <div class="pt-2">
                    <Button @click="closeModal" class="bg-rose-900 hover:bg-rose-950 text-white font-black px-6 text-xs shadow-xs">
                        Entendido / Cerrar
                    </Button>
                </div>
            </div>

            <!-- Form State (Compacto y Fluido) -->
            <form v-else @submit.prevent="submitEnrollment" class="space-y-4 pt-2">
                <!-- Error Alert -->
                <div v-if="submitError" class="p-2.5 rounded-lg bg-rose-100 dark:bg-rose-950/60 border border-rose-300 dark:border-rose-900 text-rose-950 dark:text-rose-200 text-xs font-bold flex items-start gap-2">
                    <AlertCircle class="size-4 shrink-0 mt-0.5 text-rose-800" />
                    <span>{{ submitError }}</span>
                </div>

                <!-- Sección 1: DNI y Validación -->
                <div class="space-y-2 bg-slate-50/80 dark:bg-slate-900/60 p-3 rounded-xl border border-slate-200 dark:border-slate-800">
                    <div class="flex items-center justify-between">
                        <Label for="dni-input" class="text-xs font-bold text-slate-900 dark:text-white flex items-center gap-1.5">
                            <IdCard class="size-3.5 text-rose-800" />
                            DNI del Participante <span class="text-rose-600">*</span>
                        </Label>
                        <span class="text-[10px] font-black text-rose-900 dark:text-rose-300 bg-rose-100 dark:bg-rose-950 px-2 py-0.5 rounded">
                            RENIEC
                        </span>
                    </div>

                    <div class="flex gap-2">
                        <Input
                            id="dni-input"
                            v-model="dni"
                            type="text"
                            maxlength="8"
                            placeholder="DNI (8 dígitos)"
                            class="font-mono text-sm tracking-wider font-bold bg-white dark:bg-slate-950"
                            :disabled="isSubmitting || dniLookupSuccess"
                            @keyup.enter.prevent="lookupDni"
                        />
                        <Button
                            type="button"
                            :disabled="isLookingUpDni || dni.length !== 8 || dniLookupSuccess"
                            @click="lookupDni"
                            class="shrink-0 text-xs font-black bg-rose-900 hover:bg-rose-950 text-white shadow-xs"
                        >
                            <Loader2 v-if="isLookingUpDni" class="size-3.5 mr-1 animate-spin" />
                            <Search v-else class="size-3.5 mr-1" />
                            Validar
                        </Button>
                        <Button
                            v-if="dniLookupSuccess && !authUser"
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="dniLookupSuccess = false; nombres = ''; paterno = ''; materno = ''"
                            class="text-xs font-semibold text-slate-700"
                        >
                            Cambiar
                        </Button>
                    </div>

                    <!-- DNI Lookup Feedback -->
                    <div v-if="dniLookupError" class="text-xs font-bold text-rose-700 dark:text-rose-400 flex items-center gap-1 pt-1">
                        <AlertCircle class="size-3.5 text-rose-600 shrink-0" />
                        <span>{{ dniLookupError }}</span>
                    </div>

                    <!-- Citizen Data Preview con Granate Cusco -->
                    <div
                        v-if="dniLookupSuccess"
                        class="p-2.5 bg-rose-100/90 dark:bg-rose-950/60 border border-rose-300 dark:border-rose-700 rounded-lg flex items-center gap-2.5 mt-2"
                    >
                        <div class="size-8 rounded-full bg-rose-900 text-white flex items-center justify-center font-black text-xs shrink-0">
                            <UserCheck class="size-4" />
                        </div>
                        <div class="min-w-0 flex-1 text-xs">
                            <div class="font-black text-slate-950 dark:text-white truncate">
                                {{ nombres }} {{ paterno }} {{ materno }}
                            </div>
                            <div class="text-[11px] font-bold text-rose-900 dark:text-rose-300">
                                Identidad confirmada oficialmente por DNI
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sección 2: Nombres y Apellidos en Grilla Responsiva -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="space-y-1">
                        <Label for="nombres" class="text-xs font-bold text-slate-900 dark:text-white">
                            Nombres <span class="text-rose-600">*</span>
                        </Label>
                        <Input
                            id="nombres"
                            v-model="nombres"
                            type="text"
                            placeholder="Nombres"
                            class="text-xs font-semibold"
                            required
                        />
                    </div>
                    <div class="space-y-1">
                        <Label for="paterno" class="text-xs font-bold text-slate-900 dark:text-white">
                            Apellido Paterno <span class="text-rose-600">*</span>
                        </Label>
                        <Input
                            id="paterno"
                            v-model="paterno"
                            type="text"
                            placeholder="Apellido paterno"
                            class="text-xs font-semibold"
                            required
                        />
                    </div>
                    <div class="space-y-1">
                        <Label for="materno" class="text-xs font-bold text-slate-900 dark:text-white">
                            Apellido Materno
                        </Label>
                        <Input
                            id="materno"
                            v-model="materno"
                            type="text"
                            placeholder="Apellido materno"
                            class="text-xs font-semibold"
                        />
                    </div>
                    <div class="space-y-1">
                        <Label for="phone" class="text-xs font-bold text-slate-900 dark:text-white flex items-center gap-1">
                            <Phone class="size-3 text-rose-800" />
                            Celular / WhatsApp
                        </Label>
                        <Input
                            id="phone"
                            v-model="phone"
                            type="tel"
                            placeholder="987654321"
                            class="text-xs font-semibold"
                        />
                    </div>
                    <div class="space-y-1 sm:col-span-2">
                        <Label for="email" class="text-xs font-bold text-slate-900 dark:text-white flex items-center gap-1">
                            <Mail class="size-3 text-blue-600" />
                            Correo Electrónico <span class="text-rose-600">*</span>
                        </Label>
                        <Input
                            id="email"
                            v-model="email"
                            type="email"
                            placeholder="ejemplo@correo.com"
                            class="text-xs font-semibold"
                            required
                        />
                    </div>
                </div>

                <!-- Botones de Acción Responsivos -->
                <div class="flex flex-col-reverse sm:flex-row items-center justify-end gap-2 pt-3 border-t">
                    <Button type="button" variant="outline" size="sm" @click="closeModal" :disabled="isSubmitting" class="w-full sm:w-auto text-xs font-bold border-slate-300">
                        Cancelar
                    </Button>
                    <Button
                        type="submit"
                        size="sm"
                        class="w-full sm:w-auto bg-rose-900 hover:bg-rose-950 text-white font-black text-xs px-5 shadow-xs"
                        :disabled="isSubmitting"
                    >
                        <Loader2 v-if="isSubmitting" class="size-3.5 mr-1.5 animate-spin" />
                        <CheckCircle2 v-else class="size-3.5 mr-1.5" />
                        Confirmar Inscripción Oficial
                    </Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>
