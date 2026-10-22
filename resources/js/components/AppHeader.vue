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
    ShieldCheck,
    LogIn,
    UserPlus,
    Home,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import ThemeToggle from '@/components/ThemeToggle.vue';
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
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { getInitials } from '@/composables/useInitials';
import { useAuthModal } from '@/composables/useAuthModal';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const page = usePage();
const auth = computed(() => page.props.auth);
const user = computed(() => page.props.auth?.user);
const { isCurrentUrl } = useCurrentUrl();
const { openLogin, openRegister } = useAuthModal();
const isMobileMenuOpen = ref(false);

const isHomeActive = computed(() => {
    return isCurrentUrl('/');
});

const isCoursesActive = computed(() => {
    return isCurrentUrl('/courses') || isCurrentUrl('/courses/*');
});

const isCertificatesActive = computed(() => {
    return isCurrentUrl('/certificates') || isCurrentUrl('/certificates/*');
});

const isUsersActive = computed(() => {
    return isCurrentUrl('/users') || isCurrentUrl('/users/*');
});

const canCreateCourse = computed(() => {
    return user.value?.role === 'admin' || user.value?.role === 'docente';
});

function roleBadge(role?: string) {
    switch (role) {
        case 'admin':
            return {
                label: 'Administrador',
                class: 'bg-[#3484a5] text-white font-extrabold shadow-2xs',
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
    <!-- CABECERA INSTITUCIONAL RESPONSIVE - AZUL SAN JERÓNIMO MDSJ -->
    <header
        class="sticky top-0 z-40 w-full border-b border-slate-200/90 bg-white/95 shadow-xs backdrop-blur-md dark:border-slate-800 dark:bg-slate-950/95"
    >
        <div
            class="mx-auto flex h-16 w-full max-w-7xl min-w-0 items-center justify-between gap-2 px-3 sm:gap-4 sm:px-6 lg:px-8"
        >
            <!-- 1. GRUPO IZQUIERDO: BOTÓN MÓVIL + LOGO + NAVEGACIÓN DOCKING -->
            <div class="flex min-w-0 items-center gap-2 sm:gap-3 lg:gap-4">
                <!-- Mobile Trigger (< xl) -->
                <div class="xl:hidden">
                    <Sheet v-model:open="isMobileMenuOpen">
                        <SheetTrigger :as-child="true">
                            <Button
                                variant="outline"
                                size="icon"
                                class="h-9 w-9 border-slate-300 text-slate-800 dark:border-slate-700 dark:text-white"
                                aria-label="Abrir Menú de Navegación"
                            >
                                <Menu class="h-5 w-5" />
                            </Button>
                        </SheetTrigger>
                        <SheetContent
                            side="left"
                            class="w-[300px] border-r border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-950"
                        >
                            <SheetTitle class="sr-only">Menú de Navegación</SheetTitle>
                            <SheetDescription class="sr-only">
                                Navegación del sistema y accesos rápidos
                            </SheetDescription>
                            <SheetHeader
                                class="flex items-center gap-2 border-b border-slate-200 pb-4 dark:border-slate-800"
                            >
                                <AppLogo />
                            </SheetHeader>

                            <div class="flex h-full flex-col justify-between space-y-6 py-4">
                                <nav class="space-y-4">
                                    <!-- SI ES USUARIO AUTENTICADO: NAVEGACIÓN INTRANET -->
                                    <template v-if="user">
                                        <div class="space-y-1">
                                            <div
                                                class="px-3 text-[11px] font-extrabold tracking-wider text-[#3484a5] uppercase dark:text-[#88c0d7]"
                                            >
                                                Navegación
                                            </div>
                                            <Link
                                                :href="dashboard()"
                                                class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-bold text-slate-900 hover:bg-sky-50 dark:text-white dark:hover:bg-[#256883]/40"
                                                :class="
                                                    isCurrentUrl(dashboard())
                                                        ? 'bg-sky-100/80 text-sky-950 dark:bg-sky-950 dark:text-sky-200'
                                                        : ''
                                                "
                                                @click="isMobileMenuOpen = false"
                                            >
                                                <LayoutGrid class="size-4 text-[#296d88]" />
                                                <span>Panel de Control</span>
                                            </Link>
                                            <Link
                                                href="/courses"
                                                class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-bold text-slate-900 hover:bg-sky-50 dark:text-white dark:hover:bg-[#256883]/40"
                                                :class="
                                                    isCoursesActive
                                                        ? 'bg-sky-100/80 text-sky-950 dark:bg-sky-950 dark:text-sky-200'
                                                        : ''
                                                "
                                                @click="isMobileMenuOpen = false"
                                            >
                                                <GraduationCap class="size-4 text-[#296d88]" />
                                                <span>Capacitaciones</span>
                                            </Link>
                                            <Link
                                                href="/certificates"
                                                class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-bold text-slate-900 hover:bg-sky-50 dark:text-white dark:hover:bg-[#256883]/40"
                                                :class="
                                                    isCertificatesActive
                                                        ? 'bg-sky-100/80 text-sky-950 dark:bg-sky-950 dark:text-sky-200'
                                                        : ''
                                                "
                                                @click="isMobileMenuOpen = false"
                                            >
                                                <Award class="size-4 text-amber-600" />
                                                <span>Validar Certificados</span>
                                            </Link>
                                            <Link
                                                v-if="user?.role === 'admin'"
                                                href="/users"
                                                class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-bold text-slate-900 hover:bg-sky-50 dark:text-white dark:hover:bg-[#256883]/40"
                                                :class="
                                                    isUsersActive
                                                        ? 'bg-sky-100/80 text-sky-950 dark:bg-sky-950 dark:text-sky-200'
                                                        : ''
                                                "
                                                @click="isMobileMenuOpen = false"
                                            >
                                                <ShieldCheck class="size-4 text-emerald-600" />
                                                <span>Usuarios y Roles</span>
                                            </Link>
                                            <Link
                                                href="/"
                                                class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-bold text-slate-900 hover:bg-sky-50 dark:text-white dark:hover:bg-[#256883]/40"
                                                :class="
                                                    isHomeActive
                                                        ? 'bg-sky-100/80 text-sky-950 dark:bg-sky-950 dark:text-sky-200'
                                                        : ''
                                                "
                                                @click="isMobileMenuOpen = false"
                                            >
                                                <Globe class="size-4 text-blue-600" />
                                                <span>Portal Público</span>
                                            </Link>
                                        </div>
                                    </template>

                                    <!-- SI ES VISITANTE PÚBLICO (INVITADO): NAVEGACIÓN UNIFICADA LIMPIA -->
                                    <template v-else>
                                        <div class="space-y-1">
                                            <div
                                                class="px-3 text-[11px] font-extrabold tracking-wider text-[#3484a5] uppercase dark:text-[#88c0d7]"
                                            >
                                                Navegación
                                            </div>
                                            <Link
                                                href="/"
                                                class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-bold text-slate-900 hover:bg-sky-50 dark:text-white dark:hover:bg-[#256883]/40"
                                                :class="
                                                    isHomeActive
                                                        ? 'bg-sky-100/80 text-sky-950 dark:bg-sky-950 dark:text-sky-200'
                                                        : ''
                                                "
                                                @click="isMobileMenuOpen = false"
                                            >
                                                <Home class="size-4 text-[#296d88]" />
                                                <span>Inicio</span>
                                            </Link>
                                            <Link
                                                href="/courses"
                                                class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-bold text-slate-900 hover:bg-sky-50 dark:text-white dark:hover:bg-[#256883]/40"
                                                :class="
                                                    isCoursesActive
                                                        ? 'bg-sky-100/80 text-sky-950 dark:bg-sky-950 dark:text-sky-200'
                                                        : ''
                                                "
                                                @click="isMobileMenuOpen = false"
                                            >
                                                <GraduationCap class="size-4 text-[#296d88]" />
                                                <span>Capacitaciones</span>
                                            </Link>
                                            <Link
                                                href="/certificates"
                                                class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-bold text-slate-900 hover:bg-sky-50 dark:text-white dark:hover:bg-[#256883]/40"
                                                :class="
                                                    isCertificatesActive
                                                        ? 'bg-sky-100/80 text-sky-950 dark:bg-sky-950 dark:text-sky-200'
                                                        : ''
                                                "
                                                @click="isMobileMenuOpen = false"
                                            >
                                                <Award class="size-4 text-amber-600" />
                                                <span>Validar Certificados</span>
                                            </Link>
                                        </div>
                                    </template>
                                </nav>

                                <div
                                    class="flex items-center justify-between border-t border-slate-200 px-2 pt-3 pb-1 dark:border-slate-800"
                                >
                                    <span class="text-xs font-bold text-slate-700 dark:text-slate-300">
                                        Modo Visual
                                    </span>
                                    <ThemeToggle size="sm" :show-label="true" />
                                </div>

                                <div
                                    v-if="user"
                                    class="space-y-2.5 border-t border-slate-200 px-2 pt-4 dark:border-slate-800"
                                >
                                    <div class="flex items-center gap-2.5">
                                        <Avatar
                                            class="size-8.5 shrink-0 rounded-full ring-1 ring-[#3484a5]/30"
                                        >
                                            <AvatarImage
                                                v-if="user.avatar"
                                                :src="user.avatar"
                                                :alt="user.name"
                                            />
                                            <AvatarFallback
                                                class="bg-[#3484a5] text-xs font-black text-white"
                                            >
                                                {{ getInitials(user.name) }}
                                            </AvatarFallback>
                                        </Avatar>
                                        <div class="flex min-w-0 flex-col">
                                            <span
                                                class="truncate text-xs font-black text-slate-900 dark:text-white"
                                            >
                                                {{ user.name }}
                                            </span>
                                            <span class="truncate text-[10px] text-slate-500">
                                                {{ user.email }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="flex items-center justify-between pt-0.5">
                                        <span
                                            class="rounded-full px-2 py-0.5 text-[9px] font-black uppercase shadow-2xs"
                                            :class="roleBadge(user.role).class"
                                        >
                                            {{ roleBadge(user.role).label }}
                                        </span>
                                        <Link
                                            href="/logout"
                                            method="post"
                                            as="button"
                                            class="cursor-pointer text-xs font-bold text-[#296d88] hover:text-sky-950 dark:text-[#88c0d7]"
                                            @click="isMobileMenuOpen = false"
                                        >
                                            Cerrar sesión
                                        </Link>
                                    </div>
                                </div>
                                <div
                                    v-else
                                    class="space-y-2 border-t border-slate-200 px-2 pt-4 dark:border-slate-800"
                                >
                                    <div
                                        class="mb-1 text-[11px] font-extrabold tracking-wider text-[#3484a5] uppercase dark:text-[#88c0d7]"
                                    >
                                        Acceso al Sistema
                                    </div>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        class="w-full cursor-pointer justify-center text-xs font-bold"
                                        @click="
                                            isMobileMenuOpen = false;
                                            openLogin();
                                        "
                                    >
                                        <LogIn class="mr-1.5 size-3.5 text-[#3484a5]" />
                                        Iniciar Sesión
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        class="w-full cursor-pointer justify-center bg-[#3484a5] text-xs font-bold text-white shadow-xs hover:bg-[#256883]"
                                        @click="
                                            isMobileMenuOpen = false;
                                            openRegister();
                                        "
                                    >
                                        <UserPlus class="mr-1.5 size-3.5" />
                                        Registrarse
                                    </Button>
                                </div>
                            </div>
                        </SheetContent>
                    </Sheet>
                </div>

                <!-- Logotipo Principal -->
                <Link
                    :href="user ? dashboard() : '/'"
                    class="flex shrink-0 items-center gap-x-2 transition-opacity hover:opacity-95"
                >
                    <AppLogo />
                </Link>

                <!-- Separador vertical sutil institucional -->
                <div
                    class="mx-1 hidden h-6 w-px shrink-0 bg-slate-200 xl:block dark:bg-slate-800"
                />

                <!-- CASO A: INVITADO PÚBLICO (UNIFICADO, LIMPIO Y SIN ELEMENTOS INTERNOS) -->
                <nav
                    v-if="!user"
                    class="hidden shrink-0 items-center space-x-1 lg:space-x-1.5 xl:flex"
                >
                    <Link
                        href="/"
                        class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-black transition-all"
                        :class="
                            isHomeActive
                                ? 'bg-[#3484a5] text-white shadow-xs'
                                : 'text-slate-800 hover:bg-sky-50 hover:text-[#3484a5] dark:text-slate-200 dark:hover:bg-[#256883]/40'
                        "
                    >
                        <Home class="size-4" />
                        <span>Inicio</span>
                    </Link>

                    <Link
                        href="/courses"
                        class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-black transition-all"
                        :class="
                            isCoursesActive
                                ? 'bg-[#3484a5] text-white shadow-xs'
                                : 'text-slate-800 hover:bg-sky-50 hover:text-[#3484a5] dark:text-slate-200 dark:hover:bg-[#256883]/40'
                        "
                    >
                        <GraduationCap
                            class="size-4"
                            :class="
                                isCoursesActive
                                    ? 'text-amber-300'
                                    : 'text-[#296d88]'
                            "
                        />
                        <span>Capacitaciones</span>
                    </Link>

                    <Link
                        href="/certificates"
                        class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-black transition-all"
                        :class="
                            isCertificatesActive
                                ? 'bg-[#3484a5] text-white shadow-xs'
                                : 'text-slate-800 hover:bg-sky-50 hover:text-[#3484a5] dark:text-slate-200 dark:hover:bg-[#256883]/40'
                        "
                    >
                        <Award
                            class="size-4"
                            :class="
                                isCertificatesActive
                                    ? 'text-amber-300'
                                    : 'text-amber-600'
                            "
                        />
                        <span>Validar Certificados</span>
                    </Link>
                </nav>

                <!-- CASO B: USUARIO AUTENTICADO (PANEL, MIS CURSOS, ROLES, ETC.) -->
                <nav
                    v-else
                    class="hidden shrink-0 items-center space-x-1 lg:space-x-1.5 xl:flex"
                >
                    <!-- Panel -->
                    <Link
                        :href="dashboard()"
                        class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-black transition-all"
                        :class="
                            isCurrentUrl(dashboard())
                                ? 'bg-[#3484a5] text-white shadow-xs'
                                : 'text-slate-800 hover:bg-sky-50 hover:text-[#3484a5] dark:text-slate-200 dark:hover:bg-[#256883]/40'
                        "
                    >
                        <LayoutGrid class="size-4" />
                        <span>Panel</span>
                    </Link>

                    <!-- Capacitaciones Dropdown -->
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <button
                                type="button"
                                class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-black transition-all"
                                :class="
                                    isCoursesActive
                                        ? 'bg-[#3484a5] text-white shadow-xs'
                                        : 'text-slate-800 hover:bg-sky-50 hover:text-[#3484a5] dark:text-slate-200 dark:hover:bg-[#256883]/40'
                                "
                            >
                                <GraduationCap class="size-4 text-amber-600" />
                                <span>Capacitaciones</span>
                                <ChevronDown class="size-3.5 opacity-70" />
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent
                            align="start"
                            class="w-64 rounded-2xl border border-slate-200/90 bg-white/95 p-2 shadow-2xl backdrop-blur-md dark:border-slate-800 dark:bg-slate-900/95"
                        >
                            <DropdownMenuLabel
                                class="px-2 text-[11px] font-black tracking-wider text-[#3484a5] uppercase dark:text-[#88c0d7]"
                            >
                                Gestión Académica
                            </DropdownMenuLabel>
                            <DropdownMenuItem as-child>
                                <Link
                                    href="/courses"
                                    class="flex cursor-pointer items-start gap-2.5 rounded-xl p-2.5 transition-colors hover:bg-sky-50 dark:hover:bg-[#256883]/40"
                                >
                                    <GraduationCap
                                        class="mt-0.5 size-4 text-[#296d88] dark:text-[#88c0d7]"
                                    />
                                    <div>
                                        <div
                                            class="text-xs font-bold text-slate-900 dark:text-white"
                                        >
                                            Catálogo de Cursos
                                        </div>
                                        <div
                                            class="text-[11px] text-slate-600 dark:text-slate-400"
                                        >
                                            Ver temarios y vacantes
                                        </div>
                                    </div>
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem as-child>
                                <Link
                                    :href="`${dashboard()}#mis-cursos`"
                                    class="flex cursor-pointer items-start gap-2.5 rounded-xl p-2.5 transition-colors hover:bg-sky-50 dark:hover:bg-[#256883]/40"
                                >
                                    <BookOpen
                                        class="mt-0.5 size-4 text-amber-700 dark:text-amber-400"
                                    />
                                    <div>
                                        <div
                                            class="text-xs font-bold text-slate-900 dark:text-white"
                                        >
                                            Mis Cursos
                                        </div>
                                        <div
                                            class="text-[11px] text-slate-600 dark:text-slate-400"
                                        >
                                            Asignados o matriculados
                                        </div>
                                    </div>
                                </Link>
                            </DropdownMenuItem>
                            <template v-if="canCreateCourse">
                                <DropdownMenuSeparator
                                    class="my-1 border-slate-200 dark:border-slate-800"
                                />
                                <DropdownMenuItem as-child>
                                    <Link
                                        href="/courses/create"
                                        class="flex cursor-pointer items-start gap-2.5 rounded-xl bg-sky-50/80 p-2.5 transition-colors hover:bg-sky-100 dark:bg-sky-950/40 dark:hover:bg-[#256883]/70"
                                    >
                                        <PlusCircle
                                            class="mt-0.5 size-4 text-[#296d88] dark:text-[#88c0d7]"
                                        />
                                        <div>
                                            <div
                                                class="text-xs font-black text-[#3484a5] dark:text-[#badbe9]"
                                            >
                                                Agregar Curso
                                            </div>
                                            <div
                                                class="text-[11px] text-[#22596f] dark:text-[#88c0d7]"
                                            >
                                                Registrar curso institucional
                                            </div>
                                        </div>
                                    </Link>
                                </DropdownMenuItem>
                            </template>
                        </DropdownMenuContent>
                    </DropdownMenu>

                    <!-- Validar Certificados -->
                    <Link
                        href="/certificates"
                        class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-black transition-all"
                        :class="
                            isCertificatesActive
                                ? 'bg-[#3484a5] text-white shadow-xs'
                                : 'text-slate-800 hover:bg-sky-50 hover:text-[#3484a5] dark:text-slate-200 dark:hover:bg-[#256883]/40'
                        "
                        title="Validar y Consultar Certificados"
                    >
                        <Award
                            class="size-4"
                            :class="
                                isCertificatesActive
                                    ? 'text-amber-300'
                                    : 'text-amber-600'
                            "
                        />
                        <span><span class="hidden 2xl:inline">Validar </span>Certificados</span>
                    </Link>

                    <!-- Usuarios y Roles (Solo Admin) -->
                    <Link
                        v-if="user?.role === 'admin'"
                        href="/users"
                        class="inline-flex shrink-0 items-center gap-1.5 rounded-xl px-2.5 py-1.5 text-xs font-black transition-all"
                        :class="
                            isUsersActive
                                ? 'bg-[#3484a5] text-white shadow-xs'
                                : 'text-slate-800 hover:bg-sky-50 hover:text-[#3484a5] dark:text-slate-200 dark:hover:bg-[#256883]/40'
                        "
                        title="Administración de Usuarios y Roles Institucionales"
                    >
                        <ShieldCheck
                            class="size-4"
                            :class="
                                isUsersActive
                                    ? 'text-amber-300'
                                    : 'text-emerald-600'
                            "
                        />
                        <span>Usuarios<span class="hidden 2xl:inline"> y Roles</span></span>
                    </Link>

                    <!-- Ir al Portal Web -->
                    <Link
                        href="/"
                        class="inline-flex shrink-0 items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-black transition-all"
                        :class="
                            isHomeActive
                                ? 'bg-[#3484a5] text-white shadow-xs'
                                : 'text-slate-800 hover:bg-sky-50 hover:text-[#3484a5] dark:text-slate-200 dark:hover:bg-[#256883]/40'
                        "
                        title="Ir a la página principal pública de SIGC-MDSJ"
                    >
                        <Globe
                            class="size-4 shrink-0"
                            :class="isHomeActive ? 'text-amber-300' : 'text-blue-600 dark:text-blue-400'"
                        />
                        <span>Portal</span>
                    </Link>
                </nav>
            </div>

            <!-- 2. GRUPO DERECHO: CONMUTADOR DE TEMA + PERFIL DE USUARIO (SIN BOTÓN REDUNDANTE) -->
            <div class="flex shrink-0 items-center gap-2 sm:gap-3">
                <!-- Conmutador Interactivo Modo Oscuro / Claro -->
                <ThemeToggle />

                <!-- Divisor sutil entre controles de tema y usuario -->
                <div
                    v-if="auth.user"
                    class="hidden h-5 w-px shrink-0 bg-slate-200 sm:block dark:bg-slate-800"
                />

                <template v-if="auth.user">
                    <!-- Rol del Usuario con Azul San Jerónimo -->
                    <div
                        class="hidden min-w-0 flex-col items-end text-right md:flex"
                    >
                        <span
                            class="max-w-[100px] truncate text-xs leading-tight font-black text-slate-950 xl:max-w-[130px] 2xl:max-w-[180px] dark:text-white"
                            :title="auth.user.name"
                        >
                            {{ auth.user.name }}
                        </span>
                        <span
                            class="mt-0.5 rounded-full px-2 py-0.5 text-[9px] font-black whitespace-nowrap uppercase shadow-2xs"
                            :class="roleBadge(auth.user.role).class"
                        >
                            {{ roleBadge(auth.user.role).label }}
                        </span>
                    </div>

                    <!-- Avatar Dropdown Menu -->
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <button
                                type="button"
                                class="relative size-9 shrink-0 cursor-pointer rounded-full p-0.5 ring-2 ring-[#3484a5]/40 transition-all hover:ring-[#3484a5] sm:size-10"
                                aria-label="Menú de perfil de usuario"
                            >
                                <Avatar
                                    class="size-full overflow-hidden rounded-full"
                                >
                                    <AvatarImage
                                        v-if="auth.user.avatar"
                                        :src="auth.user.avatar"
                                        :alt="auth.user.name"
                                    />
                                    <AvatarFallback
                                        class="bg-[#3484a5] text-xs font-black text-white"
                                    >
                                        {{ getInitials(auth.user.name) }}
                                    </AvatarFallback>
                                </Avatar>
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent
                            align="end"
                            class="w-64 rounded-2xl border border-slate-200/90 bg-white/95 p-2 shadow-2xl backdrop-blur-md dark:border-slate-800 dark:bg-slate-900/95"
                        >
                            <UserMenuContent :user="auth.user" />
                        </DropdownMenuContent>
                    </DropdownMenu>
                </template>
                <template v-else>
                    <div class="flex items-center gap-1.5 sm:gap-2">
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            class="cursor-pointer px-2 text-xs font-bold text-slate-800 hover:text-[#3484a5] sm:px-3 dark:text-slate-200"
                            @click="openLogin"
                        >
                            <LogIn class="size-3.5 text-[#3484a5] sm:mr-1" />
                            <span class="hidden sm:inline">Iniciar Sesión</span>
                            <span class="sm:hidden">Ingresar</span>
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            class="hidden cursor-pointer bg-[#3484a5] px-2.5 text-xs font-bold text-white shadow-xs hover:bg-[#256883] min-[420px]:inline-flex sm:px-3"
                            @click="openRegister"
                        >
                            <UserPlus class="size-3.5 sm:mr-1" />
                            <span>Registrarse</span>
                        </Button>
                    </div>
                </template>
            </div>
        </div>
    </header>
</template>
