<script setup lang="ts">
import { ref, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
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
import { UserPlus, Loader2, AlertCircle, ShieldCheck, Eye, EyeOff } from '@lucide/vue';
import { THEME_BUTTONS, THEME_MODAL } from '@/lib/theme';
import { useAuthModal } from '@/composables/useAuthModal';
import { notify } from '@/lib/notify';

const props = defineProps<{
    open?: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'switchToLogin'): void;
}>();

const { isRegisterModalOpen, switchToLogin: composableSwitchToLogin } = useAuthModal();

// Control reactivo bidireccional del estado abierto
const isOpen = computed({
    get: () => (props.open !== undefined ? props.open : isRegisterModalOpen.value),
    set: (val: boolean) => {
        emit('update:open', val);
        isRegisterModalOpen.value = val;
    },
});

const showPassword = ref(false);
const showConfirmPassword = ref(false);

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

function handleSwitchToLogin() {
    emit('switchToLogin');
    composableSwitchToLogin();
}

function submitRegister() {
    form.post('/register', {
        onSuccess: () => {
            isOpen.value = false;
            form.reset('password', 'password_confirmation');
            notify.success('¡Registro Exitoso!', 'Bienvenido a la plataforma SIGC-CUSCO.', 2000);
            router.visit('/dashboard');
        },
        onError: () => {
            const firstErr = Object.values(form.errors)[0] || 'Por favor verifique los datos del formulario.';
            notify.error('Error al registrarse', firstErr, 3000);
        },
    });
}
</script>

<template>
    <Dialog :open="isOpen" @update:open="isOpen = $event">
        <DialogContent :class="THEME_MODAL.authDialog">
            <!-- Header Superior del Modal (FIJO) -->
            <div class="p-5 sm:p-6 pb-2 shrink-0 pr-12">
                <DialogHeader class="space-y-1 text-left">
                    <div class="flex items-center gap-1.5 text-[11px] font-black text-rose-900 dark:text-rose-300 uppercase tracking-wider">
                        <ShieldCheck class="size-4 text-rose-800 dark:text-rose-400" />
                        <span>Registro Oficial</span>
                    </div>
                    <DialogTitle class="text-lg font-black text-slate-950 dark:text-white">
                        Crear Cuenta
                    </DialogTitle>
                    <DialogDescription class="text-xs text-slate-600 dark:text-slate-400">
                        Regístrate para inscribirte en capacitaciones y acceder a tus certificados.
                    </DialogDescription>
                </DialogHeader>
            </div>

            <!-- Cuerpo del Formulario con scrollbar sutil redondeado -->
            <div class="flex-1 overflow-y-auto overscroll-contain custom-scrollbar px-5 pb-5 sm:px-6 sm:pb-6">
                <form @submit.prevent="submitRegister" class="space-y-3 py-1">
                    <!-- Validation error alert -->
                    <div v-if="Object.keys(form.errors).length > 0" class="p-2.5 rounded-lg bg-rose-100 dark:bg-rose-950/60 border border-rose-300 dark:border-rose-900 text-rose-950 dark:text-rose-200 text-xs font-bold flex items-center gap-2">
                        <AlertCircle class="size-4 shrink-0 text-rose-800" />
                        <span>{{ Object.values(form.errors)[0] }}</span>
                    </div>

                    <div class="space-y-1">
                        <Label for="register-modal-name" class="text-xs font-bold text-slate-900 dark:text-white">
                            Nombre Completo
                        </Label>
                        <Input
                            id="register-modal-name"
                            v-model="form.name"
                            type="text"
                            placeholder="Nombres y Apellidos"
                            class="text-xs h-9 font-medium"
                            required
                            autofocus
                        />
                    </div>

                    <div class="space-y-1">
                        <Label for="register-modal-email" class="text-xs font-bold text-slate-900 dark:text-white">
                            Correo Electrónico
                        </Label>
                        <Input
                            id="register-modal-email"
                            v-model="form.email"
                            type="email"
                            placeholder="correo@ejemplo.com"
                            class="text-xs h-9 font-medium"
                            required
                        />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                        <div class="space-y-1">
                            <Label for="register-modal-password" class="text-xs font-bold text-slate-900 dark:text-white">
                                Contraseña
                            </Label>
                            <div class="relative">
                                <Input
                                    id="register-modal-password"
                                    v-model="form.password"
                                    :type="showPassword ? 'text' : 'password'"
                                    placeholder="Mín. 8 caracteres"
                                    class="text-xs h-9 font-medium pr-8"
                                    required
                                />
                                <button
                                    type="button"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 p-0.5 cursor-pointer"
                                    tabindex="-1"
                                    :title="showPassword ? 'Ocultar' : 'Ver'"
                                    @click="showPassword = !showPassword"
                                >
                                    <EyeOff v-if="showPassword" class="size-3.5" />
                                    <Eye v-else class="size-3.5" />
                                </button>
                            </div>
                        </div>

                        <div class="space-y-1">
                            <Label for="register-modal-password-conf" class="text-xs font-bold text-slate-900 dark:text-white">
                                Confirmar
                            </Label>
                            <div class="relative">
                                <Input
                                    id="register-modal-password-conf"
                                    v-model="form.password_confirmation"
                                    :type="showConfirmPassword ? 'text' : 'password'"
                                    placeholder="Repetir clave"
                                    class="text-xs h-9 font-medium pr-8"
                                    required
                                />
                                <button
                                    type="button"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 p-0.5 cursor-pointer"
                                    tabindex="-1"
                                    :title="showConfirmPassword ? 'Ocultar' : 'Ver'"
                                    @click="showConfirmPassword = !showConfirmPassword"
                                >
                                    <EyeOff v-if="showConfirmPassword" class="size-3.5" />
                                    <Eye v-else class="size-3.5" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <Button
                        type="submit"
                        :class="['w-full h-9.5 text-xs mt-1', THEME_BUTTONS.primary]"
                        :disabled="form.processing"
                    >
                        <Loader2 v-if="form.processing" class="size-3.5 mr-1.5 animate-spin" />
                        <UserPlus v-else class="size-3.5 mr-1.5" />
                        Registrar Cuenta
                    </Button>

                    <div class="pt-2.5 border-t border-slate-200 dark:border-slate-800 text-center text-xs text-slate-600 dark:text-slate-400">
                        ¿Ya tienes una cuenta registrada?
                        <button
                            type="button"
                            class="text-rose-900 dark:text-rose-300 font-black hover:underline ml-1 cursor-pointer"
                            @click="handleSwitchToLogin"
                        >
                            Inicia sesión aquí
                        </button>
                    </div>
                </form>
            </div>
        </DialogContent>
    </Dialog>
</template>
