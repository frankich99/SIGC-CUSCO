<script setup lang="ts">
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Spinner } from '@/components/ui/spinner';
import { Search, CheckCircle2, AlertCircle, UserCheck } from '@lucide/vue';
import { THEME_BUTTONS } from '@/lib/theme';

export interface DniPersonData {
    dni: string;
    nombres: string;
    apellido_paterno: string;
    apellido_materno: string;
    nombre_completo: string;
}

const emit = defineEmits<{
    (e: 'selected', person: DniPersonData): void;
}>();

const dniInput = ref('');
const loading = ref(false);
const error = ref<string | null>(null);
const person = ref<DniPersonData | null>(null);

async function consultDni() {
    error.value = null;
    person.value = null;
    const clean = dniInput.value.trim();

    if (!/^\d{8}$/.test(clean)) {
        error.value = 'El DNI debe tener exactamente 8 dígitos numéricos.';
        return;
    }

    loading.value = true;
    try {
        const response = await fetch(`/api/dni/${clean}`, {
            headers: {
                Accept: 'application/json',
            },
        });

        const data = await response.json();

        if (response.ok && data.success && data.data) {
            person.value = data.data;
            emit('selected', data.data);
        } else {
            error.value =
                data.message || 'No se encontró a la persona con este DNI.';
        }
    } catch (e) {
        error.value =
            'Error al comunicarse con el servicio de validación de identidad.';
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <Card
        class="border-rose-200/80 bg-gradient-to-br from-rose-50/40 to-transparent dark:border-rose-900/60 dark:from-rose-950/20"
    >
        <CardHeader class="pb-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <div
                        class="rounded-lg bg-rose-900/10 p-2 text-rose-900 dark:bg-rose-500/10 dark:text-rose-300"
                    >
                        <UserCheck class="size-5" />
                    </div>
                    <div>
                        <CardTitle
                            class="text-base font-bold text-slate-950 dark:text-white"
                            >Consulta Rápida de DNI</CardTitle
                        >
                        <CardDescription
                            class="text-xs text-slate-600 dark:text-slate-400"
                            >Validación oficial vía API RENIEC / Base
                            Pública</CardDescription
                        >
                    </div>
                </div>
                <Badge
                    variant="outline"
                    class="border-rose-300 bg-rose-50 text-[10px] font-bold text-rose-900 dark:border-rose-700 dark:bg-rose-950 dark:text-rose-300"
                >
                    En línea
                </Badge>
            </div>
        </CardHeader>
        <CardContent class="space-y-3">
            <div class="flex gap-2">
                <div class="relative flex-1">
                    <Input
                        v-model="dniInput"
                        placeholder="Ingrese DNI (8 dígitos)..."
                        maxlength="8"
                        class="pr-3 font-mono text-sm font-semibold tracking-wider"
                        @keydown.enter.prevent="consultDni"
                    />
                </div>
                <Button
                    :disabled="loading || dniInput.trim().length !== 8"
                    @click="consultDni"
                    :class="['text-xs font-bold', THEME_BUTTONS.primary]"
                >
                    <Spinner v-if="loading" class="mr-1 size-3.5" />
                    <Search v-else class="mr-1 size-3.5" />
                    Consultar
                </Button>
            </div>

            <!-- Error -->
            <div
                v-if="error"
                class="flex items-center gap-2 rounded-md border border-rose-300 bg-rose-100 p-2.5 text-xs font-bold text-rose-950 dark:border-rose-800 dark:bg-rose-950/60 dark:text-rose-200"
            >
                <AlertCircle class="size-4 shrink-0 text-rose-800" />
                <span>{{ error }}</span>
            </div>

            <!-- Resultado encontrado -->
            <div
                v-if="person"
                class="space-y-1.5 rounded-lg border border-rose-200 bg-white p-3 shadow-xs dark:border-rose-800 dark:bg-neutral-900"
            >
                <div
                    class="flex items-center gap-2 text-xs font-bold text-rose-900 dark:text-rose-300"
                >
                    <CheckCircle2 class="size-4" />
                    <span>Ciudadano validado correctamente</span>
                </div>
                <div class="text-sm font-bold text-slate-950 dark:text-white">
                    {{ person.nombre_completo }}
                </div>
                <div
                    class="grid grid-cols-2 gap-2 pt-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                >
                    <div>
                        <span class="font-bold text-slate-500">DNI:</span>
                        {{ person.dni }}
                    </div>

                    <div>
                        <span class="font-bold text-slate-500">Nombres:</span>
                        {{ person.nombres }}
                    </div>
                    <div>
                        <span class="font-bold text-slate-500">Apellidos:</span>
                        {{ person.apellido_paterno }}
                        {{ person.apellido_materno }}
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
