<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { LogIn } from '@lucide/vue';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';
import PasskeyVerify from '@/components/PasskeyVerify.vue';

defineOptions({
    layout: {
        title: 'Iniciar Sesión',
        description: 'Ingresa tus credenciales para acceder a tu panel de capacitaciones',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();
</script>

<template>
    <Head title="Iniciar sesión - SIGC-CUSCO" />

    <div
        v-if="status"
        class="mb-4 text-center text-xs font-bold text-rose-900 bg-rose-50 p-2.5 rounded-lg border border-rose-200"
    >
        {{ status }}
    </div>

    <PasskeyVerify />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-5"
    >
        <div class="grid gap-5">
            <div class="grid gap-1.5">
                <Label for="email" class="text-xs font-bold text-slate-800 dark:text-slate-200">
                    Correo electrónico
                </Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    required
                    v-focus
                    :tabindex="1"
                    autocomplete="email"
                    placeholder="correo@ejemplo.com"
                    class="text-xs text-slate-900 font-medium"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-1.5">
                <div class="flex items-center justify-between">
                    <Label for="password" class="text-xs font-bold text-slate-800 dark:text-slate-200">
                        Contraseña
                    </Label>
                    <TextLink
                        v-if="canResetPassword"
                        :href="request()"
                        class="text-xs font-bold text-rose-900 dark:text-rose-400 hover:underline"
                        :tabindex="5"
                    >
                        ¿Olvidaste tu contraseña?
                    </TextLink>
                </div>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    :tabindex="2"
                    autocomplete="current-password"
                    placeholder="••••••••"
                    class="text-xs text-slate-900"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="flex items-center justify-between">
                <Label for="remember" class="flex items-center space-x-2 text-xs font-semibold text-slate-700 dark:text-slate-300 cursor-pointer">
                    <Checkbox id="remember" name="remember" :tabindex="3" />
                    <span>Recordar mi sesión</span>
                </Label>
            </div>

            <Button
                type="submit"
                class="mt-2 w-full bg-rose-900 hover:bg-rose-950 text-white font-bold text-xs h-10 shadow-xs cursor-pointer"
                :tabindex="4"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" />
                <LogIn v-else class="size-4 mr-1.5" />
                Ingresar al Sistema
            </Button>
        </div>

        <div class="pt-3 border-t border-slate-100 dark:border-slate-800 text-center text-xs text-slate-600 dark:text-slate-400 font-medium">
            ¿No tienes una cuenta aún?
            <TextLink :href="register()" :tabindex="5" class="text-rose-900 dark:text-rose-400 font-bold hover:underline ml-1">
                Regístrate aquí
            </TextLink>
        </div>
    </Form>
</template>
