<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/DeleteUser.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Configuración del perfil',
                href: edit(),
            },
        ],
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
</script>

<template>
    <Head title="Configuración del perfil" />

    <h1 class="sr-only">Configuración del perfil</h1>

    <div class="max-w-3xl space-y-8">
        <!-- Tarjeta de Datos del Perfil -->
        <div
            class="rounded-2xl border border-slate-200/90 bg-white p-6 shadow-xs sm:p-7 dark:border-slate-800 dark:bg-slate-900 space-y-6"
        >
            <Heading
                variant="small"
                title="Información Personal"
                description="Actualiza tu nombre completo y dirección de correo electrónico institucional"
            />

            <Form
                v-bind="ProfileController.update.form()"
                class="space-y-6"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="name" class="text-xs font-bold">Nombre completo</Label>
                    <Input
                        id="name"
                        class="h-10 text-xs font-semibold"
                        name="name"
                        :default-value="user.name"
                        required
                        autocomplete="name"
                        placeholder="Tu nombre completo"
                    />
                    <InputError class="mt-1" :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="email" class="text-xs font-bold">Correo electrónico</Label>
                    <Input
                        id="email"
                        type="email"
                        class="h-10 text-xs font-semibold"
                        name="email"
                        :default-value="user.email"
                        required
                        autocomplete="username"
                        placeholder="correo@ejemplo.com"
                    />
                    <InputError class="mt-1" :message="errors.email" />
                </div>

                <div v-if="page.props.mustVerifyEmail && !user.email_verified_at">
                    <p class="-mt-4 text-xs text-muted-foreground">
                        Tu dirección de correo no está verificada.
                        <Link
                            :href="send()"
                            as="button"
                            class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                        >
                            Haz clic aquí para reenviar el correo de verificación.
                        </Link>
                    </p>

                    <div
                        v-if="page.props.status === 'verification-link-sent'"
                        class="mt-2 text-xs font-medium text-emerald-600"
                    >
                        Se ha enviado un nuevo enlace de verificación a tu dirección de correo.
                    </div>
                </div>

                <div class="flex items-center gap-4 pt-2">
                    <Button
                        :disabled="processing"
                        class="bg-[#3484a5] text-xs font-black text-white hover:bg-[#256883]"
                        data-test="update-profile-button"
                    >
                        Guardar cambios
                    </Button>
                </div>
            </Form>
        </div>

        <!-- Zona de Eliminación de Cuenta -->
        <div
            class="rounded-2xl border border-rose-200/90 bg-white p-6 shadow-xs sm:p-7 dark:border-rose-950/60 dark:bg-slate-900"
        >
            <DeleteUser />
        </div>
    </div>
</template>
