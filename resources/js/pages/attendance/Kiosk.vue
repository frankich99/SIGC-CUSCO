<script setup lang="ts">
/**
 * Kiosco de ingreso/salida con lector de DNI (SC-32 / HU-03.2).
 *
 * El lector USB de código de barras funciona como teclado: escribe el número
 * y envía Enter. El campo de lectura mantiene el foco para que cada servidor
 * solo tenga que pasar su DNI.
 */
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import SessionRosterPanel from '@/components/SessionRosterPanel.vue';
import { firstErrorMessage, postJson } from '@/lib/http';
import { useOnlineStatus } from '@/composables/useOnlineStatus';
import {
    countPendingMarks,
    dismissAllRejectedMarks,
    dismissRejectedMark,
    isOfflineStorageAvailable,
    listRejectedMarks,
    type RejectedMark,
} from '@/lib/offline/markQueue';
import { queueOfflineMark } from '@/lib/offline/queueOfflineMark';
import {
    REJECTED_RESULTS,
    SyncRejectedError,
    createSyncLoop,
    syncPendingBatch,
    type SyncStep,
} from '@/lib/offline/syncMarks';
import { syncBadge, type SyncBadgeState } from '@/lib/offline/syncStatus';
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
    CloudCheck,
    CloudOff,
    CloudUpload,
    Keyboard,
    Loader2,
    LogIn,
    LogOut,
    Maximize2,
    ScanBarcode,
    ShieldCheck,
    TriangleAlert,
    CircleX,
    X,
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

type Tone = 'success' | 'partial' | 'warning' | 'error' | 'offline';

/** Resultado que muestra el kiosco: el del servidor o «guardada sin conexión» (SC-46). */
type KioskResultKind = MarkResult['result'] | 'guardada_offline';
type KioskResult = Omit<MarkResult, 'result'> & { result: KioskResultKind };

const lastResult = ref<(KioskResult & { tone: Tone }) | null>(null);
const pendingOffline = ref(0);

/** Modo offline (SC-38, ADR 0001). */
const connection = useOnlineStatus();
const isOffline = connection.isOffline;
/** Sin respuesta en este tiempo, la marcación se guarda en el dispositivo. */
const MARK_TIMEOUT_MS = 8000;
/** Tras un fallo de red, cada cuánto se vuelve a intentar con el servidor. */
const SERVER_RETRY_MS = 30000;
let lastNetworkFailureAt = 0;

/** Sincronización de la cola local (SC-53). */
const isSyncing = ref(false);
const syncedCount = ref(0);
const syncRejectedCount = ref(0);
const syncError = ref<string | null>(null);

/** Indicador del encabezado (SC-55). */
const badge = computed(() =>
    syncBadge({
        offline: isOffline.value,
        syncing: isSyncing.value,
        pending: pendingOffline.value,
        synced: syncedCount.value,
    }),
);

const BADGE_CLASSES: Record<SyncBadgeState, string> = {
    offline: 'border-sky-300/60 bg-sky-500/25 text-sky-50',
    syncing: 'border-amber-300/60 bg-amber-400/20 text-amber-100',
    pending: 'border-amber-300/60 bg-amber-400/20 text-amber-100',
    synced: 'border-emerald-300/60 bg-emerald-500/25 text-emerald-50',
    hidden: '',
};

/** Marcaciones guardadas sin conexión que el servidor no registró (SC-56). */
const rejectedMarks = ref<RejectedMark[]>([]);

const REJECTED_REASON_LABELS: Record<string, string> = {
    no_inscrito: 'No inscrito',
    sin_ingreso: 'Salida sin ingreso',
    dni_invalido: 'DNI inválido',
    hora_invalida: 'Hora inválida',
};
const showPrivacyNotice = ref(false);
let resultTimer: ReturnType<typeof setTimeout> | null = null;
let privacyTimer: ReturnType<typeof setTimeout> | null = null;

const TONE_BY_RESULT: Record<KioskResultKind, Tone> = {
    ingreso_registrado: 'success',
    asistida: 'success',
    parcial: 'partial',
    duplicada: 'warning',
    sin_ingreso: 'error',
    no_inscrito: 'error',
    dni_invalido: 'error',
    hora_invalida: 'error',
    guardada_offline: 'offline',
};

const TONE_CLASSES: Record<Tone, string> = {
    success: 'border-emerald-300 bg-emerald-50 text-emerald-900',
    partial: 'border-orange-300 bg-orange-50 text-orange-900',
    warning: 'border-amber-300 bg-amber-50 text-amber-900',
    error: 'border-red-300 bg-red-50 text-red-900',
    offline: 'border-sky-300 bg-sky-50 text-sky-900',
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

function showResult(result: KioskResult): void {
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
        if (shouldSkipServer()) {
            await saveMarkOffline(value);
            return;
        }

        const { data, status } = await postJson<MarkResult>(
            `/courses/${props.course.id}/sessions/${session.value}/marks`,
            { identifier: value, type: mode.value, origin: origin.value },
            { timeoutMs: MARK_TIMEOUT_MS },
        );
        connection.reportNetworkSuccess();

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

        // El servidor vuelve a responder: enviar lo que quedó guardado.
        if (pendingOffline.value > 0) void syncLoop.trigger();
    } catch {
        // Sin red o sin respuesta a tiempo: el servidor no decidió nada.
        connection.reportNetworkFailure();
        lastNetworkFailureAt = Date.now();
        await saveMarkOffline(value);
        void syncLoop.kick();
    } finally {
        isSubmitting.value = false;
        focusInput();
    }
}

/**
 * No intentar con el servidor si el navegador dice que no hay red, o si el
 * último envío falló hace menos de SERVER_RETRY_MS (evita esperar 8 s en cada
 * lectura mientras la red sigue caída).
 */
function shouldSkipServer(): boolean {
    if (!connection.browserOnline.value) return true;

    return (
        connection.serverUnreachable.value &&
        Date.now() - lastNetworkFailureAt < SERVER_RETRY_MS
    );
}

/**
 * Sin conexión la marcación se guarda en el dispositivo (IndexedDB) en lugar
 * de perderse. La sincronización con el servidor llega en SC-39.
 */
async function saveMarkOffline(value: string): Promise<void> {
    const failure: KioskResult = {
        result: 'dni_invalido',
        message:
            'Sin conexión con el servidor. Use el registro manual de la pestaña Asistencia cuando vuelva la red.',
        first_mark: false,
        participant: null,
        marked_at: null,
        permanence: null,
    };

    if (!isOfflineStorageAvailable()) {
        showResult(failure);
        return;
    }

    try {
        const outcome = await queueOfflineMark({
            courseId: props.course.id,
            session: session.value,
            identifier: value,
            type: mode.value,
            origin: origin.value,
        });

        if (outcome.kind === 'invalid_dni') {
            showResult({
                ...failure,
                message:
                    'La lectura no contiene un DNI válido de 8 dígitos. Vuelva a escanear.',
            });
            return;
        }

        if (outcome.kind === 'already_pending') {
            showResult({
                ...failure,
                result: 'duplicada',
                message: `Sin conexión: la ${mode.value} del DNI ${outcome.mark.dni ?? outcome.mark.identifier} ya estaba guardada en este dispositivo. No se registró de nuevo.`,
                marked_at: outcome.mark.captured_at,
            });
            return;
        }

        await refreshPendingOffline();
        showResult({
            ...failure,
            result: 'guardada_offline',
            message: `Sin conexión: la ${mode.value} del DNI ${outcome.mark.dni ?? outcome.mark.identifier} quedó guardada en este dispositivo y se enviará cuando vuelva la red.`,
            marked_at: outcome.mark.captured_at,
        });
    } catch {
        showResult(failure);
    }
}

/**
 * Un paso de la sincronización: envía un lote de la cola de esta sesión.
 * Sin red devuelve `retry` (createSyncLoop reintenta con espera creciente).
 */
async function syncStep(): Promise<SyncStep> {
    if (!isOfflineStorageAvailable() || props.course.is_acta_closed) {
        return 'stop';
    }
    if (!connection.browserOnline.value) return 'retry';

    isSyncing.value = true;
    try {
        const summary = await syncPendingBatch(props.course.id, session.value);
        connection.reportNetworkSuccess();
        syncError.value = null;

        if (summary.results.length > 0) {
            syncedCount.value += summary.results.length;
            syncRejectedCount.value += summary.results.filter((result) =>
                REJECTED_RESULTS.has(result.result),
            ).length;
            void rosterPanel.value?.refresh(true);
        }

        if (summary.rejected.length > 0) await refreshRejected();
        await refreshPendingOffline();

        if (summary.remaining === 0) return 'done';
        // Si el servidor no contestó ninguna, no insistir en bucle.
        return summary.results.length > 0 ? 'more' : 'stop';
    } catch (error) {
        if (error instanceof SyncRejectedError) {
            syncError.value = error.message;
            return 'stop';
        }

        connection.reportNetworkFailure();
        lastNetworkFailureAt = Date.now();
        return 'retry';
    } finally {
        isSyncing.value = false;
    }
}

const syncLoop = createSyncLoop(syncStep);

// Al volver la red, enviar enseguida lo guardado.
watch(connection.browserOnline, (online) => {
    if (online) void syncLoop.trigger();
});

function syncNow(): void {
    void syncLoop.trigger();
    focusInput();
}

async function refreshRejected(): Promise<void> {
    if (!isOfflineStorageAvailable()) return;

    try {
        rejectedMarks.value = await listRejectedMarks(
            props.course.id,
            session.value,
        );
    } catch {
        rejectedMarks.value = [];
    }
}

async function dismissRejected(clientUuid: string): Promise<void> {
    await dismissRejectedMark(clientUuid);
    await refreshRejected();
    focusInput();
}

async function dismissAllRejected(): Promise<void> {
    await dismissAllRejectedMarks(props.course.id, session.value);
    await refreshRejected();
    focusInput();
}

async function refreshPendingOffline(): Promise<void> {
    if (!isOfflineStorageAvailable()) return;

    try {
        pendingOffline.value = await countPendingMarks(
            props.course.id,
            session.value,
        );
    } catch {
        pendingOffline.value = 0;
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
    void refreshRejected();
    void refreshPendingOffline().then(() => {
        if (pendingOffline.value > 0) void syncLoop.trigger();
    });
});

onUnmounted(() => {
    if (clockTimer) clearInterval(clockTimer);
    if (resultTimer) clearTimeout(resultTimer);
    if (privacyTimer) clearTimeout(privacyTimer);
    window.removeEventListener('click', onWindowClick);
    syncLoop.stop();
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
                    <div class="flex flex-wrap items-center gap-2">
                        <div
                            class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-3 py-1 text-xs font-black tracking-wider text-amber-300 uppercase"
                        >
                            <Clock class="size-3.5" />
                            {{ currentTime }}
                        </div>
                        <!-- Estado de sincronización (SC-55) -->
                        <div
                            v-if="badge.state !== 'hidden'"
                            :class="[
                                'inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-black',
                                BADGE_CLASSES[badge.state],
                            ]"
                            role="status"
                            aria-live="polite"
                        >
                            <CloudOff
                                v-if="badge.state === 'offline'"
                                class="size-3.5"
                            />
                            <Loader2
                                v-else-if="badge.state === 'syncing'"
                                class="size-3.5 animate-spin"
                            />
                            <CloudUpload
                                v-else-if="badge.state === 'pending'"
                                class="size-3.5"
                            />
                            <CloudCheck v-else class="size-3.5" />
                            {{ badge.label }}
                        </div>
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

            <div
                v-if="isOffline"
                class="flex items-center gap-3 rounded-xl border border-sky-300 bg-sky-50 p-3 text-sm font-bold text-sky-900"
                role="status"
            >
                <CloudOff class="size-5 shrink-0" />
                Sin conexión con el servidor. Las marcaciones se guardan en este
                dispositivo y se enviarán cuando vuelva la red.
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

                <div
                    v-if="pendingOffline > 0"
                    class="flex flex-wrap items-center gap-2 rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-xs font-bold text-sky-900"
                >
                    <CloudOff class="size-4 shrink-0" />
                    <span>
                        {{ pendingOffline }}
                        {{
                            pendingOffline === 1
                                ? 'marcación guardada'
                                : 'marcaciones guardadas'
                        }}
                        en este dispositivo, pendientes de enviar al servidor.
                    </span>
                    <span
                        v-if="isSyncing"
                        class="ml-auto inline-flex items-center gap-1"
                    >
                        <Loader2 class="size-3.5 animate-spin" />
                        Enviando…
                    </span>
                    <button
                        v-else
                        type="button"
                        class="ml-auto underline underline-offset-2 hover:text-sky-700"
                        @click="syncNow"
                    >
                        Enviar ahora
                    </button>
                </div>

                <p
                    v-if="syncError"
                    class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-bold text-red-800"
                    role="alert"
                >
                    No se pudieron enviar las marcaciones guardadas:
                    {{ syncError }}
                </p>
                <p
                    v-else-if="syncedCount > 0"
                    :class="[
                        'rounded-lg border px-3 py-2 text-xs font-bold',
                        syncRejectedCount > 0
                            ? 'border-amber-200 bg-amber-50 text-amber-900'
                            : 'border-emerald-200 bg-emerald-50 text-emerald-900',
                    ]"
                    role="status"
                >
                    Se enviaron {{ syncedCount }}
                    {{
                        syncedCount === 1
                            ? 'marcación guardada'
                            : 'marcaciones guardadas'
                    }}
                    sin conexión.
                    <template v-if="syncRejectedCount > 0">
                        {{ syncRejectedCount }} no
                        {{
                            syncRejectedCount === 1
                                ? 'se registró'
                                : 'se registraron'
                        }}; revise la lista de abajo.
                    </template>
                </p>
            </form>

            <!-- Rechazadas al sincronizar (SC-56) -->
            <section
                v-if="rejectedMarks.length > 0"
                class="rounded-2xl border-2 border-amber-300 bg-amber-50 p-4 text-amber-950"
                aria-labelledby="kiosk-rejected-title"
            >
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2
                        id="kiosk-rejected-title"
                        class="flex items-center gap-2 text-sm font-black"
                    >
                        <TriangleAlert class="size-4 shrink-0" />
                        Marcaciones sin conexión que no se registraron ({{
                            rejectedMarks.length
                        }})
                    </h2>
                    <button
                        type="button"
                        class="text-xs font-bold underline underline-offset-2 hover:text-amber-700"
                        @click="dismissAllRejected"
                    >
                        Descartar todas
                    </button>
                </div>
                <p class="mt-1 text-xs">
                    Se guardaron en este dispositivo sin conexión, pero al
                    enviarlas el servidor no las aceptó. Revise cada caso y, si
                    corresponde, regístrelo en la pestaña Asistencia del curso.
                </p>
                <ul class="mt-3 divide-y divide-amber-200">
                    <li
                        v-for="item in rejectedMarks"
                        :key="item.client_uuid"
                        class="flex items-start gap-3 py-2 text-xs"
                    >
                        <div class="min-w-0 flex-1 space-y-0.5">
                            <p class="font-bold">
                                <span class="font-mono"
                                    >DNI {{ item.dni ?? item.identifier }}</span
                                >
                                ·
                                {{
                                    item.type === 'entrada'
                                        ? 'Ingreso'
                                        : 'Salida'
                                }}
                                · {{ formatLimaTime(item.captured_at) }}
                            </p>
                            <p>
                                <span class="font-black"
                                    >{{
                                        REJECTED_REASON_LABELS[item.result] ??
                                        item.result
                                    }}:</span
                                >
                                {{ item.message }}
                            </p>
                        </div>
                        <button
                            type="button"
                            class="rounded-md p-1 hover:bg-amber-100"
                            :aria-label="`Descartar la marcación del DNI ${item.dni ?? item.identifier}`"
                            @click="dismissRejected(item.client_uuid)"
                        >
                            <X class="size-4" />
                        </button>
                    </li>
                </ul>
            </section>

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
                <CloudOff
                    v-else-if="lastResult.tone === 'offline'"
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
