<script setup lang="ts">
import { ref, computed } from 'vue';
import {
    Palette,
    Check,
    RotateCcw,
    Building2,
    Sliders,
    Sparkles,
    Image,
    Sun,
    Moon,
    ShieldCheck,
    Award,
    Eye,
} from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog';
import { useBranding } from '@/composables/useBranding';
import { useAppearance } from '@/composables/useAppearance';
import { notify } from '@/lib/notify';

const props = defineProps<{
    open: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:open', val: boolean): void;
}>();

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

const { appearance, updateAppearance } = useAppearance();

// Local editable copies for preview & form
const formInstitutionName = ref(institutionName.value);
const formSystemName = ref(systemName.value);
const formSystemSubtitle = ref(systemSubtitle.value);
const formLogoUrl = ref(logoUrl.value);
const formPrimaryColor = ref(primaryColor.value);

// Quick Logo Options
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
        3500,
    );
    emit('update:open', false);
}

function handleReset() {
    resetToMdsj();
    formInstitutionName.value = institutionName.value;
    formSystemName.value = systemName.value;
    formSystemSubtitle.value = systemSubtitle.value;
    formLogoUrl.value = logoUrl.value;
    formPrimaryColor.value = primaryColor.value;
    notify.info('Valores Restaurados', 'Identidad de San Jerónimo restablecida', 2500);
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent
            class="max-h-[92vh] w-[95vw] max-w-3xl overflow-y-auto rounded-3xl border border-slate-200 bg-white p-6 shadow-2xl dark:border-slate-800 dark:bg-slate-950"
        >
            <DialogHeader class="border-b border-slate-200 pb-4 dark:border-slate-800">
                <div class="flex items-center gap-3">
                    <div
                        class="flex size-11 items-center justify-center rounded-2xl text-white shadow-md"
                        :style="{ backgroundColor: formPrimaryColor }"
                    >
                        <Palette class="size-6 text-white" />
                    </div>
                    <div>
                        <DialogTitle class="text-xl font-black text-slate-950 dark:text-white">
                            Personalización Institucional y Marca Blanca
                        </DialogTitle>
                        <DialogDescription class="text-xs text-slate-600 dark:text-slate-400">
                            Modifica logotipos, nombres de entidad y colores primarios del sistema con impacto unificado en tiempo real.
                        </DialogDescription>
                    </div>
                </div>
            </DialogHeader>

            <div class="space-y-6 pt-2">
                <!-- 1. SELECTOR DE PALETAS PREDEFINIDAS -->
                <div class="space-y-2">
                    <Label class="text-xs font-black tracking-wider uppercase text-slate-700 dark:text-slate-300">
                        1. Paletas de Entidad Predefinidas (1 Clic)
                    </Label>
                    <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-3">
                        <button
                            v-for="p in presets"
                            :key="p.key"
                            type="button"
                            @click="selectPreset(p)"
                            class="flex cursor-pointer items-center gap-2.5 rounded-xl border p-2.5 text-left transition-all hover:scale-[1.02] active:scale-95"
                            :class="
                                formPrimaryColor.toLowerCase() === p.color.toLowerCase()
                                    ? 'border-slate-950 bg-slate-50 ring-2 ring-slate-950/20 dark:border-white dark:bg-slate-900 dark:ring-white/20'
                                    : 'border-slate-200 bg-white hover:border-slate-300 dark:border-slate-800 dark:bg-slate-900/50'
                            "
                        >
                            <span
                                class="size-6 shrink-0 rounded-full shadow-xs ring-1 ring-black/10"
                                :style="{ backgroundColor: p.color }"
                            />
                            <div class="min-w-0 flex-1">
                                <div class="truncate text-xs font-bold text-slate-900 dark:text-white">
                                    {{ p.label }}
                                </div>
                                <div class="font-mono text-[10px] text-slate-500 uppercase">
                                    {{ p.color }}
                                </div>
                            </div>
                            <Check
                                v-if="formPrimaryColor.toLowerCase() === p.color.toLowerCase()"
                                class="size-4 shrink-0 text-emerald-600 dark:text-emerald-400"
                            />
                        </button>
                    </div>
                </div>

                <!-- 2. INGRESO MANUAL DE COLOR (HEX / COLOR PICKER) -->
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-900/50">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <Label class="flex items-center gap-1.5 text-xs font-black text-slate-900 dark:text-white">
                                <Sliders class="size-3.5 text-[#3484a5]" />
                                Color Primario Personalizado (Ingreso Manual)
                            </Label>
                            <p class="text-[11px] text-slate-500">
                                Ingrese cualquier código hexadecimal o elija un color con el selector.
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <input
                                type="color"
                                :value="formPrimaryColor"
                                @input="onManualColorInput"
                                class="size-10 cursor-pointer rounded-xl border-2 border-white shadow-sm"
                                title="Seleccionar color con cuentagotas"
                            />
                            <Input
                                v-model="formPrimaryColor"
                                @change="setManualColor(formPrimaryColor)"
                                placeholder="#3484a5"
                                class="w-28 font-mono text-xs font-black uppercase text-slate-900 dark:text-white"
                                maxlength="7"
                            />
                        </div>
                    </div>
                </div>

                <!-- 3. IDENTIDAD INSTITUCIONAL / TEXTOS DEL SISTEMA -->
                <div class="space-y-4">
                    <Label class="text-xs font-black tracking-wider uppercase text-slate-700 dark:text-slate-300">
                        2. Identidad y Textos de la Entidad
                    </Label>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="space-y-1">
                            <Label class="text-xs font-bold text-slate-700 dark:text-slate-300">
                                Nombre de la Entidad / Institución
                            </Label>
                            <Input
                                v-model="formInstitutionName"
                                placeholder="Municipalidad Distrital de San Jerónimo"
                                class="text-xs font-semibold"
                            />
                        </div>

                        <div class="space-y-1">
                            <Label class="text-xs font-bold text-slate-700 dark:text-slate-300">
                                Siglas / Nombre del Sistema
                            </Label>
                            <Input
                                v-model="formSystemName"
                                placeholder="SIGC-MDSJ"
                                class="font-mono text-xs font-bold"
                            />
                        </div>

                        <div class="space-y-1 sm:col-span-2">
                            <Label class="text-xs font-bold text-slate-700 dark:text-slate-300">
                                Lema o Subtítulo Institucional
                            </Label>
                            <Input
                                v-model="formSystemSubtitle"
                                placeholder="Sistema Integral de Gestión de Capacitaciones"
                                class="text-xs"
                            />
                        </div>
                    </div>
                </div>

                <!-- 4. LOGOTIPO INSTITUCIONAL -->
                <div class="space-y-3">
                    <Label class="text-xs font-black tracking-wider uppercase text-slate-700 dark:text-slate-300">
                        3. Logotipo del Sistema
                    </Label>

                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            v-for="lg in availableLogos"
                            :key="lg.url"
                            type="button"
                            @click="formLogoUrl = lg.url; setLogoUrl(lg.url)"
                            class="flex cursor-pointer items-center gap-2 rounded-xl border p-2 transition-all hover:bg-slate-50 dark:hover:bg-slate-900"
                            :class="
                                formLogoUrl === lg.url
                                    ? 'border-[#3484a5] bg-sky-50/60 ring-2 ring-[#3484a5]/30 dark:bg-sky-950/40'
                                    : 'border-slate-200 dark:border-slate-800'
                            "
                        >
                            <img :src="lg.url" :alt="lg.label" class="size-6 object-contain" />
                            <span class="text-xs font-bold text-slate-800 dark:text-slate-200">{{ lg.label }}</span>
                        </button>
                    </div>

                    <div class="space-y-1">
                        <Label class="text-[11px] font-bold text-slate-600 dark:text-slate-400">
                            O URL Personalizada de Logotipo (PNG / WebP / SVG)
                        </Label>
                        <Input
                            v-model="formLogoUrl"
                            placeholder="https://ejemplo.gob.pe/logo.png"
                            class="font-mono text-xs"
                        />
                    </div>
                </div>

                <!-- 5. VISTA PREVIA INTERACTIVA EN TIEMPO REAL -->
                <div class="space-y-2">
                    <Label class="flex items-center gap-1.5 text-xs font-black tracking-wider uppercase text-slate-700 dark:text-slate-300">
                        <Eye class="size-3.5 text-amber-500" />
                        Vista Previa Dinámica del Tema
                    </Label>

                    <div
                        class="space-y-4 rounded-2xl border p-4 shadow-inner"
                        :class="appearance === 'dark' ? 'bg-slate-900 border-slate-800 text-white' : 'bg-slate-50 border-slate-200 text-slate-900'"
                    >
                        <!-- Header Preview -->
                        <div class="flex items-center justify-between rounded-xl border border-slate-200 bg-white p-3 shadow-xs dark:border-slate-800 dark:bg-slate-950">
                            <div class="flex items-center gap-2.5">
                                <img :src="formLogoUrl" alt="Logo" class="size-8 object-contain" />
                                <div>
                                    <div class="text-xs font-black text-slate-900 dark:text-white">
                                        {{ formSystemName }}
                                    </div>
                                    <div class="text-[10px] font-bold opacity-80" :style="{ color: formPrimaryColor }">
                                        {{ formInstitutionName }}
                                    </div>
                                </div>
                            </div>
                            <span
                                class="rounded-full px-2.5 py-0.5 text-[10px] font-black text-white shadow-xs"
                                :style="{ backgroundColor: formPrimaryColor }"
                            >
                                ACTIVO
                            </span>
                        </div>

                        <!-- Buttons & Badges Preview -->
                        <div class="flex flex-wrap items-center gap-2">
                            <button
                                type="button"
                                class="rounded-xl px-4 py-2 text-xs font-black text-white shadow-sm transition-all hover:brightness-110 active:scale-95"
                                :style="{ backgroundColor: formPrimaryColor }"
                            >
                                Botón Primario
                            </button>
                            <button
                                type="button"
                                class="rounded-xl border-2 px-4 py-2 text-xs font-bold transition-all"
                                :style="{ borderColor: formPrimaryColor, color: formPrimaryColor }"
                            >
                                Botón Borde
                            </button>
                            <span
                                class="inline-flex items-center gap-1 rounded-full px-3 py-1 font-mono text-xs font-bold"
                                :style="{
                                    backgroundColor: formPrimaryColor + '20',
                                    color: formPrimaryColor,
                                }"
                            >
                                <Award class="size-3" />
                                Código Acreditado
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- FOOTER DE ACCIONES -->
            <div class="mt-6 flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 pt-4 dark:border-slate-800">
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="cursor-pointer text-xs font-bold text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white"
                    @click="handleReset"
                >
                    <RotateCcw class="mr-1.5 size-3.5" />
                    Restaurar MDSJ por Defecto
                </Button>

                <div class="flex items-center gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="cursor-pointer text-xs font-bold"
                        @click="emit('update:open', false)"
                    >
                        Cancelar
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        class="cursor-pointer text-xs font-black text-white shadow-md hover:brightness-110 active:scale-95"
                        :style="{ backgroundColor: formPrimaryColor }"
                        @click="applyAllChanges"
                    >
                        <Sparkles class="mr-1.5 size-4 text-amber-300" />
                        Guardar y Aplicar a Todo el Sistema
                    </Button>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
