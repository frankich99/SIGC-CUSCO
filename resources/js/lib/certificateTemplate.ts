/**
 * Plantilla oficial de Certificados y Diplomas Universitarios SIGC-CUSCO / UNSAAC
 * Genera el documento de doble cara (Anverso y Reverso) en formato A4 Landscape de alta resolución.
 */

export interface CertificateModule {
    number: string;
    title: string;
    hours: string;
    topics: string;
}

export interface CertificateRecord {
    id: number;
    dni: string;
    student_name: string;
    full_name?: string;
    course_code: string;
    course_title: string;
    institution: string;
    hours: number;
    start_date: string;
    end_date: string;
    date_range_formal?: string;
    issued_date_formal?: string;
    city_issued_formal?: string;
    instructor_name: string;
    instructor_title?: string;
    status: string;
    final_grade: string;
    final_grade_text?: string;
    grade_numeric?: string;
    grade_text?: string;
    certificate_code: string;
    certificate_hash?: string | null;
    certificate_issued_at?: string | null;
    verification_url: string;
    qr_svg: string;
    modules: CertificateModule[];
}

export const SPANISH_MONTHS: Record<number, string> = {
    1: 'enero',
    2: 'febrero',
    3: 'marzo',
    4: 'abril',
    5: 'mayo',
    6: 'junio',
    7: 'julio',
    8: 'agosto',
    9: 'setiembre',
    10: 'octubre',
    11: 'noviembre',
    12: 'diciembre',
};

export function parseDateSafe(val?: string | null): Date | null {
    if (!val) return null;
    if (/^\d{4}-\d{2}-\d{2}/.test(val)) {
        const parts = val.split(/[-T ]/);
        return new Date(
            parseInt(parts[0], 10),
            parseInt(parts[1], 10) - 1,
            parseInt(parts[2], 10),
        );
    }
    if (/^\d{2}\/\d{2}\/\d{4}/.test(val)) {
        const parts = val.split('/');
        return new Date(
            parseInt(parts[2], 10),
            parseInt(parts[1], 10) - 1,
            parseInt(parts[0], 10),
        );
    }
    const d = new Date(val);
    return isNaN(d.getTime()) ? null : d;
}

export function formatSpanishDate(val?: string | null): string {
    const d = parseDateSafe(val);
    if (!d) {
        const now = new Date();
        const dd = String(now.getDate()).padStart(2, '0');
        const mm = SPANISH_MONTHS[now.getMonth() + 1] || 'octubre';
        return `${dd} de ${mm} de ${now.getFullYear()}`;
    }
    const day = String(d.getDate()).padStart(2, '0');
    const month = SPANISH_MONTHS[d.getMonth() + 1] || 'enero';
    return `${day} de ${month} de ${d.getFullYear()}`;
}

export function formatSpanishDateRange(
    startVal?: string | null,
    endVal?: string | null,
): string {
    const start = parseDateSafe(startVal);
    const end = parseDateSafe(endVal);

    if (!start && !end) {
        return 'del 19 de enero al 28 de febrero de 2026';
    }
    if (start && !end) {
        return `a partir del ${formatSpanishDate(startVal)}`;
    }
    if (!start && end) {
        return `al ${formatSpanishDate(endVal)}`;
    }

    const sDay = String(start!.getDate()).padStart(2, '0');
    const eDay = String(end!.getDate()).padStart(2, '0');
    const sMonth = SPANISH_MONTHS[start!.getMonth() + 1] || '';
    const eMonth = SPANISH_MONTHS[end!.getMonth() + 1] || '';
    const sYear = start!.getFullYear();
    const eYear = end!.getFullYear();

    if (sYear === eYear) {
        if (start!.getMonth() === end!.getMonth()) {
            return `del ${sDay} al ${eDay} de ${eMonth} de ${eYear}`;
        }
        return `del ${sDay} de ${sMonth} al ${eDay} de ${eMonth} de ${eYear}`;
    }
    return `del ${sDay} de ${sMonth} de ${sYear} al ${eDay} de ${eMonth} de ${eYear}`;
}

// SVG Cinta Esquinera Oficial (deprecada por diseño limpio institucional sin esquinas toscas)
export const CORNER_RIBBON_SVG = (_corner?: 'tl' | 'tr' | 'bl' | 'br') => '';

// SVG Medalla Dorada de Calidad Académica
export const GOLD_MEDAL_SVG = `
    <svg class="gold-badge" viewBox="0 0 100 100" width="84" height="84">
        <circle cx="50" cy="50" r="46" fill="url(#goldGrad)" stroke="#854d0e" stroke-width="2.5"/>
        <circle cx="50" cy="50" r="38" fill="none" stroke="#78350f" stroke-width="1.5" stroke-dasharray="3 2"/>
        <circle cx="50" cy="50" r="32" fill="#800020" stroke="#fbbf24" stroke-width="1"/>
        <path d="M50 24 L53 33 L62 33 L55 38 L58 47 L50 42 L42 47 L45 38 L38 33 L47 33 Z" fill="#fbbf24" stroke="#d97706" stroke-width="0.5"/>
        <text x="50" y="58" font-family="'Times New Roman', serif" font-size="6.5" font-weight="bold" fill="#fef08a" text-anchor="middle" letter-spacing="0.5">CALIDAD</text>
        <text x="50" y="65" font-family="'Times New Roman', serif" font-size="5.5" font-weight="bold" fill="#ffffff" text-anchor="middle" letter-spacing="0.5">ACADÉMICA</text>
        <text x="50" y="72" font-family="'Times New Roman', serif" font-size="4.5" fill="#fef08a" text-anchor="middle" letter-spacing="1">UNSAAC</text>
        <defs>
            <linearGradient id="goldGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                <stop offset="0%" stop-color="#fef08a"/>
                <stop offset="40%" stop-color="#f59e0b"/>
                <stop offset="70%" stop-color="#b45309"/>
                <stop offset="100%" stop-color="#78350f"/>
            </linearGradient>
        </defs>
    </svg>
`;

// SVG Escudo Patrio / Emblema SIGC
export const EMBLEM_SVG = `
    <svg viewBox="0 0 100 100" width="75" height="75">
        <circle cx="50" cy="50" r="46" fill="#f8fafc" stroke="#800020" stroke-width="2"/>
        <circle cx="50" cy="50" r="42" fill="none" stroke="#b45309" stroke-width="1"/>
        <!-- Laureles -->
        <path d="M22 65 C20 45, 30 25, 50 18 C70 25, 80 45, 78 65" fill="none" stroke="#15803d" stroke-width="2.5" stroke-linecap="round"/>
        <!-- Sol Radiante del Cusco -->
        <circle cx="50" cy="46" r="16" fill="#f59e0b" stroke="#b45309" stroke-width="1.2"/>
        <polygon points="50,22 53,28 50,30 47,28" fill="#d97706"/>
        <polygon points="50,70 53,64 50,62 47,64" fill="#d97706"/>
        <polygon points="26,46 32,49 34,46 32,43" fill="#d97706"/>
        <polygon points="74,46 68,49 66,46 68,43" fill="#d97706"/>
        <!-- Libro abierto -->
        <path d="M38 56 Q50 52 50 58 Q50 52 62 56 L62 46 Q50 42 50 48 Q50 42 38 46 Z" fill="#ffffff" stroke="#800020" stroke-width="1"/>
        <text x="50" y="82" font-family="'Times New Roman', serif" font-size="6" font-weight="bold" fill="#800020" text-anchor="middle">SIGC • PERÚ</text>
    </svg>
`;

// SVG Sello Redondo del Docente
export const DOCENTE_STAMP_SVG = `
    <svg viewBox="0 0 120 120" width="95" height="95" style="transform: rotate(-7deg); opacity: 0.88;">
        <circle cx="60" cy="60" r="54" fill="none" stroke="#1e3a8a" stroke-width="2.5" stroke-dasharray="4 2"/>
        <circle cx="60" cy="60" r="47" fill="none" stroke="#1e3a8a" stroke-width="1.2"/>
        <path id="docenteCurve" d="M 18,60 A 42,42 0 0,1 102,60" fill="none"/>
        <text font-family="'Times New Roman', serif" font-size="7" font-weight="bold" fill="#1e3a8a" letter-spacing="1.5">
            <textPath href="#docenteCurve" startOffset="50%" text-anchor="middle">
                DOCENTE RESPONSABLE
            </textPath>
        </text>
        <path id="docenteBottom" d="M 102,60 A 42,42 0 0,1 18,60" fill="none"/>
        <text font-family="'Times New Roman', serif" font-size="6" font-weight="bold" fill="#1e3a8a" letter-spacing="1">
            <textPath href="#docenteBottom" startOffset="50%" text-anchor="middle">
                ★ UNSAAC • CD CUSCO ★
            </textPath>
        </text>
        <!-- Centro con rúbrica -->
        <path d="M40 68 Q50 46 62 60 T78 52 T88 64" fill="none" stroke="#1e3a8a" stroke-width="1.8" stroke-linecap="round"/>
        <text x="60" y="74" font-family="'Courier New', monospace" font-size="6" font-weight="bold" fill="#1e3a8a" text-anchor="middle">ACREDITADO</text>
    </svg>
`;

// SVG Sello Redondo de Dirección Académica
export const DIRECCION_STAMP_SVG = `
    <svg viewBox="0 0 120 120" width="95" height="95" style="transform: rotate(5deg); opacity: 0.88;">
        <circle cx="60" cy="60" r="54" fill="none" stroke="#800020" stroke-width="2.5"/>
        <circle cx="60" cy="60" r="47" fill="none" stroke="#800020" stroke-width="1.2" stroke-dasharray="3 2"/>
        <path id="dirCurve" d="M 18,60 A 42,42 0 0,1 102,60" fill="none"/>
        <text font-family="'Times New Roman', serif" font-size="6.8" font-weight="bold" fill="#800020" letter-spacing="1.2">
            <textPath href="#dirCurve" startOffset="50%" text-anchor="middle">
                DIRECCIÓN ACADÉMICA
            </textPath>
        </text>
        <path id="dirBottom" d="M 102,60 A 42,42 0 0,1 18,60" fill="none"/>
        <text font-family="'Times New Roman', serif" font-size="5.8" font-weight="bold" fill="#800020" letter-spacing="1">
            <textPath href="#dirBottom" startOffset="50%" text-anchor="middle">
                ★ SIGC-CUSCO • FE PÚBLICA ★
            </textPath>
        </text>
        <polygon points="60,45 63,52 70,52 65,56 67,63 60,59 53,63 55,56 50,52 57,52" fill="#800020"/>
        <text x="60" y="73" font-family="'Times New Roman', serif" font-size="6.5" font-weight="bold" fill="#800020" text-anchor="middle">REGISTRADO</text>
    </svg>
`;

/**
 * Genera el documento HTML completo de 2 páginas (Anverso y Reverso) para impresión o guardado como PDF
 */
export function generateOfficialCertificateHtml(
    record: CertificateRecord,
): string {
    const origin = typeof window !== 'undefined' ? window.location.origin : '';
    const unsaacLogoUrl = `${origin}/images/unsaac-logo.png`;
    const verificationUrl =
        record.verification_url ||
        `${origin}/certificates?dni=${record.dni}&code=${record.certificate_code}`;
    const gradeNum = parseFloat(record.final_grade) || 20;
    const gradeHonor =
        gradeNum >= 18
            ? 'Con Mención de Excelencia Académica'
            : 'Acreditado Oficialmente';

    const dateRangeProse =
        record.date_range_formal ||
        formatSpanishDateRange(record.start_date, record.end_date);
    const issuedDateProse =
        record.issued_date_formal ||
        formatSpanishDate(record.certificate_issued_at || record.end_date);
    const cityIssuedProse =
        record.city_issued_formal || `Cusco, ${issuedDateProse}`;

    const goldMedalSvg = GOLD_MEDAL_SVG;
    const emblemSvg = EMBLEM_SVG;
    const docenteStampSvg = DOCENTE_STAMP_SVG;
    const direccionStampSvg = DIRECCION_STAMP_SVG;

    // Renderizado de módulos curriculares
    const modulesHtml =
        record.modules && record.modules.length > 0
            ? record.modules
                  .map(
                      (m, idx) => `
            <div class="module-card">
                <div class="module-header">
                    <span class="module-num">${m.number || `MÓDULO 0${idx + 1}`}</span>
                    <span class="module-title">${m.title}</span>
                    <span class="module-hours">${m.hours || '10 hrs.'}</span>
                </div>
                <div class="module-topics">${m.topics}</div>
            </div>
        `,
                  )
                  .join('')
            : `
            <div class="module-card">
                <div class="module-header">
                    <span class="module-num">MÓDULO I</span>
                    <span class="module-title">Fundamentos y Marco Normativo de ${record.course_title}</span>
                    <span class="module-hours">10 hrs.</span>
                </div>
                <div class="module-topics">Bases teóricas, conceptos fundamentales, arquitectura y estándares de calidad institucional.</div>
            </div>
            <div class="module-card">
                <div class="module-header">
                    <span class="module-num">MÓDULO II</span>
                    <span class="module-title">Desarrollo Técnico y Aplicación Práctica Especializada</span>
                    <span class="module-hours">15 hrs.</span>
                </div>
                <div class="module-topics">Metodologías operativas, resolución de casos reales, herramientas tecnológicas avanzadas.</div>
            </div>
            <div class="module-card">
                <div class="module-header">
                    <span class="module-num">MÓDULO III</span>
                    <span class="module-title">Evaluación Integral, Síntesis y Acreditación Profesional</span>
                    <span class="module-hours">15 hrs.</span>
                </div>
                <div class="module-topics">Presentación de proyecto aplicativo final y evaluación de competencias adquiridas.</div>
            </div>
        `;

    return `
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Certificado Oficial - ${record.course_title} - ${record.student_name}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 0;
        }
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        body {
            font-family: 'Times New Roman', Times, 'Georgia', serif;
            background: #e2e8f0;
            color: #0f172a;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            padding: 20px 0;
        }

        /* Hoja A4 Landscape exacta: 297mm x 210mm */
        .page-sheet {
            width: 297mm;
            height: 210mm;
            background: #ffffff;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0,0,0,0.18);
            page-break-after: always;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 10mm 12mm 8mm 12mm;
        }
        .page-sheet:last-child {
            page-break-after: auto;
            margin-top: 25px;
        }

        @media print {
            body {
                background: none !important;
                padding: 0 !important;
            }
            .page-sheet {
                box-shadow: none !important;
                margin: 0 !important;
                width: 297mm !important;
                height: 210mm !important;
                page-break-after: always !important;
            }
            .page-sheet:last-child {
                page-break-after: auto !important;
                margin-top: 0 !important;
            }
        }

        /* Marco Perimetral Académico Doble Oficial (Líneas Clásicas Nobres - Sin Esquinas Cloradas) */
        .page-border {
            position: absolute;
            top: 5mm;
            left: 5mm;
            right: 5mm;
            bottom: 5mm;
            border: 2.5px solid #800020;
            pointer-events: none;
            z-index: 10;
        }
        .page-border-inner {
            position: absolute;
            top: 7.5mm;
            left: 7.5mm;
            right: 7.5mm;
            bottom: 7.5mm;
            border: 1px solid #b45309;
            pointer-events: none;
            z-index: 10;
        }

        .issue-date-line {
            font-size: 11px;
            font-weight: 700;
            color: #475569;
            font-style: italic;
            text-align: right;
            padding-right: 20mm;
            margin: 3px 0 2px 0;
            font-family: 'Times New Roman', Times, Georgia, serif;
        }

        /* Marca de agua institucional suave */
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 145mm;
            opacity: 0.055;
            pointer-events: none;
            z-index: 1;
        }

        .cert-content {
            position: relative;
            z-index: 5;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            text-align: center;
        }

        /* Cabecera Superior */
        .header-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 2px solid #800020;
            padding-bottom: 6px;
            margin-bottom: 8px;
        }
        .header-logo-box {
            width: 85px;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .header-logo-img {
            max-height: 72px;
            max-width: 80px;
            object-fit: contain;
        }
        .header-center-text {
            flex: 1;
            padding: 0 15px;
        }
        .gov-title {
            font-size: 11.5px;
            font-weight: bold;
            color: #800020;
            letter-spacing: 2px;
            text-transform: uppercase;
        }
        .univ-title {
            font-size: 17px;
            font-weight: 900;
            color: #0f172a;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            margin: 2px 0;
            font-family: 'Times New Roman', Georgia, serif;
        }
        .fac-title {
            font-size: 9.5px;
            color: #475569;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .sigc-badge-title {
            display: inline-block;
            background: #800020;
            color: #ffffff;
            font-size: 8.5px;
            font-weight: bold;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            padding: 2px 12px;
            border-radius: 3px;
            margin-top: 3px;
        }

        /* Título del Diploma */
        .diploma-title-box {
            margin: 4px 0 2px 0;
        }
        .main-diploma-title {
            font-size: 32px;
            font-weight: 900;
            color: #800020;
            letter-spacing: 5px;
            text-transform: uppercase;
            margin-bottom: 2px;
            text-shadow: 0 1px 1px rgba(0,0,0,0.08);
        }
        .diploma-subtitle {
            font-size: 11px;
            color: #64748b;
            font-style: italic;
            letter-spacing: 0.8px;
        }

        /* Cuerpo de Otorgamiento */
        .confer-text {
            font-size: 11.5px;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 1.8px;
            font-weight: bold;
            margin-top: 6px;
        }
        .student-name {
            font-size: 26px;
            font-weight: 900;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            display: inline-block;
            padding: 3px 25px;
            border-bottom: 2.5px solid #800020;
            margin: 4px 0 3px 0;
        }
        .student-dni-tag {
            font-family: 'Courier New', Courier, monospace;
            font-size: 11px;
            font-weight: bold;
            color: #800020;
            background: #fff1f2;
            padding: 3px 14px;
            border: 1px solid #fecdd3;
            border-radius: 9999px;
            display: inline-block;
            margin-bottom: 6px;
        }

        .cert-reason {
            font-size: 13px;
            line-height: 1.55;
            color: #334155;
            max-width: 250mm;
            margin: 0 auto;
        }
        .course-title-highlight {
            font-size: 18px;
            font-weight: 900;
            color: #800020;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            display: block;
            margin: 4px 0;
        }
        .cert-details-line {
            font-size: 11.5px;
            color: #475569;
            margin-top: 3px;
        }
        .grade-badge-line {
            display: inline-block;
            background: #f8fafc;
            border: 1.2px solid #cbd5e1;
            padding: 3px 14px;
            border-radius: 20px;
            font-size: 11.5px;
            font-weight: bold;
            color: #0f172a;
            margin-top: 5px;
        }
        .grade-badge-line strong {
            color: #047857;
            font-size: 12.5px;
        }

        /* Firmas y Sellos */
        .signatures-grid {
            display: flex;
            justify-content: space-around;
            align-items: flex-end;
            margin-top: 10px;
            padding: 0 40px;
        }
        .sign-col {
            width: 220px;
            text-align: center;
            position: relative;
        }
        .sign-stamp-overlay {
            position: absolute;
            top: -45px;
            left: 50%;
            transform: translateX(-50%);
            pointer-events: none;
            z-index: 2;
        }
        .sign-line {
            border-top: 1.5px solid #475569;
            padding-top: 6px;
            position: relative;
            z-index: 3;
        }
        .sign-name {
            font-size: 12.5px;
            font-weight: 900;
            color: #0f172a;
            text-transform: uppercase;
        }
        .sign-role {
            font-size: 9.5px;
            color: #64748b;
            text-transform: uppercase;
            font-weight: 600;
        }
        .sign-univ {
            font-size: 8.5px;
            color: #94a3b8;
            text-transform: uppercase;
        }

        /* Footer de la Primera Página */
        .cert-footer-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px dashed #cbd5e1;
            padding-top: 6px;
            font-size: 9.5px;
            color: #64748b;
            font-family: 'Courier New', Courier, monospace;
        }
        .meta-code {
            color: #800020;
            font-weight: bold;
        }

        /* ========================================================
           PÁGINA 2: REVERSO (SYLLABUS, NOTAS, DOCENTE Y QR PERMANENTE)
           ======================================================== */
        .back-header {
            border-bottom: 2.5px solid #800020;
            padding-bottom: 6px;
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            text-align: left;
        }
        .back-header-left {
            flex: 1;
        }
        .back-title-sub {
            font-size: 10px;
            font-weight: bold;
            color: #800020;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }
        .back-title-main {
            font-size: 16px;
            font-weight: 900;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .back-course-tag {
            font-size: 11px;
            color: #475569;
            font-weight: 700;
        }

        .back-content-grid {
            display: grid;
            grid-template-columns: 1.35fr 1fr;
            gap: 14px;
            flex: 1;
            text-align: left;
            margin-bottom: 8px;
        }

        /* Columna Izquierda: Temario / Módulos */
        .syllabus-container {
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            gap: 6px;
        }
        .section-label {
            font-size: 11.5px;
            font-weight: 900;
            color: #800020;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-bottom: 1.5px solid #cbd5e1;
            padding-bottom: 3px;
            margin-bottom: 4px;
            display: flex;
            justify-content: space-between;
        }
        .module-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-left: 3.5px solid #800020;
            padding: 5px 8px;
            border-radius: 3px;
        }
        .module-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 10px;
            font-weight: bold;
            color: #0f172a;
            margin-bottom: 2px;
        }
        .module-num {
            color: #800020;
            font-size: 9px;
            font-weight: 900;
            margin-right: 6px;
        }
        .module-title {
            flex: 1;
            font-size: 10px;
            color: #1e293b;
        }
        .module-hours {
            font-size: 9px;
            color: #64748b;
            font-family: monospace;
            background: #ffffff;
            padding: 1px 5px;
            border-radius: 3px;
            border: 1px solid #cbd5e1;
        }
        .module-topics {
            font-size: 8.5px;
            color: #475569;
            line-height: 1.35;
        }

        /* Columna Derecha: Calificación, Docente, QR y Criptografía */
        .meta-container {
            display: flex;
            flex-direction: column;
            gap: 8px;
            justify-content: space-between;
        }

        /* Caja de Calificación Oficial */
        .grade-box-card {
            background: #f0fdf4;
            border: 1.5px solid #86efac;
            border-radius: 5px;
            padding: 8px 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .grade-box-info {
            flex: 1;
        }
        .grade-title {
            font-size: 9px;
            font-weight: bold;
            color: #166534;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .grade-number-large {
            font-size: 24px;
            font-weight: 900;
            color: #14532d;
            line-height: 1.1;
        }
        .grade-text-sub {
            font-size: 9px;
            color: #15803d;
            font-weight: bold;
        }
        .grade-status-tag {
            background: #15803d;
            color: #ffffff;
            font-size: 10px;
            font-weight: 900;
            padding: 4px 10px;
            border-radius: 4px;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        /* Caja del Docente */
        .docente-info-card {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 5px;
            padding: 6px 10px;
        }
        .docente-label {
            font-size: 8.5px;
            color: #64748b;
            font-weight: bold;
            text-transform: uppercase;
        }
        .docente-val {
            font-size: 11px;
            font-weight: 900;
            color: #0f172a;
            text-transform: uppercase;
        }
        .docente-subval {
            font-size: 9px;
            color: #475569;
        }

        /* Caja de Validación QR Permanente */
        .qr-permanent-card {
            background: #ffffff;
            border: 1.5px solid #800020;
            border-radius: 6px;
            padding: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .qr-svg-holder {
            width: 90px;
            height: 90px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            padding: 2px;
            border-radius: 4px;
        }
        .qr-svg-holder svg {
            width: 100%;
            height: 100%;
            display: block;
        }
        .qr-info-right {
            flex: 1;
            font-size: 8.5px;
            color: #334155;
            line-height: 1.35;
        }
        .qr-main-instruction {
            font-size: 9.5px;
            font-weight: 900;
            color: #800020;
            text-transform: uppercase;
            margin-bottom: 2px;
        }
        .qr-url-display {
            font-family: monospace;
            font-size: 8px;
            color: #1e3a8a;
            word-break: break-all;
            background: #f1f5f9;
            padding: 2px 4px;
            border-radius: 3px;
            display: block;
            margin: 3px 0;
        }
        .qr-security-note {
            font-size: 8px;
            color: #64748b;
        }

        /* Hash Criptográfico Footer */
        .back-crypto-footer {
            border-top: 1px solid #cbd5e1;
            padding-top: 5px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 8px;
            font-family: 'Courier New', monospace;
            color: #64748b;
        }
        .hash-string {
            color: #0f172a;
            font-weight: bold;
            font-size: 7.5px;
            word-break: break-all;
            max-width: 180mm;
        }
    </style>
</head>
<body>

    <!-- ========================================================
         HOJA 1: ANVERSO - DIPLOMA DE HONOR INSTITUCIONAL
         ======================================================== -->
    <div class="page-sheet">
        <!-- Borde Académico Doble Oficial -->
        <div class="page-border"></div>
        <div class="page-border-inner"></div>

        <!-- Marca de Agua Central -->
        <img class="watermark" src="${unsaacLogoUrl}" alt="Escudo UNSAAC" />

        <div class="cert-content">
            <!-- Cabecera Oficial Superior: 3 Logos -->
            <div class="header-top">
                <div class="header-logo-box">
                    <img src="${unsaacLogoUrl}" class="header-logo-img" alt="UNSAAC Logo" />
                </div>
                <div class="header-center-text">
                    <div class="gov-title">REPÚBLICA DEL PERÚ • REGIÓN CUSCO</div>
                    <div class="univ-title">UNIVERSIDAD NACIONAL DE SAN ANTONIO ABAD DEL CUSCO</div>
                    <div class="fac-title">FACULTAD DE INGENIERÍA ELÉCTRICA, ELECTRÓNICA, INFORMÁTICA Y MECÁNICA</div>
                    <div class="sigc-badge-title">SISTEMA INTEGRAL DE GESTIÓN DE CAPACITACIONES (SIGC-CUSCO)</div>
                </div>
                <div class="header-logo-box">
                    ${goldMedalSvg}
                </div>
            </div>

            <!-- Título del Certificado -->
            <div class="diploma-title-box">
                <div class="main-diploma-title">CERTIFICADO</div>
                <div class="diploma-subtitle">Por culminación académica satisfactoria y acreditación de competencias profesionales</div>
            </div>

            <!-- Otorgado a -->
            <div>
                <div class="confer-text">Conferido en testimonio de honor a:</div>
                <div class="student-name">${record.student_name}</div>
                <br>
                <div class="student-dni-tag">DOCUMENTO NACIONAL DE IDENTIDAD: D.N.I. N° ${record.dni}</div>
            </div>

            <!-- Motivo y Curso -->
            <div class="cert-reason">
                Por haber aprobado satisfactoriamente con alto rendimiento académico el programa de capacitación especializada en:
                <span class="course-title-highlight">"${record.course_title}"</span>
                <div class="cert-details-line">
                    Desarrollado <strong>${dateRangeProse}</strong>,
                    con una duración lectiva de <strong>${record.hours} HORAS ACADÉMICAS</strong>,
                    en cumplimiento de los estándares de acreditación universitaria de fe pública.
                </div>
                <div class="grade-badge-line">
                    Calificación Obtenida: <strong>${record.final_grade} / 20.00</strong> — ${record.final_grade_text || gradeHonor}
                </div>
            </div>

            <!-- Lugar y Fecha Oficial de Emisión -->
            <div class="issue-date-line">
                ${cityIssuedProse}
            </div>

            <!-- Firmas y Sellos Digitales -->
            <div class="signatures-grid">
                <div class="sign-col">
                    <div class="sign-stamp-overlay">
                        ${docenteStampSvg}
                    </div>
                    <div class="sign-line">
                        <div class="sign-name">${record.instructor_name}</div>
                        <div class="sign-role">${record.instructor_title || 'Docente Responsable / Especialista'}</div>
                        <div class="sign-univ">${record.institution || 'Universidad Nacional de San Antonio Abad del Cusco'}</div>
                    </div>
                </div>

                <div class="sign-col" style="width: 100px; display: flex; justify-content: center; align-items: center;">
                    ${emblemSvg}
                </div>

                <div class="sign-col">
                    <div class="sign-stamp-overlay">
                        ${direccionStampSvg}
                    </div>
                    <div class="sign-line">
                        <div class="sign-name">DIRECCIÓN ACADÉMICA</div>
                        <div class="sign-role">Coordinación General SIGC-CUSCO</div>
                        <div class="sign-univ">Universidad Nacional de San Antonio Abad del Cusco</div>
                    </div>
                </div>
            </div>

            <!-- Metadatos del Certificado -->
            <div class="cert-footer-meta">
                <span>CÓDIGO DE REGISTRO: <strong class="meta-code">${record.certificate_code}</strong></span>
                <span>FECHA DE EMISIÓN: <strong>${issuedDateProse}</strong></span>
                <span>ACREDITADO • LEY UNIVERSITARIA N° 30220 • CUSCO, PERÚ</span>
            </div>
        </div>
    </div>

    <!-- ========================================================
         HOJA 2: REVERSO - SYLLABUS, NOTAS Y QR PERMANENTE
         ======================================================== -->
    <div class="page-sheet">
        <!-- Borde Académico Doble Oficial -->
        <div class="page-border"></div>
        <div class="page-border-inner"></div>

        <div class="cert-content">
            <!-- Cabecera del Reverso -->
            <div class="back-header">
                <div class="back-header-left">
                    <div class="back-title-sub">UNIVERSIDAD NACIONAL DE SAN ANTONIO ABAD DEL CUSCO • SIGC</div>
                    <div class="back-title-main">PLAN DE ESTUDIOS Y REGISTRO OFICIAL DE CALIFICACIONES</div>
                    <div class="back-course-tag">Curso: <strong>${record.course_title}</strong> (Código: ${record.course_code})</div>
                </div>
                <div style="text-align: right; font-family: monospace; font-size: 9.5px; color: #800020;">
                    <div>REGISTRO N°: <strong>${record.certificate_code}</strong></div>
                    <div>TOTAL: <strong>${record.hours} HORAS ACADÉMICAS</strong></div>
                </div>
            </div>

            <!-- Contenido Principal: 2 Columnas -->
            <div class="back-content-grid">
                <!-- Columna 1: Módulos del Plan Curricular -->
                <div class="syllabus-container">
                    <div class="section-label">
                        <span>ESTRUCTURA CURRICULAR Y COMPETENCIAS</span>
                        <span style="font-size: 9px; color: #64748b;">${record.hours} Horas Lectivas</span>
                    </div>
                    ${modulesHtml}
                </div>

                <!-- Columna 2: Calificación, Docente y QR Permanente -->
                <div class="meta-container">
                    <!-- Registro de Calificación -->
                    <div class="grade-box-card">
                        <div class="grade-box-info">
                            <div class="grade-title">REGISTRO DE CALIFICACIÓN OFICIAL</div>
                            <div class="grade-number-large">${record.final_grade}</div>
                            <div class="grade-text-sub">${record.final_grade_text || 'Veinte (20) - Sobresaliente'}</div>
                        </div>
                        <div class="grade-status-tag">
                            ${record.status === 'aprobado' ? '✓ APROBADO' : record.status.toUpperCase()}
                        </div>
                    </div>

                    <!-- Docente Responsable -->
                    <div class="docente-info-card">
                        <div class="docente-label">DOCENTE ASIGNADO / PONENTE:</div>
                        <div class="docente-val">${record.instructor_name}</div>
                        <div class="docente-subval">${record.instructor_title || 'Docente Principal e Investigador'} • ${record.institution || 'UNSAAC'}</div>
                    </div>

                    <!-- Código QR Permanente de Validación en Línea -->
                    <div class="qr-permanent-card">
                        <div class="qr-svg-holder">
                            ${record.qr_svg || '<svg viewBox="0 0 100 100"><rect width="100" height="100" fill="#ddd"/><text x="50" y="50" text-anchor="middle">QR</text></svg>'}
                        </div>
                        <div class="qr-info-right">
                            <div class="qr-main-instruction">ESCANEE PARA VERIFICAR AUTENTICIDAD EN LÍNEA</div>
                            <div class="qr-security-note">
                                El código QR enlaza al registro inmutable y permanente del Sistema Integral de Capacitaciones.
                            </div>
                            <span class="qr-url-display">${verificationUrl}</span>
                            <div style="font-size: 7.5px; color: #475569; margin-top: 2px;">
                                Código: <strong>${record.certificate_code}</strong> | DNI: <strong>${record.dni}</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer con Firma Criptográfica SHA-256 -->
            <div class="back-crypto-footer">
                <div>
                    <strong>HUELLA DIGITAL CRIPTOGRÁFICA (SHA-256):</strong>
                    <div class="hash-string">${record.certificate_hash || 'SHA-256 INMUTABLE'}</div>
                </div>
                <div style="text-align: right; flex-shrink: 0; margin-left: 10px;">
                    FECHA DE REGISTRO EN ACTAS: <strong>${issuedDateProse}</strong>
                </div>
            </div>
        </div>
    </div>

</body>
</html>
    `;
}

/**
 * Dispara la impresión / guardado directo en PDF del certificado oficial de dos páginas
 */
export function printCertificate(record: CertificateRecord): void {
    const html = generateOfficialCertificateHtml(record);

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

    // Esperar brevemente para carga de fuentes e imágenes y disparar diálogo de impresión/PDF
    setTimeout(() => {
        iframe.contentWindow?.focus();
        iframe.contentWindow?.print();
        setTimeout(() => {
            if (document.body.contains(iframe)) {
                document.body.removeChild(iframe);
            }
        }, 3500);
    }, 400);
}
