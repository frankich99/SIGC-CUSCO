<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import EnrollmentModal from '@/components/EnrollmentModal.vue';
import { formatDateRange, formatHours } from '@/lib/formatters';
import { THEME_BADGES, THEME_BUTTONS } from '@/lib/theme';
import {
    Calendar,
    Clock,
    GraduationCap,
    Plus,
    Search,
    User,
    Users,
    Eye,
    Pencil,
    Trash2,
    BookOpen,
    Building2,
    CheckCircle2,
} from '@lucide/vue';
import type { BreadcrumbItem } from '@/types';

interface Instructor {
    id: number;
    name: string;
    paterno?: string;
    materno?: string;
    email: string;
    role: string;
}

interface CourseItem {
    id: number;
    code: string;
    title: string;
    institution?: string;
    description?: string;
    start_date: string;
    end_date: string;
    hours: number;
    capacity: number;
    status: 'abierto' | 'en_curso' | 'concluido' | 'cancelado';
    instructor?: Instructor;
    instructor_name?: string;
}

interface PaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
}

const props = defineProps<{
    courses: {
        data: CourseItem[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
        current_page: number;
        last_page: number;
        total: number;
    };
    filters: {
        search?: string;
        status?: string;
    };
    statuses: Array<{ value: string; label: string }>;
    myEnrolledCourseIds?: number[];
    can: {
        create: boolean;
    };
}>();

const page = usePage();
const authUser = computed(() => (page.props.auth as any)?.user);

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    {
        title: authUser.value ? 'Panel Principal' : 'Portal Principal',
        href: authUser.value ? '/dashboard' : '/',
    },
    { title: 'Capacitaciones', href: '/courses' },
]);

const searchQuery = ref(props.filters.search || '');
const selectedStatus = ref(props.filters.status || 'all');

const selectedCourseToEnroll = ref<CourseItem | null>(null);
const isEnrollModalOpen = ref(false);

function openEnroll(course: CourseItem) {
    selectedCourseToEnroll.value = course;
    isEnrollModalOpen.value = true;
}

function applyFilters() {
    router.get(
        '/courses',
        {
            search: searchQuery.value || undefined,
            status: selectedStatus.value !== 'all' ? selectedStatus.value : undefined,
        },
        {
            preserveState: true,
            replace: true,
        }
    );
}

function deleteCourse(course: CourseItem) {
    if (confirm(`¿Estás seguro de eliminar la capacitación "${course.title}"?`)) {
        router.delete(`/courses/${course.id}`, {
            preserveScroll: true,
        });
    }
}

function getStatusBadge(status: string) {
    switch (status) {
        case 'abierto':
            return {
                label: 'Inscripción Abierta',
                class: THEME_BADGES.abierto,
            };
        case 'en_curso':
            return {
                label: 'En curso',
                class: THEME_BADGES.en_curso,
            };
        case 'concluido':
            return {
                label: 'Concluido',
                class: THEME_BADGES.concluido,
            };
        default:
            return {
                label: status,
                class: THEME_BADGES.concluido,
            };
    }
}

function instructorName(inst?: Instructor): string {
    if (!inst) return 'Por asignar';
    const parts = [inst.name, inst.paterno, inst.materno].filter(Boolean);
    return parts.length > 0 ? parts.join(' ') : inst.name;
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Capacitaciones - SIGC-CUSCO" />

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8 space-y-6 min-w-0 w-full overflow-x-hidden">
            <!-- Header Section -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b pb-5 dark:border-neutral-800">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-neutral-100 flex items-center gap-2">
                        <GraduationCap class="size-7 text-rose-900 dark:text-rose-400" />
                        Capacitaciones y Cursos
                    </h1>
                    <p class="text-sm text-slate-600 dark:text-neutral-400 mt-1 font-medium">
                        Control del ciclo formativo: vacantes, docentes, código QR y certificaciones.
                    </p>
                </div>
                <div v-if="can.create">
                    <Button as-child class="bg-rose-900 hover:bg-rose-950 text-white font-black text-xs shadow-xs px-4 h-9">
                        <Link href="/courses/create" class="flex items-center gap-1.5">
                            <Plus class="size-4 text-amber-300 stroke-[2.5]" />
                            <span>Agregar Curso</span>
                        </Link>
                    </Button>
                </div>
            </div>

            <!-- Main Catalog with Filters -->
            <div class="space-y-6">
                <!-- Search and Status Bar -->
                <div class="flex flex-col sm:flex-row gap-3">
                    <div class="relative flex-1 min-w-0">
                        <Search class="absolute left-3 top-2.5 size-4 text-slate-500" />
                        <Input
                            v-model="searchQuery"
                            placeholder="Buscar por título, código o contenido..."
                            class="pl-9 text-slate-900 font-medium"
                            @keydown.enter="applyFilters"
                        />
                    </div>
                    <div class="flex gap-2 items-center overflow-x-auto pb-1 sm:pb-0 custom-scrollbar max-w-full min-w-0">
                        <button
                            v-for="st in [{ value: 'all', label: 'Todos' }, ...statuses]"
                            :key="st.value"
                            type="button"
                            @click="selectedStatus = st.value; applyFilters()"
                            :class="[
                                'px-3 py-1.5 rounded-xl text-xs font-bold transition-colors shrink-0',
                                selectedStatus === st.value
                                    ? 'bg-rose-900 text-white shadow-xs dark:bg-rose-800'
                                    : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300'
                            ]"
                        >
                            {{ st.label }}
                        </button>
                    </div>
                </div>

                <!-- Courses Grid -->
                <div v-if="courses.data.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                    <Card
                        v-for="course in courses.data"
                        :key="course.id"
                        class="flex flex-col justify-between border-slate-200 dark:border-neutral-800 hover:border-rose-400/80 transition-all hover:shadow-md min-w-0 max-w-full overflow-hidden"
                    >
                        <CardHeader class="p-4 sm:p-5 pb-3 space-y-2 min-w-0 max-w-full">
                            <div class="flex items-center justify-between gap-1.5 min-w-0 w-full">
                                <span class="font-mono text-xs font-bold text-rose-900 dark:text-rose-300 bg-rose-50 dark:bg-rose-950/60 px-2.5 py-0.5 rounded-full border border-rose-200 dark:border-rose-800 shrink-0">
                                    {{ course.code }}
                                </span>
                                <span :class="['text-[10px] sm:text-[11px] font-bold px-2.5 py-0.5 rounded-full border shrink-0 text-center truncate max-w-[62%]', getStatusBadge(course.status).class]">
                                    {{ getStatusBadge(course.status).label }}
                                </span>
                            </div>

                            <!-- Organizing Entity / Institution -->
                            <div
                                v-if="course.institution"
                                class="flex items-center gap-1.5 text-xs font-semibold text-rose-950 dark:text-rose-200 bg-rose-50/80 dark:bg-rose-950/40 px-2.5 py-1 rounded-xl border border-rose-200/80 dark:border-rose-800/60 w-full min-w-0 max-w-full overflow-hidden"
                            >
                                <Building2 class="size-3.5 shrink-0 text-rose-800 dark:text-rose-400" />
                                <span class="truncate block min-w-0 flex-1">{{ course.institution }}</span>
                            </div>

                            <CardTitle class="text-base font-bold leading-snug line-clamp-2 text-slate-900 dark:text-white break-words min-w-0">
                                {{ course.title }}
                            </CardTitle>
                            <CardDescription class="text-xs line-clamp-2 mt-1 break-words min-w-0">
                                {{ course.description || 'Sin descripción detallada.' }}
                            </CardDescription>
                        </CardHeader>

                        <CardContent class="px-4 sm:px-5 space-y-2.5 text-xs text-neutral-600 dark:text-neutral-400 border-t pt-3 dark:border-neutral-800 min-w-0 max-w-full overflow-hidden">
                            <div class="flex items-center gap-2 min-w-0 max-w-full">
                                <User class="size-3.5 text-neutral-400 shrink-0" />
                                <span class="truncate min-w-0 flex-1">
                                    <strong class="font-medium text-neutral-700 dark:text-neutral-300">Ponente / Docente:</strong>
                                    {{ instructorName(course.instructor) || course.instructor_name || 'Por asignar' }}
                                </span>
                            </div>
                            <div class="flex items-center gap-2 font-semibold text-slate-800 dark:text-slate-200 min-w-0">
                                <Calendar class="size-3.5 text-rose-800 dark:text-rose-400 shrink-0" />
                                <span class="truncate">{{ formatDateRange(course.start_date, course.end_date, 'compact') }}</span>
                            </div>
                            <div class="flex items-center justify-between pt-1 text-[11px] gap-2 min-w-0">
                                <span class="inline-flex items-center gap-1 font-bold text-slate-800 dark:text-slate-200 shrink-0">
                                    <Clock class="size-3 text-amber-700 dark:text-amber-400 shrink-0" />
                                    {{ formatHours(course.hours) }}
                                </span>
                                <span class="inline-flex items-center gap-1 font-bold text-rose-900 dark:text-rose-300 shrink-0">
                                    <Users class="size-3 text-rose-800 dark:text-rose-400 shrink-0" />
                                    Cupo: {{ course.capacity }} vacantes
                                </span>
                            </div>
                        </CardContent>

                        <CardFooter class="px-4 sm:px-5 pt-3 pb-4 border-t dark:border-neutral-800 flex flex-wrap items-center justify-between gap-2 min-w-0 max-w-full">
                            <Button as-child variant="outline" size="sm" class="flex-1 min-w-[90px] text-xs font-semibold">
                                <Link :href="`/courses/${course.id}`">
                                    <Eye class="mr-1 size-3.5" />
                                    Ver curso
                                </Link>
                            </Button>

                            <!-- Si el participante ya está matriculado en esta capacitación -->
                            <Button
                                v-if="authUser && myEnrolledCourseIds?.includes(course.id)"
                                as-child
                                size="sm"
                                variant="outline"
                                class="flex-1 min-w-[100px] text-xs font-black border-emerald-600 text-emerald-800 bg-emerald-50 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-700"
                            >
                                <Link :href="`/courses/${course.id}`">
                                    <CheckCircle2 class="mr-1 size-3.5 text-emerald-600" />
                                    Matriculado
                                </Link>
                            </Button>

                            <!-- Si el usuario es el docente responsable o administrador del curso -->
                            <Button
                                v-else-if="authUser && (authUser.role === 'admin' || (authUser.role === 'docente' && course.instructor?.id === authUser.id))"
                                as-child
                                size="sm"
                                class="bg-amber-700 hover:bg-amber-800 text-white text-xs flex-1 min-w-[100px] font-bold shadow-xs"
                            >
                                <Link :href="`/courses/${course.id}`">
                                    <BookOpen class="mr-1 size-3.5" />
                                    Gestionar
                                </Link>
                            </Button>

                            <!-- Si la convocatoria está abierta para postulaciones -->
                            <Button
                                v-else-if="course.status === 'abierto'"
                                size="sm"
                                class="bg-rose-900 hover:bg-rose-950 text-white text-xs flex-1 min-w-[100px] font-bold shadow-xs cursor-pointer"
                                @click="openEnroll(course)"
                            >
                                Inscribirme
                            </Button>
                            <div v-if="can.create" class="flex items-center gap-1 shrink-0">
                                <Button as-child variant="ghost" size="icon-sm" class="size-7 text-slate-600 hover:text-slate-900">
                                    <Link :href="`/courses/${course.id}/edit`">
                                        <Pencil class="size-3.5" />
                                    </Link>
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon-sm"
                                    class="size-7 text-red-600 hover:text-red-800 hover:bg-red-50 dark:hover:bg-red-950/40"
                                    @click="deleteCourse(course)"
                                >
                                    <Trash2 class="size-3.5" />
                                </Button>
                            </div>
                        </CardFooter>
                    </Card>
                </div>

                <!-- Empty State -->
                <div v-else class="text-center py-16 border rounded-xl bg-slate-50/50 dark:bg-neutral-900/40 dark:border-neutral-800">
                    <BookOpen class="mx-auto size-12 text-slate-400" />
                    <h3 class="mt-3 text-sm font-bold text-slate-900 dark:text-neutral-100">No se encontraron capacitaciones</h3>
                    <p class="mt-1 text-xs text-slate-600 font-medium">Prueba ajustando los términos de búsqueda o el filtro de estado.</p>
                    <div v-if="can.create" class="mt-4">
                        <Button as-child size="sm" class="bg-rose-900 hover:bg-rose-950 text-white font-bold text-xs shadow-xs">
                            <Link href="/courses/create" class="flex items-center gap-1.5">
                                <Plus class="size-3.5 text-amber-300 stroke-[2.5]" />
                                <span>Agregar Curso</span>
                            </Link>
                        </Button>
                    </div>
                </div>

                <!-- Pagination -->
                <div v-if="courses.total > courses.data.length" class="flex justify-center pt-4">
                    <div class="flex gap-1">
                        <template v-for="(link, i) in courses.links" :key="i">
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                :class="[
                                    'px-3 py-1.5 text-xs rounded-xl border font-bold transition-colors',
                                    link.active
                                        ? 'bg-rose-900 text-white border-rose-900 shadow-xs'
                                        : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-50 dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-300'
                                ]"
                                v-html="link.label"
                            />
                            <span
                                v-else
                                class="px-3 py-1.5 text-xs rounded-xl border border-neutral-200 text-neutral-400 dark:border-neutral-800"
                                v-html="link.label"
                            />
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <EnrollmentModal
            :course="selectedCourseToEnroll"
            v-model:open="isEnrollModalOpen"
        />
    </AppLayout>
</template>
