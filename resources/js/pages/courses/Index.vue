<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import EnrollmentModal from '@/components/EnrollmentModal.vue';
import { formatDateRange, formatHours } from '@/lib/formatters';
import { THEME_BADGES, THEME_BUTTONS, getCourseStatusBadge } from '@/lib/theme';
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
    enrollments_count?: number;
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
            status:
                selectedStatus.value !== 'all'
                    ? selectedStatus.value
                    : undefined,
        },
        {
            preserveState: true,
            replace: true,
        },
    );
}

function deleteCourse(course: CourseItem) {
    if (
        confirm(`¿Estás seguro de eliminar la capacitación "${course.title}"?`)
    ) {
        router.delete(`/courses/${course.id}`, {
            preserveScroll: true,
        });
    }
}

function getStatusBadge(status: string) {
    return getCourseStatusBadge(status);
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

        <div
            class="mx-auto w-full max-w-7xl min-w-0 space-y-6 overflow-x-hidden px-4 py-6 sm:px-6 sm:py-8 lg:px-8"
        >
            <!-- Header Section -->
            <div
                class="flex flex-col gap-4 border-b pb-5 sm:flex-row sm:items-center sm:justify-between dark:border-neutral-800"
            >
                <div>
                    <h1
                        class="flex items-center gap-2 text-2xl font-bold tracking-tight text-slate-900 dark:text-neutral-100"
                    >
                        <GraduationCap
                            class="size-7 text-rose-900 dark:text-rose-400"
                        />
                        Capacitaciones y Cursos
                    </h1>
                    <p
                        class="mt-1 text-sm font-medium text-slate-600 dark:text-neutral-400"
                    >
                        Control del ciclo formativo: vacantes, docentes, código
                        QR y certificaciones.
                    </p>
                </div>
                <div v-if="can.create">
                    <Button
                        as-child
                        class="h-9 bg-rose-900 px-4 text-xs font-black text-white shadow-xs hover:bg-rose-950"
                    >
                        <Link
                            href="/courses/create"
                            class="flex items-center gap-1.5"
                        >
                            <Plus class="size-4 stroke-[2.5] text-amber-300" />
                            <span>Agregar Curso</span>
                        </Link>
                    </Button>
                </div>
            </div>

            <!-- Main Catalog with Filters -->
            <div class="space-y-6">
                <!-- Search and Status Bar -->
                <div class="flex flex-col gap-3 sm:flex-row">
                    <div class="relative min-w-0 flex-1">
                        <Search
                            class="absolute top-2.5 left-3 size-4 text-slate-500"
                        />
                        <Input
                            v-model="searchQuery"
                            placeholder="Buscar por título, código o contenido..."
                            class="pl-9 font-medium text-slate-900"
                            @keydown.enter="applyFilters"
                        />
                    </div>
                    <div
                        class="custom-scrollbar flex max-w-full min-w-0 items-center gap-2 overflow-x-auto pb-1 sm:pb-0"
                    >
                        <button
                            v-for="st in [
                                { value: 'all', label: 'Todos' },
                                ...statuses,
                            ]"
                            :key="st.value"
                            type="button"
                            @click="
                                selectedStatus = st.value;
                                applyFilters();
                            "
                            :class="[
                                'shrink-0 rounded-xl px-3 py-1.5 text-xs font-bold transition-colors',
                                selectedStatus === st.value
                                    ? 'bg-rose-900 text-white shadow-xs dark:bg-rose-800'
                                    : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300',
                            ]"
                        >
                            {{ st.label }}
                        </button>
                    </div>
                </div>

                <!-- Courses Grid -->
                <div
                    v-if="courses.data.length > 0"
                    class="grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-3"
                >
                    <Card
                        v-for="course in courses.data"
                        :key="course.id"
                        class="flex max-w-full min-w-0 flex-col justify-between overflow-hidden border-slate-200 transition-all hover:border-rose-400/80 hover:shadow-md dark:border-neutral-800"
                    >
                        <CardHeader
                            class="max-w-full min-w-0 space-y-2 p-4 pb-3 sm:p-5"
                        >
                            <div
                                class="flex w-full min-w-0 items-center justify-between gap-1.5"
                            >
                                <span
                                    class="shrink-0 rounded-full border border-rose-200 bg-rose-50 px-2.5 py-0.5 font-mono text-xs font-bold text-rose-900 dark:border-rose-800 dark:bg-rose-950/60 dark:text-rose-300"
                                >
                                    {{ course.code }}
                                </span>
                                <span
                                    :class="[
                                        'max-w-[62%] shrink-0 truncate rounded-full border px-2.5 py-0.5 text-center text-[10px] font-bold sm:text-[11px]',
                                        getStatusBadge(course.status).class,
                                    ]"
                                >
                                    {{ getStatusBadge(course.status).label }}
                                </span>
                            </div>

                            <!-- Organizing Entity / Institution -->
                            <div
                                v-if="course.institution"
                                class="flex w-full max-w-full min-w-0 items-center gap-1.5 overflow-hidden rounded-xl border border-rose-200/80 bg-rose-50/80 px-2.5 py-1 text-xs font-semibold text-rose-950 dark:border-rose-800/60 dark:bg-rose-950/40 dark:text-rose-200"
                            >
                                <Building2
                                    class="size-3.5 shrink-0 text-rose-800 dark:text-rose-400"
                                />
                                <span class="block min-w-0 flex-1 truncate">{{
                                    course.institution
                                }}</span>
                            </div>

                            <CardTitle
                                class="line-clamp-2 min-w-0 text-base leading-snug font-bold break-words text-slate-900 dark:text-white"
                            >
                                {{ course.title }}
                            </CardTitle>
                            <CardDescription
                                class="mt-1 line-clamp-2 min-w-0 text-xs break-words"
                            >
                                {{
                                    course.description ||
                                    'Sin descripción detallada.'
                                }}
                            </CardDescription>
                        </CardHeader>

                        <CardContent
                            class="max-w-full min-w-0 space-y-2.5 overflow-hidden border-t px-4 pt-3 text-xs text-neutral-600 sm:px-5 dark:border-neutral-800 dark:text-neutral-400"
                        >
                            <div
                                class="flex max-w-full min-w-0 items-center gap-2"
                            >
                                <User
                                    class="size-3.5 shrink-0 text-neutral-400"
                                />
                                <span class="min-w-0 flex-1 truncate">
                                    <strong
                                        class="font-medium text-neutral-700 dark:text-neutral-300"
                                        >Ponente / Docente:</strong
                                    >
                                    {{
                                        instructorName(course.instructor) ||
                                        course.instructor_name ||
                                        'Por asignar'
                                    }}
                                </span>
                            </div>
                            <div
                                class="flex min-w-0 items-center gap-2 font-semibold text-slate-800 dark:text-slate-200"
                            >
                                <Calendar
                                    class="size-3.5 shrink-0 text-rose-800 dark:text-rose-400"
                                />
                                <span class="truncate">{{
                                    formatDateRange(
                                        course.start_date,
                                        course.end_date,
                                        'compact',
                                    )
                                }}</span>
                            </div>
                            <div
                                class="flex min-w-0 items-center justify-between gap-2 pt-1 text-[11px]"
                            >
                                <span
                                    class="inline-flex shrink-0 items-center gap-1 font-bold text-slate-800 dark:text-slate-200"
                                >
                                    <Clock
                                        class="size-3 shrink-0 text-amber-700 dark:text-amber-400"
                                    />
                                    {{ formatHours(course.hours) }}
                                </span>
                                <span
                                    class="inline-flex shrink-0 items-center gap-1 font-bold text-rose-900 dark:text-rose-300"
                                >
                                    <Users
                                        class="size-3 shrink-0 text-rose-800 dark:text-rose-400"
                                    />
                                    Cupo: {{ course.capacity }} vacantes
                                </span>
                            </div>
                        </CardContent>

                        <CardFooter
                            class="flex max-w-full min-w-0 flex-wrap items-center justify-between gap-2 border-t px-4 pt-3 pb-4 sm:px-5 dark:border-neutral-800"
                        >
                            <Button
                                as-child
                                variant="outline"
                                size="sm"
                                class="min-w-[90px] flex-1 text-xs font-semibold"
                            >
                                <Link :href="`/courses/${course.id}`">
                                    <Eye class="mr-1 size-3.5" />
                                    Ver curso
                                </Link>
                            </Button>

                            <!-- Si el participante ya está matriculado en esta capacitación -->
                            <Button
                                v-if="
                                    authUser &&
                                    myEnrolledCourseIds?.includes(course.id)
                                "
                                as-child
                                size="sm"
                                variant="outline"
                                class="min-w-[100px] flex-1 border-emerald-600 bg-emerald-50 text-xs font-black text-emerald-800 dark:border-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300"
                            >
                                <Link :href="`/courses/${course.id}`">
                                    <CheckCircle2
                                        class="mr-1 size-3.5 text-emerald-600"
                                    />
                                    Matriculado
                                </Link>
                            </Button>

                            <!-- Si el usuario es el docente responsable o administrador del curso -->
                            <Button
                                v-else-if="
                                    authUser &&
                                    (authUser.role === 'admin' ||
                                        (authUser.role === 'docente' &&
                                            course.instructor?.id ===
                                                authUser.id))
                                "
                                as-child
                                size="sm"
                                class="min-w-[100px] flex-1 bg-amber-700 text-xs font-bold text-white shadow-xs hover:bg-amber-800"
                            >
                                <Link :href="`/courses/${course.id}`">
                                    <BookOpen class="mr-1 size-3.5" />
                                    Gestionar
                                </Link>
                            </Button>

                            <!-- Si la convocatoria está abierta pero se agotaron los cupos -->
                            <Button
                                v-else-if="
                                    course.status === 'abierto' &&
                                    course.enrollments_count !== undefined &&
                                    course.capacity -
                                        course.enrollments_count <=
                                        0
                                "
                                size="sm"
                                variant="secondary"
                                disabled
                                class="min-w-[100px] flex-1 text-xs font-semibold opacity-75"
                            >
                                Agotado
                            </Button>

                            <!-- Si la convocatoria está abierta para postulaciones -->
                            <Button
                                v-else-if="course.status === 'abierto'"
                                size="sm"
                                class="min-w-[100px] flex-1 cursor-pointer bg-rose-900 text-xs font-bold text-white shadow-xs hover:bg-rose-950"
                                @click="openEnroll(course)"
                            >
                                Inscribirme
                            </Button>
                            <div
                                v-if="can.create"
                                class="flex shrink-0 items-center gap-1"
                            >
                                <Button
                                    as-child
                                    variant="ghost"
                                    size="icon-sm"
                                    class="size-7 text-slate-600 hover:text-slate-900"
                                >
                                    <Link :href="`/courses/${course.id}/edit`">
                                        <Pencil class="size-3.5" />
                                    </Link>
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon-sm"
                                    class="size-7 text-red-600 hover:bg-red-50 hover:text-red-800 dark:hover:bg-red-950/40"
                                    @click="deleteCourse(course)"
                                >
                                    <Trash2 class="size-3.5" />
                                </Button>
                            </div>
                        </CardFooter>
                    </Card>
                </div>

                <!-- Empty State -->
                <div
                    v-else
                    class="rounded-xl border bg-slate-50/50 py-16 text-center dark:border-neutral-800 dark:bg-neutral-900/40"
                >
                    <BookOpen class="mx-auto size-12 text-slate-400" />
                    <h3
                        class="mt-3 text-sm font-bold text-slate-900 dark:text-neutral-100"
                    >
                        No se encontraron capacitaciones
                    </h3>
                    <p class="mt-1 text-xs font-medium text-slate-600">
                        Prueba ajustando los términos de búsqueda o el filtro de
                        estado.
                    </p>
                    <div v-if="can.create" class="mt-4">
                        <Button
                            as-child
                            size="sm"
                            class="bg-rose-900 text-xs font-bold text-white shadow-xs hover:bg-rose-950"
                        >
                            <Link
                                href="/courses/create"
                                class="flex items-center gap-1.5"
                            >
                                <Plus
                                    class="size-3.5 stroke-[2.5] text-amber-300"
                                />
                                <span>Agregar Curso</span>
                            </Link>
                        </Button>
                    </div>
                </div>

                <!-- Pagination -->
                <div
                    v-if="courses.total > courses.data.length"
                    class="flex justify-center pt-4"
                >
                    <div class="flex gap-1">
                        <template v-for="(link, i) in courses.links" :key="i">
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                :class="[
                                    'rounded-xl border px-3 py-1.5 text-xs font-bold transition-colors',
                                    link.active
                                        ? 'border-rose-900 bg-rose-900 text-white shadow-xs'
                                        : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-300',
                                ]"
                                v-html="link.label"
                            />
                            <span
                                v-else
                                class="rounded-xl border border-neutral-200 px-3 py-1.5 text-xs text-neutral-400 dark:border-neutral-800"
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
