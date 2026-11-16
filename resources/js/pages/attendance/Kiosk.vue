<script setup lang="ts">
/**
 * Kiosco de ingreso/salida con lector de DNI (SC-32 / HU-03.2).
 *
 * El lector USB de código de barras funciona como teclado: escribe el número
 * y envía Enter. El campo de lectura mantiene el foco para que cada servidor
 * solo tenga que pasar su DNI.
 */
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import SessionRosterPanel from '@/components/SessionRosterPanel.vue';
import { firstErrorMessage, postJson } from '@/lib/http';
import {
    formatLimaTime,
    type MarkOrigin,
    type MarkResult,
    type MarkType,
    type SessionRoster,
} from '@/types/attendance';
import type { BreadcrumbItem } from '@/types';
import {
    ArrowLeft,
    CheckCircle2,
    Clock,
    Keyboard,
    Loader2,
    LogIn,
    LogOut,
    Maximize2,
    ScanBarcode,
    ShieldCheck,
    TriangleAlert,
    CircleX,
} from '@lucide/vue';

const props = defineProps<{
    course: {
        id: number;
        code: string;
        title: string;
        total_sessions: number;
        is_acta_closed: boolean;
    };
    roster: SessionRoster;
    initialMode: MarkType;
}>();

const session = computed(() => props.roster.session);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Capacitaciones', href: '/courses' },
    { title: props.course.title, href: `/courses/${props.course.id}` },
    {
        title: `Kiosco · Sesión ${props.roster.session}`,
        href: `/courses/${props.course.id}/kiosco/${props.roster.session}`,
    },
];

const mode = ref<MarkType>(props.initialMode);
const origin = ref<MarkOrigin>('dni_lector');
const identifier = ref('');
const isSubmitting = ref(false);
const inputRef = ref<InstanceType<typeof Input> | null>(null);
const rosterPanel = ref<InstanceType<typeof SessionRosterPanel> | null>(null);
const summary = ref(props.roster.summary);

type Tone = 'success' | 'partial' | 'warning' | 'error';
const lastResult = ref<(MarkResult & { tone: Tone }) | null>(null);
const showPrivacyNotice = ref(false);
let resultTimer: ReturnType<typeof setTimeout> | null = null;
let privacyTimer: ReturnType<typeof setTimeout> | null = null;

const TONE_BY_RESULT: Record<MarkResult['result'], Tone> = {
    ingreso_registrado: 'success',
    asistida: 'success',
    parcial: 'partial',
    duplicada: 'warning',
    sin_ingreso: 'error',
    no_inscrito: 'error',
    dni_invalido: 'error',
};

const TONE_CLASSES: Record<Tone, string> = {
    success: 'border-emerald-300 bg-emerald-50 text-emerald-900',
    partial: 'border-orange-300 bg-orange-50 text-orange-900',
    warning: 'border-amber-300 bg-amber-50 text-amber-900',
    error: 'border-red-300 bg-red-50 text-red-900',
};

function focusInput(): void {
    void nextTick(() => {
        const el = (inputRef.value as unknown as { $el?: HTMLElement })?.$el;
        const input =
            el instanceof HTMLInputElement
                ? el
                : (el?.querySelector?.('input') ?? null);
        input?.focus();
    });
}

function beep(tone: Tone): void {
    try {
        const AudioCtx =
            window.AudioContext ||
            (window as unknown as { webkitAudioContext?: typeof AudioContext })
                .webkitAudioContext;
        if (!AudioCtx) return;
        const ctx = new AudioCtx();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.frequency.value =
            tone === 'success' ? 880 : tone === 'error' ? 220 : 520;
        gain.gain.value = 0.08;
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start();
        osc.stop(ctx.currentTime + (tone === 'error' ? 0.35 : 0.15));
        osc.onended = () => void ctx.close();
    } catch {
        // Sin audio disponible: el aviso visual es suficiente.
    }
}

function showResult(result: MarkResult): void {
    const tone = TONE_BY_RESULT[result.result] ?? 'error';
    lastResult.value = { ...result, tone };
    beep(tone);

    if (resultTimer) clearTimeout(resultTimer);
    resultTimer = setTimeout(() => (lastResult.value = null), 8000);

    if (result.first_mark) {
        showPrivacyNotice.value = true;
        if (privacyTimer) clearTimeout(privacyTimer);
        privacyTimer = setTimeout(
            () => (showPrivacyNotice.value = false),
            10000,
        );
    }
}

async function submitMark(): Promise<void> {
    const value = identifier.value.trim();
    if (!value || isSubmitting.value) return;

    isSubmitting.value = true;
    identifier.value = '';

    try {
        const { data, status } = await postJson<MarkResult>(
            `/courses/${props.course.id}/sessions/${session.value}/marks`,
            { identifier: value, type: mode.value, origin: origin.value },
        );

        if (data && typeof data === 'object' && 'result' in data) {
            showResult(data);
        } else {
            showResult({
                result: 'dni_invalido',
                message: firstErrorMessage(
                    data,
                    status === 419
                        ? 'La sesión del kiosco expiró. Recargue la página.'
                        : 'No se pudo registrar la marca.',
                ),
                first_mark: false,
                participant: null,
                marked_at: null,
                permanence: null,
            });
        }

        void rosterPanel.value?.refresh(true);
    } catch {
        showResult({
            result: 'dni_invalido',
            message:
                'Sin conexión con el servidor. Use el registro manual de la pestaña Asistencia cuando vuelva la red.',
            first_mark: false,
            participant: null,
            marked_at: null,
            permanence: null,
        });
    } finally {
        isSubmitting.value = false;
        focusInput();
    }
}

function setMode(next: MarkType): void {
    mode.value = next;
    if (typeof window !== 'undefined') {
        const url = new URL(window.location.href);
        url.searchParams.set('modo', next);
        window.history.replaceState(window.history.state, '', url);
    }
    focusInput();
}

function goToSession(n: number): void {
    router.get(
        `/courses/${props.course.id}/kiosco/${n}`,
        { modo: mode.value },
        { preserveScroll: true },
    );
}

function toggleFullscreen(): void {
    if (typeof document === 'undefined') return;
    if (document.fullscreenElement) {
        void document.exitFullscreen();
    } else {
        void document.documentElement.requestFullscreen?.();
    }
}

const currentTime = ref('');
let clockTimer: ReturnType<typeof setInterval> | null = null;

function tick(): void {
    currentTime.value = formatLimaTime(new Date().toISOString());
}

// Mantener el foco en el campo de lectura salvo cuando se usa otro control o un diálogo.
function onWindowClick(event: MouseEvent): void {
    const target = event.target as HTMLElement | null;
    if (
        target?.closest('input, textarea, select, button, a, [role="dialog"]')
    ) {
        return;
    }
    focusInput();
}

onMounted(() => {
    tick();
    clockTimer = setInterval(tick, 1000);
    focusInput();
    window.addEventListener('click', onWindowClick);
});

onUnmounted(() => {
    if (clockTimer) clearInterval(clockTimer);
    if (resultTimer) clearTimeout(resultTimer);
    if (privacyTimer) clearTimeout(privacyTimer);
    window.removeEventListener('click', onWindowClick);
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`Kiosco de asistencia · ${course.code}`" />

        <div
            class="mx-auto w-full max-w-6xl min-w-0 space-y-5 px-4 py-6 sm:px-6 lg:px-8"
        >
            <!-- Encabezado -->
            <div
                class="flex flex-col gap-3 rounded-2xl bg-gradient-to-br from-rose-950 via-rose-900 to-rose-950 p-5 text-white shadow-md sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="space-y-1">
                    <div
                        class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-3 py-1 text-xs font-black tracking-wider text-amber-300 uppercase"
                    >
                        <Clock class="size-3.5" />
                        {{ currentTime }}
                    </div>
                    <h1 class="text-xl font-black tracking-tight sm:text-2xl">
                        Control de ingreso y salida · Sesión {{ session }}
                    </h1>
                    <p class="text-xs text-rose-200">
                        {{ course.title }} ({{ course.code }})
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <Button
                        as-child
                        size="sm"
                        variant="outline"
                        class="h-8 border-white/30 bg-white/10 text-xs font-bold text-white hover:bg-white/20"
                    >
                        <Link :href="`/courses/${course.id}`">
                            <ArrowLeft class="mr-1.5 size-3.5" />
                            Volver al curso
                        </Link>
                    </Button>
                    <Button
                        size="sm"
                        variant="outline"
                        class="h-8 border-white/30 bg-white/10 text-xs font-bold text-white hover:bg-white/20"
                        @click="toggleFullscreen"
                    >
                        <Maximize2 class="mr-1.5 size-3.5" />
                        Pantalla completa
                    </Button>
                </div>
            </div>

            <div
                v-if="course.is_acta_closed"
                class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm font-bold text-red-800"
            >
                El acta oficial de esta capacitación está cerrada. No se pueden
                registrar marcas.
            </div>

            <!-- Sesión -->
            <div class="flex flex-wrap items-center gap-1.5">
                <span
                    class="mr-1 text-xs font-black tracking-wider text-slate-700 uppercase dark:text-slate-300"
                    >Sesión:</span
                >
                <Button
                    v-for="n in course.total_sessions"
                    :key="n"
                    size="sm"
                    :variant="n === session ? 'default' : 'outline'"
                    :class="
                        n === session
                            ? 'h-7 bg-rose-900 px-2.5 text-xs font-black text-white'
                            : 'h-7 px-2.5 text-xs font-bold'
                    "
                    @click="goToSession(n)"
                >
                    S{{ n }}
                </Button>
            </div>

            <!-- Modo de marca -->
            <div class="grid gap-3 sm:grid-cols-2">
                <button
                    type="button"
                    :class="[
                        'flex items-center justify-center gap-3 rounded-2xl border-2 p-5 text-lg font-black transition-all',
                        mode === 'entrada'
                            ? 'border-emerald-600 bg-emerald-600 text-white shadow-lg'
                            : 'border-slate-200 bg-white text-slate-600 hover:border-emerald-300 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-300',
                    ]"
                    @click="setMode('entrada')"
                >
                    <LogIn class="size-6" />
                    ENTRADA
                </button>
                <button
                    type="button"
                    :class="[
                        'flex items-center justify-center gap-3 rounded-2xl border-2 p-5 text-lg font-black transition-all',
                        mode === 'salida'
                            ? 'border-rose-900 bg-rose-900 text-white shadow-lg'
                            : 'border-slate-200 bg-white text-slate-600 hover:border-rose-300 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-300',
                    ]"
                    @click="setMode('salida')"
                >
                    <LogOut class="size-6" />
                    SALIDA
                </button>
            </div>

            <!-- Lectura -->
            <form
                class="space-y-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-xs dark:border-slate-800 dark:bg-slate-950"
                @submit.prevent="submitMark"
            >
                <label
                    for="kiosk-dni"
                    class="flex items-center gap-2 text-sm font-black text-slate-800 dark:text-slate-200"
                >
                    <ScanBarcode
                        v-if="origin === 'dni_lector'"
                        class="size-5 text-rose-900"
                    />
                    <Keyboard v-else class="size-5 text-rose-900" />
                    {{
                        origin === 'dni_lector'
                            ? 'Pase el DNI por el lector'
                            : 'Modo alterno: digite el DNI y pulse Enter'
                    }}
                </label>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <Input
                        id="kiosk-dni"
                        ref="inputRef"
                        v-model="identifier"
                        type="text"
                        inputmode="numeric"
                        autocomplete="off"
                        :placeholder="
                            origin === 'dni_lector'
                                ? 'Esperando lectura del código de barras…'
                                : 'DNI de 8 dígitos'
                        "
                        :disabled="course.is_acta_closed"
                        class="h-14 flex-1 text-center font-mono text-2xl font-black tracking-[0.3em] placeholder:font-sans placeholder:text-base placeholder:font-medium placeholder:tracking-normal"
                    />
                    <Button
                        type="submit"
                        class="h-14 bg-rose-900 px-6 text-sm font-black text-white hover:bg-rose-950"
                        :disabled="
                            isSubmitting ||
                            !identifier.trim() ||
                            course.is_acta_closed
                        "
                    >
                        <Loader2
                            v-if="isSubmitting"
                            class="mr-2 size-4 animate-spin"
                        />
                        Registrar {{ mode }}
                    </Button>
                </div>

                <div
                    class="flex flex-col gap-2 text-xs text-slate-600 sm:flex-row sm:items-center sm:justify-between dark:text-slate-400"
                >
                    <label
                        class="inline-flex cursor-pointer items-center gap-2"
                    >
                        <input
                            type="checkbox"
                            class="size-4 accent-rose-900"
                            :checked="origin === 'manual'"
                            @change="
                                origin = ($event.target as HTMLInputElement)
                                    .checked
                                    ? 'manual'
                                    : 'dni_lector';
                                focusInput();
                            "
                        />
                        <span class="font-bold">
                            Modo alterno (lector averiado): DNI digitado
                        </span>
                    </label>
                    <span>
                        Fuera del auditorio: proyecte el
                        <Link
                            :href="`/courses/${course.id}`"
                            class="font-bold text-rose-900 underline"
                            >QR dinámico de la sesión</Link
                        >.
                    </span>
                </div>
            </form>

            <!-- Resultado de la última lectura -->
            <div
                v-if="lastResult"
                :class="[
                    'flex items-start gap-4 rounded-2xl border-2 p-5',
                    TONE_CLASSES[lastResult.tone],
                ]"
                role="status"
                aria-live="assertive"
            >
                <CheckCircle2
                    v-if="lastResult.tone === 'success'"
                    class="size-10 shrink-0"
                />
                <TriangleAlert
                    v-else-if="
                        lastResult.tone === 'partial' ||
                        lastResult.tone === 'warning'
                    "
                    class="size-10 shrink-0"
                />
                <CircleX v-else class="size-10 shrink-0" />
                <div class="min-w-0 space-y-1">
                    <p
                        v-if="lastResult.participant"
                        class="text-2xl font-black tracking-tight"
                    >
                        {{ lastResult.participant.full_name }}
                    </p>
                    <p class="text-sm font-bold">{{ lastResult.message }}</p>
                    <p
                        v-if="lastResult.participant || lastResult.marked_at"
                        class="font-mono text-xs opacity-80"
                    >
                        <template v-if="lastResult.participant">
                            DNI {{ lastResult.participant.dni }}
                        </template>
                        <template v-if="lastResult.marked_at">
                            · {{ formatLimaTime(lastResult.marked_at) }}
                        </template>
                    </p>
                </div>
            </div>

            <!-- Aviso de datos personales (Ley N.° 29733) -->
            <div
                :class="[
                    'flex items-start gap-3 rounded-xl border p-3 text-[11px] leading-relaxed transition-all',
                    showPrivacyNotice
                        ? 'border-sky-300 bg-sky-50 text-sky-950 shadow-md ring-2 ring-sky-200'
                        : 'border-slate-200 bg-slate-50 text-slate-600 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400',
                ]"
            >
                <ShieldCheck class="mt-0.5 size-4 shrink-0" />
                <p>
                    <strong
                        >Aviso de tratamiento de datos personales (Ley N.°
                        29733).</strong
                    >
                    La entidad organizadora registra únicamente su DNI, la
                    sesión, el tipo de marca (ingreso o salida), el origen de la
                    marca y la hora, con la finalidad de acreditar las horas de
                    capacitación del PDP. No se guardan fotografías ni
                    geolocalización. Puede ejercer sus derechos ARCO ante el
                    área de Recursos Humanos.
                </p>
            </div>

            <!-- Resumen rápido -->
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-5">
                <div
                    class="rounded-xl border border-sky-200 bg-sky-50 p-3 text-center"
                >
                    <div class="text-2xl font-black text-sky-900">
                        {{ summary.en_sala }}
                    </div>
                    <div class="text-[10px] font-bold text-sky-800 uppercase">
                        En sala
                    </div>
                </div>
                <div
                    class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-center"
                >
                    <div class="text-2xl font-black text-emerald-900">
                        {{ summary.presentes }}
                    </div>
                    <div
                        class="text-[10px] font-bold text-emerald-800 uppercase"
                    >
                        Asistidas
                    </div>
                </div>
                <div
                    class="rounded-xl border border-orange-200 bg-orange-50 p-3 text-center"
                >
                    <div class="text-2xl font-black text-orange-900">
                        {{ summary.parciales }}
                    </div>
                    <div
                        class="text-[10px] font-bold text-orange-800 uppercase"
                    >
                        Parciales
                    </div>
                </div>
                <div
                    class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-center"
                >
                    <div class="text-2xl font-black text-slate-800">
                        {{ summary.ausentes }}
                    </div>
                    <div class="text-[10px] font-bold text-slate-600 uppercase">
                        Ausentes
                    </div>
                </div>
                <div
                    class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-center"
                >
                    <div class="text-2xl font-black text-amber-900">
                        {{ summary.no_inscritos }}
                    </div>
                    <div class="text-[10px] font-bold text-amber-800 uppercase">
                        No inscritos
                    </div>
                </div>
            </div>

            <SessionRosterPanel
                ref="rosterPanel"
                :course-id="course.id"
                :session="session"
                :initial-roster="roster"
                @updated="(r) => (summary = r.summary)"
            />
        </div>
    </AppLayout>
</template>
