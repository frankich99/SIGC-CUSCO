<script setup lang="ts">
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLogo from '@/components/AppLogo.vue';
import PeruGeoBadge from '@/components/PeruGeoBadge.vue';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import {
    ShieldCheck,
    GraduationCap,
    Globe,
    Award,
    Menu,
    ArrowLeft,
} from '@lucide/vue';
import { home } from '@/routes';
import GlobalToast from '@/components/GlobalToast.vue';

defineProps<{
    title?: string;
    description?: string;
}>();

const mobileMenuOpen = ref(false);
</script>

<template>
    <div
        class="flex min-h-screen flex-col justify-between bg-slate-50/70 text-slate-900 antialiased selection:bg-rose-900 selection:text-white dark:bg-slate-950 dark:text-slate-100"
    >
        <!-- TOP NAVBAR INSTITUCIONAL -->
        <header
            class="sticky top-0 z-40 w-full border-b border-slate-200/80 bg-white/95 shadow-xs backdrop-blur-md dark:border-slate-800 dark:bg-slate-950/90"
        >
            <div
                class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8"
            >
                <!-- Logotipo Principal UNSAAC Cusco -->
                <Link :href="home()" class="flex shrink-0 items-center gap-3">
                    <AppLogo />
                </Link>

                <!-- Navegación Central (Desktop) -->
                <nav
                    class="hidden items-center gap-6 text-xs font-bold text-slate-700 md:flex dark:text-slate-300"
                >
                    <Link
                        :href="home()"
                        class="flex items-center gap-1.5 transition-colors hover:text-rose-900 dark:hover:text-rose-400"
                    >
                        <Globe class="size-4 text-blue-600" />
                        <span>Portal Principal</span>
                    </Link>
                    <Link
                        href="/courses"
                        class="flex items-center gap-1.5 transition-colors hover:text-rose-900 dark:hover:text-rose-400"
                    >
                        <GraduationCap class="size-4 text-rose-800" />
                        <span>Capacitaciones</span>
                    </Link>
                    <Link
                        href="/certificates"
                        class="flex items-center gap-1.5 transition-colors hover:text-rose-900 dark:hover:text-rose-400"
                    >
                        <Award class="size-4 text-amber-600" />
                        <span>Certificados Digitales</span>
                    </Link>
                </nav>

                <!-- Acciones Derecha (Desktop) -->
                <div class="hidden items-center gap-3 sm:flex">
                    <Button
                        as-child
                        variant="outline"
                        size="sm"
                        class="border-slate-300 text-xs font-bold hover:bg-rose-50 hover:text-rose-900 dark:border-slate-700"
                    >
                        <Link :href="home()">
                            <ArrowLeft class="mr-1 size-3.5 text-rose-900" />
                            Volver al Inicio
                        </Link>
                    </Button>
                </div>

                <!-- Botón Menú Móvil (< md) -->
                <div class="flex md:hidden">
                    <Sheet v-model:open="mobileMenuOpen">
                        <SheetTrigger as-child>
                            <Button
                                variant="outline"
                                size="icon"
                                class="size-9 border-slate-300 text-slate-800"
                            >
                                <Menu class="size-5" />
                            </Button>
                        </SheetTrigger>
                        <SheetContent
                            side="left"
                            class="w-[300px] border-r border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-950"
                        >
                            <SheetTitle class="sr-only"
                                >Navegación Institucional</SheetTitle
                            >
                            <SheetHeader
                                class="border-b border-slate-200 pb-4 dark:border-slate-800"
                            >
                                <AppLogo />
                            </SheetHeader>
                            <div class="space-y-4 py-4 text-sm font-bold">
                                <Link
                                    :href="home()"
                                    class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-slate-800 hover:bg-rose-50"
                                    @click="mobileMenuOpen = false"
                                >
                                    <Globe class="size-4 text-blue-600" />
                                    <span>Portal Principal</span>
                                </Link>
                                <Link
                                    href="/courses"
                                    class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-slate-800 hover:bg-rose-50"
                                    @click="mobileMenuOpen = false"
                                >
                                    <GraduationCap
                                        class="size-4 text-rose-800"
                                    />
                                    <span>Capacitaciones</span>
                                </Link>
                                <Link
                                    href="/certificates"
                                    class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-slate-800 hover:bg-rose-50"
                                    @click="mobileMenuOpen = false"
                                >
                                    <Award class="size-4 text-amber-600" />
                                    <span>Certificados por DNI</span>
                                </Link>
                            </div>
                        </SheetContent>
                    </Sheet>
                </div>
            </div>
        </header>

        <!-- CONTENEDOR PRINCIPAL: TARJETA DE ACCESO INSTITUCIONAL (DIMENSIÓN APROBADA SM:MAX-W-SM) -->
        <main class="flex flex-1 items-center justify-center p-4 sm:p-6 lg:p-8">
            <div class="w-[92vw] sm:max-w-sm">
                <div
                    class="space-y-4 rounded-2xl border border-rose-200 bg-white p-5 shadow-2xl sm:p-6 dark:border-rose-900 dark:bg-slate-950"
                >
                    <!-- Encabezado de la Tarjeta con Identidad Cusco UNSAAC -->
                    <div class="space-y-1.5 text-center">
                        <div
                            class="inline-flex items-center gap-1.5 rounded-full border border-rose-200 bg-rose-50 px-3 py-1 text-[11px] font-black text-rose-900 shadow-2xs dark:border-rose-800 dark:bg-rose-950/60 dark:text-rose-300"
                        >
                            <ShieldCheck class="size-3.5 text-rose-800" />
                            <span>Acceso Institucional UNSAAC</span>
                        </div>
                        <h1
                            class="text-xl font-black tracking-tight text-slate-950 dark:text-white"
                        >
                            {{ title }}
                        </h1>
                        <p
                            v-if="description"
                            class="text-xs leading-relaxed font-medium text-slate-600 dark:text-slate-400"
                        >
                            {{ description }}
                        </p>
                    </div>

                    <!-- Ranura del Formulario (Login, Registro, etc.) -->
                    <slot />
                </div>
            </div>
        </main>

        <!-- PIE DE PÁGINA INSTITUCIONAL -->
        <footer
            class="border-t border-slate-200/80 bg-white/80 py-4 text-xs font-medium text-slate-600 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-400"
        >
            <div
                class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-3 px-4 sm:flex-row"
            >
                <div
                    class="flex items-center gap-1.5 font-bold text-slate-800 dark:text-slate-200"
                >
                    <GraduationCap class="size-4 text-rose-900" />
                    <span
                        >SIGC-CUSCO • Universidad Nacional de San Antonio Abad
                        del Cusco</span
                    >
                </div>
                <div class="flex items-center gap-4">
                    <PeruGeoBadge />
                    <span class="hidden md:inline">Cusco, Perú 2026</span>
                </div>
            </div>
        </footer>
        <GlobalToast />
    </div>
</template>
