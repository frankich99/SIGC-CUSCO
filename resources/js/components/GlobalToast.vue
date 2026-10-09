<script setup lang="ts">
import { ref, computed, watch, onUnmounted } from 'vue';
import { activeToasts, removeToast, type ActiveToast } from '@/lib/notify';
import {
    CheckCircle2,
    XCircle,
    AlertTriangle,
    Info,
    HelpCircle,
    X,
    Clock,
    Pause,
} from '@lucide/vue';

// Obtener estrictamente una única notificación activa (la más reciente)
const currentToast = computed<ActiveToast | null>(() => {
    const list = activeToasts.value.filter((t) => !t.position || t.position === 'top-end');
    return list.length > 0 ? list[list.length - 1] : null;
});

const remainingMs = ref<number>(0);
const totalDuration = ref<number>(0);
const isPaused = ref<boolean>(false);
let timerInterval: ReturnType<typeof setInterval> | null = null;
let lastTick: number = 0;

function stopTimer() {
    if (timerInterval) {
        clearInterval(timerInterval);
        timerInterval = null;
    }
}

function startTimer(duration: number) {
    stopTimer();
    if (!duration || duration <= 0) {
        totalDuration.value = 0;
        remainingMs.value = 0;
        return;
    }

    totalDuration.value = duration;
    remainingMs.value = duration;
    isPaused.value = false;
    lastTick = Date.now();

    timerInterval = setInterval(() => {
        if (isPaused.value) {
            lastTick = Date.now();
            return;
        }

        const now = Date.now();
        const elapsed = now - lastTick;
        lastTick = now;
        remainingMs.value = Math.max(0, remainingMs.value - elapsed);

        if (remainingMs.value <= 0) {
            stopTimer();
            if (currentToast.value) {
                removeToast(currentToast.value.id);
            }
        }
    }, 40);
}

function pauseTimer() {
    isPaused.value = true;
}

function resumeTimer() {
    lastTick = Date.now();
    isPaused.value = false;
}

watch(
    () => currentToast.value?.id,
    (newId) => {
        if (newId && currentToast.value) {
            startTimer(currentToast.value.timer);
        } else {
            stopTimer();
        }
    },
    { immediate: true }
);

onUnmounted(() => {
    stopTimer();
});

const progressPercent = computed(() => {
    if (totalDuration.value <= 0) return 0;
    return Math.max(0, Math.min(100, (remainingMs.value / totalDuration.value) * 100));
});

const secondsLeft = computed(() => {
    return Math.max(1, Math.ceil(remainingMs.value / 1000));
});

function accentBorder(icon: string) {
    switch (icon) {
        case 'success':
            return 'border-l-4 border-l-emerald-500';
        case 'error':
            return 'border-l-4 border-l-rose-600';
        case 'warning':
            return 'border-l-4 border-l-amber-500';
        case 'info':
            return 'border-l-4 border-l-sky-500';
        default:
            return 'border-l-4 border-l-slate-500';
    }
}

function progressBarColor(icon: string) {
    switch (icon) {
        case 'success':
            return 'bg-emerald-500';
        case 'error':
            return 'bg-rose-600';
        case 'warning':
            return 'bg-amber-500';
        case 'info':
            return 'bg-sky-500';
        default:
            return 'bg-slate-500';
    }
}
</script>

<template>
    <!-- CONTENEDOR FLOTANTE TOP-END: EXACTAMENTE UNA NOTIFICACIÓN ACTIVA -->
    <div
        aria-live="polite"
        class="fixed top-2.5 right-2.5 sm:top-4 sm:right-4 z-[99999] pointer-events-none flex flex-col items-end max-w-[calc(100vw-1.25rem)] overflow-hidden"
    >
        <Transition
            enter-active-class="transform ease-out duration-300 transition"
            enter-from-class="-translate-y-3 opacity-0 scale-95"
            enter-to-class="translate-y-0 opacity-100 scale-100"
            leave-active-class="transition ease-in duration-200"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="-translate-y-3 opacity-0 scale-95"
        >
            <div
                v-if="currentToast"
                :key="currentToast.id"
                role="status"
                class="group pointer-events-auto relative overflow-hidden rounded-xl border bg-white/95 dark:bg-slate-900/95 backdrop-blur-md shadow-xl py-3 px-3.5 flex items-start gap-2.5 transition-all text-left border-slate-200 dark:border-slate-800 w-[min(22rem,calc(100vw-1.5rem))] max-w-full"
                :class="accentBorder(currentToast.icon)"
                @mouseenter="pauseTimer"
                @mouseleave="resumeTimer"
            >
                <!-- ICONO COMPACTO ESTILO SWEETALERT2 -->
                <div class="shrink-0 mt-0.5">
                    <!-- Success -->
                    <div
                        v-if="currentToast.icon === 'success'"
                        class="size-6 rounded-full bg-emerald-100 dark:bg-emerald-950/80 text-emerald-600 dark:text-emerald-400 flex items-center justify-center ring-2 ring-emerald-500/20 shadow-xs"
                    >
                        <CheckCircle2 class="size-3.5 stroke-[2.8]" />
                    </div>

                    <!-- Error -->
                    <div
                        v-else-if="currentToast.icon === 'error'"
                        class="size-6 rounded-full bg-rose-100 dark:bg-rose-950/80 text-rose-600 dark:text-rose-400 flex items-center justify-center ring-2 ring-rose-500/20 shadow-xs"
                    >
                        <XCircle class="size-3.5 stroke-[2.8]" />
                    </div>

                    <!-- Warning -->
                    <div
                        v-else-if="currentToast.icon === 'warning'"
                        class="size-6 rounded-full bg-amber-100 dark:bg-amber-950/80 text-amber-600 dark:text-amber-400 flex items-center justify-center ring-2 ring-amber-500/20 shadow-xs"
                    >
                        <AlertTriangle class="size-3.5 stroke-[2.8]" />
                    </div>

                    <!-- Info -->
                    <div
                        v-else-if="currentToast.icon === 'info'"
                        class="size-6 rounded-full bg-sky-100 dark:bg-sky-950/80 text-sky-600 dark:text-sky-400 flex items-center justify-center ring-2 ring-sky-500/20 shadow-xs"
                    >
                        <Info class="size-3.5 stroke-[2.8]" />
                    </div>

                    <!-- Question / Default -->
                    <div
                        v-else
                        class="size-6 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 flex items-center justify-center ring-2 ring-slate-400/20"
                    >
                        <HelpCircle class="size-3.5 stroke-[2.8]" />
                    </div>
                </div>

                <!-- CONTENIDO DEL TOAST: COMPACTO, LEGIBLE Y ELEGANTE -->
                <div class="flex-1 min-w-0 pr-1">
                    <p class="text-xs font-black text-slate-900 dark:text-white leading-tight break-words">
                        {{ currentToast.title }}
                    </p>
                    <p
                        v-if="currentToast.text"
                        class="text-[11px] text-slate-600 dark:text-slate-400 mt-0.5 leading-snug break-words font-medium"
                    >
                        {{ currentToast.text }}
                    </p>
                </div>

                <!-- ACCIONES DE CABECERA: TEMPORIZADOR VISIBLE + BOTÓN CERRAR -->
                <div class="flex items-center gap-1.5 shrink-0 ml-1">
                    <!-- Indicador visible del temporizador -->
                    <div
                        v-if="currentToast.timer > 0"
                        class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-md text-[10px] font-mono font-bold transition-all select-none"
                        :class="isPaused ? 'bg-amber-100 dark:bg-amber-950/80 text-amber-800 dark:text-amber-300 ring-1 ring-amber-400/40' : 'bg-slate-100 dark:bg-slate-800/80 text-slate-500 dark:text-slate-400'"
                        :title="isPaused ? 'Temporizador en pausa (mouse encima)' : `Cierre automático en ${secondsLeft}s`"
                    >
                        <Pause v-if="isPaused" class="size-2.5 animate-pulse text-amber-600 dark:text-amber-400" />
                        <Clock v-else class="size-2.5 text-slate-400" />
                        <span>{{ isPaused ? 'Pausa' : `${secondsLeft}s` }}</span>
                    </div>

                    <!-- Botón cerrar discreto -->
                    <button
                        type="button"
                        class="text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 p-1 rounded-md transition-colors cursor-pointer"
                        title="Cerrar notificación"
                        @click="removeToast(currentToast.id)"
                    >
                        <X class="size-3.5" />
                    </button>
                </div>

                <!-- LÍNEA DE TIEMPO / BARRA DE PROGRESO INFERIOR -->
                <div
                    v-if="currentToast.timerProgressBar && currentToast.timer > 0"
                    class="absolute bottom-0 left-0 right-0 h-[3.5px] bg-slate-100 dark:bg-slate-800 overflow-hidden"
                >
                    <div
                        class="h-full origin-left transition-[width] duration-75 ease-linear"
                        :class="progressBarColor(currentToast.icon)"
                        :style="{ width: `${progressPercent}%` }"
                    />
                </div>
            </div>
        </Transition>
    </div>
</template>
