<script setup lang="ts">
import { computed } from 'vue';
import { Moon, Sun } from '@lucide/vue';
import { useAppearance } from '@/composables/useAppearance';

const props = withDefaults(
    defineProps<{
        size?: 'sm' | 'default' | 'lg';
        showLabel?: boolean;
    }>(),
    {
        size: 'default',
        showLabel: false,
    },
);

const { resolvedAppearance, updateAppearance } = useAppearance();

const isDark = computed(() => resolvedAppearance.value === 'dark');

function toggleTheme() {
    updateAppearance(isDark.value ? 'light' : 'dark');
}
</script>

<template>
    <button
        type="button"
        @click="toggleTheme"
        :aria-label="isDark ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro'"
        :title="isDark ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro'"
        class="group relative inline-flex cursor-pointer items-center justify-center rounded-xl border border-slate-200/90 bg-white/90 p-2 text-slate-700 shadow-2xs backdrop-blur-xs transition-all duration-200 hover:border-rose-900/40 hover:bg-rose-50 hover:text-rose-900 focus-visible:ring-2 focus-visible:ring-rose-900/40 focus-visible:outline-hidden dark:border-slate-800 dark:bg-slate-900/90 dark:text-slate-300 dark:hover:border-rose-700 dark:hover:bg-rose-950/50 dark:hover:text-rose-200"
        :class="[
            size === 'sm' ? 'h-8 px-2 text-xs' : 'h-9 px-2.5 text-sm',
            showLabel ? 'gap-2' : '',
        ]"
    >
        <!-- Icono Sol (Visible en modo oscuro para volver a claro) -->
        <Sun
            v-if="isDark"
            class="size-4.5 text-amber-400 transition-transform duration-300 group-hover:scale-110 group-hover:rotate-45"
        />

        <!-- Icono Luna (Visible en modo claro para pasar a oscuro) -->
        <Moon
            v-else
            class="size-4.5 text-slate-700 transition-transform duration-300 group-hover:scale-110 group-hover:-rotate-12 dark:text-slate-300"
        />

        <span
            v-if="showLabel"
            class="text-xs font-bold text-slate-800 select-none dark:text-slate-200"
        >
            {{ isDark ? 'Modo Claro' : 'Modo Oscuro' }}
        </span>
    </button>
</template>
