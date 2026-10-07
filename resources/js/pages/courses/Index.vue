<script setup lang="ts">
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import EnrollmentModal from '@/components/EnrollmentModal.vue';
import { formatDateRange, formatHours } from '@/lib/formatters';
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
    can: {
        create: boolean;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Panel Principal', href: '/dashboard' },
    { title: 'Capacitaciones', href: '/courses' },
];

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
                label: 'Convocatoria Abierta',
                class: 'bg-emerald-50 text-emerald-700 border-emerald-300 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800',
            };
        case 'en_curso':
            return {
                label: 'En curso',
                class: 'bg-blue-50 text-blue-700 border-blue-300 dark:bg-blue-950/40 dark:text-blue-300 dark:border-blue-800',
            };
        case 'concluido':
            return {
                label: 'Concluido',
                class: 'bg-neutral-100 text-neutral-700 border-neutral-300 dark:bg-neutral-800 dark:text-neutral-300 dark:border-neutral-700',
            };
        default:
            return {
                label: status,
                class: 'bg-neutral-100 text-neutral-600 border-neutral-200',
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

        <div class="space-y-6 px-4 py-6 md:px-8">
            <!-- Header Section -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b pb-5 dark:border-neutral-800">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-neutral-900 dark:text-neutral-100 flex items-center gap-2">
                        <GraduationCap class="size-7 text-emerald-600" />
                        Capacitaciones y Cursos
                    </h1>
                    <p class="text-sm text-neutral-500 dark:text-neutral-400 mt-1">
                        Control del ciclo formativo: vacantes, docentes, código QR y certificaciones.
                    </p>
                </div>
                <div v-if="can.create">
                    <Button as-child class="bg-emerald-600 hover:bg-emerald-700 text-white shadow-xs">
                        <Link href="/courses/create">
                            <Plus class="mr-1.5 size-4" />
                            Nueva Capacitación
                        </Link>
                    </Button>
                </div>
            </div>

            <!-- Main Catalog with Filters -->
            <div class="space-y-6">
                <!-- Search and Status Bar -->
                <div class="flex flex-col sm:flex-row gap-3">
                    <div class="relative flex-1">
                        <Search class="absolute left-3 top-2.5 size-4 text-neutral-400" />
                        <Input
                            v-model="searchQuery"
                            placeholder="Buscar por título, código o contenido..."
                            class="pl-9"
                            @keydown.enter="applyFilters"
                        />
                    </div>
                    <div class="flex gap-2 items-center overflow-x-auto pb-1 sm:pb-0">
                        <button
                            v-for="st in [{ value: 'all', label: 'Todos' }, ...statuses]"
                            :key="st.value"
                            type="button"
                            @click="selectedStatus = st.value; applyFilters()"
                            :class="[
                                'px-3 py-1.5 rounded-md text-xs font-medium transition-colors shrink-0',
                                selectedStatus === st.value
                                    ? 'bg-neutral-900 text-white dark:bg-neutral-100 dark:text-neutral-900'
                                    : 'bg-neutral-100 text-neutral-600 hover:bg-neutral-200 dark:bg-neutral-800 dark:text-neutral-400'
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
                        class="flex flex-col justify-between hover:border-emerald-400/80 transition-all hover:shadow-md"
                    >
                        <CardHeader class="pb-3">
                            <div class="flex items-center justify-between gap-2 mb-2">
                                <span class="font-mono text-xs font-semibold text-emerald-700 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/60 px-2 py-0.5 rounded border border-emerald-200 dark:border-emerald-800">
                                    {{ course.code }}
                                </span>
                                <span :class="['text-[11px] font-medium px-2 py-0.5 rounded-full border', getStatusBadge(course.status).class]">
                                    {{ getStatusBadge(course.status).label }}
                                </span>
                            </div>

                            <!-- Organizing Entity / Institution -->
                            <div
                                v-if="course.institution"
                                class="flex items-center gap-1.5 text-xs font-medium text-emerald-800 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/60 px-2.5 py-1 rounded-md border border-emerald-200/80 dark:border-emerald-800/60 mb-2"
                            >
                                <Building2 class="size-3.5 shrink-0 text-emerald-600 dark:text-emerald-400" />
                                <span class="truncate">{{ course.institution }}</span>
                            </div>

                            <CardTitle class="text-base font-semibold leading-snug line-clamp-2">
                                {{ course.title }}
                            </CardTitle>
                            <CardDescription class="text-xs line-clamp-2 mt-1">
                                {{ course.description || 'Sin descripción detallada.' }}
                            </CardDescription>
                        </CardHeader>

                        <CardContent class="space-y-2.5 text-xs text-neutral-600 dark:text-neutral-400 border-t pt-3 dark:border-neutral-800">
                            <div class="flex items-center gap-2">
                                <User class="size-3.5 text-neutral-400 shrink-0" />
                                <span class="truncate">
                                    <strong class="font-medium text-neutral-700 dark:text-neutral-300">Docente:</strong>
                                    {{ instructorName(course.instructor) }}
                                </span>
                            </div>
                            <div class="flex items-center gap-2 font-medium text-slate-800 dark:text-slate-200">
                                <Calendar class="size-3.5 text-emerald-600 shrink-0" />
                                <span>{{ formatDateRange(course.start_date, course.end_date, 'compact') }}</span>
                            </div>
                            <div class="flex items-center justify-between pt-1 text-[11px]">
                                <span class="inline-flex items-center gap-1 font-bold text-slate-800 dark:text-slate-200">
                                    <Clock class="size-3 text-blue-600" />
                                    {{ formatHours(course.hours) }}
                                </span>
                                <span class="inline-flex items-center gap-1 font-bold text-emerald-700 dark:text-emerald-400">
                                    <Users class="size-3 text-emerald-600" />
                                    Cupo: {{ course.capacity }} vacantes
                                </span>
                            </div>
                        </CardContent>

                        <CardFooter class="pt-3 border-t dark:border-neutral-800 flex items-center justify-between gap-2">
                            <Button as-child variant="outline" size="sm" class="flex-1 text-xs">
                                <Link :href="`/courses/${course.id}`">
                                    <Eye class="mr-1 size-3.5" />
                                    Ver curso
                                </Link>
                            </Button>
                            <Button
                                v-if="course.status === 'abierto'"
                                size="sm"
                                class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs"
                                @click="openEnroll(course)"
                            >
                                Inscribirme
                            </Button>
                            <div v-if="can.create" class="flex items-center gap-1">
                                <Button as-child variant="ghost" size="icon-sm" class="size-7 text-neutral-500 hover:text-neutral-900">
                                    <Link :href="`/courses/${course.id}/edit`">
                                        <Pencil class="size-3.5" />
                                    </Link>
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon-sm"
                                    class="size-7 text-red-500 hover:text-red-700 hover:bg-red-50 dark:hover:bg-red-950/40"
                                    @click="deleteCourse(course)"
                                >
                                    <Trash2 class="size-3.5" />
                                </Button>
                            </div>
                        </CardFooter>
                    </Card>
                </div>

                <!-- Empty State -->
                <div v-else class="text-center py-16 border rounded-xl bg-neutral-50/50 dark:bg-neutral-900/40 dark:border-neutral-800">
                    <BookOpen class="mx-auto size-12 text-neutral-400" />
                    <h3 class="mt-3 text-sm font-semibold text-neutral-900 dark:text-neutral-100">No se encontraron capacitaciones</h3>
                    <p class="mt-1 text-xs text-neutral-500">Prueba ajustando los términos de búsqueda o el filtro de estado.</p>
                    <div v-if="can.create" class="mt-4">
                        <Button as-child size="sm" class="bg-emerald-600 hover:bg-emerald-700 text-white">
                            <Link href="/courses/create">Crear primera capacitación</Link>
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
                                    'px-3 py-1.5 text-xs rounded-md border font-medium transition-colors',
                                    link.active
                                        ? 'bg-emerald-600 text-white border-emerald-600'
                                        : 'bg-white text-neutral-700 border-neutral-300 hover:bg-neutral-50 dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-300'
                                ]"
                                v-html="link.label"
                            />
                            <span
                                v-else
                                class="px-3 py-1.5 text-xs rounded-md border border-neutral-200 text-neutral-400 dark:border-neutral-800"
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
