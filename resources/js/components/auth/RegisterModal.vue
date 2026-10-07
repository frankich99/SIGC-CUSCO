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
import { UserPlus, Loader2, AlertCircle, ShieldCheck } from '@lucide/vue';
import { THEME_BUTTONS, THEME_MODAL } from '@/lib/theme';

const props = defineProps<{
    open: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'switchToLogin'): void;
}>();

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

function submitRegister() {
    form.post('/register', {
        onSuccess: () => {
            emit('update:open', false);
            form.reset('password', 'password_confirmation');
            router.visit('/dashboard');
        },
    });
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent :class="THEME_MODAL.authDialog">
            <DialogHeader class="space-y-1">
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

            <form @submit.prevent="submitRegister" class="space-y-3 py-1">
                <!-- Validation error alert -->
                <div v-if="Object.keys(form.errors).length > 0" class="p-2.5 rounded-lg bg-rose-100 dark:bg-rose-950/60 border border-rose-300 dark:border-rose-900 text-rose-950 dark:text-rose-200 text-xs font-bold flex items-center gap-2">
                    <AlertCircle class="size-4 shrink-0 text-rose-800" />
                    <span>{{ Object.values(form.errors)[0] }}</span>
                </div>

                <div class="space-y-1">
                    <Label for="register-modal-name" class="text-xs font-bold text-slate-900 dark:text-white">Nombre Completo</Label>
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
                    <Label for="register-modal-email" class="text-xs font-bold text-slate-900 dark:text-white">Correo Electrónico</Label>
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
                        <Label for="register-modal-password" class="text-xs font-bold text-slate-900 dark:text-white">Contraseña</Label>
                        <Input
                            id="register-modal-password"
                            v-model="form.password"
                            type="password"
                            placeholder="Mín. 8 caracteres"
                            class="text-xs h-9 font-medium"
                            required
                        />
                    </div>
                    <div class="space-y-1">
                        <Label for="register-modal-password-conf" class="text-xs font-bold text-slate-900 dark:text-white">Confirmar</Label>
                        <Input
                            id="register-modal-password-conf"
                            v-model="form.password_confirmation"
                            type="password"
                            placeholder="Repetir clave"
                            class="text-xs h-9 font-medium"
                            required
                        />
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
                        @click="emit('switchToLogin')"
                    >
                        Inicia sesión aquí
                    </button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>
