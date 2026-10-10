<script setup lang="ts">
import { ref, onMounted } from 'vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { formatDate, formatDateRange, formatHours } from '@/lib/formatters';
import { THEME_BUTTONS } from '@/lib/theme';
import PdfIntegrityVerifier from '@/components/PdfIntegrityVerifier.vue';
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
    Eye,
    Printer,
    Copy,
    Check,
    QrCode,
    ShieldCheck,
    FileCheck,
    ExternalLink,
    BookOpen,
    Share2,
    Sparkles,
} from '@lucide/vue';
import {
    type CertificateRecord,
    printCertificate,
    formatSpanishDate,
    formatSpanishDateRange,
} from '@/lib/certificateTemplate';

export type { CertificateRecord };

const dniQuery = ref('');
const loading = ref(false);
const searched = ref(false);
const error = ref<string | null>(null);
const studentName = ref<string | null>(null);
const records = ref<CertificateRecord[]>([]);

// Preview Modal State
const selectedCert = ref<CertificateRecord | null>(null);
const isPreviewModalOpen = ref(false);
const previewTab = ref<'anverso' | 'reverso'>('anverso');
const copiedCode = ref(false);
const copiedUrl = ref(false);

onMounted(() => {
    // Si viene con parámetro ?dni=12345678 o ?code=... en la URL pública
    const params = new URLSearchParams(window.location.search);
    const dniParam = params.get('dni');
    const codeParam = params.get('code');
    if (codeParam) {
        searchCertificates(codeParam.trim());
    } else if (dniParam && /^\d{8}$/.test(dniParam.trim())) {
        dniQuery.value = dniParam.trim();
        searchCertificates();
    }
});

function onDniInput(e: Event) {
    const target = e.target as HTMLInputElement;
    dniQuery.value = target.value.replace(/\D/g, '').slice(0, 8);
    if (error.value) error.value = null;
}

async function searchCertificates(targetCode?: string) {
    const clean = dniQuery.value.trim();
    const code = targetCode?.trim() || '';

    if (!code && !/^\d{8}$/.test(clean)) {
        error.value =
            'Ingrese un número de DNI válido de exactamente 8 dígitos numéricos.';
        return;
    }

    loading.value = true;
    error.value = null;
    records.value = [];
    searched.value = true;

    try {
        const queryParams = new URLSearchParams();
        if (clean) queryParams.set('dni', clean);
        if (code) queryParams.set('code', code);

        const response = await fetch(
            `/api/certificates/lookup?${queryParams.toString()}`,
            {
                headers: {
                    Accept: 'application/json',
                },
            },
        );

        const data = await response.json();

        if (response.ok && data.success) {
            records.value = data.records || [];
            if (data.dni) {
                dniQuery.value = data.dni;
            }
            if (records.value.length > 0) {
                studentName.value = records.value[0].student_name;
                if (code) {
                    const matched = records.value.find(
                        (r) =>
                            r.certificate_code.toLowerCase() ===
                            code.toLowerCase(),
                    );
                    if (matched) {
                        selectedCert.value = matched;
                        previewTab.value = 'anverso';
                        isPreviewModalOpen.value = true;
                    }
                }
            } else {
                studentName.value = null;
            }
        } else {
            error.value =
                data.message ||
                'No se pudo consultar el registro de certificados.';
        }
    } catch {
        error.value =
            'Error al comunicarse con el servidor. Intente nuevamente.';
    } finally {
        loading.value = false;
    }
}

function openPreview(record: CertificateRecord) {
    selectedCert.value = record;
    previewTab.value = 'anverso';
    isPreviewModalOpen.value = true;
}

async function copyVerificationCode(code: string) {
    try {
        await navigator.clipboard.writeText(code);
        copiedCode.value = true;
        setTimeout(() => {
            copiedCode.value = false;
        }, 2000);
    } catch {
        // Fallback
    }
}

/**
 * Genera el documento HTML oficial para imprimir o guardar como PDF
 */
function generateCertificateHtml(record: CertificateRecord): string {
    return `
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Certificado Oficial - ${record.course_title}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 10mm;
        }
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Times New Roman', serif;
            background: #ffffff;
            color: #111827;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .cert-card {
            width: 100%;
            max-width: 960px;
            background: #ffffff;
            border: 8px double #800020;
            padding: 40px 50px;
            position: relative;
            text-align: center;
        }
        .cert-card::before {
            content: "";
            position: absolute;
            top: 8px;
            left: 8px;
            right: 8px;
            bottom: 8px;
            border: 1.5px solid #d97706;
            pointer-events: none;
        }
        .inst-header {
            font-size: 13px;
            font-weight: bold;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #800020;
            margin-bottom: 4px;
        }
        .inst-sub {
            font-size: 11px;
            color: #4b5563;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-bottom: 24px;
        }
        .cert-title {
            font-size: 34px;
            font-weight: bold;
            letter-spacing: 3px;
            color: #800020;
            text-transform: uppercase;
            margin-bottom: 6px;
        }
        .cert-subtitle {
            font-size: 14px;
            color: #6b7280;
            font-style: italic;
            margin-bottom: 24px;
        }
        .awarded-to {
            font-size: 13px;
            text-transform: uppercase;
            color: #6b7280;
            letter-spacing: 1.5px;
            margin-bottom: 8px;
        }
        .recipient {
            font-size: 26px;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
            padding: 6px 30px;
            border-bottom: 2px solid #800020;
            display: inline-block;
            margin-bottom: 22px;
            letter-spacing: 1px;
        }
        .cert-text {
            font-size: 15px;
            line-height: 1.7;
            color: #334155;
            max-width: 780px;
            margin: 0 auto 24px auto;
        }
        .course-title {
            font-size: 20px;
            font-weight: bold;
            color: #800020;
            margin: 8px 0;
            display: block;
        }
        .signatures {
            margin-top: 40px;
            display: flex;
            justify-content: space-around;
            align-items: flex-end;
            padding: 0 40px;
        }
        .sig-box {
            width: 240px;
            border-top: 1.5px solid #475569;
            padding-top: 8px;
            font-size: 12px;
            line-height: 1.4;
            color: #1e293b;
        }
        .sig-role {
            font-size: 11px;
            color: #64748b;
            text-transform: uppercase;
        }
        .footer-bar {
            margin-top: 35px;
            padding-top: 15px;
            border-top: 1px dashed #cbd5e1;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-family: monospace;
            font-size: 11px;
            color: #64748b;
        }
        .badge-valid {
            color: #800020;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="cert-card">
        <div class="inst-header">${record.institution || 'SIGC-CUSCO'}</div>
        <div class="inst-sub">Sistema Integral de Gestión de Capacitaciones • Cusco, Perú</div>
        
        <h1 class="cert-title">Certificado Oficial</h1>
        <div class="cert-subtitle">Acreditación Académica y Asistencia Digital</div>

        <div class="awarded-to">Se otorga el presente reconocimiento a:</div>
        <div class="recipient">${record.student_name}</div>

        <div class="cert-text">
            Por haber participado y aprobado satisfactoriamente la capacitación especializada:
            <span class="course-title">"${record.course_title}"</span>
            con una duración lectiva de <strong>${formatHours(record.hours)}</strong>, desarrollada
            del <strong>${record.start_date || 'Fecha de inicio'}</strong> al <strong>${record.end_date || 'Fecha de término'}</strong>,
            habiendo cumplido con los requisitos de asistencia mediante código QR y las evaluaciones académicas institucionales.
        </div>

        <div class="signatures">
            <div class="sig-box">
                <strong>${record.instructor_name}</strong><br>
                <span class="sig-role">Ponente / Docente</span>
            </div>
            <div class="sig-box">
                <strong>Dirección Académica</strong><br>
                <span class="sig-role">Coordinación SIGC-CUSCO</span>
            </div>
        </div>

        <div class="footer-bar">
            <div>Código de Certificado: <strong>${record.certificate_code}</strong></div>
            <div class="badge-valid">CERTIFICADO OFICIAL VÁLIDO • CUSCO, PERÚ</div>
            <div>Carga: ${record.hours}h</div>
        </div>
    </div>
</body>
</html>
`;
}

function printOrDownloadCertificate(record: CertificateRecord) {
    printCertificate(record);
}
function copyVerificationUrl(url: string) {
    navigator.clipboard.writeText(url);
    copiedUrl.value = true;
    setTimeout(() => {
        copiedUrl.value = false;
    }, 2000);
}
</script>

<template>
    <Card
        class="overflow-hidden border border-slate-200 bg-white shadow-sm dark:border-neutral-800 dark:bg-neutral-900"
    >
        <CardHeader
            class="border-b bg-gradient-to-r from-rose-50/90 via-amber-50/30 to-transparent pb-4 dark:from-rose-950/40 dark:to-neutral-900"
        >
            <div
                class="mb-1 flex items-center gap-1.5 text-xs font-bold tracking-wider text-rose-900 uppercase dark:text-rose-300"
            >
                <Award class="size-4 text-rose-800" />
                <span>Acreditación Digital Oficial • Cusco, Perú</span>
            </div>
            <CardTitle
                class="text-lg font-bold tracking-tight text-slate-950 sm:text-xl dark:text-white"
            >
                Consulta y Descarga de Certificados Oficiales
            </CardTitle>
            <CardDescription
                class="text-xs font-medium text-slate-600 dark:text-neutral-400"
            >
                Ingresa tu número de DNI para consultar las capacitaciones
                aprobadas y descargar tu diploma digital oficial con código de
                verificación.
            </CardDescription>
        </CardHeader>

        <CardContent class="space-y-6 p-4 sm:p-6">
            <!-- Barra de búsqueda por DNI -->
            <form
                @submit.prevent="() => searchCertificates()"
                class="flex max-w-md flex-col gap-2 sm:flex-row"
            >
                <div class="relative flex-1">
                    <Search
                        class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400"
                    />
                    <Input
                        v-model="dniQuery"
                        type="text"
                        maxlength="8"
                        placeholder="Ingresa tu DNI (8 dígitos)"
                        class="bg-white pl-9 font-mono text-sm font-bold tracking-wider dark:bg-slate-950"
                        :disabled="loading"
                        @input="onDniInput"
                    />
                </div>
                <Button
                    type="submit"
                    class="h-10 shrink-0 cursor-pointer bg-rose-900 px-5 text-xs font-bold text-white shadow-xs hover:bg-rose-950"
                    :disabled="loading || dniQuery.trim().length !== 8"
                >
                    <Loader2
                        v-if="loading"
                        class="mr-1.5 size-3.5 animate-spin"
                    />
                    <Search v-else class="mr-1.5 size-3.5" />
                    Consultar DNI
                </Button>
            </form>

            <!-- Error message -->
            <div
                v-if="error"
                class="flex items-center gap-2.5 rounded-xl border border-rose-200 bg-rose-50 p-3.5 text-xs font-bold text-rose-800 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-200"
            >
                <AlertCircle class="size-4 shrink-0 text-rose-700" />
                <span>{{ error }}</span>
            </div>

            <!-- Empty result state -->
            <div
                v-else-if="searched && !loading && records.length === 0"
                class="space-y-2.5 rounded-2xl border border-dashed bg-slate-50/50 p-8 text-center dark:bg-neutral-900/40"
            >
                <div
                    class="mx-auto flex size-12 items-center justify-center rounded-full bg-slate-100 text-slate-400 dark:bg-slate-800"
                >
                    <Award class="size-6" />
                </div>
                <h4 class="text-sm font-bold text-slate-900 dark:text-white">
                    No se encontraron certificados para el DNI
                    <span class="font-mono text-rose-900 dark:text-rose-400">{{
                        dniQuery
                    }}</span>
                </h4>
                <p
                    class="mx-auto max-w-sm text-xs font-medium text-slate-600 dark:text-neutral-400"
                >
                    Verifica que los 8 dígitos sean correctos o inscríbete en
                    una de las capacitaciones disponibles para obtener tu
                    certificación oficial.
                </p>
            </div>

            <!-- Records Results -->
            <div v-else-if="records.length > 0" class="space-y-6">
                <PdfIntegrityVerifier
                    :expected-hash="
                        selectedCert?.certificate_hash ||
                        records[0]?.certificate_hash
                    "
                />
                <div
                    class="flex items-center justify-between border-b border-slate-200 pb-2 dark:border-neutral-800"
                >
                    <div class="flex items-center gap-2 text-xs">
                        <span
                            class="font-medium text-slate-600 dark:text-neutral-400"
                            >Participante Titular:</span
                        >
                        <strong
                            class="text-sm font-bold text-slate-950 dark:text-white"
                            >{{ studentName }}</strong
                        >
                    </div>
                    <Badge
                        variant="outline"
                        class="border-rose-300 bg-rose-50 text-xs font-black text-rose-900 dark:border-rose-800 dark:bg-rose-950 dark:text-rose-300"
                    >
                        {{ records.length }}
                        {{
                            records.length === 1
                                ? 'registro encontrado'
                                : 'registros encontrados'
                        }}
                    </Badge>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div
                        v-for="cert in records"
                        :key="cert.id"
                        class="flex flex-col justify-between space-y-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-xs transition-all hover:border-rose-400/80 sm:p-5 dark:border-neutral-800 dark:bg-neutral-900"
                    >
                        <div class="space-y-2.5">
                            <div
                                class="flex items-center justify-between gap-2"
                            >
                                <span
                                    class="rounded border border-rose-300 bg-rose-100 px-2.5 py-0.5 font-mono text-[11px] font-black text-rose-950 dark:border-rose-800 dark:bg-rose-950 dark:text-rose-200"
                                >
                                    {{ cert.course_code }}
                                </span>
                                <Badge
                                    variant="outline"
                                    class="text-[11px] font-black capitalize"
                                    :class="
                                        cert.status === 'aprobado'
                                            ? 'border-rose-300 bg-rose-100 text-rose-950 dark:border-rose-800 dark:bg-rose-950 dark:text-rose-200'
                                            : 'border-sky-300 bg-sky-100 text-sky-900 dark:bg-sky-950 dark:text-sky-200'
                                    "
                                >
                                    <CheckCircle2
                                        class="mr-1 size-3 text-rose-800"
                                    />
                                    {{ cert.status }}
                                </Badge>
                            </div>

                            <h4
                                class="line-clamp-2 text-sm leading-snug font-black text-slate-950 dark:text-white"
                            >
                                {{ cert.course_title }}
                            </h4>

                            <!-- Entidad convocante -->
                            <div
                                class="flex items-center gap-1.5 text-xs text-slate-700 dark:text-neutral-300"
                            >
                                <Building2
                                    class="size-3.5 shrink-0 text-rose-800 dark:text-rose-400"
                                />
                                <span
                                    class="truncate font-semibold text-slate-900 dark:text-neutral-100"
                                >
                                    {{ cert.institution }}
                                </span>
                            </div>

                            <div
                                class="grid grid-cols-2 gap-2 pt-1 text-[11px] font-bold text-slate-800 dark:text-slate-200"
                            >
                                <span class="flex items-center gap-1">
                                    <Clock
                                        class="size-3.5 text-amber-700 dark:text-amber-400"
                                    />
                                    {{ formatHours(cert.hours) }}
                                </span>
                                <span class="flex items-center gap-1">
                                    <Calendar
                                        class="size-3.5 text-rose-800 dark:text-rose-400"
                                    />
                                    {{ formatDate(cert.start_date, 'compact') }}
                                </span>
                            </div>

                            <div class="flex items-center gap-2">
                                <Badge
                                    class="border-emerald-300 bg-emerald-100 px-2.5 py-0.5 text-xs font-bold text-emerald-950 dark:bg-emerald-950 dark:text-emerald-200"
                                >
                                    <CheckCircle2
                                        class="mr-1 size-3 text-emerald-700"
                                    />
                                    Calificación: {{ cert.final_grade }} / 20.00
                                    ({{ cert.final_grade_text || 'Aprobado' }})
                                </Badge>
                            </div>

                            <div
                                class="space-y-1 rounded-xl border border-slate-200 bg-slate-50 p-2.5 font-mono text-[11px] text-slate-800 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-200"
                            >
                                <div>
                                    Ponente / Docente:
                                    <strong
                                        class="text-slate-950 dark:text-white"
                                        >{{ cert.instructor_name }}</strong
                                    >
                                </div>
                                <div>
                                    Código Verif.:
                                    <strong
                                        class="font-black text-rose-900 dark:text-rose-300"
                                        >{{ cert.certificate_code }}</strong
                                    >
                                </div>
                            </div>
                        </div>

                        <!-- Acciones: Ver Diploma Oficial + Descargar PDF -->
                        <div
                            class="flex flex-col items-center justify-between gap-2 border-t border-slate-100 pt-3 sm:flex-row dark:border-neutral-800"
                        >
                            <span
                                class="text-[11px] font-medium text-slate-600 dark:text-neutral-400"
                            >
                                Expedición:
                                <strong>{{
                                    cert.certificate_issued_at ||
                                    cert.end_date ||
                                    'Oficial'
                                }}</strong>
                            </span>

                            <div
                                class="flex w-full items-center gap-2 sm:w-auto"
                            >
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    class="h-8.5 flex-1 cursor-pointer border-slate-300 text-xs font-bold text-slate-800 hover:bg-rose-50 hover:text-rose-900 sm:flex-none"
                                    @click="openPreview(cert)"
                                >
                                    <Eye class="mr-1 size-3.5 text-slate-600" />
                                    Ver Diploma
                                </Button>
                                <Button
                                    type="button"
                                    size="sm"
                                    class="h-8.5 flex-1 cursor-pointer bg-rose-900 text-xs font-black text-white shadow-xs hover:bg-rose-950 sm:flex-none"
                                    @click="printOrDownloadCertificate(cert)"
                                >
                                    <Download class="mr-1 size-3.5" />
                                    Descargar PDF (2 Páginas)
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>

    <!-- MODAL DE VISTA PREVIA Y DESCARGA DEL DIPLOMA (SIN ESQUINAS CORTADAS) -->
    <Dialog v-model:open="isPreviewModalOpen">
        <DialogContent
            class="flex max-h-[92vh] w-[96vw] flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white p-0 shadow-2xl sm:max-w-4xl dark:border-slate-800 dark:bg-slate-900"
        >
            <!-- Header Institucional -->
            <div
                class="shrink-0 border-b border-slate-200 bg-slate-50/90 p-4 pb-3 sm:p-5 dark:border-slate-800 dark:bg-slate-900/90"
            >
                <DialogHeader class="space-y-1 text-left">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <Award class="size-4 text-[#800020]" />
                            <DialogTitle
                                class="text-sm font-black text-slate-950 sm:text-base dark:text-white"
                            >
                                Diploma Oficial Acreditado
                            </DialogTitle>
                        </div>
                        <Badge
                            variant="outline"
                            class="border-[#800020]/40 font-mono text-xs font-black text-[#800020]"
                        >
                            {{ selectedCert?.certificate_code }}
                        </Badge>
                    </div>
                    <DialogDescription
                        class="text-xs font-medium text-slate-500"
                    >
                        Universidad Nacional de San Antonio Abad del Cusco •
                        Registro Oficial de Fe Pública
                    </DialogDescription>
                </DialogHeader>
            </div>

            <!-- Selector de Cara: Anverso vs Reverso -->
            <div
                class="flex shrink-0 items-center justify-center border-b border-slate-200 bg-slate-100/90 p-2.5 dark:border-slate-800 dark:bg-slate-900"
            >
                <div
                    class="inline-flex rounded-xl border border-slate-200 bg-white p-1 shadow-xs dark:border-slate-700 dark:bg-slate-800"
                >
                    <button
                        type="button"
                        @click="previewTab = 'anverso'"
                        class="flex cursor-pointer items-center gap-1.5 rounded-lg px-4 py-1.5 text-xs font-bold transition-all"
                        :class="
                            previewTab === 'anverso'
                                ? 'bg-[#800020] text-white shadow-xs'
                                : 'text-slate-700 hover:text-[#800020] dark:text-slate-300'
                        "
                    >
                        <Award class="size-3.5" />
                        <span>Anverso (Diploma)</span>
                    </button>
                    <button
                        type="button"
                        @click="previewTab = 'reverso'"
                        class="flex cursor-pointer items-center gap-1.5 rounded-lg px-4 py-1.5 text-xs font-bold transition-all"
                        :class="
                            previewTab === 'reverso'
                                ? 'bg-[#800020] text-white shadow-xs'
                                : 'text-slate-700 hover:text-[#800020] dark:text-slate-300'
                        "
                    >
                        <QrCode class="size-3.5" />
                        <span>Reverso (Plan, Notas y QR)</span>
                    </button>
                </div>
            </div>

            <!-- Cuerpo Scrollable Interno -->
            <div
                class="flex flex-1 items-start justify-center overflow-y-auto overscroll-contain bg-slate-100/80 p-4 sm:p-6 dark:bg-slate-950/60"
            >
                <div v-if="selectedCert" class="w-full max-w-2xl">
                    <!-- CARA 1: ANVERSO -->
                    <div
                        v-if="previewTab === 'anverso'"
                        class="space-y-4 rounded-2xl border-2 border-[#800020] bg-white p-6 text-center shadow-md sm:p-8 dark:bg-slate-900"
                    >
                        <div
                            class="rounded-xl border border-[#b45309] p-4 sm:p-6"
                        >
                            <!-- Cabecera con 3 Logos -->
                            <div
                                class="mb-4 flex items-center justify-between border-b-2 border-[#800020] pb-3"
                            >
                                <div class="flex w-14 justify-center">
                                    <img
                                        src="/images/unsaac-logo.png"
                                        alt="UNSAAC"
                                        class="h-12 object-contain"
                                    />
                                </div>
                                <div class="flex-1 px-2 text-center">
                                    <div
                                        class="text-[9px] font-black tracking-widest text-[#800020] uppercase"
                                    >
                                        REPÚBLICA DEL PERÚ • REGIÓN CUSCO
                                    </div>
                                    <div
                                        class="text-xs font-black tracking-tight text-slate-950 uppercase sm:text-sm dark:text-white"
                                    >
                                        Universidad Nacional de San Antonio Abad
                                        del Cusco
                                    </div>
                                    <div
                                        class="text-[9px] font-semibold tracking-wider text-slate-600 uppercase dark:text-slate-400"
                                    >
                                        Facultad de Ing. Eléctrica, Electrónica,
                                        Informática y Mecánica
                                    </div>
                                    <div
                                        class="mt-1 inline-block rounded-full bg-[#800020] px-2.5 py-0.5 text-[8px] font-bold tracking-wider text-white uppercase"
                                    >
                                        SIGC-CUSCO • ACREDITACIÓN OFICIAL
                                    </div>
                                </div>
                                <div class="flex w-14 justify-center">
                                    <div
                                        class="flex size-12 flex-col items-center justify-center rounded-full border-2 border-amber-600 bg-gradient-to-tr from-amber-600 to-amber-300 p-1 text-center text-[#800020] shadow-xs"
                                    >
                                        <Sparkles
                                            class="size-3 text-amber-950"
                                        />
                                        <span
                                            class="text-[7px] leading-none font-black text-[#800020]"
                                            >CALIDAD</span
                                        >
                                    </div>
                                </div>
                            </div>

                            <!-- Título -->
                            <div class="my-3 space-y-1">
                                <h2
                                    class="font-serif text-2xl font-black tracking-widest text-[#800020] uppercase"
                                >
                                    CERTIFICADO
                                </h2>
                                <p
                                    class="text-[11px] text-slate-600 italic dark:text-slate-400"
                                >
                                    Por culminación académica satisfactoria y
                                    acreditación de competencias
                                </p>
                            </div>

                            <!-- Otorgado a -->
                            <div class="my-4">
                                <p
                                    class="text-[11px] font-bold tracking-wider text-slate-600 uppercase"
                                >
                                    Conferido a:
                                </p>
                                <div
                                    class="inline-block border-b-2 border-[#800020] px-4 py-1 text-xl font-black tracking-tight text-slate-950 uppercase dark:text-white"
                                >
                                    {{ selectedCert.student_name }}
                                </div>
                                <div class="mt-2">
                                    <span
                                        class="rounded-full border border-rose-200 bg-rose-50 px-3.5 py-1 font-mono text-xs font-bold text-[#800020] dark:bg-rose-950/60"
                                    >
                                        D.N.I. N° {{ selectedCert.dni }}
                                    </span>
                                </div>
                            </div>

                            <!-- Motivo -->
                            <div
                                class="mx-auto my-3 max-w-lg font-serif text-xs leading-relaxed text-slate-700 dark:text-slate-300"
                            >
                                Por haber aprobado el programa de capacitación
                                en:
                                <strong
                                    class="my-1 block text-sm font-black text-[#800020]"
                                >
                                    "{{ selectedCert.course_title }}"
                                </strong>
                                desarrollado
                                <strong>{{
                                    selectedCert.date_range_formal ||
                                    formatSpanishDateRange(
                                        selectedCert.start_date,
                                        selectedCert.end_date,
                                    )
                                }}</strong
                                >, con un total de
                                <strong>{{
                                    formatHours(selectedCert.hours)
                                }}</strong>
                                lectivas.
                            </div>

                            <!-- Calificación -->
                            <div
                                class="my-3 inline-flex items-center gap-2 rounded-full border border-slate-300 bg-slate-50 px-4 py-1.5 text-xs font-bold text-slate-900 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                            >
                                <span>Calificación:</span>
                                <span
                                    class="font-black text-emerald-700 dark:text-emerald-400"
                                    >{{ selectedCert.final_grade }} /
                                    20.00</span
                                >
                                <span
                                    class="font-semibold text-slate-600 dark:text-slate-400"
                                    >({{
                                        selectedCert.final_grade_text ||
                                        'Aprobado'
                                    }})</span
                                >
                            </div>

                            <!-- Fecha Formal -->
                            <div
                                class="my-1 pr-2 text-right font-serif text-[11px] font-bold text-slate-600 italic dark:text-slate-400"
                            >
                                {{
                                    selectedCert.city_issued_formal ||
                                    'Cusco, ' +
                                        formatSpanishDate(
                                            selectedCert.certificate_issued_at ||
                                                selectedCert.end_date,
                                        )
                                }}
                            </div>

                            <!-- Firmas -->
                            <div
                                class="mt-4 grid grid-cols-2 gap-6 border-t border-slate-300 pt-5 text-xs dark:border-slate-800"
                            >
                                <div>
                                    <div
                                        class="font-black text-slate-900 uppercase dark:text-white"
                                    >
                                        {{ selectedCert.instructor_name }}
                                    </div>
                                    <div
                                        class="text-[10px] text-slate-500 uppercase"
                                    >
                                        {{
                                            selectedCert.instructor_title ||
                                            'Docente Responsable'
                                        }}
                                    </div>
                                </div>
                                <div>
                                    <div
                                        class="font-black text-slate-900 uppercase dark:text-white"
                                    >
                                        Dirección Académica
                                    </div>
                                    <div
                                        class="text-[10px] text-slate-500 uppercase"
                                    >
                                        Coordinación General SIGC
                                    </div>
                                </div>
                            </div>

                            <!-- Pie -->
                            <div
                                class="mt-4 flex items-center justify-between border-t border-dashed border-slate-300 pt-3 font-mono text-[10px] text-slate-500 dark:border-slate-800"
                            >
                                <span
                                    >Cód:
                                    <strong>{{
                                        selectedCert.certificate_code
                                    }}</strong></span
                                >
                                <span
                                    >Emisión:
                                    <strong>{{
                                        selectedCert.issued_date_formal ||
                                        formatSpanishDate(
                                            selectedCert.certificate_issued_at ||
                                                selectedCert.end_date,
                                        )
                                    }}</strong></span
                                >
                                <span class="font-bold text-[#800020]"
                                    >VÁLIDO OFICIALMENTE • CUSCO</span
                                >
                            </div>
                        </div>
                    </div>

                    <!-- CARA 2: REVERSO -->
                    <div
                        v-else
                        class="space-y-4 rounded-2xl border-2 border-[#800020] bg-white p-6 text-left shadow-md dark:bg-slate-900"
                    >
                        <div class="border-b-2 border-[#800020] pb-2">
                            <div
                                class="text-[10px] font-black text-[#800020] uppercase"
                            >
                                UNIVERSIDAD NACIONAL DE SAN ANTONIO ABAD DEL
                                CUSCO • SIGC
                            </div>
                            <h3
                                class="text-base font-black text-slate-950 uppercase dark:text-white"
                            >
                                Plan Curricular y Registro de Calificación
                            </h3>
                            <p
                                class="text-xs text-slate-600 dark:text-slate-400"
                            >
                                Curso:
                                <strong>{{ selectedCert.course_title }}</strong>
                                ({{ selectedCert.course_code }})
                            </p>
                        </div>

                        <!-- Módulos -->
                        <div class="space-y-2">
                            <div
                                class="flex items-center justify-between text-xs font-black text-[#800020] uppercase"
                            >
                                <span class="flex items-center gap-1">
                                    <BookOpen class="size-3.5" />
                                    Módulos Académicos
                                </span>
                                <span class="text-[10px] text-slate-500"
                                    >{{ selectedCert.hours }} Horas</span
                                >
                            </div>

                            <div
                                class="custom-scrollbar max-h-48 space-y-2 overflow-y-auto pr-1"
                            >
                                <div
                                    v-for="(
                                        module, index
                                    ) in selectedCert.modules"
                                    :key="index"
                                    class="rounded-xl border-l-4 border-l-[#800020] bg-slate-50 p-2.5 text-xs dark:bg-slate-800"
                                >
                                    <div
                                        class="flex items-center justify-between font-bold text-slate-900 dark:text-white"
                                    >
                                        <span
                                            class="mr-2 font-black text-[#800020]"
                                            >{{ module.number }}</span
                                        >
                                        <span class="flex-1 truncate">{{
                                            module.title
                                        }}</span>
                                        <span
                                            class="font-mono text-[10px] text-slate-500"
                                            >{{ module.hours }}</span
                                        >
                                    </div>
                                    <p
                                        class="mt-0.5 text-[10px] text-slate-600 dark:text-slate-400"
                                    >
                                        {{ module.topics }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Calificación y Docente -->
                        <div class="grid grid-cols-1 gap-3 pt-2 sm:grid-cols-2">
                            <div
                                class="flex items-center justify-between rounded-xl border border-emerald-300 bg-emerald-50 p-3 dark:border-emerald-800 dark:bg-emerald-950/40"
                            >
                                <div>
                                    <div
                                        class="text-[10px] font-bold text-emerald-800 uppercase dark:text-emerald-300"
                                    >
                                        Calificación
                                    </div>
                                    <div
                                        class="text-2xl font-black text-emerald-950 dark:text-emerald-100"
                                    >
                                        {{ selectedCert.final_grade }}
                                    </div>
                                    <div
                                        class="text-[9px] font-bold text-emerald-700"
                                    >
                                        {{
                                            selectedCert.final_grade_text ||
                                            'Sobresaliente'
                                        }}
                                    </div>
                                </div>
                                <Badge
                                    class="bg-emerald-700 text-xs font-black text-white"
                                    >✓ APROBADO</Badge
                                >
                            </div>

                            <div
                                class="rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-800"
                            >
                                <div
                                    class="text-[10px] font-bold text-slate-500 uppercase"
                                >
                                    Docente:
                                </div>
                                <div
                                    class="mt-0.5 text-xs font-black text-slate-900 uppercase dark:text-white"
                                >
                                    {{ selectedCert.instructor_name }}
                                </div>
                                <div class="text-[10px] text-slate-600">
                                    {{ selectedCert.institution || 'UNSAAC' }}
                                </div>
                            </div>
                        </div>

                        <!-- QR Permanente -->
                        <div
                            class="flex items-center gap-3 rounded-xl border-2 border-[#800020] bg-white p-3 dark:bg-slate-800"
                        >
                            <div
                                class="flex size-20 shrink-0 items-center justify-center rounded border border-slate-200 bg-white p-1"
                            >
                                <div
                                    v-html="selectedCert.qr_svg"
                                    class="size-full [&>svg]:size-full"
                                ></div>
                            </div>
                            <div class="min-w-0 flex-1 space-y-1">
                                <div
                                    class="flex items-center gap-1 text-xs font-black text-[#800020] uppercase"
                                >
                                    <QrCode class="size-3.5" />
                                    QR Permanente de Verificación
                                </div>
                                <p
                                    class="text-[10px] text-slate-600 dark:text-slate-400"
                                >
                                    Escanee para validar en tiempo real la
                                    autenticidad e integridad del certificado.
                                </p>
                                <a
                                    :href="selectedCert.verification_url"
                                    target="_blank"
                                    class="block truncate font-mono text-[9px] text-blue-700 hover:underline"
                                >
                                    {{ selectedCert.verification_url }}
                                </a>
                            </div>
                        </div>

                        <!-- Hash SHA-256 -->
                        <div
                            v-if="selectedCert.certificate_hash"
                            class="rounded border bg-slate-50 p-2 font-mono text-[9px] break-all text-slate-600 dark:bg-slate-800"
                        >
                            <strong>SHA-256:</strong>
                            {{ selectedCert.certificate_hash }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Fijo -->
            <DialogFooter
                class="flex shrink-0 flex-col items-center justify-between gap-3 border-t border-slate-200 bg-slate-50/90 p-4 sm:flex-row dark:border-slate-800 dark:bg-slate-900/90"
            >
                <div class="flex items-center gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="border-slate-300 text-xs font-bold"
                        @click="
                            selectedCert &&
                            copyVerificationCode(selectedCert.certificate_code)
                        "
                    >
                        <Check
                            v-if="copiedCode"
                            class="mr-1 size-3.5 text-[#800020]"
                        />
                        <Copy v-else class="mr-1 size-3.5" />
                        <span>{{
                            copiedCode ? '¡Código Copiado!' : 'Copiar Código'
                        }}</span>
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="border-slate-300 text-xs font-bold"
                        @click="
                            selectedCert &&
                            copyVerificationUrl(selectedCert.verification_url)
                        "
                    >
                        <Check
                            v-if="copiedUrl"
                            class="mr-1 size-3.5 text-[#800020]"
                        />
                        <Share2 v-else class="mr-1 size-3.5" />
                        <span>{{
                            copiedUrl ? '¡Enlace Copiado!' : 'Copiar Enlace QR'
                        }}</span>
                    </Button>
                </div>

                <div class="flex items-center gap-2">
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="text-xs"
                        @click="isPreviewModalOpen = false"
                    >
                        Cerrar
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        class="cursor-pointer bg-[#800020] text-xs font-bold text-white shadow-xs hover:bg-[#5a0017]"
                        @click="
                            selectedCert &&
                            printOrDownloadCertificate(selectedCert)
                        "
                    >
                        <Printer class="mr-1.5 size-3.5" />
                        Imprimir / Guardar en PDF (2 Páginas)
                    </Button>
                </div>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
