<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    BookOpen,
    GraduationCap,
    LayoutGrid,
    Menu,
    PlusCircle,
    Globe,
    ChevronDown,
    Award,
    QrCode,
    Sparkles,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import PeruGeoBadge from '@/components/PeruGeoBadge.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { getInitials } from '@/composables/useInitials';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

const props = withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const page = usePage();
const auth = computed(() => page.props.auth);
const user = computed(() => page.props.auth?.user);
const { isCurrentUrl } = useCurrentUrl();

const isCoursesActive = computed(() => {
    return isCurrentUrl('/courses') || isCurrentUrl('/courses/*');
});

function roleBadge(role?: string) {
    switch (role) {
        case 'admin':
            return {
                label: 'Administrador',
                class: 'bg-rose-900 text-white font-extrabold shadow-2xs',
            };
        case 'docente':
            return {
                label: 'Docente',
                class: 'bg-amber-700 text-white font-extrabold shadow-2xs',
            };
        default:
            return {
                label: 'Participante',
                class: 'bg-blue-800 text-white font-extrabold shadow-2xs',
            };
    }
}
</script>

<template>
    <!-- CABECERA INSTITUCIONAL RESPONSIVE - GRANATE IMPERIAL CUSCO -->
    <header class="sticky top-0 z-40 w-full border-b border-slate-200 dark:border-slate-800 bg-white/95 dark:bg-slate-950/95 backdrop-blur-md shadow-xs">
        <div class="mx-auto flex h-16 items-center justify-between px-3 sm:px-6 max-w-7xl gap-2 sm:gap-4">
            <!-- 1. Lado Izquierdo: Botón Móvil + Logo -->
            <div class="flex items-center gap-2 sm:gap-3 shrink-0">
                <!-- Mobile Trigger (< md) -->
                <div class="md:hidden">
                    <Sheet>
                        <SheetTrigger :as-child="true">
                            <Button
                                variant="outline"
                                size="icon"
                                class="h-9 w-9 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-white"
                            >
                                <Menu class="h-5 w-5" />
                            </Button>
                        </SheetTrigger>
                        <SheetContent side="left" class="w-[300px] p-6 bg-white dark:bg-slate-950 border-r border-slate-200 dark:border-slate-800">
                            <SheetTitle class="sr-only">Menú de Navegación</SheetTitle>
                            <SheetHeader class="flex items-center gap-2 pb-4 border-b border-slate-200 dark:border-slate-800">
                                <AppLogo />
                            </SheetHeader>

                            <div class="flex flex-col h-full justify-between py-4 space-y-6">
                                <nav class="space-y-4">
                                    <div class="space-y-1">
                                        <div class="text-[11px] font-extrabold uppercase tracking-wider text-rose-900 dark:text-rose-400 px-3">
                                            Navegación Principal
                                        </div>
                                        <Link
                                            :href="dashboard()"
                                            class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-bold text-slate-900 dark:text-white hover:bg-rose-50 dark:hover:bg-rose-950/40"
                                            :class="isCurrentUrl(dashboard()) ? 'bg-rose-100/80 text-rose-950 dark:bg-rose-950 dark:text-rose-200' : ''"
                                        >
                                            <LayoutGrid class="size-4 text-rose-800" />
                                            <span>Panel de Control</span>
                                        </Link>
                                    </div>

                                    <div class="space-y-1">
                                        <div class="text-[11px] font-extrabold uppercase tracking-wider text-rose-900 dark:text-rose-400 px-3">
                                            Capacitaciones
                                        </div>
                                        <Link
                                            href="/courses"
                                            class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-bold text-slate-900 dark:text-white hover:bg-rose-50 dark:hover:bg-rose-950/40"
                                            :class="isCurrentUrl('/courses') ? 'bg-rose-100/80 text-rose-950 dark:bg-rose-950 dark:text-rose-200' : ''"
                                        >
                                            <GraduationCap class="size-4 text-rose-800" />
                                            <span>Catálogo de Cursos</span>
                                        </Link>
                                        <Link
                                            v-if="user?.role === 'admin'"
                                            href="/courses/create"
                                            class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-bold text-amber-700 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-950/40"
                                        >
                                            <PlusCircle class="size-4 text-amber-600" />
                                            <span>Nueva Capacitación</span>
                                        </Link>
                                    </div>

                                    <div class="space-y-1">
                                        <div class="text-[11px] font-extrabold uppercase tracking-wider text-rose-900 dark:text-rose-400 px-3">
                                            Servicios al Ciudadano
                                        </div>
                                        <Link
                                            href="/#consulta-certificados"
                                            class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-bold text-slate-900 dark:text-white hover:bg-rose-50 dark:hover:bg-rose-950/40"
                                        >
                                            <Award class="size-4 text-amber-600" />
                                            <span>Validar Certificados</span>
                                        </Link>
                                        <Link
                                            href="/"
                                            class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-bold text-slate-900 dark:text-white hover:bg-rose-50 dark:hover:bg-rose-950/40"
                                        >
                                            <Globe class="size-4 text-blue-600" />
                                            <span>Portal Público</span>
                                        </Link>
                                    </div>
                                </nav>

                                <div class="pt-4 border-t border-slate-200 dark:border-slate-800 space-y-2">
                                    <PeruGeoBadge />
                                    <div class="text-xs text-slate-700 dark:text-slate-300 font-semibold px-2">
                                        Usuario: {{ user?.name }}
                                    </div>
                                </div>
                            </div>
                        </SheetContent>
                    </Sheet>
                </div>

                <!-- Logotipo Principal -->
                <Link :href="dashboard()" class="flex items-center gap-x-2 shrink-0">
                    <AppLogo />
                </Link>
            </div>

            <!-- 2. Centro: Navegación Jerárquica Desktop (Flexible, nunca desborda) -->
            <nav class="hidden md:flex items-center space-x-1 lg:space-x-2">
                <!-- Panel -->
                <Link
                    :href="dashboard()"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-black transition-all"
                    :class="isCurrentUrl(dashboard()) ? 'bg-rose-900 text-white shadow-xs' : 'text-slate-800 dark:text-slate-200 hover:bg-rose-50 dark:hover:bg-rose-950/40 hover:text-rose-900'"
                >
                    <LayoutGrid class="size-4" />
                    <span>Panel</span>
                </Link>

                <!-- Capacitaciones Dropdown -->
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-black transition-all cursor-pointer"
                            :class="isCoursesActive ? 'bg-rose-900 text-white shadow-xs' : 'text-slate-800 dark:text-slate-200 hover:bg-rose-50 dark:hover:bg-rose-950/40 hover:text-rose-900'"
                        >
                            <GraduationCap class="size-4 text-amber-600" />
                            <span>Capacitaciones</span>
                            <ChevronDown class="size-3.5 opacity-70" />
                        </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start" class="w-64 p-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xl rounded-xl">
                        <DropdownMenuLabel class="text-[11px] font-black text-rose-900 dark:text-rose-400 uppercase tracking-wider px-2">
                            Gestión Académica
                        </DropdownMenuLabel>
                        <DropdownMenuItem as-child>
                            <Link href="/courses" class="flex items-start gap-2.5 p-2 rounded-lg hover:bg-rose-50 dark:hover:bg-rose-950/40 cursor-pointer">
                                <GraduationCap class="size-4 text-rose-800 mt-0.5" />
                                <div>
                                    <div class="text-xs font-bold text-slate-900 dark:text-white">Catálogo de Cursos</div>
                                    <div class="text-[11px] text-slate-600 dark:text-slate-400">Ver temarios y vacantes</div>
                                </div>
                            </Link>
                        </DropdownMenuItem>
                        <DropdownMenuItem as-child>
                            <Link :href="`${dashboard()}#mis-cursos`" class="flex items-start gap-2.5 p-2 rounded-lg hover:bg-rose-50 dark:hover:bg-rose-950/40 cursor-pointer">
                                <BookOpen class="size-4 text-amber-700 mt-0.5" />
                                <div>
                                    <div class="text-xs font-bold text-slate-900 dark:text-white">Mis Cursos</div>
                                    <div class="text-[11px] text-slate-600 dark:text-slate-400">Asignados o matriculados</div>
                                </div>
                            </Link>
                        </DropdownMenuItem>
                        <template v-if="user?.role === 'admin'">
                            <DropdownMenuSeparator class="my-1 border-slate-200 dark:border-slate-800" />
                            <DropdownMenuItem as-child>
                                <Link href="/courses/create" class="flex items-start gap-2.5 p-2 rounded-lg bg-rose-50/80 dark:bg-rose-950/40 hover:bg-rose-100 cursor-pointer">
                                    <PlusCircle class="size-4 text-rose-800 mt-0.5" />
                                    <div>
                                        <div class="text-xs font-black text-rose-900 dark:text-rose-300">Nueva Capacitación</div>
                                        <div class="text-[11px] text-rose-700 dark:text-rose-400">Crear curso institucional</div>
                                    </div>
                                </Link>
                            </DropdownMenuItem>
                        </template>
                    </DropdownMenuContent>
                </DropdownMenu>

                <!-- Certificados Dropdown -->
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-black transition-all cursor-pointer text-slate-800 dark:text-slate-200 hover:bg-rose-50 dark:hover:bg-rose-950/40 hover:text-rose-900"
                        >
                            <Award class="size-4 text-amber-600" />
                            <span>Certificados & QR</span>
                            <ChevronDown class="size-3.5 opacity-70" />
                        </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start" class="w-64 p-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xl rounded-xl">
                        <DropdownMenuLabel class="text-[11px] font-black text-rose-900 dark:text-rose-400 uppercase tracking-wider px-2">
                            Acreditación Digital
                        </DropdownMenuLabel>
                        <DropdownMenuItem as-child>
                            <Link href="/#consulta-certificados" class="flex items-start gap-2.5 p-2 rounded-lg hover:bg-rose-50 dark:hover:bg-rose-950/40 cursor-pointer">
                                <Award class="size-4 text-amber-600 mt-0.5" />
                                <div>
                                    <div class="text-xs font-bold text-slate-900 dark:text-white">Validar Certificados</div>
                                    <div class="text-[11px] text-slate-600 dark:text-slate-400">Consulta por DNI o código</div>
                                </div>
                            </Link>
                        </DropdownMenuItem>
                        <DropdownMenuItem as-child>
                            <Link :href="dashboard()" class="flex items-start gap-2.5 p-2 rounded-lg hover:bg-rose-50 dark:hover:bg-rose-950/40 cursor-pointer">
                                <QrCode class="size-4 text-rose-800 mt-0.5" />
                                <div>
                                    <div class="text-xs font-bold text-slate-900 dark:text-white">Asistencia con QR</div>
                                    <div class="text-[11px] text-slate-600 dark:text-slate-400">Marcación y control en vivo</div>
                                </div>
                            </Link>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>

                <!-- Portal Público -->
                <Link
                    href="/"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-black transition-all text-slate-800 dark:text-slate-200 hover:bg-rose-50 dark:hover:bg-rose-950/40 hover:text-rose-900"
                >
                    <Globe class="size-4 text-blue-600" />
                    <span>Portal</span>
                </Link>
            </nav>

            <!-- 3. Lado Derecho: Geobadge + Rol + Avatar -->
            <div class="flex items-center gap-2 sm:gap-3 shrink-0">
                <!-- Geobadge visible en desktop -->
                <div class="hidden lg:block">
                    <PeruGeoBadge />
                </div>

                <!-- Rol del Usuario con Granate Imperial -->
                <div class="hidden sm:flex flex-col items-end text-right">
                    <span class="text-xs font-black text-slate-950 dark:text-white leading-tight">
                        {{ auth.user?.name }}
                    </span>
                    <span
                        class="text-[10px] uppercase font-black px-2.5 py-0.5 rounded-full mt-0.5 shadow-2xs"
                        :class="roleBadge(auth.user?.role).class"
                    >
                        {{ roleBadge(auth.user?.role).label }}
                    </span>
                </div>

                <!-- Avatar Dropdown Menu -->
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <button
                            type="button"
                            class="relative size-10 rounded-full ring-2 ring-rose-900/40 hover:ring-rose-900 transition-all p-0.5 cursor-pointer"
                        >
                            <Avatar class="size-full overflow-hidden rounded-full">
                                <AvatarImage
                                    v-if="auth.user?.avatar"
                                    :src="auth.user.avatar"
                                    :alt="auth.user.name"
                                />
                                <AvatarFallback class="bg-rose-900 text-white font-black text-xs">
                                    {{ getInitials(auth.user?.name) }}
                                </AvatarFallback>
                            </Avatar>
                        </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-60 p-1 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xl rounded-xl">
                        <UserMenuContent :user="auth.user" />
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </div>
    </header>
</template>
