<script setup lang="ts">
import { ref } from 'vue';
import {
    ShieldCheck,
    UploadCloud,
    FileCheck,
    AlertTriangle,
    CheckCircle2,
    Loader2,
    X,
} from '@lucide/vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { notify } from '@/lib/notify';

const props = defineProps<{
    expectedHash?: string | null;
}>();

const selectedFileName = ref<string | null>(null);
const calculatedHash = ref<string | null>(null);
const isCalculating = ref(false);
const errorMsg = ref<string | null>(null);

async function handleFileChange(event: Event) {
    const input = event.target as HTMLInputElement;
    if (!input.files || input.files.length === 0) return;

    const file = input.files[0];
    if (file.type !== 'application/pdf' && !file.name.endsWith('.pdf')) {
        errorMsg.value = 'Por favor seleccione un archivo en formato PDF.';
        notify.warning(
            'Formato no válido',
            'Seleccione un archivo en formato PDF.',
            2500,
        );
        return;
    }

    selectedFileName.value = file.name;
    errorMsg.value = null;
    isCalculating.value = true;
    calculatedHash.value = null;

    try {
        const arrayBuffer = await file.arrayBuffer();
        const hashBuffer = await crypto.subtle.digest('SHA-256', arrayBuffer);
        const hashArray = Array.from(new Uint8Array(hashBuffer));
        const hashHex = hashArray
            .map((b) => b.toString(16).padStart(2, '0'))
            .join('');
        calculatedHash.value = hashHex;

        if (props.expectedHash) {
            if (hashHex.toLowerCase() === props.expectedHash.toLowerCase()) {
                notify.success(
                    'Certificado Auténtico',
                    'La huella SHA-256 coincide exactamente con el registro oficial.',
                    3000,
                );
            } else {
                notify.error(
                    'Alerta de Alteración',
                    'La huella no coincide con el certificado oficial registrado.',
                    3500,
                );
            }
        } else {
            notify.success(
                'Huella SHA-256 Obtenida',
                'Cálculo completado exitosamente en su navegador.',
                2000,
            );
        }
    } catch (err) {
        errorMsg.value =
            'No se pudo calcular el hash criptográfico del archivo.';
        notify.error('Error de cálculo', errorMsg.value, 3000);
    } finally {
        isCalculating.value = false;
    }
}

function resetVerification() {
    selectedFileName.value = null;
    calculatedHash.value = null;
    errorMsg.value = null;
}
</script>

<template>
    <Card
        class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-50/50 shadow-sm dark:border-slate-800 dark:bg-slate-900/50"
    >
        <CardHeader class="border-b bg-white pb-3 dark:bg-slate-950">
            <div class="flex items-center gap-2">
                <div
                    class="flex size-8 items-center justify-center rounded-lg bg-rose-900 font-black text-white"
                >
                    <ShieldCheck class="size-4" />
                </div>
                <div>
                    <CardTitle
                        class="text-sm font-black text-slate-950 dark:text-white"
                    >
                        Verificación de Integridad Digital SHA-256 (SIGC-8)
                    </CardTitle>
                    <CardDescription
                        class="text-xs text-slate-600 dark:text-slate-400"
                    >
                        Comprueba que tu diploma PDF no fue alterado ni
                        modificado tras su emisión oficial.
                    </CardDescription>
                </div>
            </div>
        </CardHeader>

        <CardContent class="space-y-4 p-4 text-xs sm:p-5">
            <!-- Zona de Carga / Arrastre del PDF -->
            <div
                v-if="!selectedFileName"
                class="relative space-y-2 rounded-xl border-2 border-dashed border-rose-300 p-6 text-center transition-colors hover:bg-rose-50/40 dark:border-rose-900"
            >
                <input
                    type="file"
                    accept=".pdf,application/pdf"
                    class="absolute inset-0 cursor-pointer opacity-0"
                    @change="handleFileChange"
                />
                <div
                    class="mx-auto flex size-10 items-center justify-center rounded-full bg-rose-100 text-rose-900 dark:bg-rose-950"
                >
                    <UploadCloud class="size-5" />
                </div>
                <div class="font-bold text-slate-800 dark:text-slate-200">
                    Arrastra aquí tu certificado PDF o haz clic para
                    seleccionarlo
                </div>
                <p class="text-[11px] text-slate-500">
                    El cálculo se realiza 100% en tu navegador mediante Web
                    Crypto API.
                </p>
            </div>

            <!-- Archivo Seleccionado y Resultados -->
            <div v-else class="space-y-3">
                <div
                    class="flex items-center justify-between rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-950"
                >
                    <div class="flex items-center gap-2">
                        <FileCheck class="size-5 shrink-0 text-rose-900" />
                        <div>
                            <div
                                class="max-w-xs truncate font-bold text-slate-900 dark:text-white"
                            >
                                {{ selectedFileName }}
                            </div>
                            <div class="text-[10px] text-slate-500">
                                Archivo PDF analizado
                            </div>
                        </div>
                    </div>
                    <Button
                        variant="ghost"
                        size="sm"
                        class="h-7 w-7 p-0"
                        @click="resetVerification"
                    >
                        <X class="size-3.5" />
                    </Button>
                </div>

                <div
                    v-if="isCalculating"
                    class="flex items-center justify-center gap-2 py-4 font-bold text-slate-600"
                >
                    <Loader2 class="size-4 animate-spin text-rose-800" />
                    <span>Calculando huella SHA-256 en tiempo real...</span>
                </div>

                <div v-else-if="calculatedHash" class="space-y-3">
                    <div
                        class="space-y-2 rounded-xl border bg-white p-3 font-mono text-[11px] dark:bg-slate-950"
                    >
                        <div>
                            <span class="font-sans font-bold text-slate-500"
                                >Hash SHA-256 Calculado en Navegador:</span
                            >
                            <div
                                class="mt-0.5 rounded bg-slate-100 p-1.5 font-black break-all text-rose-950 select-all dark:bg-slate-800 dark:text-rose-200"
                            >
                                {{ calculatedHash }}
                            </div>
                        </div>

                        <div v-if="expectedHash">
                            <span class="font-sans font-bold text-slate-500"
                                >Hash Oficial Registrado en Base de Datos:</span
                            >
                            <div
                                class="mt-0.5 rounded bg-slate-100 p-1.5 font-black break-all text-emerald-900 select-all dark:bg-slate-800 dark:text-emerald-200"
                            >
                                {{ expectedHash }}
                            </div>
                        </div>
                    </div>

                    <!-- Estado de Coincidencia -->
                    <div
                        v-if="
                            expectedHash &&
                            calculatedHash.toLowerCase() ===
                                expectedHash.toLowerCase()
                        "
                        class="flex items-center gap-2 rounded-xl border border-emerald-300 bg-emerald-50 p-3 font-bold text-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200"
                    >
                        <CheckCircle2
                            class="size-5 shrink-0 text-emerald-700"
                        />
                        <div>
                            <div class="font-black">
                                ✓ Certificado 100% Auténtico e Inalterado
                            </div>
                            <div class="text-[11px] font-normal">
                                La huella calculada coincide exactamente con el
                                registro original emitido por SIGC-CUSCO.
                            </div>
                        </div>
                    </div>

                    <div
                        v-else-if="
                            expectedHash &&
                            calculatedHash.toLowerCase() !==
                                expectedHash.toLowerCase()
                        "
                        class="flex items-center gap-2 rounded-xl border border-amber-300 bg-amber-50 p-3 font-bold text-amber-900 dark:bg-amber-950/40 dark:text-amber-200"
                    >
                        <AlertTriangle class="size-5 shrink-0 text-amber-700" />
                        <div>
                            <div class="font-black">
                                ⚠ Advertencia de Modificación
                            </div>
                            <div class="text-[11px] font-normal">
                                La huella del PDF no coincide con el certificado
                                oficial registrado. El archivo puede haber sido
                                editado.
                            </div>
                        </div>
                    </div>

                    <div
                        v-else
                        class="flex items-center gap-2 rounded-xl border border-sky-300 bg-sky-50 p-3 font-bold text-sky-900 dark:bg-sky-950/40 dark:text-sky-200"
                    >
                        <CheckCircle2 class="size-5 shrink-0 text-sky-700" />
                        <div>
                            <div class="font-black">
                                Huella SHA-256 Obtenida con Éxito
                            </div>
                            <div class="text-[11px] font-normal">
                                Puedes comparar este hash con el código impreso
                                al pie de tu diploma oficial.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
