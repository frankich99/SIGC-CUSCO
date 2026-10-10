<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';

const name = usePage().props.name || 'SIGC-CUSCO';
</script>

<template>
    <div class="flex items-center gap-2 sm:gap-2.5">
        <div
            class="flex size-8.5 shrink-0 items-center justify-center rounded-xl bg-gradient-to-tr from-[#701a31] to-[#800020] text-white shadow-xs ring-1 ring-rose-950/20 sm:size-9"
        >
            <AppLogoIcon
                class="size-4.5 stroke-[2.2] text-amber-300 sm:size-5"
            />
        </div>
        <div class="flex shrink-0 flex-col text-left leading-tight">
            <span
                class="text-xs font-black tracking-tight text-slate-950 sm:text-sm dark:text-white"
            >
                {{ name }}
            </span>
            <span
                class="text-[9px] font-extrabold tracking-widest text-amber-600 uppercase sm:text-[10px] dark:text-amber-400"
            >
                UNSAAC • Cusco
            </span>
        </div>
    </div>
</template>
