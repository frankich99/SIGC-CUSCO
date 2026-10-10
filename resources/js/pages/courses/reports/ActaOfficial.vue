<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    Printer,
    ArrowLeft,
    ShieldCheck,
    Lock,
    Award,
    Users,
    CheckCircle2,
    XCircle,
    Calendar,
    GraduationCap,
} from '@lucide/vue';

interface CourseData {
    id: number;
    code: string;
    title: string;
    institution: string;
    hours: number;
    total_sessions: number;
    min_attendance_percentage: number;
    status: string;
    is_acta_closed: boolean;
    acta_closed_at?: string | null;
    date_range_formal: string;
    acta_date_formal: string;
    instructor_name: string;
}

interface StatsData {
    total_enrolled: number;
    approved_count: number;
    failed_count: number;
    in_progress_count: number;
    approval_rate: number;
    avg_grade: number | string;
    avg_attendance: number | string;
}

interface ParticipantData {
    id: number;
    dni: string;
    full_name: string;
    attended_sessions: number;
    attendance_percentage: number;
    final_grade: string;
    final_grade_text: string;
    status: string;
    certificate_code: string;
    certificate_hash: string;
}

const props = defineProps<{
    course: CourseData;
    stats: StatsData;
    participants: ParticipantData[];
}>();

function printDocument(): void {
    window.print();
}
</script>

<template>
    <Head :title="`Acta Oficial - ${course.code} - ${course.title}`" />

    <div
        class="min-h-screen bg-slate-100 font-sans text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100 print:bg-white print:text-black"
    >
        <!-- BARRA SUPERIOR DE ACCIONES (OCULTA AL IMPRIMIR) -->
        <header
            class="sticky top-0 z-50 border-b border-slate-200 bg-white/95 px-4 py-3 shadow-xs backdrop-blur dark:border-slate-800 dark:bg-slate-900/95 print:hidden"
        >
            <div
                class="mx-auto flex max-w-5xl items-center justify-between gap-4"
            >
                <div class="flex items-center gap-3">
                    <Button
                        as-child
                        variant="ghost"
                        size="sm"
                        class="text-xs font-bold text-slate-600 dark:text-slate-300"
                    >
                        <Link :href="`/courses/${course.id}`">
                            <ArrowLeft class="mr-1.5 size-4" />
                            Volver al Curso
                        </Link>
                    </Button>
                    <div
                        class="hidden h-4 w-px bg-slate-300 sm:block dark:bg-slate-700"
                    />
                    <span
                        class="hidden rounded border border-rose-300 bg-rose-100 px-2.5 py-0.5 font-mono text-xs font-black text-rose-950 sm:inline"
                    >
                        {{ course.code }}
                    </span>
                    <span
                        class="hidden max-w-xs truncate text-xs font-bold text-slate-600 md:inline md:max-w-md dark:text-slate-400"
                    >
                        {{ course.title }}
                    </span>
                </div>

                <div class="flex items-center gap-2">
                    <Button
                        type="button"
                        size="sm"
                        class="cursor-pointer bg-rose-900 text-xs font-bold text-white shadow-sm hover:bg-rose-950"
                        @click="printDocument"
                    >
                        <Printer class="mr-1.5 size-4" />
                        Imprimir / Guardar en PDF
                    </Button>
                </div>
            </div>
        </header>

        <!-- CONTENIDO DEL DOCUMENTO A4 IMPRIMIBLE -->
        <main class="mx-auto max-w-5xl p-4 sm:p-8 print:max-w-none print:p-0">
            <article
                class="rounded-xl border border-slate-200 bg-white p-8 text-slate-950 shadow-md sm:p-12 print:rounded-none print:border-none print:p-0 print:shadow-none"
            >
                <!-- MEMBRETE OFICIAL UNSAAC -->
                <header class="mb-6 border-b-2 border-rose-950 pb-6">
                    <div class="flex items-center justify-between gap-6">
                        <div class="flex items-center gap-4">
                            <div
                                class="flex size-16 items-center justify-center rounded-xl border border-rose-800 bg-gradient-to-br from-rose-950 via-rose-900 to-rose-950 font-serif text-2xl font-black text-white shadow-sm print:border-black"
                            >
                                U
                            </div>
                            <div>
                                <h2
                                    class="text-xs font-black tracking-widest text-rose-950 uppercase sm:text-sm"
                                >
                                    Universidad Nacional de San Antonio Abad del
                                    Cusco
                                </h2>
                                <h3
                                    class="text-[11px] font-bold tracking-wider text-slate-600 uppercase sm:text-xs"
                                >
                                    Sistema Integral de Gestión de
                                    Capacitaciones — SIGC CUSCO
                                </h3>
                                <p
                                    class="text-[10px] font-medium text-slate-500"
                                >
                                    Vicerrectorado Académico · Registro Oficial
                                    de Certificaciones
                                </p>
                            </div>
                        </div>

                        <div class="shrink-0 text-right">
                            <div
                                class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-black tracking-wider uppercase"
                                :class="
                                    course.is_acta_closed
                                        ? 'border-emerald-300 bg-emerald-50 text-emerald-900'
                                        : 'border-amber-300 bg-amber-50 text-amber-900'
                                "
                            >
                                <Lock
                                    v-if="course.is_acta_closed"
                                    class="size-3.5 text-emerald-700"
                                />
                                <span>{{
                                    course.is_acta_closed
                                        ? 'Acta Cerrada Oficialmente'
                                        : 'Acta en Edición'
                                }}</span>
                            </div>
                            <div
                                class="mt-1 font-mono text-[10px] text-slate-500"
                            >
                                Cód. Registro: {{ course.code }}
                            </div>
                        </div>
                    </div>

                    <!-- TÍTULO PRINCIPAL DEL ACTA -->
                    <div class="mt-6 text-center">
                        <h1
                            class="text-xl font-black tracking-wide text-slate-950 uppercase sm:text-2xl"
                        >
                            Acta Oficial de Evaluación y Asistencia
                        </h1>
                        <p
                            class="mt-1 text-xs font-semibold text-rose-950 sm:text-sm"
                        >
                            {{ course.title }}
                        </p>
                    </div>
                </header>

                <!-- METADATOS DEL CURSO -->
                <section
                    class="mb-6 grid grid-cols-2 gap-3 rounded-lg border border-slate-200 bg-slate-50 p-4 text-xs md:grid-cols-4 print:bg-slate-50"
                >
                    <div>
                        <span
                            class="block text-[10px] font-bold text-slate-500 uppercase"
                            >Docente Titular:</span
                        >
                        <span class="font-bold text-slate-900">{{
                            course.instructor_name
                        }}</span>
                    </div>
                    <div>
                        <span
                            class="block text-[10px] font-bold text-slate-500 uppercase"
                            >Horas Académicas:</span
                        >
                        <span class="font-bold text-slate-900"
                            >{{ course.hours }} horas lectivas ({{
                                course.total_sessions
                            }}
                            sesiones)</span
                        >
                    </div>
                    <div>
                        <span
                            class="block text-[10px] font-bold text-slate-500 uppercase"
                            >Período de Ejecución:</span
                        >
                        <span class="font-bold text-slate-900">{{
                            course.date_range_formal
                        }}</span>
                    </div>
                    <div>
                        <span
                            class="block text-[10px] font-bold text-slate-500 uppercase"
                            >Criterio Aprobatorio:</span
                        >
                        <span class="font-bold text-slate-900"
                            >Asistencia &ge;
                            {{ course.min_attendance_percentage }}% y Nota &ge;
                            11.00</span
                        >
                    </div>
                </section>

                <!-- CUADRO DE RESUMEN ESTADÍSTICO -->
                <section class="mb-6 grid grid-cols-2 gap-2 sm:grid-cols-5">
                    <div class="rounded-lg border bg-slate-50 p-3 text-center">
                        <div
                            class="text-[10px] font-bold text-slate-500 uppercase"
                        >
                            Matriculados
                        </div>
                        <div class="text-lg font-black text-slate-900">
                            {{ stats.total_enrolled }}
                        </div>
                    </div>
                    <div
                        class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-center"
                    >
                        <div
                            class="text-[10px] font-bold text-emerald-800 uppercase"
                        >
                            Aprobados
                        </div>
                        <div class="text-lg font-black text-emerald-900">
                            {{ stats.approved_count }}
                        </div>
                    </div>
                    <div
                        class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-center"
                    >
                        <div
                            class="text-[10px] font-bold text-rose-800 uppercase"
                        >
                            Reprobados
                        </div>
                        <div class="text-lg font-black text-rose-900">
                            {{ stats.failed_count }}
                        </div>
                    </div>
                    <div
                        class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-center"
                    >
                        <div
                            class="text-[10px] font-bold text-amber-800 uppercase"
                        >
                            % Aprobación
                        </div>
                        <div class="text-lg font-black text-amber-900">
                            {{ stats.approval_rate }}%
                        </div>
                    </div>
                    <div
                        class="col-span-2 rounded-lg border bg-slate-50 p-3 text-center sm:col-span-1"
                    >
                        <div
                            class="text-[10px] font-bold text-slate-500 uppercase"
                        >
                            Nota Promedio
                        </div>
                        <div class="text-lg font-black text-slate-900">
                            {{ stats.avg_grade }}
                        </div>
                    </div>
                </section>

                <!-- TABLA OFICIAL DE NOTAS Y ASISTENCIA -->
                <section class="mb-8">
                    <div class="overflow-x-auto">
                        <table
                            class="w-full border-collapse border border-slate-300 text-left text-[11px]"
                        >
                            <thead>
                                <tr
                                    class="bg-rose-950 text-[10px] font-bold tracking-wider text-white uppercase"
                                >
                                    <th
                                        class="w-8 border border-rose-900 px-2 py-2 text-center"
                                    >
                                        N°
                                    </th>
                                    <th
                                        class="w-20 border border-rose-900 px-2.5 py-2 text-center font-mono"
                                    >
                                        DNI
                                    </th>
                                    <th
                                        class="border border-rose-900 px-3 py-2"
                                    >
                                        Apellidos y Nombres
                                    </th>
                                    <th
                                        class="w-16 border border-rose-900 px-2 py-2 text-center"
                                    >
                                        Asist. (%)
                                    </th>
                                    <th
                                        class="w-14 border border-rose-900 px-2 py-2 text-center"
                                    >
                                        Nota
                                    </th>
                                    <th
                                        class="border border-rose-900 px-3 py-2"
                                    >
                                        Nota en Letras
                                    </th>
                                    <th
                                        class="w-24 border border-rose-900 px-2 py-2 text-center"
                                    >
                                        Condición
                                    </th>
                                    <th
                                        class="border border-rose-900 px-3 py-2 text-center font-mono text-[9px]"
                                    >
                                        Código Diploma
                                    </th>
                                    <th
                                        class="w-28 border border-rose-900 px-2 py-2 text-center font-mono text-[8px]"
                                    >
                                        Firma HMAC
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(p, index) in participants"
                                    :key="p.id"
                                    class="border-b border-slate-200 transition-colors hover:bg-slate-50/80"
                                    :class="
                                        index % 2 === 1
                                            ? 'bg-slate-50/50'
                                            : 'bg-white'
                                    "
                                >
                                    <td
                                        class="border border-slate-300 px-2 py-1.5 text-center font-bold text-slate-600"
                                    >
                                        {{ index + 1 }}
                                    </td>
                                    <td
                                        class="border border-slate-300 px-2.5 py-1.5 text-center font-mono font-bold text-slate-800"
                                    >
                                        {{ p.dni }}
                                    </td>
                                    <td
                                        class="border border-slate-300 px-3 py-1.5 font-bold text-slate-900 uppercase"
                                    >
                                        {{ p.full_name }}
                                    </td>
                                    <td
                                        class="border border-slate-300 px-2 py-1.5 text-center font-semibold"
                                    >
                                        {{ p.attendance_percentage }}%
                                    </td>
                                    <td
                                        class="border border-slate-300 px-2 py-1.5 text-center font-mono font-black"
                                        :class="
                                            p.status === 'aprobado'
                                                ? 'text-emerald-700'
                                                : 'text-rose-700'
                                        "
                                    >
                                        {{ p.final_grade }}
                                    </td>
                                    <td
                                        class="border border-slate-300 px-3 py-1.5 text-[10px] text-slate-700 capitalize"
                                    >
                                        {{ p.final_grade_text }}
                                    </td>
                                    <td
                                        class="border border-slate-300 px-2 py-1.5 text-center text-[10px] font-bold"
                                    >
                                        <span
                                            class="inline-block rounded px-1.5 py-0.5 text-[9px] font-black uppercase"
                                            :class="
                                                p.status === 'aprobado'
                                                    ? 'bg-emerald-100 text-emerald-900'
                                                    : p.status === 'reprobado'
                                                      ? 'bg-rose-100 text-rose-900'
                                                      : 'bg-slate-100 text-slate-700'
                                            "
                                        >
                                            {{ p.status }}
                                        </span>
                                    </td>
                                    <td
                                        class="border border-slate-300 px-2 py-1.5 text-center font-mono text-[9px] font-bold text-slate-800"
                                    >
                                        {{ p.certificate_code }}
                                    </td>
                                    <td
                                        class="max-w-[110px] truncate border border-slate-300 px-2 py-1.5 text-center font-mono text-[8px] text-slate-500"
                                        :title="p.certificate_hash"
                                    >
                                        {{
                                            p.certificate_hash !== '-'
                                                ? p.certificate_hash.slice(
                                                      0,
                                                      14,
                                                  ) + '...'
                                                : '-'
                                        }}
                                    </td>
                                </tr>
                                <tr v-if="participants.length === 0">
                                    <td
                                        colspan="9"
                                        class="border border-slate-300 px-4 py-8 text-center text-slate-500 italic"
                                    >
                                        No hay participantes registrados en esta
                                        capacitación.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- FECHA Y FIRMAS OFICIALES -->
                <footer class="border-t border-slate-200 pt-6">
                    <div class="mb-12 text-right text-xs text-slate-600">
                        Cusco, {{ course.acta_date_formal }}
                    </div>

                    <div
                        class="grid grid-cols-3 gap-8 pt-8 text-center text-xs"
                    >
                        <div>
                            <div
                                class="mx-auto max-w-[200px] border-t border-slate-800 pt-2"
                            >
                                <div
                                    class="text-[11px] font-bold text-slate-900 uppercase"
                                >
                                    {{ course.instructor_name }}
                                </div>
                                <div class="text-[10px] text-slate-500">
                                    Docente Titular / Responsable
                                </div>
                            </div>
                        </div>
                        <div>
                            <div
                                class="mx-auto max-w-[200px] border-t border-slate-800 pt-2"
                            >
                                <div
                                    class="text-[11px] font-bold text-slate-900 uppercase"
                                >
                                    Coordinación Académica
                                </div>
                                <div class="text-[10px] text-slate-500">
                                    Comité de Capacitaciones UNSAAC
                                </div>
                            </div>
                        </div>
                        <div>
                            <div
                                class="mx-auto max-w-[200px] border-t border-slate-800 pt-2"
                            >
                                <div
                                    class="text-[11px] font-bold text-slate-900 uppercase"
                                >
                                    Dirección General
                                </div>
                                <div class="text-[10px] text-slate-500">
                                    Sistema SIGC - UNSAAC
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        class="mt-8 flex items-center justify-between border-t border-slate-100 pt-4 font-mono text-[9px] text-slate-400"
                    >
                        <span
                            >UNSAAC · Sistema Integral de Gestión de
                            Capacitaciones</span
                        >
                        <span
                            >Documento Oficial Certificado
                            Criptográficamente</span
                        >
                    </div>
                </footer>
            </article>
        </main>
    </div>
</template>

<style scoped>
@media print {
    @page {
        size: A4 portrait;
        margin: 12mm 10mm;
    }

    body {
        background: white !important;
        color: black !important;
    }
}
</style>
