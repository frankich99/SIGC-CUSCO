<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { User, ShieldCheck, Palette, SlidersHorizontal } from '@lucide/vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';

const sidebarNavItems = [
    {
        title: 'Perfil',
        href: editProfile(),
        icon: User,
    },
    {
        title: 'Seguridad',
        href: editSecurity(),
        icon: ShieldCheck,
    },
    {
        title: 'Apariencia',
        href: editAppearance(),
        icon: Palette,
    },
];

const { isCurrentOrParentUrl } = useCurrentUrl();
</script>

<template>
    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8 space-y-8">
        <!-- Encabezado Institucional de Configuración -->
        <div
            class="flex flex-col justify-between gap-4 border-b border-slate-200/90 pb-6 sm:flex-row sm:items-center dark:border-slate-800"
        >
            <div class="flex items-center gap-3">
                <div
                    class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-[#3484a5] text-white shadow-xs"
                >
                    <SlidersHorizontal class="size-5 text-amber-300" />
                </div>
                <div>
                    <h1
                        class="text-xl font-black tracking-tight text-slate-950 sm:text-2xl dark:text-white"
                    >
                        Configuración del Sistema
                    </h1>
                    <p class="text-xs font-medium text-slate-600 sm:text-sm dark:text-slate-400">
                        Administra la apariencia visual, la identidad institucional, opciones de cuenta y seguridad
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span
                    class="rounded-full border border-sky-300 bg-sky-50 px-3 py-1 text-xs font-black text-sky-950 dark:border-[#296d88] dark:bg-[#153542] dark:text-sky-200"
                >
                    SIGC-MDSJ
                </span>
            </div>
        </div>

        <div class="flex flex-col gap-8 lg:flex-row lg:gap-10">
            <!-- Barra Lateral de Navegación (Tabs) con Soporte Móvil y Desktop -->
            <aside class="w-full shrink-0 lg:w-60">
                <nav
                    class="flex flex-row gap-1.5 overflow-x-auto pb-2 lg:flex-col lg:overflow-x-visible lg:pb-0"
                    aria-label="Configuración"
                >
                    <Link
                        v-for="item in sidebarNavItems"
                        :key="toUrl(item.href)"
                        :href="item.href"
                        class="flex items-center gap-2.5 rounded-xl px-4 py-2.5 text-xs font-bold transition-all whitespace-nowrap"
                        :class="
                            isCurrentOrParentUrl(item.href)
                                ? 'bg-[#3484a5] text-white shadow-xs dark:bg-[#3484a5] dark:text-white'
                                : 'text-slate-700 hover:bg-sky-50 hover:text-[#3484a5] dark:text-slate-300 dark:hover:bg-[#256883]/30 dark:hover:text-white'
                        "
                    >
                        <component :is="item.icon" class="size-4 shrink-0" />
                        <span>{{ item.title }}</span>
                    </Link>
                </nav>
            </aside>

            <!-- Contenedor Principal Amplio sin Restricciones Rígidas -->
            <main class="min-w-0 flex-1">
                <slot />
            </main>
        </div>
    </div>
</template>
