<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    BookOpen,
    Folder,
    GraduationCap,
    LayoutGrid,
    Menu,
    PlusCircle,
    Globe,
    Search,
    ChevronDown,
    Award,
    QrCode,
    Sparkles,
    ShieldCheck,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
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
import { toUrl } from '@/lib/utils';
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
                class: 'bg-indigo-600 text-white font-bold',
            };
        case 'docente':
            return {
                label: 'Docente',
                class: 'bg-emerald-600 text-white font-bold',
            };
        default:
            return {
                label: 'Participante',
                class: 'bg-blue-600 text-white font-bold',
            };
    }
}
</script>

<template>
    <!-- BARRA ÚNICA DE NAVEGACIÓN (SIN NAVBAR DUPLICADO DEBAJO) -->
    <header class="sticky top-0 z-40 w-full border-b border-slate-200 dark:border-slate-800 bg-white/95 dark:bg-slate-950/95 backdrop-blur-md shadow-xs">
        <div class="mx-auto flex h-16 items-center justify-between px-4 sm:px-6 lg:px-8 max-w-7xl">
            <!-- Izquierda: Mobile toggle + Logo + Breadcrumb Integrado -->
            <div class="flex items-center gap-3">
                <!-- Mobile Menu Sheet -->
                <div class="lg:hidden">
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
                        <SheetContent side="left" class="w-[310px] p-6 bg-white dark:bg-slate-900 border-r border-slate-200 dark:border-slate-800">
                            <SheetTitle class="sr-only">Menú de navegación</SheetTitle>
                            <SheetHeader class="flex items-center gap-2 pb-4 border-b border-slate-200 dark:border-slate-800">
                                <AppLogo />
                            </SheetHeader>

                            <div class="flex flex-col h-full justify-between py-4 space-y-6">
                                <nav class="space-y-4">
                                    <!-- Sección Principal -->
                                    <div class="space-y-1">
                                        <div class="text-[11px] font-extrabold uppercase tracking-wider text-slate-700 dark:text-slate-300 px-3">
                                            Principal
                                        </div>
                                        <Link
                                            :href="dashboard()"
                                            class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-bold text-slate-900 dark:text-white hover:bg-slate-100 dark:hover:bg-slate-800"
                                            :class="isCurrentUrl(dashboard()) ? 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : ''"
                                        >
                                            <LayoutGrid class="size-4 text-emerald-600" />
                                            <span>Panel de Control</span>
                                        </Link>
                                    </div>

                                    <!-- Sección Capacitaciones -->
                                    <div class="space-y-1">
                                        <div class="text-[11px] font-extrabold uppercase tracking-wider text-slate-700 dark:text-slate-300 px-3">
                                            Gestión Académica
                                        </div>
                                        <Link
                                            href="/courses"
                                            class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-bold text-slate-900 dark:text-white hover:bg-slate-100 dark:hover:bg-slate-800"
                                            :class="isCurrentUrl('/courses') ? 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : ''"
                                        >
                                            <GraduationCap class="size-4 text-teal-600" />
                                            <span>Catálogo de Cursos</span>
                                        </Link>
                                        <Link
                                            v-if="user?.role === 'admin'"
                                            href="/courses/create"
                                            class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-bold text-emerald-700 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-950/60"
                                        >
                                            <PlusCircle class="size-4 text-emerald-600" />
                                            <span>Nueva Capacitación</span>
                                        </Link>
                                    </div>

                                    <!-- Sección Certificación & Enlaces -->
                                    <div class="space-y-1">
                                        <div class="text-[11px] font-extrabold uppercase tracking-wider text-slate-700 dark:text-slate-300 px-3">
                                            Servicios
                                        </div>
                                        <Link
                                            href="/#consulta-certificados"
                                            class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-bold text-slate-900 dark:text-white hover:bg-slate-100 dark:hover:bg-slate-800"
                                        >
                                            <Award class="size-4 text-indigo-600" />
                                            <span>Validar Certificado</span>
                                        </Link>
                                        <Link
                                            href="/"
                                            class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-bold text-slate-900 dark:text-white hover:bg-slate-100 dark:hover:bg-slate-800"
                                        >
                                            <Globe class="size-4 text-blue-600" />
                                            <span>Portal Público</span>
                                        </Link>
                                    </div>
                                </nav>

                                <div class="pt-4 border-t border-slate-200 dark:border-slate-800">
                                    <div class="flex items-center gap-3 px-3 py-2">
                                        <div class="text-xs">
                                            <div class="font-extrabold text-slate-900 dark:text-white">{{ user?.name }}</div>
                                            <div class="text-[10px] text-slate-700 dark:text-slate-300">{{ user?.email }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </SheetContent>
                    </Sheet>
                </div>

                <!-- Logo Principal -->
                <Link :href="dashboard()" class="flex items-center gap-x-2 shrink-0">
                    <AppLogo />
                </Link>

                <!-- Breadcrumb Integrado En La Misma Barra (Evita barra duplicada) -->
                <div v-if="breadcrumbs && breadcrumbs.length > 1" class="hidden md:flex items-center">
                    <span class="mx-3 text-slate-400 font-bold">/</span>
                    <Breadcrumbs :breadcrumbs="breadcrumbs" />
                </div>
            </div>

            <!-- Centro: Navegación Jerárquica con Ramificaciones y Submenús (Desktop) -->
            <nav class="hidden lg:flex items-center space-x-1">
                <!-- 1. Enlace Directo: Panel Principal -->
                <Link
                    :href="dashboard()"
                    class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-extrabold transition-all"
                    :class="isCurrentUrl(dashboard()) ? 'bg-emerald-100 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-200 shadow-xs' : 'text-slate-800 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-emerald-700'"
                >
                    <LayoutGrid class="size-4 text-emerald-600" />
                    <span>Panel Principal</span>
                </Link>

                <!-- 2. Ramificación Jerárquica: Capacitaciones (Dropdown con Sub-opciones) -->
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-extrabold transition-all cursor-pointer"
                            :class="isCoursesActive ? 'bg-emerald-100 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-200 shadow-xs' : 'text-slate-800 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-emerald-700'"
                        >
                            <GraduationCap class="size-4 text-teal-600" />
                            <span>Capacitaciones</span>
                            <ChevronDown class="size-3.5 text-slate-500" />
                        </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start" class="w-64 p-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xl rounded-xl">
                        <DropdownMenuLabel class="text-[11px] font-extrabold text-slate-700 dark:text-slate-300 uppercase tracking-wider px-2">
                            Gestión de Cursos
                        </DropdownMenuLabel>
                        <DropdownMenuItem as-child>
                            <Link href="/courses" class="flex items-start gap-2.5 p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer">
                                <GraduationCap class="size-4 text-emerald-600 mt-0.5" />
                                <div>
                                    <div class="text-xs font-bold text-slate-900 dark:text-white">Catálogo de Cursos</div>
                                    <div class="text-[11px] text-slate-700 dark:text-slate-300">Explorar programas y temarios</div>
                                </div>
                            </Link>
                        </DropdownMenuItem>
                        <DropdownMenuItem as-child>
                            <Link :href="`${dashboard()}#mis-cursos`" class="flex items-start gap-2.5 p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer">
                                <BookOpen class="size-4 text-blue-600 mt-0.5" />
                                <div>
                                    <div class="text-xs font-bold text-slate-900 dark:text-white">Mis Capacitaciones</div>
                                    <div class="text-[11px] text-slate-700 dark:text-slate-300">Cursos matriculados y dictados</div>
                                </div>
                            </Link>
                        </DropdownMenuItem>
                        <template v-if="user?.role === 'admin'">
                            <DropdownMenuSeparator class="my-1 border-slate-200 dark:border-slate-800" />
                            <DropdownMenuItem as-child>
                                <Link href="/courses/create" class="flex items-start gap-2.5 p-2 rounded-lg bg-emerald-50/80 dark:bg-emerald-950/40 hover:bg-emerald-100 cursor-pointer">
                                    <PlusCircle class="size-4 text-emerald-600 mt-0.5" />
                                    <div>
                                        <div class="text-xs font-extrabold text-emerald-900 dark:text-emerald-300">Nueva Capacitación</div>
                                        <div class="text-[11px] text-emerald-700 dark:text-emerald-400">Crear curso y abrir vacantes</div>
                                    </div>
                                </Link>
                            </DropdownMenuItem>
                        </template>
                    </DropdownMenuContent>
                </DropdownMenu>

                <!-- 3. Ramificación Jerárquica: Servicios & Certificados -->
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-extrabold transition-all cursor-pointer text-slate-800 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-emerald-700"
                        >
                            <Award class="size-4 text-indigo-600" />
                            <span>Certificación & QR</span>
                            <ChevronDown class="size-3.5 text-slate-500" />
                        </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start" class="w-64 p-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xl rounded-xl">
                        <DropdownMenuLabel class="text-[11px] font-extrabold text-slate-700 dark:text-slate-300 uppercase tracking-wider px-2">
                            Verificación de Documentos
                        </DropdownMenuLabel>
                        <DropdownMenuItem as-child>
                            <Link href="/#consulta-certificados" class="flex items-start gap-2.5 p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer">
                                <Award class="size-4 text-indigo-600 mt-0.5" />
                                <div>
                                    <div class="text-xs font-bold text-slate-900 dark:text-white">Validar Certificados</div>
                                    <div class="text-[11px] text-slate-700 dark:text-slate-300">Búsqueda oficial por DNI / Código</div>
                                </div>
                            </Link>
                        </DropdownMenuItem>
                        <DropdownMenuItem as-child>
                            <Link :href="dashboard()" class="flex items-start gap-2.5 p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer">
                                <QrCode class="size-4 text-emerald-600 mt-0.5" />
                                <div>
                                    <div class="text-xs font-bold text-slate-900 dark:text-white">Asistencia con Código QR</div>
                                    <div class="text-[11px] text-slate-700 dark:text-slate-300">Proyección y marcación en vivo</div>
                                </div>
                            </Link>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>

                <!-- 4. Portal Público -->
                <Link
                    href="/"
                    class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-extrabold transition-all text-slate-800 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-emerald-700"
                >
                    <Globe class="size-4 text-blue-600" />
                    <span>Portal Público</span>
                </Link>
            </nav>

            <!-- Derecha: Perfil de Usuario con Badge de Rol y Avatar -->
            <div class="flex items-center space-x-3">
                <!-- User Info & Role Badge Vivo -->
                <div class="hidden sm:flex flex-col items-end text-right">
                    <span class="text-xs font-black text-slate-900 dark:text-white leading-tight">
                        {{ auth.user?.name }}
                    </span>
                    <span
                        class="text-[10px] uppercase font-bold px-2.5 py-0.5 rounded-full mt-0.5 shadow-2xs"
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
                            class="relative size-10 rounded-full ring-2 ring-emerald-500/40 hover:ring-emerald-600 transition-all p-0.5 cursor-pointer"
                        >
                            <Avatar class="size-full overflow-hidden rounded-full">
                                <AvatarImage
                                    v-if="auth.user?.avatar"
                                    :src="auth.user.avatar"
                                    :alt="auth.user.name"
                                />
                                <AvatarFallback class="bg-emerald-700 text-white font-black text-xs">
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
