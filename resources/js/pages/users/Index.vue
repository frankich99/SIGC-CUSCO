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
            role: selectedRoleFilter.value !== 'all' ? selectedRoleFilter.value : undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        }
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
                toast.success('El rol institucional fue actualizado exitosamente.');
            },
            onError: (errors) => {
                const msg = Object.values(errors)[0] || 'Error al actualizar el rol.';
                toast.error(msg);
            },
            onFinish: () => {
                isUpdatingRole.value = false;
            },
        }
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
        return d.toLocaleDateString('es-PE', { day: '2-digit', month: '2-digit', year: 'numeric' });
    } catch {
        return dateStr;
    }
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Gestión de Usuarios y Roles - SIGC-CUSCO" />

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8 space-y-8 min-w-0 w-full overflow-x-hidden">
            <!-- HEADER DE SECCIÓN INSTITUCIONAL -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200/90 dark:border-slate-800 pb-5">
                <div class="space-y-1">
                    <div class="flex items-center gap-2">
                        <div class="size-10 rounded-2xl bg-gradient-to-tr from-[#701a31] to-[#800020] text-white flex items-center justify-center shadow-sm shrink-0">
                            <ShieldCheck class="size-5 text-amber-300" />
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h1 class="text-xl sm:text-2xl font-black tracking-tight text-slate-950 dark:text-white">
                                    Usuarios, Roles y Permisos
                                </h1>
                                <span class="hidden sm:inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-rose-100 text-rose-950 dark:bg-rose-950/80 dark:text-rose-200 border border-rose-300 dark:border-rose-800">
                                    Admin Oficial
                                </span>
                            </div>
                            <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-400 font-medium">
                                Control de accesos institucionales, reasignación de roles y matriz de capacidades SIGC-CUSCO.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- SELECTOR DE PESTAÑAS OVALADAS -->
                <div class="inline-flex p-1 bg-slate-100 dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shrink-0 self-start sm:self-auto shadow-2xs">
                    <button
                        type="button"
                        class="flex items-center gap-2 px-3.5 py-1.5 rounded-xl text-xs font-black transition-all cursor-pointer"
                        :class="activeTab === 'directorio' ? 'bg-white dark:bg-slate-800 text-rose-950 dark:text-rose-200 shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900'"
                        @click="activeTab = 'directorio'"
                    >
                        <Users class="size-3.5" />
                        <span>Directorio y Roles</span>
                    </button>
                    <button
                        type="button"
                        class="flex items-center gap-2 px-3.5 py-1.5 rounded-xl text-xs font-black transition-all cursor-pointer"
                        :class="activeTab === 'matriz' ? 'bg-white dark:bg-slate-800 text-rose-950 dark:text-rose-200 shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900'"
                        @click="activeTab = 'matriz'"
                    >
                        <Shield class="size-3.5 text-amber-600" />
                        <span>Matriz de Permisos</span>
                    </button>
                </div>
            </div>

            <!-- TARJETAS DE MÉTRICAS INSTITUCIONALES (KPIs) -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <Card class="rounded-2xl border border-slate-200/90 dark:border-slate-800/90 shadow-xs bg-white dark:bg-slate-900/60 p-5 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-slate-600 dark:text-slate-400">Total Usuarios</span>
                        <div class="size-8 rounded-xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-700 dark:text-slate-300">
                            <Users class="size-4" />
                        </div>
                    </div>
                    <div class="text-2xl font-black text-slate-950 dark:text-white">
                        {{ stats.total }}
                    </div>
                    <p class="text-[11px] text-slate-600 dark:text-slate-400">Registrados en la plataforma</p>
                </Card>

                <Card class="rounded-2xl border border-rose-200 dark:border-rose-900/40 shadow-xs bg-rose-50/40 dark:bg-rose-950/20 p-5 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-rose-950 dark:text-rose-300">Administradores</span>
                        <div class="size-8 rounded-xl bg-rose-900 text-white flex items-center justify-center">
                            <ShieldCheck class="size-4 text-amber-300" />
                        </div>
                    </div>
                    <div class="text-2xl font-black text-rose-950 dark:text-rose-200">
                        {{ stats.admins }}
                    </div>
                    <p class="text-[11px] text-rose-800 dark:text-rose-400">Control total del sistema</p>
                </Card>

                <Card class="rounded-2xl border border-amber-200 dark:border-amber-900/40 shadow-xs bg-amber-50/40 dark:bg-amber-950/20 p-5 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-amber-950 dark:text-amber-300">Docentes / Ponentes</span>
                        <div class="size-8 rounded-xl bg-amber-700 text-white flex items-center justify-center">
                            <GraduationCap class="size-4 text-white" />
                        </div>
                    </div>
                    <div class="text-2xl font-black text-amber-950 dark:text-amber-200">
                        {{ stats.docentes }}
                    </div>
                    <p class="text-[11px] text-amber-800 dark:text-amber-400">Gestores de capacitaciones</p>
                </Card>

                <Card class="rounded-2xl border border-blue-200 dark:border-blue-900/40 shadow-xs bg-blue-50/40 dark:bg-blue-950/20 p-5 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-blue-950 dark:text-blue-300">Participantes</span>
                        <div class="size-8 rounded-xl bg-blue-800 text-white flex items-center justify-center">
                            <UserCheck class="size-4 text-white" />
                        </div>
                    </div>
                    <div class="text-2xl font-black text-blue-950 dark:text-blue-200">
                        {{ stats.participantes }}
                    </div>
                    <p class="text-[11px] text-blue-800 dark:text-blue-400">Alumnos y beneficiarios</p>
                </Card>
            </div>

            <!-- CONTENIDO PESTAÑA 1: DIRECTORIO DE USUARIOS Y ASIGNACIÓN DE ROLES -->
            <div v-if="activeTab === 'directorio'" class="space-y-6">
                <!-- Barra de Búsqueda y Filtros Unificada -->
                <div class="flex flex-col sm:flex-row gap-3">
                    <div class="relative flex-1">
                        <Search class="absolute left-3.5 top-1/2 -translate-y-1/2 size-4 text-slate-400" />
                        <Input
                            v-model="searchQuery"
                            placeholder="Buscar por DNI, nombres, apellidos o correo electrónico institucional..."
                            class="pl-10 h-10 rounded-xl bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 text-xs sm:text-sm font-medium"
                            @keydown.enter="applyFilters"
                        />
                    </div>

                    <div class="w-full sm:w-56 shrink-0">
                        <Select v-model="selectedRoleFilter" @update:model-value="applyFilters">
                            <SelectTrigger class="w-full h-10 rounded-xl bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 text-xs font-bold">
                                <SelectValue placeholder="Filtrar por Rol" />
                            </SelectTrigger>
                            <SelectContent class="rounded-2xl">
                                <SelectItem value="all">Todos los Roles</SelectItem>
                                <SelectItem value="admin">Administrador</SelectItem>
                                <SelectItem value="docente">Docente / Instructor</SelectItem>
                                <SelectItem value="participante">Participante / Alumno</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <Button
                        type="button"
                        class="bg-rose-900 hover:bg-rose-950 text-white font-extrabold text-xs h-10 px-5 rounded-xl shadow-xs cursor-pointer"
                        @click="applyFilters"
                    >
                        Filtrar
                    </Button>

                    <Button
                        v-if="filters.search || filters.role !== 'all'"
                        variant="outline"
                        type="button"
                        class="text-xs font-bold h-10 rounded-xl border-slate-300 cursor-pointer"
                        @click="clearFilters"
                    >
                        Limpiar
                    </Button>
                </div>

                <!-- Tabla de Usuarios con Bordes Ovalados y Responsive -->
                <Card class="rounded-2xl border border-slate-200/90 dark:border-slate-800 shadow-sm overflow-hidden bg-white dark:bg-slate-900">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-50/90 dark:bg-slate-950/60 border-b border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400 uppercase tracking-wider font-extrabold">
                                <tr>
                                    <th class="px-5 py-3.5">Usuario e Identificación</th>
                                    <th class="px-4 py-3.5">Rol Institucional</th>
                                    <th class="px-4 py-3.5">Contacto</th>
                                    <th class="px-4 py-3.5 text-center">Actividad</th>
                                    <th class="px-4 py-3.5 text-center">Registro</th>
                                    <th class="px-5 py-3.5 text-right">Acción</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
                                <tr
                                    v-for="u in users.data"
                                    :key="u.id"
                                    class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition-colors"
                                >
                                    <!-- Usuario e Identificación -->
                                    <td class="px-5 py-4">
                                        <div class="flex items-center gap-3">
                                            <Avatar class="size-9 rounded-full ring-1 ring-slate-200 dark:ring-slate-700 shrink-0">
                                                <AvatarFallback class="bg-rose-900 text-white font-black text-xs">
                                                    {{ getInitials(u.name) }}
                                                </AvatarFallback>
                                            </Avatar>
                                            <div class="min-w-0">
                                                <div class="font-black text-slate-900 dark:text-white truncate text-xs sm:text-sm">
                                                    {{ getFullName(u) }}
                                                </div>
                                                <div class="flex items-center gap-2 text-[11px] text-slate-600 dark:text-slate-400 mt-0.5">
                                                    <span v-if="u.dni" class="font-mono bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 rounded-md font-bold text-slate-700 dark:text-slate-300">
                                                        DNI: {{ u.dni }}
                                                    </span>
                                                    <span v-else class="italic text-slate-400">Sin DNI registrado</span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Rol Institucional -->
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <Badge :class="roleBadge(u.role).class" class="px-2.5 py-1 rounded-full text-[10px] uppercase font-black tracking-wide border">
                                            <component :is="roleBadge(u.role).icon" class="size-3 mr-1" />
                                            <span>{{ roleBadge(u.role).label }}</span>
                                        </Badge>
                                    </td>

                                    <!-- Contacto -->
                                    <td class="px-4 py-4 whitespace-nowrap text-slate-600 dark:text-slate-400">
                                        <div class="space-y-0.5">
                                            <div class="flex items-center gap-1.5 font-medium truncate max-w-[200px]" :title="u.email">
                                                <Mail class="size-3 text-slate-400 shrink-0" />
                                                <span>{{ u.email }}</span>
                                            </div>
                                            <div v-if="u.phone" class="flex items-center gap-1.5 text-[11px]">
                                                <Phone class="size-3 text-slate-400 shrink-0" />
                                                <span>{{ u.phone }}</span>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Actividad Institucional -->
                                    <td class="px-4 py-4 whitespace-nowrap text-center">
                                        <div v-if="u.role === 'docente'" class="text-amber-800 dark:text-amber-400 font-bold">
                                            {{ u.taught_courses_count ?? 0 }} capacitaciones dictadas
                                        </div>
                                        <div v-else-if="u.role === 'admin'" class="text-rose-900 dark:text-rose-400 font-black">
                                            Administrador General
                                        </div>
                                        <div v-else class="text-blue-800 dark:text-blue-400 font-bold">
                                            {{ u.enrollments_count ?? 0 }} cursos matriculados
                                        </div>
                                    </td>

                                    <!-- Fecha de Registro -->
                                    <td class="px-4 py-4 whitespace-nowrap text-center text-slate-500 font-mono text-[11px]">
                                        {{ formatDate(u.created_at) }}
                                    </td>

                                    <!-- Acción: Gestionar Rol -->
                                    <td class="px-5 py-4 whitespace-nowrap text-right">
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            class="rounded-xl border-slate-300 hover:border-rose-300 hover:bg-rose-50 dark:hover:bg-rose-950/40 text-xs font-bold cursor-pointer"
                                            @click="openRoleModal(u)"
                                        >
                                            <UserCog class="size-3.5 mr-1 text-rose-800" />
                                            <span>Cambiar Rol</span>
                                        </Button>
                                    </td>
                                </tr>

                                <tr v-if="users.data.length === 0">
                                    <td colspan="6" class="p-12 text-center text-slate-500 space-y-2">
                                        <Users class="size-10 mx-auto text-slate-300 dark:text-slate-700" />
                                        <p class="font-bold text-sm">No se encontraron usuarios registrados con los filtros seleccionados.</p>
                                        <Button size="sm" variant="outline" class="rounded-xl mt-2 cursor-pointer" @click="clearFilters">
                                            Limpiar Filtros
                                        </Button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación -->
                    <div v-if="users.links.length > 3" class="p-4 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-xs">
                        <div class="text-slate-500 font-medium">
                            Mostrando {{ users.from }} a {{ users.to }} de {{ users.total }} usuarios
                        </div>
                        <div class="flex items-center gap-1">
                            <template v-for="(link, i) in users.links" :key="i">
                                <Button
                                    v-if="link.url"
                                    as-child
                                    size="sm"
                                    :variant="link.active ? 'default' : 'outline'"
                                    class="h-8 min-w-8 px-2 rounded-lg text-xs font-bold"
                                    :class="link.active ? 'bg-rose-900 text-white' : ''"
                                >
                                    <Link :href="link.url" preserve-scroll v-html="link.label" />
                                </Button>
                                <span v-else class="h-8 min-w-8 flex items-center justify-center text-slate-300 text-xs px-2" v-html="link.label" />
                            </template>
                        </div>
                    </div>
                </Card>
            </div>

            <!-- CONTENIDO PESTAÑA 2: MATRIZ OFICIAL DE ROLES Y PERMISOS -->
            <div v-else-if="activeTab === 'matriz'" class="space-y-6">
                <!-- Tarjeta de Introducción Institucional -->
                <Card class="rounded-2xl border-2 border-rose-900/20 dark:border-rose-900/40 bg-gradient-to-r from-rose-50/70 via-white to-amber-50/40 dark:from-slate-900 dark:via-slate-900 dark:to-slate-950 p-6 space-y-3 shadow-xs">
                    <div class="flex items-center gap-2">
                        <ShieldCheck class="size-5 text-rose-900 dark:text-rose-400" />
                        <h2 class="text-base font-black text-slate-950 dark:text-white">
                            Políticas de Control de Acceso Basadas en Roles (RBAC) - SIGC-CUSCO
                        </h2>
                    </div>
                    <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed font-medium">
                        El Sistema Integral de Gestión de Capacitaciones cuenta con tres roles institucionales rigurosamente definidos conforme al modelo de seguridad y proceso de desarrollo académico. A continuación se desglosan los privilegios asignados a cada función.
                    </p>
                </Card>

                <!-- Resumen Visual de los 3 Roles -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <Card class="rounded-2xl border border-rose-200 dark:border-rose-900/60 p-5 space-y-3 bg-white dark:bg-slate-900 shadow-xs">
                        <div class="flex items-center gap-2.5">
                            <div class="size-8 rounded-xl bg-rose-900 text-white flex items-center justify-center">
                                <ShieldCheck class="size-4 text-amber-300" />
                            </div>
                            <div>
                                <h3 class="font-black text-sm text-slate-900 dark:text-white">Administrador</h3>
                                <span class="text-[10px] uppercase font-bold text-rose-800 dark:text-rose-400">Nivel Superior</span>
                            </div>
                        </div>
                        <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
                            Supervisa globalmente todas las capacitaciones de la institución, administra roles de usuario, emite actas y certificados oficiales, y posee control absoluto sobre los cursos y padrones.
                        </p>
                    </Card>

                    <Card class="rounded-2xl border border-amber-200 dark:border-amber-900/60 p-5 space-y-3 bg-white dark:bg-slate-900 shadow-xs">
                        <div class="flex items-center gap-2.5">
                            <div class="size-8 rounded-xl bg-amber-700 text-white flex items-center justify-center">
                                <GraduationCap class="size-4 text-white" />
                            </div>
                            <div>
                                <h3 class="font-black text-sm text-slate-900 dark:text-white">Docente / Instructor</h3>
                                <span class="text-[10px] uppercase font-bold text-amber-700 dark:text-amber-400">Facultad Académica</span>
                            </div>
                        </div>
                        <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
                            Imparte los cursos asignados, proyecta códigos QR en pantalla para asistencia en tiempo real, registra asistencia manual, califica alumnos y cierra actas de sus cursos a cargo.
                        </p>
                    </Card>

                    <Card class="rounded-2xl border border-blue-200 dark:border-blue-900/60 p-5 space-y-3 bg-white dark:bg-slate-900 shadow-xs">
                        <div class="flex items-center gap-2.5">
                            <div class="size-8 rounded-xl bg-blue-800 text-white flex items-center justify-center">
                                <UserCheck class="size-4 text-white" />
                            </div>
                            <div>
                                <h3 class="font-black text-sm text-slate-900 dark:text-white">Participante / Alumno</h3>
                                <span class="text-[10px] uppercase font-bold text-blue-700 dark:text-blue-400">Beneficiario Formativo</span>
                            </div>
                        </div>
                        <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
                            Se inscribe en cursos con vacantes, porta su Credencial QR para el escaneo presencial en aula, consulta su porcentaje de asistencia y récord de notas, y descarga sus diplomas verificados.
                        </p>
                    </Card>
                </div>

                <!-- Tabla Comparativa Detallada de Permisos por Módulo -->
                <Card class="rounded-2xl border border-slate-200/90 dark:border-slate-800 shadow-sm overflow-hidden bg-white dark:bg-slate-900">
                    <CardHeader class="p-5 border-b border-slate-100 dark:border-slate-800">
                        <CardTitle class="text-sm font-black text-slate-900 dark:text-white">
                            Matriz de Permisos por Módulo Académico
                        </CardTitle>
                        <CardDescription class="text-xs text-slate-500">
                            Detalle exhaustivo de capacidades y restricciones operativas por cada rol del sistema.
                        </CardDescription>
                    </CardHeader>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-50/90 dark:bg-slate-950/60 border-b border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400 uppercase tracking-wider font-extrabold">
                                <tr>
                                    <th class="px-5 py-3.5 w-1/4">Módulo del Sistema</th>
                                    <th class="px-4 py-3.5 w-1/4">Administrador</th>
                                    <th class="px-4 py-3.5 w-1/4">Docente / Instructor</th>
                                    <th class="px-5 py-3.5 w-1/4">Participante / Alumno</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
                                <tr
                                    v-for="(row, idx) in permissionsMatrix"
                                    :key="idx"
                                    class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition-colors"
                                >
                                    <td class="px-5 py-4">
                                        <div class="font-black text-slate-900 dark:text-white text-xs sm:text-sm">
                                            {{ row.modulo }}
                                        </div>
                                        <div class="text-[11px] text-slate-500 mt-0.5 leading-relaxed">
                                            {{ row.descripcion }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="flex items-start gap-1.5 text-rose-950 dark:text-rose-200 font-bold">
                                            <CheckCircle2 class="size-4 text-emerald-600 shrink-0 mt-0.5" />
                                            <span>{{ row.admin }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="flex items-start gap-1.5 text-amber-950 dark:text-amber-200 font-bold">
                                            <CheckCircle2 class="size-4 text-amber-600 shrink-0 mt-0.5" />
                                            <span>{{ row.docente }}</span>
                                        </div>
                                    </td>
                                    <td class="px-5 py-4">
                                        <div class="flex items-start gap-1.5 text-slate-700 dark:text-slate-300 font-medium">
                                            <CheckCircle2 class="size-4 text-blue-600 shrink-0 mt-0.5" />
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
            <DialogContent class="rounded-2xl max-w-md p-6 bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 space-y-4">
                <DialogHeader class="space-y-1">
                    <DialogTitle class="text-base font-black text-slate-950 dark:text-white flex items-center gap-2">
                        <UserCog class="size-5 text-rose-900 dark:text-rose-400" />
                        Reasignar Rol Institucional
                    </DialogTitle>
                    <DialogDescription class="text-xs text-slate-600 dark:text-slate-400 font-medium">
                        Actualiza los privilegios y nivel de acceso para este usuario en el sistema SIGC-CUSCO.
                    </DialogDescription>
                </DialogHeader>

                <div v-if="userToEdit" class="space-y-4 pt-1">
                    <!-- Ficha del Usuario -->
                    <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-1">
                        <div class="font-black text-slate-900 dark:text-white text-sm">
                            {{ getFullName(userToEdit) }}
                        </div>
                        <div class="text-xs text-slate-500 font-mono">
                            {{ userToEdit.email }} <span v-if="userToEdit.dni">• DNI: {{ userToEdit.dni }}</span>
                        </div>
                        <div class="pt-1 flex items-center gap-2">
                            <span class="text-[11px] text-slate-500 font-bold">Rol actual:</span>
                            <Badge :class="roleBadge(userToEdit.role).class" class="text-[10px] uppercase font-black px-2 py-0.5 rounded-full border">
                                {{ roleBadge(userToEdit.role).label }}
                            </Badge>
                        </div>
                    </div>

                    <!-- Selector de Nuevo Rol -->
                    <div class="space-y-2">
                        <label class="text-xs font-black text-slate-800 dark:text-slate-200">
                            Nuevo Rol a Asignar:
                        </label>
                        <Select v-model="newRole">
                            <SelectTrigger class="w-full h-10 rounded-xl bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 font-bold text-xs">
                                <SelectValue placeholder="Seleccione un rol" />
                            </SelectTrigger>
                            <SelectContent class="rounded-2xl">
                                <SelectItem value="admin">
                                    <div class="flex items-center gap-2">
                                        <ShieldCheck class="size-4 text-rose-900" />
                                        <span>Administrador (Control Total)</span>
                                    </div>
                                </SelectItem>
                                <SelectItem value="docente">
                                    <div class="flex items-center gap-2">
                                        <GraduationCap class="size-4 text-amber-600" />
                                        <span>Docente / Instructor (Gestión de Cursos)</span>
                                    </div>
                                </SelectItem>
                                <SelectItem value="participante">
                                    <div class="flex items-center gap-2">
                                        <UserCheck class="size-4 text-blue-600" />
                                        <span>Participante / Alumno (Inscripción y Asistencia)</span>
                                    </div>
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <!-- Explicación del Rol Seleccionado -->
                    <div class="p-3 rounded-xl bg-rose-50/60 dark:bg-rose-950/30 border border-rose-200 dark:border-rose-900/60 text-xs text-rose-900 dark:text-rose-200 space-y-1">
                        <div class="font-bold flex items-center gap-1.5">
                            <ShieldAlert class="size-3.5" />
                            <span>Implicancia de Seguridad:</span>
                        </div>
                        <p v-if="newRole === 'admin'" class="text-[11px] leading-relaxed">
                            Tendrá acceso a la creación y eliminación de cursos, actas de notas, asignación de roles y configuraciones avanzadas.
                        </p>
                        <p v-else-if="newRole === 'docente'" class="text-[11px] leading-relaxed">
                            Podrá ser asignado como titular de cursos, proyectar el QR de asistencia en aula y asentar notas en actas.
                        </p>
                        <p v-else class="text-[11px] leading-relaxed">
                            Podrá matricularse en cursos abiertos, portar su credencial QR y consultar sus notas y diplomas.
                        </p>
                    </div>
                </div>

                <DialogFooter class="flex sm:justify-end gap-2 pt-2">
                    <Button
                        type="button"
                        variant="ghost"
                        class="text-xs font-bold rounded-xl cursor-pointer"
                        :disabled="isUpdatingRole"
                        @click="isRoleModalOpen = false"
                    >
                        Cancelar
                    </Button>
                    <Button
                        type="button"
                        class="bg-rose-900 hover:bg-rose-950 text-white font-extrabold text-xs px-4 rounded-xl shadow-xs cursor-pointer"
                        :disabled="isUpdatingRole"
                        @click="saveRoleChange"
                    >
                        <Check v-if="!isUpdatingRole" class="size-3.5 mr-1" />
                        <span>{{ isUpdatingRole ? 'Guardando...' : 'Confirmar Rol' }}</span>
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
