<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
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
} from '@lucide/vue';

export interface CertificateRecord {
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

// Preview Modal State
const selectedCert = ref<CertificateRecord | null>(null);
const isPreviewModalOpen = ref(false);
const copiedCode = ref(false);

onMounted(() => {
    // Si viene con parámetro ?dni=12345678 en la URL pública
    const params = new URLSearchParams(window.location.search);
    const dniParam = params.get('dni');
    if (dniParam && /^\d{8}$/.test(dniParam.trim())) {
        dniQuery.value = dniParam.trim();
        searchCertificates();
    }
});

function onDniInput(e: Event) {
    const target = e.target as HTMLInputElement;
    dniQuery.value = target.value.replace(/\D/g, '').slice(0, 8);
    if (error.value) error.value = null;
}

async function searchCertificates() {
    const clean = dniQuery.value.trim();
    if (!/^\d{8}$/.test(clean)) {
        error.value = 'Ingrese un número de DNI válido de exactamente 8 dígitos numéricos.';
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

function openPreview(record: CertificateRecord) {
    selectedCert.value = record;
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
                <span class="sig-role">Docente Instructor</span>
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

/**
 * Impresión / Descarga a PDF fiable que no es bloqueada por bloqueadores de ventanas emergentes (popups)
 */
function printOrDownloadCertificate(record: CertificateRecord) {
    const html = generateCertificateHtml(record);
    const iframe = document.createElement('iframe');
    iframe.style.position = 'fixed';
    iframe.style.right = '0';
    iframe.style.bottom = '0';
    iframe.style.width = '0';
    iframe.style.height = '0';
    iframe.style.border = '0';
    document.body.appendChild(iframe);

    const doc = iframe.contentWindow?.document;
    if (!doc) return;

    doc.open();
    doc.write(html);
    doc.close();

    setTimeout(() => {
        iframe.contentWindow?.focus();
        iframe.contentWindow?.print();
        setTimeout(() => {
            if (document.body.contains(iframe)) {
                document.body.removeChild(iframe);
            }
        }, 3000);
    }, 350);
}
</script>

<template>
    <Card class="border border-slate-200 dark:border-neutral-800 shadow-sm overflow-hidden bg-white dark:bg-neutral-900">
        <CardHeader class="bg-gradient-to-r from-rose-50/90 via-amber-50/30 to-transparent dark:from-rose-950/40 dark:to-neutral-900 border-b pb-4">
            <div class="flex items-center gap-1.5 text-xs font-bold text-rose-900 dark:text-rose-300 uppercase tracking-wider mb-1">
                <Award class="size-4 text-rose-800" />
                <span>Acreditación Digital Oficial • Cusco, Perú</span>
            </div>
            <CardTitle class="text-lg sm:text-xl font-bold tracking-tight text-slate-950 dark:text-white">
                Consulta y Descarga de Certificados Oficiales
            </CardTitle>
            <CardDescription class="text-xs text-slate-600 dark:text-neutral-400 font-medium">
                Ingresa tu número de DNI para consultar las capacitaciones aprobadas y descargar tu diploma digital oficial con código de verificación.
            </CardDescription>
        </CardHeader>

        <CardContent class="p-4 sm:p-6 space-y-6">
            <!-- Barra de búsqueda por DNI -->
            <form @submit.prevent="searchCertificates" class="flex flex-col sm:flex-row gap-2 max-w-md">
                <div class="relative flex-1">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-slate-400" />
                    <Input
                        v-model="dniQuery"
                        type="text"
                        maxlength="8"
                        placeholder="Ingresa tu DNI (8 dígitos)"
                        class="pl-9 font-mono text-sm tracking-wider font-bold bg-white dark:bg-slate-950"
                        :disabled="loading"
                        @input="onDniInput"
                    />
                </div>
                <Button
                    type="submit"
                    class="bg-rose-900 hover:bg-rose-950 text-white font-bold text-xs shrink-0 shadow-xs cursor-pointer h-10 px-5"
                    :disabled="loading || dniQuery.trim().length !== 8"
                >
                    <Loader2 v-if="loading" class="size-3.5 mr-1.5 animate-spin" />
                    <Search v-else class="size-3.5 mr-1.5" />
                    Consultar DNI
                </Button>
            </form>

            <!-- Error message -->
            <div v-if="error" class="p-3.5 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900 text-rose-800 dark:text-rose-200 text-xs flex items-center gap-2.5 font-bold">
                <AlertCircle class="size-4 shrink-0 text-rose-700" />
                <span>{{ error }}</span>
            </div>

            <!-- Empty result state -->
            <div v-else-if="searched && !loading && records.length === 0" class="p-8 text-center border border-dashed rounded-2xl space-y-2.5 bg-slate-50/50 dark:bg-neutral-900/40">
                <div class="size-12 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center mx-auto text-slate-400">
                    <Award class="size-6" />
                </div>
                <h4 class="text-sm font-bold text-slate-900 dark:text-white">
                    No se encontraron certificados para el DNI <span class="font-mono text-rose-900 dark:text-rose-400">{{ dniQuery }}</span>
                </h4>
                <p class="text-xs text-slate-600 dark:text-neutral-400 max-w-sm mx-auto font-medium">
                    Verifica que los 8 dígitos sean correctos o inscríbete en una de las capacitaciones disponibles para obtener tu certificación oficial.
                </p>
            </div>

            <!-- Records Results -->
            <div v-else-if="records.length > 0" class="space-y-4">
                <div class="flex items-center justify-between pb-2 border-b border-slate-200 dark:border-neutral-800">
                    <div class="text-xs flex items-center gap-2">
                        <span class="text-slate-600 dark:text-neutral-400 font-medium">Participante Titular:</span>
                        <strong class="text-slate-950 dark:text-white text-sm font-bold">{{ studentName }}</strong>
                    </div>
                    <Badge variant="outline" class="border-rose-300 text-rose-900 dark:border-rose-800 dark:text-rose-300 font-black text-xs bg-rose-50 dark:bg-rose-950">
                        {{ records.length }} {{ records.length === 1 ? 'registro encontrado' : 'registros encontrados' }}
                    </Badge>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div
                        v-for="cert in records"
                        :key="cert.id"
                        class="p-4 sm:p-5 rounded-2xl border border-slate-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 flex flex-col justify-between space-y-4 hover:border-rose-400/80 transition-all shadow-xs"
                    >
                        <div class="space-y-2.5">
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-mono text-[11px] font-black bg-rose-100 text-rose-950 dark:bg-rose-950 dark:text-rose-200 px-2.5 py-0.5 rounded border border-rose-300 dark:border-rose-800">
                                    {{ cert.course_code }}
                                </span>
                                <Badge
                                    variant="outline"
                                    class="text-[11px] font-black capitalize"
                                    :class="cert.status === 'aprobado' ? 'bg-rose-100 text-rose-950 border-rose-300 dark:bg-rose-950 dark:text-rose-200 dark:border-rose-800' : 'bg-sky-100 text-sky-900 border-sky-300 dark:bg-sky-950 dark:text-sky-200'"
                                >
                                    <CheckCircle2 class="size-3 mr-1 text-rose-800" />
                                    {{ cert.status }}
                                </Badge>
                            </div>

                            <h4 class="text-sm font-black text-slate-950 dark:text-white line-clamp-2 leading-snug">
                                {{ cert.course_title }}
                            </h4>

                            <!-- Entidad convocante -->
                            <div class="flex items-center gap-1.5 text-xs text-slate-700 dark:text-neutral-300">
                                <Building2 class="size-3.5 text-rose-800 dark:text-rose-400 shrink-0" />
                                <span class="font-semibold text-slate-900 dark:text-neutral-100 truncate">
                                    {{ cert.institution }}
                                </span>
                            </div>

                            <div class="grid grid-cols-2 gap-2 text-[11px] font-bold text-slate-800 dark:text-slate-200 pt-1">
                                <span class="flex items-center gap-1">
                                    <Clock class="size-3.5 text-amber-700 dark:text-amber-400" />
                                    {{ formatHours(cert.hours) }}
                                </span>
                                <span class="flex items-center gap-1">
                                    <Calendar class="size-3.5 text-rose-800 dark:text-rose-400" />
                                    {{ formatDate(cert.start_date, 'compact') }}
                                </span>
                            </div>

                            <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-[11px] space-y-1 font-mono text-slate-800 dark:text-slate-200">
                                <div>Docente: <strong class="text-slate-950 dark:text-white">{{ cert.instructor_name }}</strong></div>
                                <div>Código Verif.: <strong class="text-rose-900 dark:text-rose-300 font-black">{{ cert.certificate_code }}</strong></div>
                            </div>
                        </div>

                        <!-- Acciones: Ver Diploma Oficial + Descargar PDF -->
                        <div class="pt-3 border-t border-slate-100 dark:border-neutral-800 flex flex-col sm:flex-row items-center justify-between gap-2">
                            <span class="text-[11px] text-slate-600 dark:text-neutral-400 font-medium">
                                Asistencia: <strong>{{ cert.attended_sessions }} ses.</strong>
                            </span>

                            <div class="flex items-center gap-2 w-full sm:w-auto">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    class="flex-1 sm:flex-none text-xs h-8.5 font-bold border-slate-300 hover:bg-rose-50 text-slate-800 hover:text-rose-900 cursor-pointer"
                                    @click="openPreview(cert)"
                                >
                                    <Eye class="size-3.5 mr-1 text-slate-600" />
                                    Ver Diploma
                                </Button>
                                <Button
                                    type="button"
                                    size="sm"
                                    class="flex-1 sm:flex-none bg-rose-900 hover:bg-rose-950 text-white text-xs h-8.5 shadow-xs font-black cursor-pointer"
                                    @click="printOrDownloadCertificate(cert)"
                                >
                                    <Download class="size-3.5 mr-1" />
                                    Descargar PDF
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
        <DialogContent class="w-[96vw] sm:max-w-3xl max-h-[90vh] flex flex-col p-0 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 overflow-hidden">
            <!-- Header Fijo Pinned -->
            <div class="p-5 sm:p-6 pb-3 border-b border-slate-200 dark:border-slate-800 shrink-0 pr-12">
                <DialogHeader class="text-left space-y-1">
                    <div class="flex items-center gap-2 text-xs font-black text-rose-900 dark:text-rose-400 uppercase tracking-wider">
                        <Award class="size-4 text-rose-800" />
                        <span>Vista Previa del Diploma Digital Oficial</span>
                    </div>
                    <DialogTitle class="text-lg font-black text-slate-950 dark:text-white">
                        {{ selectedCert?.course_title }}
                    </DialogTitle>
                    <DialogDescription class="text-xs text-slate-600 dark:text-slate-400 font-medium">
                        Certificado digital oficial válido para acreditación académica y laboral.
                    </DialogDescription>
                </DialogHeader>
            </div>

            <!-- Cuerpo Scrollable Interno con scrollbar sutil -->
            <div class="flex-1 overflow-y-auto overscroll-contain custom-scrollbar p-5 sm:p-6">
                <div v-if="selectedCert" class="space-y-6">
                    <!-- Marco Oficial del Certificado -->
                    <div class="p-6 sm:p-8 bg-[#fdfbf7] dark:bg-slate-950 border-4 border-double border-[#800020] text-center space-y-4 rounded-xl shadow-inner relative">
                        <div class="text-[11px] font-black tracking-widest text-[#800020] uppercase">
                            {{ selectedCert.institution || 'SIGC-CUSCO' }}
                        </div>
                        <div class="text-[10px] tracking-wider text-slate-500 font-semibold uppercase">
                            Sistema Integral de Gestión de Capacitaciones • Cusco, Perú
                        </div>

                        <div class="pt-2">
                            <h2 class="text-2xl font-black text-[#800020] uppercase tracking-wider">
                                Certificado Oficial
                            </h2>
                            <p class="text-xs text-slate-600 italic">
                                Por culminación académica satisfactoria y asistencia
                            </p>
                        </div>

                        <div class="pt-2">
                            <p class="text-xs text-slate-500 uppercase tracking-wider">Otorgado a:</p>
                            <div class="text-xl font-black text-slate-950 dark:text-white uppercase tracking-tight py-1 border-b-2 border-[#800020] inline-block px-4">
                                {{ selectedCert.student_name }}
                            </div>
                        </div>

                        <div class="text-xs text-slate-700 dark:text-slate-300 max-w-lg mx-auto leading-relaxed pt-1">
                            Habiendo completado satisfactoriamente la capacitación especializada
                            <strong class="text-[#800020] block my-1 text-sm font-black">"{{ selectedCert.course_title }}"</strong>
                            con un total de <strong>{{ formatHours(selectedCert.hours) }}</strong> lectivas, desarrollada
                            del <strong>{{ selectedCert.start_date || 'Inicio' }}</strong> al <strong>{{ selectedCert.end_date || 'Fin' }}</strong>,
                            cumpliendo con la asistencia por código QR y evaluaciones oficiales.
                        </div>

                        <div class="pt-6 grid grid-cols-2 gap-6 text-xs border-t border-slate-300 dark:border-slate-800">
                            <div>
                                <div class="font-bold text-slate-900 dark:text-white">{{ selectedCert.instructor_name }}</div>
                                <div class="text-[10px] text-slate-500 uppercase">Docente Instructor</div>
                            </div>
                            <div>
                                <div class="font-bold text-slate-900 dark:text-white">Dirección Académica</div>
                                <div class="text-[10px] text-slate-500 uppercase">Coordinación SIGC-CUSCO</div>
                            </div>
                        </div>

                        <div class="pt-3 text-[10px] font-mono text-slate-500 flex justify-between items-center border-t border-dashed border-slate-300 dark:border-slate-800">
                            <span>Código: <strong class="text-slate-900 dark:text-white">{{ selectedCert.certificate_code }}</strong></span>
                            <span class="text-[#800020] font-bold">VÁLIDO OFICIALMENTE • CUSCO</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Fijo -->
            <DialogFooter class="flex flex-col sm:flex-row items-center justify-between gap-3 p-4 border-t border-slate-200 dark:border-slate-800 shrink-0 bg-slate-50/80 dark:bg-slate-950/60">
                <div class="flex items-center gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="text-xs font-bold border-slate-300"
                        @click="selectedCert && copyVerificationCode(selectedCert.certificate_code)"
                    >
                        <Check v-if="copiedCode" class="size-3.5 mr-1 text-rose-800" />
                        <Copy v-else class="size-3.5 mr-1" />
                        <span>{{ copiedCode ? '¡Código Copiado!' : 'Copiar Código' }}</span>
                    </Button>
                </div>

                <div class="flex items-center gap-2">
                    <Button type="button" variant="ghost" size="sm" class="text-xs" @click="isPreviewModalOpen = false">
                        Cerrar
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        class="bg-rose-900 hover:bg-rose-950 text-white font-black text-xs shadow-xs cursor-pointer"
                        @click="selectedCert && printOrDownloadCertificate(selectedCert)"
                    >
                        <Printer class="size-3.5 mr-1.5" />
                        Imprimir / Guardar en PDF
                    </Button>
                </div>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
