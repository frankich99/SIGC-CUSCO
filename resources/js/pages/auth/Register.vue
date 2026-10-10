<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { UserPlus } from '@lucide/vue';
import { login } from '@/routes';
import { store } from '@/routes/register';

defineProps<{
    passwordRules: string;
}>();

defineOptions({
    layout: {
        title: 'Crear una Cuenta',
        description:
            'Ingresa tus datos a continuación para registrarte en el sistema de capacitaciones',
    },
});
</script>

<template>
    <Head title="Registro de Usuario - SIGC-CUSCO" />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-4"
    >
        <div class="grid gap-4">
            <div class="grid gap-1.5">
                <Label
                    for="name"
                    class="text-xs font-bold text-slate-800 dark:text-slate-200"
                >
                    Nombre completo
                </Label>
                <Input
                    id="name"
                    type="text"
                    required
                    v-focus
                    :tabindex="1"
                    autocomplete="name"
                    name="name"
                    placeholder="Nombres y Apellidos"
                    class="text-xs font-medium text-slate-900"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-1.5">
                <Label
                    for="email"
                    class="text-xs font-bold text-slate-800 dark:text-slate-200"
                >
                    Correo electrónico
                </Label>
                <Input
                    id="email"
                    type="email"
                    required
                    :tabindex="2"
                    autocomplete="email"
                    name="email"
                    placeholder="correo@ejemplo.com"
                    class="text-xs font-medium text-slate-900"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div class="grid gap-1.5">
                    <Label
                        for="password"
                        class="text-xs font-bold text-slate-800 dark:text-slate-200"
                    >
                        Contraseña
                    </Label>
                    <PasswordInput
                        id="password"
                        required
                        :tabindex="3"
                        autocomplete="new-password"
                        name="password"
                        placeholder="••••••••"
                        :passwordrules="passwordRules"
                        class="text-xs text-slate-900"
                    />
                    <InputError :message="errors.password" />
                </div>

                <div class="grid gap-1.5">
                    <Label
                        for="password_confirmation"
                        class="text-xs font-bold text-slate-800 dark:text-slate-200"
                    >
                        Confirmar
                    </Label>
                    <PasswordInput
                        id="password_confirmation"
                        required
                        :tabindex="4"
                        autocomplete="new-password"
                        name="password_confirmation"
                        placeholder="••••••••"
                        :passwordrules="passwordRules"
                        class="text-xs text-slate-900"
                    />
                    <InputError :message="errors.password_confirmation" />
                </div>
            </div>

            <Button
                type="submit"
                class="mt-2 h-10 w-full cursor-pointer bg-rose-900 text-xs font-bold text-white shadow-xs hover:bg-rose-950"
                tabindex="5"
                :disabled="processing"
                data-test="register-user-button"
            >
                <Spinner v-if="processing" />
                <UserPlus v-else class="mr-1.5 size-4" />
                Registrar Cuenta
            </Button>
        </div>

        <div
            class="border-t border-slate-100 pt-3 text-center text-xs font-medium text-slate-600 dark:border-slate-800 dark:text-slate-400"
        >
            ¿Ya tienes una cuenta registrada?
            <TextLink
                :href="login()"
                class="ml-1 font-bold text-rose-900 hover:underline dark:text-rose-400"
                :tabindex="6"
            >
                Inicia sesión aquí
            </TextLink>
        </div>
    </Form>
</template>
