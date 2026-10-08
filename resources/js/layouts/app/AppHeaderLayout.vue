<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppHeader from '@/components/AppHeader.vue';
import AppShell from '@/components/AppShell.vue';
import PeruGeoBadge from '@/components/PeruGeoBadge.vue';
import { GraduationCap } from '@lucide/vue';
import { Toaster } from '@/components/ui/sonner';
import GlobalToast from '@/components/GlobalToast.vue';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppShell variant="header">
        <AppHeader :breadcrumbs="breadcrumbs" />
        <AppContent variant="header" class="min-h-[calc(100vh-8.5rem)]">
            <slot />
        </AppContent>

        <!-- PIE DE PÁGINA INSTITUCIONAL CON GEOLOCALIZACIÓN Y HORA PERÚ -->
        <footer class="mt-auto border-t border-slate-200/90 dark:border-slate-800 bg-white/90 dark:bg-slate-950/90 py-4 text-xs text-slate-600 dark:text-slate-400">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-3">
                <div class="flex items-center gap-2 font-bold text-slate-900 dark:text-slate-200">
                    <GraduationCap class="size-4 text-rose-900 dark:text-rose-400" />
                    <span>SIGC-CUSCO</span>
                    <span class="text-slate-400 dark:text-slate-600 font-normal">•</span>
                    <span class="text-xs font-semibold text-slate-600 dark:text-slate-400">Sistema Integral de Gestión de Capacitaciones</span>
                </div>
                <div class="flex items-center">
                    <PeruGeoBadge />
                </div>
            </div>
        </footer>

        <Toaster />
        <GlobalToast />
    </AppShell>
</template>
