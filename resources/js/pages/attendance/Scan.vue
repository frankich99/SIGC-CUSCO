<script setup lang="ts">
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { CheckCircle2, Clock3, Loader2, QrCode, ShieldCheck } from '@lucide/vue';

const props = defineProps<{
    course: {
        id: number;
        code: string;
        title: string;
    };
    session: number;
    enrollment: {
        id: number;
        full_name: string;
        attended_sessions: number;
    };
}>();

const processing = ref(false);
const completed = ref(false);

function registerAttendance(): void {
    if (processing.value || completed.value) return;

    processing.value = true;
    router.post(window.location.href, {}, {
        preserveScroll: true,
        onSuccess: () => {
            completed.value = true;
        },
        onFinish: () => {
            processing.value = false;
        },
    });
}
</script>

<template>
    <AppLayout>
        <Head title="Registrar asistencia" />

        <div class="min-h-[calc(100vh-8rem)] bg-slate-50 px-4 py-10 dark:bg-slate-950 sm:px-6">
            <Card class="mx-auto max-w-lg overflow-hidden border-rose-900/20 shadow-lg dark:border-rose-900/40">
                <CardHeader class="bg-gradient-to-br from-rose-950 via-rose-900 to-rose-950 text-white">
                    <div class="flex items-center gap-3">
                        <div class="flex size-11 items-center justify-center rounded-xl bg-white/10">
                            <QrCode class="size-6 text-amber-300" />
                        </div>
                        <div>
                            <CardTitle class="text-xl">Asistencia digital</CardTitle>
                            <CardDescription class="text-rose-200">
                                {{ course.code }} · Sesión {{ session }}
                            </CardDescription>
                        </div>
                    </div>
                </CardHeader>

                <CardContent class="space-y-6 p-6 sm:p-8">
                    <div class="space-y-1 text-center">
                        <p class="text-xs font-black uppercase tracking-wider text-rose-900 dark:text-rose-300">
                            {{ course.title }}
                        </p>
                        <h1 class="text-2xl font-black text-slate-950 dark:text-white">
                            {{ enrollment.full_name }}
                        </h1>
                        <p class="text-sm text-slate-600 dark:text-slate-400">
                            Confirma tu presencia en esta sesión desde tu cuenta verificada.
                        </p>
                    </div>

                    <div class="flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-200">
                        <ShieldCheck class="mt-0.5 size-5 shrink-0" />
                        <p class="text-sm leading-relaxed">
                            El enlace QR está firmado, vinculado a este curso y expira automáticamente.
                        </p>
                    </div>

                    <div v-if="completed" class="space-y-4 text-center">
                        <CheckCircle2 class="mx-auto size-14 text-emerald-600" />
                        <div>
                            <h2 class="text-lg font-black text-slate-950 dark:text-white">Asistencia registrada</h2>
                            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                                Tu asistencia para la sesión {{ session }} fue guardada correctamente.
                            </p>
                        </div>
                    </div>

                    <Button
                        v-else
                        type="button"
                        class="h-12 w-full bg-rose-900 text-sm font-black text-white hover:bg-rose-950"
                        :disabled="processing"
                        @click="registerAttendance"
                    >
                        <Loader2 v-if="processing" class="mr-2 size-4 animate-spin" />
                        <CheckCircle2 v-else class="mr-2 size-4 text-amber-300" />
                        {{ processing ? 'Guardando asistencia...' : 'Confirmar mi asistencia' }}
                    </Button>

                    <div class="flex items-center justify-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                        <Clock3 class="size-3.5" />
                        <span>Solo se puede registrar una vez por sesión.</span>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
