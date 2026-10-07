<script setup lang="ts">
import { ref } from 'vue';
import { ShieldCheck, UploadCloud, FileCheck, AlertTriangle, CheckCircle2, Loader2, X } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

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
        const hashHex = hashArray.map((b) => b.toString(16).padStart(2, '0')).join('');
        calculatedHash.value = hashHex;
    } catch (err) {
        errorMsg.value = 'No se pudo calcular el hash criptográfico del archivo.';
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
    <Card class="border border-slate-200 dark:border-slate-800 shadow-sm rounded-2xl overflow-hidden bg-slate-50/50 dark:bg-slate-900/50">
        <CardHeader class="pb-3 border-b bg-white dark:bg-slate-950">
            <div class="flex items-center gap-2">
                <div class="size-8 rounded-lg bg-rose-900 text-white flex items-center justify-center font-black">
                    <ShieldCheck class="size-4" />
                </div>
                <div>
                    <CardTitle class="text-sm font-black text-slate-950 dark:text-white">
                        Verificación de Integridad Digital SHA-256 (SIGC-8)
                    </CardTitle>
                    <CardDescription class="text-xs text-slate-600 dark:text-slate-400">
                        Comprueba que tu diploma PDF no fue alterado ni modificado tras su emisión oficial.
                    </CardDescription>
                </div>
            </div>
        </CardHeader>

        <CardContent class="p-4 sm:p-5 space-y-4 text-xs">
            <!-- Zona de Carga / Arrastre del PDF -->
            <div
                v-if="!selectedFileName"
                class="border-2 border-dashed border-rose-300 dark:border-rose-900 rounded-xl p-6 text-center space-y-2 hover:bg-rose-50/40 transition-colors relative"
            >
                <input
                    type="file"
                    accept=".pdf,application/pdf"
                    class="absolute inset-0 opacity-0 cursor-pointer"
                    @change="handleFileChange"
                />
                <div class="size-10 rounded-full bg-rose-100 dark:bg-rose-950 text-rose-900 mx-auto flex items-center justify-center">
                    <UploadCloud class="size-5" />
                </div>
                <div class="font-bold text-slate-800 dark:text-slate-200">
                    Arrastra aquí tu certificado PDF o haz clic para seleccionarlo
                </div>
                <p class="text-[11px] text-slate-500">
                    El cálculo se realiza 100% en tu navegador mediante Web Crypto API.
                </p>
            </div>

            <!-- Archivo Seleccionado y Resultados -->
            <div v-else class="space-y-3">
                <div class="flex items-center justify-between p-3 bg-white dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800">
                    <div class="flex items-center gap-2">
                        <FileCheck class="size-5 text-rose-900 shrink-0" />
                        <div>
                            <div class="font-bold text-slate-900 dark:text-white truncate max-w-xs">{{ selectedFileName }}</div>
                            <div class="text-[10px] text-slate-500">Archivo PDF analizado</div>
                        </div>
                    </div>
                    <Button variant="ghost" size="sm" class="h-7 w-7 p-0" @click="resetVerification">
                        <X class="size-3.5" />
                    </Button>
                </div>

                <div v-if="isCalculating" class="flex items-center justify-center gap-2 py-4 text-slate-600 font-bold">
                    <Loader2 class="size-4 animate-spin text-rose-800" />
                    <span>Calculando huella SHA-256 en tiempo real...</span>
                </div>

                <div v-else-if="calculatedHash" class="space-y-3">
                    <div class="p-3 bg-white dark:bg-slate-950 rounded-xl border space-y-2 font-mono text-[11px]">
                        <div>
                            <span class="text-slate-500 font-sans font-bold">Hash SHA-256 Calculado en Navegador:</span>
                            <div class="p-1.5 bg-slate-100 dark:bg-slate-800 rounded font-black text-rose-950 dark:text-rose-200 break-all select-all mt-0.5">
                                {{ calculatedHash }}
                            </div>
                        </div>

                        <div v-if="expectedHash">
                            <span class="text-slate-500 font-sans font-bold">Hash Oficial Registrado en Base de Datos:</span>
                            <div class="p-1.5 bg-slate-100 dark:bg-slate-800 rounded font-black text-emerald-900 dark:text-emerald-200 break-all select-all mt-0.5">
                                {{ expectedHash }}
                            </div>
                        </div>
                    </div>

                    <!-- Estado de Coincidencia -->
                    <div
                        v-if="expectedHash && calculatedHash.toLowerCase() === expectedHash.toLowerCase()"
                        class="p-3 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-300 text-emerald-900 dark:text-emerald-200 font-bold flex items-center gap-2"
                    >
                        <CheckCircle2 class="size-5 text-emerald-700 shrink-0" />
                        <div>
                            <div class="font-black">✓ Certificado 100% Auténtico e Inalterado</div>
                            <div class="text-[11px] font-normal">La huella calculada coincide exactamente con el registro original emitido por SIGC-CUSCO.</div>
                        </div>
                    </div>

                    <div
                        v-else-if="expectedHash && calculatedHash.toLowerCase() !== expectedHash.toLowerCase()"
                        class="p-3 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-300 text-amber-900 dark:text-amber-200 font-bold flex items-center gap-2"
                    >
                        <AlertTriangle class="size-5 text-amber-700 shrink-0" />
                        <div>
                            <div class="font-black">⚠ Advertencia de Modificación</div>
                            <div class="text-[11px] font-normal">La huella del PDF no coincide con el certificado oficial registrado. El archivo puede haber sido editado.</div>
                        </div>
                    </div>

                    <div
                        v-else
                        class="p-3 rounded-xl bg-sky-50 dark:bg-sky-950/40 border border-sky-300 text-sky-900 dark:text-sky-200 font-bold flex items-center gap-2"
                    >
                        <CheckCircle2 class="size-5 text-sky-700 shrink-0" />
                        <div>
                            <div class="font-black">Huella SHA-256 Obtenida con Éxito</div>
                            <div class="text-[11px] font-normal">Puedes comparar este hash con el código impreso al pie de tu diploma oficial.</div>
                        </div>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
