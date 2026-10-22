<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import {
    Palette,
    Check,
    RotateCcw,
    Building2,
    Sparkles,
    ShieldCheck,
    Award,
    Eye,
    Sun,
    GraduationCap,
    QrCode,
    FileText,
} from '@lucide/vue';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useBranding } from '@/composables/useBranding';
import { notify } from '@/lib/notify';
import { edit } from '@/routes/appearance';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Configuración de apariencia',
                href: edit(),
            },
        ],
    },
});

const {
    institutionName,
    systemName,
    systemSubtitle,
    logoUrl,
    primaryColor,
    presetKey,
    presets,
    applyPreset,
    setManualColor,
    setLogoUrl,
    setEntityNames,
    resetToMdsj,
    saveBranding,
} = useBranding();

const formInstitutionName = ref(institutionName.value);
const formSystemName = ref(systemName.value);
const formSystemSubtitle = ref(systemSubtitle.value);
const formLogoUrl = ref(logoUrl.value);
const formPrimaryColor = ref(primaryColor.value);

const availableLogos = [
    { label: 'Escudo MDSJ', url: '/images/mdsj/escudo_sm.webp' },
    { label: 'Logo MDSJ', url: '/images/mdsj/logo_mdsj_sm.webp' },
    { label: 'UNSAAC', url: '/images/unsaac-logo.png' },
    { label: 'Escudo Nacional', url: '/images/escudo.png' },
];

function selectPreset(preset: (typeof presets)[0]) {
    applyPreset(preset);
    formInstitutionName.value = preset.institutionName;
    formSystemName.value = preset.systemName;
    formSystemSubtitle.value = preset.systemSubtitle;
    formLogoUrl.value = preset.logoUrl;
    formPrimaryColor.value = preset.color;
    notify.success('Paleta Aplicada', `Identidad visual cambiada a ${preset.label}`, 2500);
}

function onManualColorInput(e: Event) {
    const target = e.target as HTMLInputElement;
    formPrimaryColor.value = target.value;
    setManualColor(target.value);
}

function applyAllChanges() {
    setEntityNames({
        institutionName: formInstitutionName.value,
        systemName: formSystemName.value,
        systemSubtitle: formSystemSubtitle.value,
    });
    setLogoUrl(formLogoUrl.value);
    setManualColor(formPrimaryColor.value);
    saveBranding();

    notify.success(
        '¡Identidad Actualizada!',
        'Los cambios de colores, entidad y logotipos se han unificado en todo el sistema.',
        3000,
    );
}

function handleReset() {
    resetToMdsj();
    formInstitutionName.value = institutionName.value;
    formSystemName.value = systemName.value;
    formSystemSubtitle.value = systemSubtitle.value;
    formLogoUrl.value = logoUrl.value;
    formPrimaryColor.value = primaryColor.value;
    notify.info('Restaurado', 'Se ha restablecido la identidad oficial de la MDSJ San Jerónimo.');
}
</script>

<template>
    <Head title="Configuración de apariencia" />

    <h1 class="sr-only">Configuración de apariencia</h1>

    <div class="space-y-8">
        <!-- 1. MODO VISUAL (TEMA CLARO / OSCURO / SISTEMA) -->
        <div
            class="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-xs sm:p-6 dark:border-slate-800 dark:bg-slate-900"
        >
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="space-y-1">
                    <div class="flex items-center gap-2">
                        <div
                            class="flex size-7 items-center justify-center rounded-lg bg-sky-100 text-[#3484a5] dark:bg-sky-950 dark:text-sky-300"
                        >
                            <Sun class="size-4" />
                        </div>
                        <h2 class="text-sm font-bold text-slate-900 sm:text-base dark:text-white">
                            Modo Visual del Sistema
                        </h2>
                    </div>
                    <p class="text-xs text-slate-500 sm:text-sm dark:text-slate-400">
                        Selecciona el tema de contraste que mejor se adapte a tu preferencia de trabajo.
                    </p>
                </div>

                <div class="shrink-0">
                    <AppearanceTabs />
                </div>
            </div>
        </div>

        <!-- 2. GRID PRINCIPAL EN 2 COLUMNAS (CONTROLES A LA IZQUIERDA + VISTA PREVIA STICKY A LA DERECHA) -->
        <div class="grid grid-cols-1 gap-8 xl:grid-cols-12">
            <!-- COLUMNA IZQUIERDA (xl:col-span-7): PREAJUSTES, COLOR HEX Y DATOS DE ENTIDAD -->
            <div class="space-y-6 xl:col-span-7">
                <!-- PREAJUSTES DE ENTIDAD Y COLORES -->
                <div
                    class="space-y-4 rounded-2xl border border-slate-200/90 bg-white p-5 shadow-xs sm:p-6 dark:border-slate-800 dark:bg-slate-900"
                >
                    <div class="space-y-1">
                        <div class="flex items-center gap-2">
                            <Sparkles class="size-4 text-amber-500" />
                            <h3 class="text-xs font-black tracking-wider text-slate-800 uppercase dark:text-slate-200">
                                Preajustes de Entidad y Colores
                            </h3>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Aplica configuraciones oficiales con un solo clic. Modifica colores, escudo y lema institucional.
                        </p>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <button
                            v-for="p in presets"
                            :key="p.key"
                            type="button"
                            @click="selectPreset(p)"
                            class="group relative flex cursor-pointer items-start gap-3 rounded-xl border p-3.5 text-left transition-all hover:scale-[1.01]"
                            :class="
                                presetKey === p.key
                                    ? 'border-[#3484a5] bg-sky-50/70 shadow-sm ring-2 ring-[#3484a5]/40 dark:border-[#3484a5] dark:bg-[#153542]/40'
                                    : 'border-slate-200 bg-white hover:border-slate-300 dark:border-slate-800 dark:bg-slate-900 dark:hover:border-slate-700'
                            "
                        >
                            <div
                                class="relative flex size-9 shrink-0 items-center justify-center rounded-lg shadow-sm"
                                :style="{ backgroundColor: p.color }"
                            >
                                <Check v-if="presetKey === p.key" class="size-4 text-white stroke-[3]" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-1.5 text-xs font-bold text-slate-900 dark:text-white">
                                    <span class="truncate">{{ p.label }}</span>
                                </div>
                                <p class="mt-0.5 line-clamp-2 text-[11px] text-slate-500 dark:text-slate-400">
                                    {{ p.description }}
                                </p>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- SELECTOR MANUAL DE COLOR HEX -->
                <div
                    class="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-xs sm:p-6 dark:border-slate-800 dark:bg-slate-900"
                >
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <Palette class="size-4 text-[#3484a5]" />
                                <h3 class="text-xs font-bold text-slate-900 sm:text-sm dark:text-white">
                                    Color Institucional Personalizado (HEX)
                                </h3>
                            </div>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                Modifica el tono maestro en tiempo real para todos los botones, bordes y diplomas.
                            </p>
                        </div>

                        <div class="flex items-center gap-3">
                            <input
                                type="color"
                                :value="formPrimaryColor"
                                @input="onManualColorInput"
                                class="size-10 cursor-pointer rounded-xl border border-slate-300 p-0.5 shadow-xs dark:border-slate-700"
                                title="Haz clic para abrir el selector de color"
                            />
                            <div class="relative w-32">
                                <Input
                                    v-model="formPrimaryColor"
                                    placeholder="#3484a5"
                                    maxlength="7"
                                    class="h-10 font-mono text-xs uppercase"
                                    @input="setManualColor(formPrimaryColor)"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- INFORMACIÓN Y CAMPOS EDITABLES DE LA ENTIDAD -->
                <div
                    class="space-y-5 rounded-2xl border border-slate-200/90 bg-white p-5 shadow-xs sm:p-6 dark:border-slate-800 dark:bg-slate-900"
                >
                    <div class="space-y-1">
                        <div class="flex items-center gap-2">
                            <Building2 class="size-4 text-[#3484a5]" />
                            <h3 class="text-xs font-black tracking-wider text-slate-800 uppercase dark:text-slate-200">
                                Información de la Entidad
                            </h3>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Nombres oficiales que figurarán en diplomas de graduación, actas y credenciales.
                        </p>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="space-y-1.5 sm:col-span-2">
                            <Label for="settings_inst_name" class="text-xs font-bold">Nombre Oficial de la Entidad</Label>
                            <Input
                                id="settings_inst_name"
                                v-model="formInstitutionName"
                                placeholder="Ej. Municipalidad Distrital de San Jerónimo"
                                class="h-10 text-xs font-semibold"
                            />
                        </div>

                        <div class="space-y-1.5">
                            <Label for="settings_sys_name" class="text-xs font-bold">Sigla / Nombre del Sistema</Label>
                            <Input
                                id="settings_sys_name"
                                v-model="formSystemName"
                                placeholder="Ej. SIGC-MDSJ"
                                class="h-10 text-xs font-bold"
                            />
                        </div>

                        <div class="space-y-1.5">
                            <Label for="settings_sys_sub" class="text-xs font-bold">Lema o Subtítulo Institucional</Label>
                            <Input
                                id="settings_sys_sub"
                                v-model="formSystemSubtitle"
                                placeholder="Ej. Sistema Integral de Gestión de Capacitaciones"
                                class="h-10 text-xs"
                            />
                        </div>

                        <div class="space-y-2 sm:col-span-2">
                            <Label for="settings_logo_url" class="text-xs font-bold">Ruta o URL del Logotipo</Label>
                            <div class="flex gap-2">
                                <Input
                                    id="settings_logo_url"
                                    v-model="formLogoUrl"
                                    placeholder="/images/mdsj/escudo_sm.webp o https://..."
                                    class="h-10 text-xs"
                                />
                                <div
                                    class="flex size-10 shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-slate-50 p-1 dark:border-slate-800 dark:bg-slate-800"
                                >
                                    <img
                                        :src="formLogoUrl"
                                        alt="Vista Previa Logo"
                                        class="max-h-full max-w-full object-contain"
                                        @error="(e) => ((e.target as HTMLImageElement).src = '/images/escudo.png')"
                                    />
                                </div>
                            </div>

                            <!-- Opciones Rápidas de Logotipo -->
                            <div class="flex flex-wrap items-center gap-1.5 pt-1 text-[11px]">
                                <span class="text-slate-500">Logotipos disponibles:</span>
                                <button
                                    v-for="logo in availableLogos"
                                    :key="logo.url"
                                    type="button"
                                    @click="formLogoUrl = logo.url; setLogoUrl(logo.url)"
                                    class="cursor-pointer rounded-lg border border-slate-200 bg-slate-100 px-2.5 py-1 font-semibold text-slate-700 transition-colors hover:border-[#3484a5] hover:text-[#3484a5] dark:border-slate-800 dark:bg-slate-800 dark:text-slate-300"
                                >
                                    {{ logo.label }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- COLUMNA DERECHA (xl:col-span-5): VISTA PREVIA EN VIVO STICKY Y ACCIONES -->
            <div class="space-y-6 xl:col-span-5">
                <div class="space-y-6 xl:sticky xl:top-24">
                    <!-- TARJETA DE VISTA PREVIA INTERACTIVA EN TIEMPO REAL -->
                    <div
                        class="space-y-4 rounded-2xl border-2 border-slate-200/90 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                    >
                        <div class="flex items-center justify-between border-b border-slate-100 pb-3 dark:border-slate-800">
                            <div class="flex items-center gap-2">
                                <Eye class="size-4 text-emerald-500" />
                                <h3 class="text-xs font-black tracking-wider text-slate-800 uppercase dark:text-slate-200">
                                    Vista Previa en Vivo
                                </h3>
                            </div>
                            <span
                                class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-black uppercase tracking-wider text-white"
                                :style="{ backgroundColor: formPrimaryColor }"
                            >
                                <span class="size-1.5 rounded-full bg-white animate-pulse" />
                                Activo
                            </span>
                        </div>

                        <!-- 1. MOCKUP DE NAVBAR -->
                        <div class="space-y-1.5">
                            <div class="text-[11px] font-bold text-slate-500">Cabecera del Sistema</div>
                            <div
                                class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50/70 p-3 dark:border-slate-800 dark:bg-slate-950/60"
                            >
                                <div class="flex items-center gap-2.5">
                                    <div
                                        class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-white p-1 shadow-2xs dark:bg-slate-900"
                                    >
                                        <img
                                            :src="formLogoUrl"
                                            alt="Logo"
                                            class="size-full object-contain"
                                            @error="(e) => ((e.target as HTMLImageElement).src = '/images/escudo.png')"
                                        />
                                    </div>
                                    <div class="min-w-0">
                                        <div class="truncate text-xs font-black text-slate-900 dark:text-white">
                                            {{ formSystemName || 'SIGC' }}
                                        </div>
                                        <div
                                            class="truncate text-[10px] font-bold uppercase"
                                            :style="{ color: formPrimaryColor }"
                                        >
                                            {{ formInstitutionName || 'Entidad' }}
                                        </div>
                                    </div>
                                </div>
                                <div
                                    class="rounded-md px-2 py-0.5 text-[10px] font-black text-white"
                                    :style="{ backgroundColor: formPrimaryColor }"
                                >
                                    Panel
                                </div>
                            </div>
                        </div>

                        <!-- 2. MOCKUP DE COMPONENTES INTERACTIVOS -->
                        <div class="space-y-1.5">
                            <div class="text-[11px] font-bold text-slate-500">Componentes y Botones</div>
                            <div
                                class="flex flex-wrap items-center gap-2.5 rounded-xl border border-slate-200 bg-slate-50/70 p-3 dark:border-slate-800 dark:bg-slate-950/60"
                            >
                                <button
                                    type="button"
                                    class="flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-bold text-white shadow-xs"
                                    :style="{ backgroundColor: formPrimaryColor }"
                                >
                                    <GraduationCap class="size-3.5" />
                                    <span>Inscribirme</span>
                                </button>
                                <span
                                    class="rounded-full px-2.5 py-0.5 text-[10px] font-black uppercase text-white shadow-2xs"
                                    :style="{ backgroundColor: formPrimaryColor }"
                                >
                                    <Award class="mr-0.5 inline size-3" />
                                    Certificado
                                </span>
                                <span
                                    class="rounded-lg border px-2 py-1 text-[11px] font-bold"
                                    :style="{ borderColor: formPrimaryColor, color: formPrimaryColor }"
                                >
                                    20 hrs acad.
                                </span>
                            </div>
                        </div>

                        <!-- 3. MOCKUP DE DIPLOMA OFICIAL -->
                        <div class="space-y-1.5">
                            <div class="text-[11px] font-bold text-slate-500">Diploma Oficial MDSJ</div>
                            <div
                                class="relative overflow-hidden rounded-xl border-2 bg-white/95 p-3 text-center dark:bg-slate-900"
                                :style="{ borderColor: formPrimaryColor }"
                            >
                                <div
                                    class="absolute inset-1 rounded-lg border pointer-events-none opacity-40"
                                    :style="{ borderColor: formPrimaryColor }"
                                />
                                <div class="relative space-y-1">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <FileText class="size-3.5" :style="{ color: formPrimaryColor }" />
                                        <span
                                            class="font-serif text-[11px] font-black tracking-wider uppercase text-slate-900 dark:text-white"
                                        >
                                            DIPLOMA DE CAPACITACIÓN
                                        </span>
                                    </div>
                                    <p class="text-[10px] text-slate-600 dark:text-slate-300">
                                        Otorgado por: <strong class="text-slate-900 dark:text-white">{{ formInstitutionName }}</strong>
                                    </p>
                                    <div class="flex items-center justify-center gap-3 pt-1 text-[9px] text-slate-500 dark:text-slate-400">
                                        <span class="flex items-center gap-1">
                                            <QrCode class="size-3 text-emerald-600" />
                                            QR de Fe Pública
                                        </span>
                                        <span class="flex items-center gap-1">
                                            <ShieldCheck class="size-3 text-blue-600" />
                                            Firma Criptográfica
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TARJETA DE ACCIONES Y PERSISTENCIA -->
                    <div
                        class="space-y-3 rounded-2xl border border-slate-200/90 bg-white p-5 shadow-xs dark:border-slate-800 dark:bg-slate-900"
                    >
                        <Button
                            type="button"
                            class="h-11 w-full cursor-pointer justify-center text-xs font-black text-white shadow-sm transition-transform hover:scale-[1.01]"
                            :style="{ backgroundColor: formPrimaryColor }"
                            @click="applyAllChanges"
                        >
                            <Check class="mr-1.5 size-4 stroke-[2.5]" />
                            Guardar Cambios Institucionales
                        </Button>

                        <Button
                            type="button"
                            variant="outline"
                            class="h-10 w-full cursor-pointer justify-center border-slate-300 text-xs font-bold text-slate-700 hover:bg-slate-100 hover:text-slate-900 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                            @click="handleReset"
                        >
                            <RotateCcw class="mr-1.5 size-3.5 text-slate-500" />
                            Restablecer Oficial MDSJ
                        </Button>

                        <p class="text-center text-[11px] text-slate-500 dark:text-slate-400">
                            Los cambios se guardan localmente y se propagan inmediatamente a todos los módulos.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
