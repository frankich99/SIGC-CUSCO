<script setup lang="ts">
import { computed } from 'vue';
import { activeToasts, removeToast, type ActiveToast } from '@/lib/notify';
import { CheckCircle2, XCircle, AlertTriangle, Info, HelpCircle, X } from '@lucide/vue';

// Filtrar toasts por posición (por defecto top-end en la esquina superior derecha)
const topEndToasts = computed(() =>
    activeToasts.value.filter((t) => !t.position || t.position === 'top-end')
);
</script>

<template>
    <!-- CONTENEDOR FLOTANTE TOP-END (ESQUINA SUPERIOR DERECHA) -->
    <div
        aria-live="polite"
        class="fixed top-4 right-4 z-[9999] flex flex-col gap-2 pointer-events-none max-w-sm sm:max-w-md w-full sm:w-auto"
    >
        <TransitionGroup
            enter-active-class="transform ease-out duration-300 transition"
            enter-from-class="translate-x-8 opacity-0 scale-95"
            enter-to-class="translate-x-0 opacity-100 scale-100"
            leave-active-class="transition ease-in duration-200"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="translate-x-8 opacity-0 scale-95"
            move-class="transition-all duration-300"
        >
            <div
                v-for="toast in topEndToasts"
                :key="toast.id"
                role="status"
                class="pointer-events-auto relative overflow-hidden rounded-xl border bg-white/95 dark:bg-slate-900/95 backdrop-blur-md shadow-2xl p-3 pr-8 flex items-start gap-2.5 transition-all text-left border-slate-200 dark:border-slate-800 ring-1 ring-black/5"
            >
                <!-- ICONO ESTILO SWEETALERT2 -->
                <div class="shrink-0 mt-0.5">
                    <!-- Success -->
                    <div
                        v-if="toast.icon === 'success'"
                        class="size-6 rounded-full bg-emerald-100 dark:bg-emerald-950 text-emerald-600 dark:text-emerald-400 flex items-center justify-center ring-2 ring-emerald-500/20 shadow-xs"
                    >
                        <CheckCircle2 class="size-3.5 stroke-[2.5]" />
                    </div>

                    <!-- Error -->
                    <div
                        v-else-if="toast.icon === 'error'"
                        class="size-6 rounded-full bg-rose-100 dark:bg-rose-950 text-rose-600 dark:text-rose-400 flex items-center justify-center ring-2 ring-rose-500/20 shadow-xs"
                    >
                        <XCircle class="size-3.5 stroke-[2.5]" />
                    </div>

                    <!-- Warning -->
                    <div
                        v-else-if="toast.icon === 'warning'"
                        class="size-6 rounded-full bg-amber-100 dark:bg-amber-950 text-amber-600 dark:text-amber-400 flex items-center justify-center ring-2 ring-amber-500/20 shadow-xs"
                    >
                        <AlertTriangle class="size-3.5 stroke-[2.5]" />
                    </div>

                    <!-- Info -->
                    <div
                        v-else-if="toast.icon === 'info'"
                        class="size-6 rounded-full bg-sky-100 dark:bg-sky-950 text-sky-600 dark:text-sky-400 flex items-center justify-center ring-2 ring-sky-500/20 shadow-xs"
                    >
                        <Info class="size-3.5 stroke-[2.5]" />
                    </div>

                    <!-- Question / Default -->
                    <div
                        v-else
                        class="size-6 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 flex items-center justify-center"
                    >
                        <HelpCircle class="size-3.5 stroke-[2.5]" />
                    </div>
                </div>

                <!-- CONTENIDO DEL TOAST -->
                <div class="flex-1 min-w-0 pr-1">
                    <p class="text-xs font-black text-slate-900 dark:text-white leading-tight break-words">
                        {{ toast.title }}
                    </p>
                    <p
                        v-if="toast.text"
                        class="text-[11px] text-slate-600 dark:text-slate-400 mt-0.5 leading-snug break-words"
                    >
                        {{ toast.text }}
                    </p>
                </div>

                <!-- BOTÓN CERRAR DISCRETO -->
                <button
                    type="button"
                    class="absolute top-2.5 right-2 text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 p-1 rounded-md transition-colors cursor-pointer"
                    title="Cerrar notificación"
                    @click="removeToast(toast.id)"
                >
                    <X class="size-3" />
                </button>

                <!-- BARRA DE PROGRESO DE TEMPORIZADOR (SweetAlert2 timerProgressBar) -->
                <div
                    v-if="toast.timerProgressBar && toast.timer > 0"
                    class="absolute bottom-0 left-0 right-0 h-0.5 bg-slate-100 dark:bg-slate-800 overflow-hidden"
                >
                    <div
                        class="h-full origin-left transition-all"
                        :class="[
                            toast.icon === 'success' ? 'bg-emerald-600' : '',
                            toast.icon === 'error' ? 'bg-rose-600' : '',
                            toast.icon === 'warning' ? 'bg-amber-600' : '',
                            toast.icon === 'info' ? 'bg-sky-600' : '',
                            !['success', 'error', 'warning', 'info'].includes(toast.icon) ? 'bg-slate-600' : '',
                        ]"
                        :style="{
                            animation: `toast-progress ${toast.timer}ms linear forwards`,
                        }"
                    />
                </div>
            </div>
        </TransitionGroup>
    </div>
</template>

<style scoped>
@keyframes toast-progress {
    from {
        width: 100%;
    }
    to {
        width: 0%;
    }
}
</style>
