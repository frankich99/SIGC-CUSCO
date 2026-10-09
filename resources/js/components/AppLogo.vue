<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';

const name = usePage().props.name || 'SIGC-CUSCO';
</script>

<template>
    <div class="flex items-center gap-2 sm:gap-2.5">
        <div class="flex size-8.5 sm:size-9 items-center justify-center rounded-xl bg-gradient-to-tr from-[#701a31] to-[#800020] text-white shadow-xs shrink-0 ring-1 ring-rose-950/20">
            <AppLogoIcon class="size-4.5 sm:size-5 text-amber-300 stroke-[2.2]" />
        </div>
        <div class="flex flex-col text-left leading-tight shrink-0">
            <span class="text-xs sm:text-sm font-black tracking-tight text-slate-950 dark:text-white">
                {{ name }}
            </span>
            <span class="text-[9px] sm:text-[10px] font-extrabold uppercase tracking-widest text-amber-600 dark:text-amber-400">
                UNSAAC • Cusco
            </span>
        </div>
    </div>
</template>
