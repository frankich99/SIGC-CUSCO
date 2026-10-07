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
                    <span>Acceso a la Plataforma</span>
                </div>
                <DialogTitle class="text-xl font-bold">
                    Iniciar Sesión
                </DialogTitle>
                <DialogDescription class="text-xs text-neutral-500">
                    Ingresa tus credenciales para acceder a tu panel de capacitaciones.
                </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="submitLogin" class="space-y-4 py-2">
                <!-- Generic error alert -->
                <div v-if="form.errors.email || form.errors.password" class="p-3 rounded-lg bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900 text-rose-700 dark:text-rose-300 text-xs flex items-center gap-2">
                    <AlertCircle class="size-4 shrink-0" />
                    <span>{{ form.errors.email || form.errors.password }}</span>
                </div>

                <div class="space-y-1.5">
                    <Label for="login-modal-email" class="text-xs font-semibold">Correo Electrónico</Label>
                    <Input
                        id="login-modal-email"
                        v-model="form.email"
                        type="email"
                        placeholder="correo@ejemplo.com"
                        class="text-xs"
                        required
                        autofocus
                    />
                </div>

                <div class="space-y-1.5">
                    <div class="flex items-center justify-between">
                        <Label for="login-modal-password" class="text-xs font-semibold">Contraseña</Label>
                        <a href="/forgot-password" class="text-[11px] text-emerald-600 hover:underline">
                            ¿Olvidaste tu contraseña?
                        </a>
                    </div>
                    <Input
                        id="login-modal-password"
                        v-model="form.password"
                        type="password"
                        placeholder="••••••••"
                        class="text-xs"
                        required
                    />
                </div>

                <div class="flex items-center justify-between pt-1">
                    <div class="flex items-center space-x-2">
                        <Checkbox id="login-modal-remember" v-model:checked="form.remember" />
                        <label for="login-modal-remember" class="text-xs text-neutral-600 dark:text-neutral-400 cursor-pointer">
                            Recordar mi sesión
                        </label>
                    </div>
                </div>

                <Button
                    type="submit"
                    class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs shadow-xs"
                    :disabled="form.processing"
                >
                    <Loader2 v-if="form.processing" class="size-3.5 mr-1.5 animate-spin" />
                    <LogIn v-else class="size-3.5 mr-1.5" />
                    Ingresar al Sistema
                </Button>

                <div class="pt-3 border-t text-center text-xs text-neutral-500">
                    ¿No tienes una cuenta aún?
                    <button
                        type="button"
                        class="text-emerald-600 dark:text-emerald-400 font-semibold hover:underline ml-1"
                        @click="emit('switchToRegister')"
                    >
                        Regístrate aquí
                    </button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>
