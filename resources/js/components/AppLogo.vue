<script setup lang="ts">
import { useBranding } from '@/composables/useBranding';

const { systemName, institutionName, logoUrl } = useBranding();
</script>

<template>
    <div class="flex items-center gap-2.5 sm:gap-3">
        <div
            class="flex size-9 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-white p-1 shadow-xs ring-1 ring-slate-950/15 transition-transform hover:scale-105 sm:size-10 dark:bg-slate-900 dark:ring-white/20"
        >
            <img
                :src="logoUrl"
                :alt="systemName"
                class="size-full object-contain"
                width="40"
                height="40"
                loading="eager"
            />
        </div>
        <div class="flex min-w-0 flex-col text-left leading-tight">
            <span
                class="truncate text-xs font-black tracking-tight text-slate-950 sm:text-sm dark:text-white"
            >
                {{ systemName }}
            </span>
            <span
                class="hidden max-w-[150px] truncate text-[9px] font-extrabold tracking-wider uppercase min-[440px]:block sm:max-w-xs md:max-w-sm lg:max-w-none sm:text-[10px]"
                :style="{ color: 'var(--institutional, #3484a5)' }"
            >
                {{ institutionName }}
            </span>
        </div>
    </div>
</template>
