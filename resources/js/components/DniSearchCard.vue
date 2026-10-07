<script setup lang="ts">
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Spinner } from '@/components/ui/spinner';
import { Search, CheckCircle2, AlertCircle, UserCheck } from '@lucide/vue';

export interface DniPersonData {
    dni: string;
    nombres: string;
    apellido_paterno: string;
    apellido_materno: string;
    nombre_completo: string;
    genero?: string;
    fecha_nacimiento?: string;
    codigo_verificacion?: string;
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
            error.value = data.message || 'No se encontró a la persona con este DNI.';
        }
    } catch (e) {
        error.value = 'Error al comunicarse con el servicio de validación de identidad.';
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <Card class="border-emerald-200/60 dark:border-emerald-900/40 bg-gradient-to-br from-emerald-50/40 to-transparent dark:from-emerald-950/10">
        <CardHeader class="pb-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <div class="rounded-lg bg-emerald-600/10 p-2 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400">
                        <UserCheck class="size-5" />
                    </div>
                    <div>
                        <CardTitle class="text-base font-semibold">Consulta Rápida de DNI</CardTitle>
                        <CardDescription class="text-xs">Validación oficial vía API RENIEC / Base Pública</CardDescription>
                    </div>
                </div>
                <Badge variant="outline" class="border-emerald-300 text-emerald-700 dark:border-emerald-700 dark:text-emerald-300 text-[10px]">
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
                        class="pr-3 text-sm font-mono tracking-wider"
                        @keydown.enter.prevent="consultDni"
                    />
                </div>
                <Button :disabled="loading || dniInput.trim().length !== 8" @click="consultDni" class="bg-emerald-600 hover:bg-emerald-700 text-white">
                    <Spinner v-if="loading" class="mr-1 size-4" />
                    <Search v-else class="mr-1 size-4" />
                    Consultar
                </Button>
            </div>

            <!-- Error -->
            <div v-if="error" class="flex items-center gap-2 rounded-md bg-red-50 p-2.5 text-xs text-red-700 dark:bg-red-950/40 dark:text-red-300 border border-red-200 dark:border-red-800">
                <AlertCircle class="size-4 shrink-0" />
                <span>{{ error }}</span>
            </div>

            <!-- Resultado encontrado -->
            <div v-if="person" class="rounded-lg border border-emerald-200 bg-white p-3 shadow-xs dark:border-emerald-800 dark:bg-neutral-900 space-y-1.5">
                <div class="flex items-center gap-2 text-emerald-700 dark:text-emerald-400 text-xs font-medium">
                    <CheckCircle2 class="size-4" />
                    <span>Ciudadano validado correctamente</span>
                </div>
                <div class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">
                    {{ person.nombre_completo }}
                </div>
                <div class="grid grid-cols-2 gap-2 text-xs text-neutral-600 dark:text-neutral-400 pt-1">
                    <div>
                        <span class="font-medium text-neutral-500">DNI:</span> {{ person.dni }}
                    </div>
                    <div>
                        <span class="font-medium text-neutral-500">Dígito Verif.:</span> {{ person.codigo_verificacion || '-' }}
                    </div>
                    <div>
                        <span class="font-medium text-neutral-500">Nombres:</span> {{ person.nombres }}
                    </div>
                    <div>
                        <span class="font-medium text-neutral-500">Apellidos:</span> {{ person.apellido_paterno }} {{ person.apellido_materno }}
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
