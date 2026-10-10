<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Mail } from '@lucide/vue';
import { login } from '@/routes';
import { email } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Recuperar Contraseña',
        description:
            'Ingresa tu correo institucional o personal para recibir un enlace de restablecimiento seguro',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="Recuperar contraseña - SIGC-CUSCO" />

    <div
        v-if="status"
        class="mb-4 rounded-lg border border-rose-200 bg-rose-50 p-2.5 text-center text-xs font-bold text-rose-900"
    >
        {{ status }}
    </div>

    <div class="space-y-5">
        <Form
            v-bind="email.form()"
            v-slot="{ errors, processing }"
            class="space-y-4"
        >
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
                    name="email"
                    autocomplete="off"
                    v-focus
                    placeholder="correo@ejemplo.com"
                    class="text-xs font-medium text-slate-900"
                />
                <InputError :message="errors.email" />
            </div>

            <Button
                class="h-10 w-full cursor-pointer bg-rose-900 text-xs font-bold text-white shadow-xs hover:bg-rose-950"
                :disabled="processing"
                data-test="email-password-reset-link-button"
            >
                <Spinner v-if="processing" />
                <Mail v-else class="mr-1.5 size-4" />
                Enviar enlace de recuperación
            </Button>
        </Form>

        <div
            class="border-t border-slate-100 pt-3 text-center text-xs font-medium text-slate-600 dark:border-slate-800 dark:text-slate-400"
        >
            <span>¿Recordaste tu contraseña?</span>
            <TextLink
                :href="login()"
                class="ml-1 font-bold text-rose-900 hover:underline dark:text-rose-400"
            >
                Iniciar sesión
            </TextLink>
        </div>
    </div>
</template>
