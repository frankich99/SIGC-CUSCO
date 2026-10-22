<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/security';
import type { Props as ManagePasskeysProps } from '@/components/ManagePasskeys.vue';
import ManagePasskeys from '@/components/ManagePasskeys.vue';
import type { Props as ManageTwoFactorProps } from '@/components/ManageTwoFactor.vue';
import ManageTwoFactor from '@/components/ManageTwoFactor.vue';

// oxfmt-ignore
type Props = {
    passwordRules: string;
} & ManagePasskeysProps &
    ManageTwoFactorProps;

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Configuración de seguridad',
                href: edit(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Configuración de seguridad" />

    <h1 class="sr-only">Configuración de seguridad</h1>

    <div class="max-w-3xl space-y-8">
        <!-- Tarjeta de Contraseña -->
        <div
            class="rounded-2xl border border-slate-200/90 bg-white p-6 shadow-xs sm:p-7 dark:border-slate-800 dark:bg-slate-900 space-y-6"
        >
            <Heading
                variant="small"
                title="Actualizar contraseña"
                description="Asegúrate de que tu cuenta utilice una contraseña segura y difícil de adivinar"
            />

            <Form
                v-bind="SecurityController.update.form()"
                :options="{
                    preserveScroll: true,
                }"
                reset-on-success
                :reset-on-error="[
                    'password',
                    'password_confirmation',
                    'current_password',
                ]"
                class="space-y-6"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="current_password" class="text-xs font-bold">Contraseña actual</Label>
                    <PasswordInput
                        id="current_password"
                        name="current_password"
                        class="h-10 text-xs"
                        autocomplete="current-password"
                        placeholder="Contraseña actual"
                    />
                    <InputError :message="errors.current_password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password" class="text-xs font-bold">Nueva contraseña</Label>
                    <PasswordInput
                        id="password"
                        name="password"
                        class="h-10 text-xs"
                        autocomplete="new-password"
                        placeholder="Nueva contraseña"
                        :passwordrules="props.passwordRules"
                    />
                    <InputError :message="errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation" class="text-xs font-bold"
                        >Confirmar nueva contraseña</Label
                    >
                    <PasswordInput
                        id="password_confirmation"
                        name="password_confirmation"
                        class="h-10 text-xs"
                        autocomplete="new-password"
                        placeholder="Confirmar contraseña"
                        :passwordrules="props.passwordRules"
                    />
                    <InputError :message="errors.password_confirmation" />
                </div>

                <div class="flex items-center gap-4 pt-2">
                    <Button
                        :disabled="processing"
                        class="bg-[#3484a5] text-xs font-black text-white hover:bg-[#256883]"
                        data-test="update-password-button"
                    >
                        Guardar cambios
                    </Button>
                </div>
            </Form>
        </div>

        <!-- Tarjeta de Autenticación en Dos Pasos (2FA) -->
        <div
            v-if="canManageTwoFactor"
            class="rounded-2xl border border-slate-200/90 bg-white p-6 shadow-xs sm:p-7 dark:border-slate-800 dark:bg-slate-900"
        >
            <ManageTwoFactor
                :canManageTwoFactor="canManageTwoFactor"
                :requiresConfirmation="requiresConfirmation"
                :twoFactorEnabled="twoFactorEnabled"
            />
        </div>

        <!-- Tarjeta de Llaves de Acceso (Passkeys) -->
        <div
            v-if="canManagePasskeys"
            class="rounded-2xl border border-slate-200/90 bg-white p-6 shadow-xs sm:p-7 dark:border-slate-800 dark:bg-slate-900"
        >
            <ManagePasskeys
                :canManagePasskeys="canManagePasskeys"
                :passkeys="passkeys"
            />
        </div>
    </div>
</template>
