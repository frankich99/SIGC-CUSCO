<script setup lang="ts">
import { ref } from 'vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { formatDate, formatDateRange, formatHours } from '@/lib/formatters';
import {
    Award,
    Search,
    Loader2,
    Calendar,
    Clock,
    CheckCircle2,
    Download,
    Building2,
    AlertCircle,
    FileCheck,
} from '@lucide/vue';

interface CertificateRecord {
    id: number;
    course_code: string;
    course_title: string;
    institution: string;
    hours: number;
    start_date: string;
    end_date: string;
    instructor_name: string;
    student_name: string;
    status: string;
    attended_sessions: number;
    final_grade?: number | string | null;
    certificate_code: string;
}

const dniQuery = ref('');
const loading = ref(false);
const searched = ref(false);
const error = ref<string | null>(null);
const studentName = ref<string | null>(null);
const records = ref<CertificateRecord[]>([]);

async function searchCertificates() {
    const clean = dniQuery.value.trim();
    if (!/^\d{8}$/.test(clean)) {
        error.value = 'Ingrese un número de DNI válido de 8 dígitos numéricos.';
        return;
    }

    loading.value = true;
    error.value = null;
    records.value = [];
    searched.value = true;

    try {
        const response = await fetch(`/api/certificates/lookup?dni=${clean}`, {
            headers: {
                Accept: 'application/json',
            },
        });

        const data = await response.json();

        if (response.ok && data.success) {
            records.value = data.records || [];
            if (records.value.length > 0) {
                studentName.value = records.value[0].student_name;
            } else {
                studentName.value = null;
            }
        } else {
            error.value = data.message || 'No se pudo consultar el registro de certificados.';
        }
    } catch {
        error.value = 'Error al comunicarse con el servidor. Intente nuevamente.';
    } finally {
        loading.value = false;
    }
}

function printMockCertificate(record: CertificateRecord) {
    const printWindow = window.open('', '_blank');
    if (!printWindow) return;

    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Certificado - ${record.course_title}</title>
            <style>
                body { font-family: 'Times New Roman', serif; text-align: center; padding: 40px; background: #fafafa; }
                .cert-container { border: 8px double #800020; padding: 40px; background: #fff; max-width: 800px; margin: 0 auto; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
                h1 { color: #800020; font-size: 32px; margin-bottom: 5px; text-transform: uppercase; letter-spacing: 2px; }
                h2 { font-size: 18px; color: #4b5563; font-style: italic; margin-top: 0; }
                .recipient { font-size: 26px; font-weight: bold; color: #111827; margin: 25px 0; border-bottom: 2px solid #800020; display: inline-block; padding: 0 30px; }
                .course { font-size: 20px; font-weight: bold; color: #800020; margin: 15px 0; }
                .details { font-size: 14px; color: #374151; line-height: 1.6; margin: 20px auto; max-width: 600px; }
                .footer { margin-top: 40px; display: flex; justify-content: space-around; font-size: 12px; }
                .sig { border-top: 1px solid #9ca3af; padding-top: 5px; width: 200px; }
                .code { font-family: monospace; font-size: 11px; color: #6b7280; margin-top: 25px; }
            </style>
        </head>
        <body>
            <div class="cert-container">
                <h1>Certificado de Aprobación</h1>
                <h2>${record.institution || 'SIGC-CUSCO'}</h2>
                <p>Otorga el presente documento a:</p>
                <div class="recipient">${record.student_name}</div>
                <p>Por haber participado y aprobado satisfactoriamente la capacitación:</p>
                <div class="course">"${record.course_title}"</div>
                <div class="details">
                    Con una duración lectiva de <strong>${formatHours(record.hours)}</strong>,
                    desarrollada del ${formatDateRange(record.start_date, record.end_date, 'medium')},
                    habiendo cumplido con los requisitos de asistencia por QR y evaluación oficial.
                </div>
                <div class="footer">
                    <div class="sig">${record.instructor_name}<br><small>Docente Instructor</small></div>
                    <div class="sig">Dirección Académica<br><small>SIGC-CUSCO</small></div>
                </div>
                <div class="code">Código de Verificación Digital: ${record.certificate_code}</div>
            </div>
            <script>window.print();<\/script>
        </body>
        </html>
    `);
    printWindow.document.close();
}
</script>

<template>
    <Card class="border border-slate-200 dark:border-neutral-800 shadow-sm overflow-hidden">
        <CardHeader class="bg-gradient-to-r from-rose-50/90 via-amber-50/30 to-transparent dark:from-rose-950/40 dark:to-neutral-900 border-b pb-4">
            <div class="flex items-center gap-1.5 text-xs font-bold text-rose-900 dark:text-rose-300 uppercase tracking-wider mb-1">
                <Award class="size-4 text-rose-800" />
                <span>Validación Oficial UNSAAC / Perú</span>
            </div>
            <CardTitle class="text-lg sm:text-xl font-bold tracking-tight text-slate-900 dark:text-white">
                Consulta y Descarga de Certificados
            </CardTitle>
            <CardDescription class="text-xs text-slate-600 dark:text-neutral-400 font-medium">
                Ingresa tu DNI para consultar tus cursos aprobados y obtener tu certificado digital oficial.
            </CardDescription>
        </CardHeader>

        <CardContent class="p-4 sm:p-6 space-y-6">
            <!-- Search bar -->
            <form @submit.prevent="searchCertificates" class="flex flex-col sm:flex-row gap-2 max-w-md">
                <div class="relative flex-1">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-neutral-400" />
                    <Input
                        v-model="dniQuery"
                        type="text"
                        maxlength="8"
                        placeholder="Ingresa tu DNI (8 dígitos)"
                        class="pl-9 font-mono text-sm tracking-wider"
                        :disabled="loading"
                    />
                </div>
                <Button
                    type="submit"
                    class="bg-rose-900 hover:bg-rose-950 text-white font-bold text-xs shrink-0 shadow-xs"
                    :disabled="loading || dniQuery.trim().length !== 8"
                >
                    <Loader2 v-if="loading" class="size-3.5 mr-1.5 animate-spin" />
                    <Search v-else class="size-3.5 mr-1.5" />
                    Consultar
                </Button>
            </form>

            <!-- Error message -->
            <div v-if="error" class="p-3 rounded-lg bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900 text-rose-800 dark:text-rose-200 text-xs flex items-center gap-2 font-medium">
                <AlertCircle class="size-4 shrink-0 text-rose-700" />
                <span>{{ error }}</span>
            </div>

            <!-- Empty result state -->
            <div v-else-if="searched && !loading && records.length === 0" class="p-8 text-center border border-dashed rounded-xl space-y-2 bg-slate-50/50 dark:bg-neutral-900/40">
                <Award class="size-10 text-slate-400 mx-auto" />
                <h4 class="text-sm font-bold text-slate-900 dark:text-white">No se encontraron capacitaciones para el DNI {{ dniQuery }}</h4>
                <p class="text-xs text-slate-600 dark:text-neutral-400 max-w-sm mx-auto font-medium">
                    Asegúrate de haber ingresado correctamente tu número de DNI o inscríbete a una capacitación activa para obtener tu certificado.
                </p>
            </div>

            <!-- Records Results -->
            <div v-else-if="records.length > 0" class="space-y-4">
                <div class="flex items-center justify-between pb-2 border-b border-slate-200 dark:border-neutral-800">
                    <div class="text-xs">
                        <span class="text-slate-600 dark:text-neutral-400 font-medium">Participante:</span>
                        <strong class="text-slate-900 dark:text-white ml-1.5 font-bold">{{ studentName }}</strong>
                    </div>
                    <span class="text-xs text-rose-900 dark:text-rose-400 font-bold">
                        {{ records.length }} registro(s)
                    </span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div
                        v-for="cert in records"
                        :key="cert.id"
                        class="p-4 rounded-xl border border-slate-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 flex flex-col justify-between space-y-3 hover:border-rose-400/80 transition-all shadow-xs"
                    >
                        <div class="space-y-2">
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-mono text-[10px] font-bold bg-slate-100 dark:bg-neutral-800 text-slate-800 dark:text-neutral-200 px-2 py-0.5 rounded border border-slate-200 dark:border-neutral-700">
                                    {{ cert.course_code }}
                                </span>
                                <Badge
                                    variant="outline"
                                    class="text-[11px] font-bold capitalize"
                                    :class="cert.status === 'aprobado' ? 'bg-rose-50 text-rose-900 border-rose-200 dark:bg-rose-950/60 dark:text-rose-300' : 'bg-blue-50 text-blue-800 border-blue-200'"
                                >
                                    {{ cert.status }}
                                </Badge>
                            </div>

                            <h4 class="text-sm font-bold text-slate-900 dark:text-white line-clamp-2 leading-snug">
                                {{ cert.course_title }}
                            </h4>

                            <!-- Organizing Entity -->
                            <div class="flex items-center gap-1.5 text-xs text-slate-700 dark:text-neutral-300">
                                <Building2 class="size-3.5 text-rose-800 dark:text-rose-400 shrink-0" />
                                <span class="font-semibold text-slate-900 dark:text-neutral-100 truncate">
                                    {{ cert.institution }}
                                </span>
                            </div>

                            <div class="grid grid-cols-2 gap-1 text-[11px] font-bold text-slate-800 dark:text-slate-200 pt-1">
                                <span class="flex items-center gap-1">
                                    <Clock class="size-3.5 text-amber-700 dark:text-amber-400" />
                                    {{ formatHours(cert.hours) }}
                                </span>
                                <span class="flex items-center gap-1">
                                    <Calendar class="size-3.5 text-rose-800 dark:text-rose-400" />
                                    {{ formatDate(cert.start_date, 'compact') }}
                                </span>
                            </div>

                            <div class="p-2.5 rounded bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-[11px] space-y-0.5 font-mono text-slate-800 dark:text-slate-200">
                                <div>Docente: <span class="font-bold">{{ cert.instructor_name }}</span></div>
                                <div>Código: <strong class="text-rose-900 dark:text-rose-300 font-bold">{{ cert.certificate_code }}</strong></div>
                            </div>
                        </div>

                        <div class="pt-2 border-t dark:border-neutral-800 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                            <span class="text-[11px] text-slate-600 dark:text-neutral-400 font-medium">
                                Asistencia: <strong>{{ cert.attended_sessions }} ses.</strong>
                            </span>
                            <Button
                                size="sm"
                                class="bg-rose-900 hover:bg-rose-950 text-white text-xs h-8 shadow-xs w-full sm:w-auto font-bold"
                                @click="printMockCertificate(cert)"
                            >
                                <Download class="size-3.5 mr-1" />
                                Descargar PDF
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
