<script setup lang="ts">
import { ref } from 'vue';
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
import { Checkbox } from '@/components/ui/checkbox';
import { LogIn, Loader2, AlertCircle, ShieldCheck } from '@lucide/vue';
import { THEME_BUTTONS, THEME_MODAL } from '@/lib/theme';

const props = defineProps<{
    open: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'switchToRegister'): void;
}>();

const form = useForm({
    email: '',
    password: '',
    remember: true,
});

function submitLogin() {
    form.post('/login', {
        onSuccess: () => {
            emit('update:open', false);
            form.reset('password');
        },
    });
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent :class="THEME_MODAL.authDialog">
            <!-- Header Superior del Modal (FIJO) -->
            <div class="p-5 sm:p-6 pb-2 shrink-0 pr-12">
                <DialogHeader class="space-y-1 text-left">
                    <div class="flex items-center gap-1.5 text-[11px] font-black text-rose-900 dark:text-rose-300 uppercase tracking-wider">
                        <ShieldCheck class="size-4 text-rose-800 dark:text-rose-400" />
                        <span>Acceso al Sistema</span>
                    </div>
                    <DialogTitle class="text-lg font-black text-slate-950 dark:text-white">
                        Iniciar Sesión
                    </DialogTitle>
                    <DialogDescription class="text-xs text-slate-600 dark:text-slate-400">
                        Ingresa con tu correo o DNI y contraseña para acceder a tus capacitaciones.
                    </DialogDescription>
                </DialogHeader>
            </div>

            <!-- Cuerpo del Formulario con scrollbar sutil redondeado si es necesario -->
            <div class="flex-1 overflow-y-auto overscroll-contain custom-scrollbar px-5 pb-5 sm:px-6 sm:pb-6">
                <form @submit.prevent="submitLogin" class="space-y-3.5 py-1">
                    <!-- Generic error alert -->
                    <div v-if="form.errors.email || form.errors.password" class="p-2.5 rounded-lg bg-rose-100 dark:bg-rose-950/60 border border-rose-300 dark:border-rose-900 text-rose-950 dark:text-rose-200 text-xs font-bold flex items-center gap-2">
                        <AlertCircle class="size-4 shrink-0 text-rose-800" />
                        <span>{{ form.errors.email || form.errors.password }}</span>
                    </div>

                    <div class="space-y-1">
                        <Label for="login-modal-email" class="text-xs font-bold text-slate-900 dark:text-white">Correo Electrónico o DNI</Label>
                        <Input
                            id="login-modal-email"
                            v-model="form.email"
                            type="text"
                            placeholder="correo@ejemplo.com o DNI"
                            class="text-xs h-9 font-medium"
                            required
                            autofocus
                        />
                    </div>

                    <div class="space-y-1">
                        <div class="flex items-center justify-between">
                            <Label for="login-modal-password" class="text-xs font-bold text-slate-900 dark:text-white">Contraseña</Label>
                            <a href="/forgot-password" class="text-[11px] font-semibold text-rose-900 dark:text-rose-400 hover:underline">
                                ¿Olvidaste tu clave?
                            </a>
                        </div>
                        <Input
                            id="login-modal-password"
                            v-model="form.password"
                            type="password"
                            placeholder="••••••••"
                            class="text-xs h-9 font-medium"
                            required
                        />
                    </div>

                    <div class="flex items-center justify-between pt-0.5">
                        <div class="flex items-center space-x-2">
                            <Checkbox id="login-modal-remember" v-model:checked="form.remember" />
                            <label for="login-modal-remember" class="text-xs font-medium text-slate-700 dark:text-slate-300 cursor-pointer">
                                Recordar mi sesión
                            </label>
                        </div>
                    </div>

                    <Button
                        type="submit"
                        :class="['w-full h-9.5 text-xs', THEME_BUTTONS.primary]"
                        :disabled="form.processing"
                    >
                        <Loader2 v-if="form.processing" class="size-3.5 mr-1.5 animate-spin" />
                        <LogIn v-else class="size-3.5 mr-1.5" />
                        Ingresar al Panel
                    </Button>

                    <div class="pt-2.5 border-t border-slate-200 dark:border-slate-800 text-center text-xs text-slate-600 dark:text-slate-400">
                        ¿No tienes una cuenta aún?
                        <button
                            type="button"
                            class="text-rose-900 dark:text-rose-300 font-black hover:underline ml-1 cursor-pointer"
                            @click="emit('switchToRegister')"
                        >
                            Regístrate aquí
                        </button>
                    </div>
                </form>
            </div>
        </DialogContent>
    </Dialog>
</template>
