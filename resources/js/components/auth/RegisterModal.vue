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
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <div class="flex items-center gap-2 text-xs font-semibold text-emerald-700 dark:text-emerald-400 uppercase tracking-wider mb-1">
                    <ShieldCheck class="size-4" />
                    <span>Registro de Nuevo Usuario</span>
                </div>
                <DialogTitle class="text-xl font-bold">
                    Crear una Cuenta
                </DialogTitle>
                <DialogDescription class="text-xs text-neutral-500">
                    Regístrate como participante para inscribirte a cursos y descargar tus certificados.
                </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="submitRegister" class="space-y-3.5 py-2">
                <!-- Validation error alert -->
                <div v-if="Object.keys(form.errors).length > 0" class="p-3 rounded-lg bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900 text-rose-700 dark:text-rose-300 text-xs flex items-center gap-2">
                    <AlertCircle class="size-4 shrink-0" />
                    <span>{{ Object.values(form.errors)[0] }}</span>
                </div>

                <div class="space-y-1">
                    <Label for="register-modal-name" class="text-xs font-semibold">Nombre Completo</Label>
                    <Input
                        id="register-modal-name"
                        v-model="form.name"
                        type="text"
                        placeholder="Nombres y Apellidos"
                        class="text-xs"
                        required
                        autofocus
                    />
                </div>

                <div class="space-y-1">
                    <Label for="register-modal-email" class="text-xs font-semibold">Correo Electrónico</Label>
                    <Input
                        id="register-modal-email"
                        v-model="form.email"
                        type="email"
                        placeholder="correo@ejemplo.com"
                        class="text-xs"
                        required
                    />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="space-y-1">
                        <Label for="register-modal-password" class="text-xs font-semibold">Contraseña</Label>
                        <Input
                            id="register-modal-password"
                            v-model="form.password"
                            type="password"
                            placeholder="Mínimo 8 caracteres"
                            class="text-xs"
                            required
                        />
                    </div>
                    <div class="space-y-1">
                        <Label for="register-modal-password-conf" class="text-xs font-semibold">Confirmar</Label>
                        <Input
                            id="register-modal-password-conf"
                            v-model="form.password_confirmation"
                            type="password"
                            placeholder="Repetir contraseña"
                            class="text-xs"
                            required
                        />
                    </div>
                </div>

                <Button
                    type="submit"
                    class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs shadow-xs mt-2"
                    :disabled="form.processing"
                >
                    <Loader2 v-if="form.processing" class="size-3.5 mr-1.5 animate-spin" />
                    <UserPlus v-else class="size-3.5 mr-1.5" />
                    Registrar Cuenta
                </Button>

                <div class="pt-3 border-t text-center text-xs text-neutral-500">
                    ¿Ya tienes una cuenta registrada?
                    <button
                        type="button"
                        class="text-emerald-600 dark:text-emerald-400 font-semibold hover:underline ml-1"
                        @click="emit('switchToLogin')"
                    >
                        Inicia sesión aquí
                    </button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>
