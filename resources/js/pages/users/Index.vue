<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowUpDown,
    Award,
    BookOpen,
    Check,
    CheckCircle2,
    Clock,
    Filter,
    GraduationCap,
    IdCard,
    Mail,
    Phone,
    Plus,
    QrCode,
    Search,
    Shield,
    ShieldAlert,
    ShieldCheck,
    UserCheck,
    UserCog,
    Users,
    X,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { getInitials } from '@/composables/useInitials';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

interface UserItem {
    id: number;
    dni: string | null;
    name: string;
    paterno: string | null;
    materno: string | null;
    email: string;
    role: 'admin' | 'docente' | 'participante' | string;
    phone: string | null;
    created_at: string;
    enrollments_count?: number;
    taught_courses_count?: number;
}

interface PaginationLinks {
    url: string | null;
    label: string;
    active: boolean;
}

interface Props {
    users: {
        data: UserItem[];
        links: PaginationLinks[];
        current_page: number;
        last_page: number;
        total: number;
        from: number;
        to: number;
    };
    filters: {
        search: string;
        role: string;
    };
    stats: {
        total: number;
        admins: number;
        docentes: number;
        participantes: number;
    };
    permissionsMatrix: Array<{
        modulo: string;
        descripcion: string;
        admin: string;
        docente: string;
        participante: string;
    }>;
    roles: Array<{
        value: string;
        label: string;
        description: string;
    }>;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Panel Principal', href: '/dashboard' },
    { title: 'Usuarios y Roles', href: '/users' },
];

const activeTab = ref<'directorio' | 'matriz'>('directorio');
const searchQuery = ref(props.filters.search || '');
const selectedRoleFilter = ref(props.filters.role || 'all');

// Modal de cambio de rol
const isRoleModalOpen = ref(false);
const userToEdit = ref<UserItem | null>(null);
const newRole = ref<'admin' | 'docente' | 'participante'>('participante');
const isUpdatingRole = ref(false);

function applyFilters() {
    router.get(
        '/users',
        {
            search: searchQuery.value ? searchQuery.value : undefined,
            role:
                selectedRoleFilter.value !== 'all'
                    ? selectedRoleFilter.value
                    : undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

function clearFilters() {
    searchQuery.value = '';
    selectedRoleFilter.value = 'all';
    applyFilters();
}

function openRoleModal(user: UserItem) {
    userToEdit.value = user;
    newRole.value = (user.role as any) || 'participante';
    isRoleModalOpen.value = true;
}

function saveRoleChange() {
    if (!userToEdit.value) return;

    isUpdatingRole.value = true;
    router.put(
        `/users/${userToEdit.value.id}/role`,
        {
            role: newRole.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                isRoleModalOpen.value = false;
                userToEdit.value = null;
                toast.success(
                    'El rol institucional fue actualizado exitosamente.',
                );
            },
            onError: (errors) => {
                const msg =
                    Object.values(errors)[0] || 'Error al actualizar el rol.';
                toast.error(msg);
            },
            onFinish: () => {
                isUpdatingRole.value = false;
            },
        },
    );
}

function getFullName(u: UserItem): string {
    const parts = [u.name, u.paterno, u.materno].filter(Boolean);
    return parts.length > 0 ? parts.join(' ') : u.name;
}

function roleBadge(role: string) {
    switch (role) {
        case 'admin':
            return {
                label: 'Administrador',
                class: 'bg-rose-950 text-white font-extrabold border-rose-800 shadow-2xs',
                icon: ShieldCheck,
            };
        case 'docente':
            return {
                label: 'Docente / Instructor',
                class: 'bg-amber-700 text-white font-extrabold border-amber-600 shadow-2xs',
                icon: GraduationCap,
            };
        default:
            return {
                label: 'Participante / Alumno',
                class: 'bg-blue-800 text-white font-extrabold border-blue-700 shadow-2xs',
                icon: UserCheck,
            };
    }
}

function formatDate(dateStr?: string): string {
    if (!dateStr) return '-';
    try {
        const d = new Date(dateStr);
        return d.toLocaleDateString('es-PE', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
        });
    } catch {
        return dateStr;
    }
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Gestión de Usuarios y Roles - SIGC-CUSCO" />

        <div
            class="mx-auto w-full max-w-7xl min-w-0 space-y-8 overflow-x-hidden px-4 py-6 sm:px-6 sm:py-8 lg:px-8"
        >
            <!-- HEADER DE SECCIÓN INSTITUCIONAL -->
            <div
                class="flex flex-col justify-between gap-4 border-b border-slate-200/90 pb-5 sm:flex-row sm:items-center dark:border-slate-800"
            >
                <div class="space-y-1">
                    <div class="flex items-center gap-2">
                        <div
                            class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-tr from-[#701a31] to-[#800020] text-white shadow-sm"
                        >
                            <ShieldCheck class="size-5 text-amber-300" />
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h1
                                    class="text-xl font-black tracking-tight text-slate-950 sm:text-2xl dark:text-white"
                                >
                                    Usuarios, Roles y Permisos
                                </h1>
                                <span
                                    class="hidden items-center rounded-full border border-rose-300 bg-rose-100 px-2.5 py-0.5 text-[10px] font-black tracking-wider text-rose-950 uppercase sm:inline-flex dark:border-rose-800 dark:bg-rose-950/80 dark:text-rose-200"
                                >
                                    Admin Oficial
                                </span>
                            </div>
                            <p
                                class="text-xs font-medium text-slate-600 sm:text-sm dark:text-slate-400"
                            >
                                Control de accesos institucionales, reasignación
                                de roles y matriz de capacidades SIGC-CUSCO.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- SELECTOR DE PESTAÑAS OVALADAS -->
                <div
                    class="inline-flex shrink-0 self-start rounded-2xl border border-slate-200 bg-slate-100 p-1 shadow-2xs sm:self-auto dark:border-slate-800 dark:bg-slate-900"
                >
                    <button
                        type="button"
                        class="flex cursor-pointer items-center gap-2 rounded-xl px-3.5 py-1.5 text-xs font-black transition-all"
                        :class="
                            activeTab === 'directorio'
                                ? 'bg-white text-rose-950 shadow-xs dark:bg-slate-800 dark:text-rose-200'
                                : 'text-slate-600 hover:text-slate-900 dark:text-slate-400'
                        "
                        @click="activeTab = 'directorio'"
                    >
                        <Users class="size-3.5" />
                        <span>Directorio y Roles</span>
                    </button>
                    <button
                        type="button"
                        class="flex cursor-pointer items-center gap-2 rounded-xl px-3.5 py-1.5 text-xs font-black transition-all"
                        :class="
                            activeTab === 'matriz'
                                ? 'bg-white text-rose-950 shadow-xs dark:bg-slate-800 dark:text-rose-200'
                                : 'text-slate-600 hover:text-slate-900 dark:text-slate-400'
                        "
                        @click="activeTab = 'matriz'"
                    >
                        <Shield class="size-3.5 text-amber-600" />
                        <span>Matriz de Permisos</span>
                    </button>
                </div>
            </div>

            <!-- TARJETAS DE MÉTRICAS INSTITUCIONALES (KPIs) -->
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <Card
                    class="space-y-2 rounded-2xl border border-slate-200/90 bg-white p-5 shadow-xs dark:border-slate-800/90 dark:bg-slate-900/60"
                >
                    <div class="flex items-center justify-between">
                        <span
                            class="text-xs font-bold text-slate-600 dark:text-slate-400"
                            >Total Usuarios</span
                        >
                        <div
                            class="flex size-8 items-center justify-center rounded-xl bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300"
                        >
                            <Users class="size-4" />
                        </div>
                    </div>
                    <div
                        class="text-2xl font-black text-slate-950 dark:text-white"
                    >
                        {{ stats.total }}
                    </div>
                    <p class="text-[11px] text-slate-600 dark:text-slate-400">
                        Registrados en la plataforma
                    </p>
                </Card>

                <Card
                    class="space-y-2 rounded-2xl border border-rose-200 bg-rose-50/40 p-5 shadow-xs dark:border-rose-900/40 dark:bg-rose-950/20"
                >
                    <div class="flex items-center justify-between">
                        <span
                            class="text-xs font-bold text-rose-950 dark:text-rose-300"
                            >Administradores</span
                        >
                        <div
                            class="flex size-8 items-center justify-center rounded-xl bg-rose-900 text-white"
                        >
                            <ShieldCheck class="size-4 text-amber-300" />
                        </div>
                    </div>
                    <div
                        class="text-2xl font-black text-rose-950 dark:text-rose-200"
                    >
                        {{ stats.admins }}
                    </div>
                    <p class="text-[11px] text-rose-800 dark:text-rose-400">
                        Control total del sistema
                    </p>
                </Card>

                <Card
                    class="space-y-2 rounded-2xl border border-amber-200 bg-amber-50/40 p-5 shadow-xs dark:border-amber-900/40 dark:bg-amber-950/20"
                >
                    <div class="flex items-center justify-between">
                        <span
                            class="text-xs font-bold text-amber-950 dark:text-amber-300"
                            >Docentes / Ponentes</span
                        >
                        <div
                            class="flex size-8 items-center justify-center rounded-xl bg-amber-700 text-white"
                        >
                            <GraduationCap class="size-4 text-white" />
                        </div>
                    </div>
                    <div
                        class="text-2xl font-black text-amber-950 dark:text-amber-200"
                    >
                        {{ stats.docentes }}
                    </div>
                    <p class="text-[11px] text-amber-800 dark:text-amber-400">
                        Gestores de capacitaciones
                    </p>
                </Card>

                <Card
                    class="space-y-2 rounded-2xl border border-blue-200 bg-blue-50/40 p-5 shadow-xs dark:border-blue-900/40 dark:bg-blue-950/20"
                >
                    <div class="flex items-center justify-between">
                        <span
                            class="text-xs font-bold text-blue-950 dark:text-blue-300"
                            >Participantes</span
                        >
                        <div
                            class="flex size-8 items-center justify-center rounded-xl bg-blue-800 text-white"
                        >
                            <UserCheck class="size-4 text-white" />
                        </div>
                    </div>
                    <div
                        class="text-2xl font-black text-blue-950 dark:text-blue-200"
                    >
                        {{ stats.participantes }}
                    </div>
                    <p class="text-[11px] text-blue-800 dark:text-blue-400">
                        Alumnos y beneficiarios
                    </p>
                </Card>
            </div>

            <!-- CONTENIDO PESTAÑA 1: DIRECTORIO DE USUARIOS Y ASIGNACIÓN DE ROLES -->
            <div v-if="activeTab === 'directorio'" class="space-y-6">
                <!-- Barra de Búsqueda y Filtros Unificada -->
                <div class="flex flex-col gap-3 sm:flex-row">
                    <div class="relative flex-1">
                        <Search
                            class="absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-slate-400"
                        />
                        <Input
                            v-model="searchQuery"
                            placeholder="Buscar por DNI, nombres, apellidos o correo electrónico institucional..."
                            class="h-10 rounded-xl border-slate-200 bg-white pl-10 text-xs font-medium sm:text-sm dark:border-slate-800 dark:bg-slate-900"
                            @keydown.enter="applyFilters"
                        />
                    </div>

                    <div class="w-full shrink-0 sm:w-56">
                        <Select
                            v-model="selectedRoleFilter"
                            @update:model-value="applyFilters"
                        >
                            <SelectTrigger
                                class="h-10 w-full rounded-xl border-slate-200 bg-white text-xs font-bold dark:border-slate-800 dark:bg-slate-900"
                            >
                                <SelectValue placeholder="Filtrar por Rol" />
                            </SelectTrigger>
                            <SelectContent class="rounded-2xl">
                                <SelectItem value="all"
                                    >Todos los Roles</SelectItem
                                >
                                <SelectItem value="admin"
                                    >Administrador</SelectItem
                                >
                                <SelectItem value="docente"
                                    >Docente / Instructor</SelectItem
                                >
                                <SelectItem value="participante"
                                    >Participante / Alumno</SelectItem
                                >
                            </SelectContent>
                        </Select>
                    </div>

                    <Button
                        type="button"
                        class="h-10 cursor-pointer rounded-xl bg-rose-900 px-5 text-xs font-extrabold text-white shadow-xs hover:bg-rose-950"
                        @click="applyFilters"
                    >
                        Filtrar
                    </Button>

                    <Button
                        v-if="filters.search || filters.role !== 'all'"
                        variant="outline"
                        type="button"
                        class="h-10 cursor-pointer rounded-xl border-slate-300 text-xs font-bold"
                        @click="clearFilters"
                    >
                        Limpiar
                    </Button>
                </div>

                <!-- Tabla de Usuarios con Bordes Ovalados y Responsive -->
                <Card
                    class="overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
                >
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead
                                class="border-b border-slate-200 bg-slate-50/90 font-extrabold tracking-wider text-slate-600 uppercase dark:border-slate-800 dark:bg-slate-950/60 dark:text-slate-400"
                            >
                                <tr>
                                    <th class="px-5 py-3.5">
                                        Usuario e Identificación
                                    </th>
                                    <th class="px-4 py-3.5">
                                        Rol Institucional
                                    </th>
                                    <th class="px-4 py-3.5">Contacto</th>
                                    <th class="px-4 py-3.5 text-center">
                                        Actividad
                                    </th>
                                    <th class="px-4 py-3.5 text-center">
                                        Registro
                                    </th>
                                    <th class="px-5 py-3.5 text-right">
                                        Acción
                                    </th>
                                </tr>
                            </thead>
                            <tbody
                                class="divide-y divide-slate-100 dark:divide-slate-800/80"
                            >
                                <tr
                                    v-for="u in users.data"
                                    :key="u.id"
                                    class="transition-colors hover:bg-slate-50/70 dark:hover:bg-slate-800/40"
                                >
                                    <!-- Usuario e Identificación -->
                                    <td class="px-5 py-4">
                                        <div class="flex items-center gap-3">
                                            <Avatar
                                                class="size-9 shrink-0 rounded-full ring-1 ring-slate-200 dark:ring-slate-700"
                                            >
                                                <AvatarFallback
                                                    class="bg-rose-900 text-xs font-black text-white"
                                                >
                                                    {{ getInitials(u.name) }}
                                                </AvatarFallback>
                                            </Avatar>
                                            <div class="min-w-0">
                                                <div
                                                    class="truncate text-xs font-black text-slate-900 sm:text-sm dark:text-white"
                                                >
                                                    {{ getFullName(u) }}
                                                </div>
                                                <div
                                                    class="mt-0.5 flex items-center gap-2 text-[11px] text-slate-600 dark:text-slate-400"
                                                >
                                                    <span
                                                        v-if="u.dni"
                                                        class="rounded-md bg-slate-100 px-1.5 py-0.5 font-mono font-bold text-slate-700 dark:bg-slate-800 dark:text-slate-300"
                                                    >
                                                        DNI: {{ u.dni }}
                                                    </span>
                                                    <span
                                                        v-else
                                                        class="text-slate-400 italic"
                                                        >Sin DNI
                                                        registrado</span
                                                    >
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Rol Institucional -->
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <Badge
                                            :class="roleBadge(u.role).class"
                                            class="rounded-full border px-2.5 py-1 text-[10px] font-black tracking-wide uppercase"
                                        >
                                            <component
                                                :is="roleBadge(u.role).icon"
                                                class="mr-1 size-3"
                                            />
                                            <span>{{
                                                roleBadge(u.role).label
                                            }}</span>
                                        </Badge>
                                    </td>

                                    <!-- Contacto -->
                                    <td
                                        class="px-4 py-4 whitespace-nowrap text-slate-600 dark:text-slate-400"
                                    >
                                        <div class="space-y-0.5">
                                            <div
                                                class="flex max-w-[200px] items-center gap-1.5 truncate font-medium"
                                                :title="u.email"
                                            >
                                                <Mail
                                                    class="size-3 shrink-0 text-slate-400"
                                                />
                                                <span>{{ u.email }}</span>
                                            </div>
                                            <div
                                                v-if="u.phone"
                                                class="flex items-center gap-1.5 text-[11px]"
                                            >
                                                <Phone
                                                    class="size-3 shrink-0 text-slate-400"
                                                />
                                                <span>{{ u.phone }}</span>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Actividad Institucional -->
                                    <td
                                        class="px-4 py-4 text-center whitespace-nowrap"
                                    >
                                        <div
                                            v-if="u.role === 'docente'"
                                            class="font-bold text-amber-800 dark:text-amber-400"
                                        >
                                            {{ u.taught_courses_count ?? 0 }}
                                            capacitaciones dictadas
                                        </div>
                                        <div
                                            v-else-if="u.role === 'admin'"
                                            class="font-black text-rose-900 dark:text-rose-400"
                                        >
                                            Administrador General
                                        </div>
                                        <div
                                            v-else
                                            class="font-bold text-blue-800 dark:text-blue-400"
                                        >
                                            {{ u.enrollments_count ?? 0 }}
                                            cursos matriculados
                                        </div>
                                    </td>

                                    <!-- Fecha de Registro -->
                                    <td
                                        class="px-4 py-4 text-center font-mono text-[11px] whitespace-nowrap text-slate-500"
                                    >
                                        {{ formatDate(u.created_at) }}
                                    </td>

                                    <!-- Acción: Gestionar Rol -->
                                    <td
                                        class="px-5 py-4 text-right whitespace-nowrap"
                                    >
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            class="cursor-pointer rounded-xl border-slate-300 text-xs font-bold hover:border-rose-300 hover:bg-rose-50 dark:hover:bg-rose-950/40"
                                            @click="openRoleModal(u)"
                                        >
                                            <UserCog
                                                class="mr-1 size-3.5 text-rose-800"
                                            />
                                            <span>Cambiar Rol</span>
                                        </Button>
                                    </td>
                                </tr>

                                <tr v-if="users.data.length === 0">
                                    <td
                                        colspan="6"
                                        class="space-y-2 p-12 text-center text-slate-500"
                                    >
                                        <Users
                                            class="mx-auto size-10 text-slate-300 dark:text-slate-700"
                                        />
                                        <p class="text-sm font-bold">
                                            No se encontraron usuarios
                                            registrados con los filtros
                                            seleccionados.
                                        </p>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            class="mt-2 cursor-pointer rounded-xl"
                                            @click="clearFilters"
                                        >
                                            Limpiar Filtros
                                        </Button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación -->
                    <div
                        v-if="users.links.length > 3"
                        class="flex items-center justify-between border-t border-slate-100 p-4 text-xs dark:border-slate-800"
                    >
                        <div class="font-medium text-slate-500">
                            Mostrando {{ users.from }} a {{ users.to }} de
                            {{ users.total }} usuarios
                        </div>
                        <div class="flex items-center gap-1">
                            <template v-for="(link, i) in users.links" :key="i">
                                <Button
                                    v-if="link.url"
                                    as-child
                                    size="sm"
                                    :variant="
                                        link.active ? 'default' : 'outline'
                                    "
                                    class="h-8 min-w-8 rounded-lg px-2 text-xs font-bold"
                                    :class="
                                        link.active
                                            ? 'bg-rose-900 text-white'
                                            : ''
                                    "
                                >
                                    <Link
                                        :href="link.url"
                                        preserve-scroll
                                        v-html="link.label"
                                    />
                                </Button>
                                <span
                                    v-else
                                    class="flex h-8 min-w-8 items-center justify-center px-2 text-xs text-slate-300"
                                    v-html="link.label"
                                />
                            </template>
                        </div>
                    </div>
                </Card>
            </div>

            <!-- CONTENIDO PESTAÑA 2: MATRIZ OFICIAL DE ROLES Y PERMISOS -->
            <div v-else-if="activeTab === 'matriz'" class="space-y-6">
                <!-- Tarjeta de Introducción Institucional -->
                <Card
                    class="space-y-3 rounded-2xl border-2 border-rose-900/20 bg-gradient-to-r from-rose-50/70 via-white to-amber-50/40 p-6 shadow-xs dark:border-rose-900/40 dark:from-slate-900 dark:via-slate-900 dark:to-slate-950"
                >
                    <div class="flex items-center gap-2">
                        <ShieldCheck
                            class="size-5 text-rose-900 dark:text-rose-400"
                        />
                        <h2
                            class="text-base font-black text-slate-950 dark:text-white"
                        >
                            Políticas de Control de Acceso Basadas en Roles
                            (RBAC) - SIGC-CUSCO
                        </h2>
                    </div>
                    <p
                        class="text-xs leading-relaxed font-medium text-slate-600 dark:text-slate-400"
                    >
                        El Sistema Integral de Gestión de Capacitaciones cuenta
                        con tres roles institucionales rigurosamente definidos
                        conforme al modelo de seguridad y proceso de desarrollo
                        académico. A continuación se desglosan los privilegios
                        asignados a cada función.
                    </p>
                </Card>

                <!-- Resumen Visual de los 3 Roles -->
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <Card
                        class="space-y-3 rounded-2xl border border-rose-200 bg-white p-5 shadow-xs dark:border-rose-900/60 dark:bg-slate-900"
                    >
                        <div class="flex items-center gap-2.5">
                            <div
                                class="flex size-8 items-center justify-center rounded-xl bg-rose-900 text-white"
                            >
                                <ShieldCheck class="size-4 text-amber-300" />
                            </div>
                            <div>
                                <h3
                                    class="text-sm font-black text-slate-900 dark:text-white"
                                >
                                    Administrador
                                </h3>
                                <span
                                    class="text-[10px] font-bold text-rose-800 uppercase dark:text-rose-400"
                                    >Nivel Superior</span
                                >
                            </div>
                        </div>
                        <p
                            class="text-xs leading-relaxed text-slate-600 dark:text-slate-400"
                        >
                            Supervisa globalmente todas las capacitaciones de la
                            institución, administra roles de usuario, emite
                            actas y certificados oficiales, y posee control
                            absoluto sobre los cursos y padrones.
                        </p>
                    </Card>

                    <Card
                        class="space-y-3 rounded-2xl border border-amber-200 bg-white p-5 shadow-xs dark:border-amber-900/60 dark:bg-slate-900"
                    >
                        <div class="flex items-center gap-2.5">
                            <div
                                class="flex size-8 items-center justify-center rounded-xl bg-amber-700 text-white"
                            >
                                <GraduationCap class="size-4 text-white" />
                            </div>
                            <div>
                                <h3
                                    class="text-sm font-black text-slate-900 dark:text-white"
                                >
                                    Docente / Instructor
                                </h3>
                                <span
                                    class="text-[10px] font-bold text-amber-700 uppercase dark:text-amber-400"
                                    >Facultad Académica</span
                                >
                            </div>
                        </div>
                        <p
                            class="text-xs leading-relaxed text-slate-600 dark:text-slate-400"
                        >
                            Imparte los cursos asignados, proyecta códigos QR en
                            pantalla para asistencia en tiempo real, registra
                            asistencia manual, califica alumnos y cierra actas
                            de sus cursos a cargo.
                        </p>
                    </Card>

                    <Card
                        class="space-y-3 rounded-2xl border border-blue-200 bg-white p-5 shadow-xs dark:border-blue-900/60 dark:bg-slate-900"
                    >
                        <div class="flex items-center gap-2.5">
                            <div
                                class="flex size-8 items-center justify-center rounded-xl bg-blue-800 text-white"
                            >
                                <UserCheck class="size-4 text-white" />
                            </div>
                            <div>
                                <h3
                                    class="text-sm font-black text-slate-900 dark:text-white"
                                >
                                    Participante / Alumno
                                </h3>
                                <span
                                    class="text-[10px] font-bold text-blue-700 uppercase dark:text-blue-400"
                                    >Beneficiario Formativo</span
                                >
                            </div>
                        </div>
                        <p
                            class="text-xs leading-relaxed text-slate-600 dark:text-slate-400"
                        >
                            Se inscribe en cursos con vacantes, porta su
                            Credencial QR para el escaneo presencial en aula,
                            consulta su porcentaje de asistencia y récord de
                            notas, y descarga sus diplomas verificados.
                        </p>
                    </Card>
                </div>

                <!-- Tabla Comparativa Detallada de Permisos por Módulo -->
                <Card
                    class="overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
                >
                    <CardHeader
                        class="border-b border-slate-100 p-5 dark:border-slate-800"
                    >
                        <CardTitle
                            class="text-sm font-black text-slate-900 dark:text-white"
                        >
                            Matriz de Permisos por Módulo Académico
                        </CardTitle>
                        <CardDescription class="text-xs text-slate-500">
                            Detalle exhaustivo de capacidades y restricciones
                            operativas por cada rol del sistema.
                        </CardDescription>
                    </CardHeader>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead
                                class="border-b border-slate-200 bg-slate-50/90 font-extrabold tracking-wider text-slate-600 uppercase dark:border-slate-800 dark:bg-slate-950/60 dark:text-slate-400"
                            >
                                <tr>
                                    <th class="w-1/4 px-5 py-3.5">
                                        Módulo del Sistema
                                    </th>
                                    <th class="w-1/4 px-4 py-3.5">
                                        Administrador
                                    </th>
                                    <th class="w-1/4 px-4 py-3.5">
                                        Docente / Instructor
                                    </th>
                                    <th class="w-1/4 px-5 py-3.5">
                                        Participante / Alumno
                                    </th>
                                </tr>
                            </thead>
                            <tbody
                                class="divide-y divide-slate-100 dark:divide-slate-800/80"
                            >
                                <tr
                                    v-for="(row, idx) in permissionsMatrix"
                                    :key="idx"
                                    class="transition-colors hover:bg-slate-50/70 dark:hover:bg-slate-800/40"
                                >
                                    <td class="px-5 py-4">
                                        <div
                                            class="text-xs font-black text-slate-900 sm:text-sm dark:text-white"
                                        >
                                            {{ row.modulo }}
                                        </div>
                                        <div
                                            class="mt-0.5 text-[11px] leading-relaxed text-slate-500"
                                        >
                                            {{ row.descripcion }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div
                                            class="flex items-start gap-1.5 font-bold text-rose-950 dark:text-rose-200"
                                        >
                                            <CheckCircle2
                                                class="mt-0.5 size-4 shrink-0 text-emerald-600"
                                            />
                                            <span>{{ row.admin }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div
                                            class="flex items-start gap-1.5 font-bold text-amber-950 dark:text-amber-200"
                                        >
                                            <CheckCircle2
                                                class="mt-0.5 size-4 shrink-0 text-amber-600"
                                            />
                                            <span>{{ row.docente }}</span>
                                        </div>
                                    </td>
                                    <td class="px-5 py-4">
                                        <div
                                            class="flex items-start gap-1.5 font-medium text-slate-700 dark:text-slate-300"
                                        >
                                            <CheckCircle2
                                                class="mt-0.5 size-4 shrink-0 text-blue-600"
                                            />
                                            <span>{{ row.participante }}</span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </Card>
            </div>
        </div>

        <!-- MODAL DE CAMBIO DE ROL INSTITUCIONAL CON BORDES OVALADOS -->
        <Dialog v-model:open="isRoleModalOpen">
            <DialogContent
                class="max-w-md space-y-4 rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-950"
            >
                <DialogHeader class="space-y-1">
                    <DialogTitle
                        class="flex items-center gap-2 text-base font-black text-slate-950 dark:text-white"
                    >
                        <UserCog
                            class="size-5 text-rose-900 dark:text-rose-400"
                        />
                        Reasignar Rol Institucional
                    </DialogTitle>
                    <DialogDescription
                        class="text-xs font-medium text-slate-600 dark:text-slate-400"
                    >
                        Actualiza los privilegios y nivel de acceso para este
                        usuario en el sistema SIGC-CUSCO.
                    </DialogDescription>
                </DialogHeader>

                <div v-if="userToEdit" class="space-y-4 pt-1">
                    <!-- Ficha del Usuario -->
                    <div
                        class="space-y-1 rounded-xl border border-slate-200 bg-slate-50 p-3.5 dark:border-slate-800 dark:bg-slate-900"
                    >
                        <div
                            class="text-sm font-black text-slate-900 dark:text-white"
                        >
                            {{ getFullName(userToEdit) }}
                        </div>
                        <div class="font-mono text-xs text-slate-500">
                            {{ userToEdit.email }}
                            <span v-if="userToEdit.dni"
                                >• DNI: {{ userToEdit.dni }}</span
                            >
                        </div>
                        <div class="flex items-center gap-2 pt-1">
                            <span class="text-[11px] font-bold text-slate-500"
                                >Rol actual:</span
                            >
                            <Badge
                                :class="roleBadge(userToEdit.role).class"
                                class="rounded-full border px-2 py-0.5 text-[10px] font-black uppercase"
                            >
                                {{ roleBadge(userToEdit.role).label }}
                            </Badge>
                        </div>
                    </div>

                    <!-- Selector de Nuevo Rol -->
                    <div class="space-y-2">
                        <label
                            class="text-xs font-black text-slate-800 dark:text-slate-200"
                        >
                            Nuevo Rol a Asignar:
                        </label>
                        <Select v-model="newRole">
                            <SelectTrigger
                                class="h-10 w-full rounded-xl border-slate-200 bg-white text-xs font-bold dark:border-slate-800 dark:bg-slate-900"
                            >
                                <SelectValue placeholder="Seleccione un rol" />
                            </SelectTrigger>
                            <SelectContent class="rounded-2xl">
                                <SelectItem value="admin">
                                    <div class="flex items-center gap-2">
                                        <ShieldCheck
                                            class="size-4 text-rose-900"
                                        />
                                        <span
                                            >Administrador (Control Total)</span
                                        >
                                    </div>
                                </SelectItem>
                                <SelectItem value="docente">
                                    <div class="flex items-center gap-2">
                                        <GraduationCap
                                            class="size-4 text-amber-600"
                                        />
                                        <span
                                            >Docente / Instructor (Gestión de
                                            Cursos)</span
                                        >
                                    </div>
                                </SelectItem>
                                <SelectItem value="participante">
                                    <div class="flex items-center gap-2">
                                        <UserCheck
                                            class="size-4 text-blue-600"
                                        />
                                        <span
                                            >Participante / Alumno (Inscripción
                                            y Asistencia)</span
                                        >
                                    </div>
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <!-- Explicación del Rol Seleccionado -->
                    <div
                        class="space-y-1 rounded-xl border border-rose-200 bg-rose-50/60 p-3 text-xs text-rose-900 dark:border-rose-900/60 dark:bg-rose-950/30 dark:text-rose-200"
                    >
                        <div class="flex items-center gap-1.5 font-bold">
                            <ShieldAlert class="size-3.5" />
                            <span>Implicancia de Seguridad:</span>
                        </div>
                        <p
                            v-if="newRole === 'admin'"
                            class="text-[11px] leading-relaxed"
                        >
                            Tendrá acceso a la creación y eliminación de cursos,
                            actas de notas, asignación de roles y
                            configuraciones avanzadas.
                        </p>
                        <p
                            v-else-if="newRole === 'docente'"
                            class="text-[11px] leading-relaxed"
                        >
                            Podrá ser asignado como titular de cursos, proyectar
                            el QR de asistencia en aula y asentar notas en
                            actas.
                        </p>
                        <p v-else class="text-[11px] leading-relaxed">
                            Podrá matricularse en cursos abiertos, portar su
                            credencial QR y consultar sus notas y diplomas.
                        </p>
                    </div>
                </div>

                <DialogFooter class="flex gap-2 pt-2 sm:justify-end">
                    <Button
                        type="button"
                        variant="ghost"
                        class="cursor-pointer rounded-xl text-xs font-bold"
                        :disabled="isUpdatingRole"
                        @click="isRoleModalOpen = false"
                    >
                        Cancelar
                    </Button>
                    <Button
                        type="button"
                        class="cursor-pointer rounded-xl bg-rose-900 px-4 text-xs font-extrabold text-white shadow-xs hover:bg-rose-950"
                        :disabled="isUpdatingRole"
                        @click="saveRoleChange"
                    >
                        <Check v-if="!isUpdatingRole" class="mr-1 size-3.5" />
                        <span>{{
                            isUpdatingRole ? 'Guardando...' : 'Confirmar Rol'
                        }}</span>
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
