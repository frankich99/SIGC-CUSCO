<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import { MapPin, Clock, Globe } from '@lucide/vue';
import { PERU_CONFIG, getCurrentPeruTime } from '@/lib/peru';

const currentPeruTime = ref(getCurrentPeruTime());
let intervalId: ReturnType<typeof setInterval> | null = null;

onMounted(() => {
    intervalId = setInterval(() => {
        currentPeruTime.value = getCurrentPeruTime();
    }, 1000);
});

onUnmounted(() => {
    if (intervalId) clearInterval(intervalId);
});
</script>

<template>
    <div class="inline-flex items-center gap-2 text-[11px] font-bold text-slate-800 dark:text-slate-200 bg-white/90 dark:bg-slate-900/90 px-3 py-1 rounded-full border border-slate-300 dark:border-slate-700 shadow-2xs">
        <div class="flex items-center gap-1 text-rose-900 dark:text-rose-300">
            <MapPin class="size-3.5 shrink-0" />
            <span>{{ PERU_CONFIG.institution.city }}, {{ PERU_CONFIG.country }}</span>
        </div>
        <span class="text-slate-300 dark:text-slate-700">|</span>
        <div class="flex items-center gap-1 text-blue-700 dark:text-blue-400 font-mono">
            <Clock class="size-3 shrink-0" />
            <span>{{ currentPeruTime }} (UTC-5)</span>
        </div>
    </div>
</template>
