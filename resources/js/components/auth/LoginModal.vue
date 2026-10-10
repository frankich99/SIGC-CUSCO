<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
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
import { Checkbox } from '@/components/ui/checkbox';
import {
    LogIn,
    Loader2,
    AlertCircle,
    ShieldCheck,
    Eye,
    EyeOff,
} from '@lucide/vue';
import { THEME_BUTTONS, THEME_MODAL } from '@/lib/theme';
import { useAuthModal } from '@/composables/useAuthModal';
import { notify } from '@/lib/notify';

const props = withDefaults(
    defineProps<{
        open?: boolean | null;
    }>(),
    {
        open: null,
    },
);

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'switchToRegister'): void;
}>();

const { isLoginModalOpen, switchToRegister: composableSwitchToRegister } =
    useAuthModal();

watch(
    () => props.open,
    (val) => {
        if (typeof val === 'boolean') {
            isLoginModalOpen.value = val;
        }
    },
    { immediate: true },
);

// Control reactivo bidireccional del estado abierto
const isOpen = computed({
    get: () => isLoginModalOpen.value,
    set: (val: boolean) => {
        isLoginModalOpen.value = val;
        emit('update:open', val);
    },
});

const showPassword = ref(false);

const form = useForm({
    email: '',
    password: '',
    remember: true,
});

function handleSwitchToRegister() {
    emit('switchToRegister');
    composableSwitchToRegister();
}

function submitLogin() {
    form.post('/login', {
        onSuccess: () => {
            isOpen.value = false;
            form.reset('password');
            notify.success(
                '¡Bienvenido!',
                'Sesión iniciada correctamente.',
                1800,
            );
        },
        onError: () => {
            const err =
                form.errors.email ||
                form.errors.password ||
                'Por favor verifique sus credenciales.';
            notify.error('Error al iniciar sesión', err, 3000);
        },
    });
}
</script>

<template>
    <Dialog :open="isOpen" @update:open="isOpen = $event">
        <DialogContent :class="THEME_MODAL.authDialog">
            <!-- Header Superior del Modal (FIJO) -->
            <div class="shrink-0 p-5 pr-12 pb-2 sm:p-6">
                <DialogHeader class="space-y-1 text-left">
                    <div
                        class="flex items-center gap-1.5 text-[11px] font-black tracking-wider text-rose-900 uppercase dark:text-rose-300"
                    >
                        <ShieldCheck
                            class="size-4 text-rose-800 dark:text-rose-400"
                        />
                        <span>Acceso al Sistema</span>
                    </div>
                    <DialogTitle
                        class="text-lg font-black text-slate-950 dark:text-white"
                    >
                        Iniciar Sesión
                    </DialogTitle>
                    <DialogDescription
                        class="text-xs text-slate-600 dark:text-slate-400"
                    >
                        Ingresa con tu correo o DNI y contraseña para acceder a
                        tus capacitaciones.
                    </DialogDescription>
                </DialogHeader>
            </div>

            <!-- Cuerpo del Formulario con scrollbar sutil redondeado -->
            <div
                class="custom-scrollbar flex-1 overflow-y-auto overscroll-contain px-5 pb-5 sm:px-6 sm:pb-6"
            >
                <form @submit.prevent="submitLogin" class="space-y-3.5 py-1">
                    <!-- Generic error alert -->
                    <div
                        v-if="form.errors.email || form.errors.password"
                        class="flex items-center gap-2 rounded-lg border border-rose-300 bg-rose-100 p-2.5 text-xs font-bold text-rose-950 dark:border-rose-900 dark:bg-rose-950/60 dark:text-rose-200"
                    >
                        <AlertCircle class="size-4 shrink-0 text-rose-800" />
                        <span>{{
                            form.errors.email || form.errors.password
                        }}</span>
                    </div>

                    <div class="space-y-1">
                        <Label
                            for="login-modal-email"
                            class="text-xs font-bold text-slate-900 dark:text-white"
                        >
                            Correo Electrónico o DNI
                        </Label>
                        <Input
                            id="login-modal-email"
                            v-model="form.email"
                            type="text"
                            placeholder="correo@ejemplo.com o DNI"
                            class="h-9 text-xs font-medium"
                            required
                            autofocus
                        />
                    </div>

                    <div class="space-y-1">
                        <div class="flex items-center justify-between">
                            <Label
                                for="login-modal-password"
                                class="text-xs font-bold text-slate-900 dark:text-white"
                            >
                                Contraseña
                            </Label>
                            <a
                                href="/forgot-password"
                                class="text-[11px] font-semibold text-rose-900 hover:underline dark:text-rose-400"
                            >
                                ¿Olvidaste tu clave?
                            </a>
                        </div>
                        <div class="relative">
                            <Input
                                id="login-modal-password"
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                placeholder="••••••••"
                                class="h-9 pr-9 text-xs font-medium"
                                required
                            />
                            <button
                                type="button"
                                class="absolute top-1/2 right-2.5 -translate-y-1/2 cursor-pointer p-0.5 text-slate-400 hover:text-slate-700 dark:hover:text-slate-200"
                                tabindex="-1"
                                :title="
                                    showPassword
                                        ? 'Ocultar contraseña'
                                        : 'Ver contraseña'
                                "
                                @click="showPassword = !showPassword"
                            >
                                <EyeOff v-if="showPassword" class="size-4" />
                                <Eye v-else class="size-4" />
                            </button>
                        </div>
                    </div>

                    <div class="flex items-center justify-between pt-0.5">
                        <div class="flex items-center space-x-2">
                            <Checkbox
                                id="login-modal-remember"
                                v-model:checked="form.remember"
                            />
                            <label
                                for="login-modal-remember"
                                class="cursor-pointer text-xs font-medium text-slate-700 dark:text-slate-300"
                            >
                                Recordar mi sesión
                            </label>
                        </div>
                    </div>

                    <Button
                        type="submit"
                        :class="['h-9.5 w-full text-xs', THEME_BUTTONS.primary]"
                        :disabled="form.processing"
                    >
                        <Loader2
                            v-if="form.processing"
                            class="mr-1.5 size-3.5 animate-spin"
                        />
                        <LogIn v-else class="mr-1.5 size-3.5" />
                        Ingresar al Panel
                    </Button>

                    <div
                        class="border-t border-slate-200 pt-2.5 text-center text-xs text-slate-600 dark:border-slate-800 dark:text-slate-400"
                    >
                        ¿No tienes una cuenta aún?
                        <button
                            type="button"
                            class="ml-1 cursor-pointer font-black text-rose-900 hover:underline dark:text-rose-300"
                            @click="handleSwitchToRegister"
                        >
                            Regístrate aquí
                        </button>
                    </div>
                </form>
            </div>
        </DialogContent>
    </Dialog>
</template>
