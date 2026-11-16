<script setup lang="ts">
/**
 * Padrón en vivo de una sesión (SC-33) con control de permanencia (SC-32):
 * presentes, en sala, parciales, sin salida, ausentes, hora y origen de cada marca,
 * lista «No inscritos», cierre de sesión y justificación con motivo.
 */
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { notify } from '@/lib/notify';
import { firstErrorMessage, getJson, postJson } from '@/lib/http';
import { THEME_BUTTONS } from '@/lib/theme';
import {
    formatLimaTime,
    ORIGIN_LABELS,
    ROSTER_STATE_CLASSES,
    ROSTER_STATE_LABELS,
    type RosterParticipant,
    type RosterState,
    type SessionRoster,
} from '@/types/attendance';
import {
    DoorClosed,
    Loader2,
    RefreshCw,
    Search,
    ShieldAlert,
    UserX,
    Users,
} from '@lucide/vue';

const props = withDefaults(
    defineProps<{
        courseId: number;
        session: number;
        initialRoster?: SessionRoster | null;
        pollMs?: number;
        canManage?: boolean;
    }>(),
    {
        initialRoster: null,
        pollMs: 5000,
        canManage: true,
    },
);

const emit = defineEmits<{
    (e: 'updated', roster: SessionRoster): void;
}>();

const roster = ref<SessionRoster | null>(props.initialRoster);
const isLoading = ref(false);
const loadError = ref<string | null>(null);
const lastUpdated = ref<Date | null>(props.initialRoster ? new Date() : null);
const search = ref('');

type FilterKey =
    | 'todos'
    | 'presentes'
    | 'en_sala'
    | 'parcial'
    | 'sin_salida'
    | 'ausentes';
const filter = ref<FilterKey>('todos');

const FILTER_STATES: Record<Exclude<FilterKey, 'todos'>, RosterState[]> = {
    presentes: ['presente', 'tardanza'],
    en_sala: ['en_sala'],
    parcial: ['parcial'],
    sin_salida: ['sin_salida'],
    ausentes: ['ausente', 'falta'],
};

const filters = computed(() => {
    const s = roster.value?.summary;

    return [
        { key: 'todos' as const, label: 'Todos', count: s?.total ?? 0 },
        {
            key: 'presentes' as const,
            label: 'Presentes',
            count: s?.presentes ?? 0,
        },
        { key: 'en_sala' as const, label: 'En sala', count: s?.en_sala ?? 0 },
        { key: 'parcial' as const, label: 'Parcial', count: s?.parciales ?? 0 },
        {
            key: 'sin_salida' as const,
            label: 'Sin salida',
            count: s?.sin_salida ?? 0,
        },
        {
            key: 'ausentes' as const,
            label: 'Ausentes',
            count: s?.ausentes ?? 0,
        },
    ];
});

const visibleParticipants = computed<RosterParticipant[]>(() => {
    const list = roster.value?.participants ?? [];
    const term = search.value.trim().toLowerCase();

    return list.filter((p) => {
        const matchesFilter =
            filter.value === 'todos' ||
            FILTER_STATES[filter.value].includes(p.state);
        const matchesSearch =
            !term ||
            p.dni.includes(term) ||
            p.full_name.toLowerCase().includes(term);

        return matchesFilter && matchesSearch;
    });
});

const rosterUrl = computed(
    () => `/courses/${props.courseId}/sessions/${props.session}/roster`,
);

// Each request gets a sequence number so a slow response for a previous session never overwrites the current one.
let requestSeq = 0;

async function refresh(force = false): Promise<void> {
    if (isLoading.value && !force) return;

    const seq = ++requestSeq;
    isLoading.value = true;
    try {
        const { ok, data } = await getJson<SessionRoster>(rosterUrl.value);
        if (seq !== requestSeq) return;
        if (!ok) {
            throw new Error(
                firstErrorMessage(
                    data,
                    'No se pudo cargar el padrón de la sesión.',
                ),
            );
        }
        roster.value = data;
        loadError.value = null;
        lastUpdated.value = new Date();
        emit('updated', data);
    } catch (e) {
        if (seq !== requestSeq) return;
        loadError.value =
            e instanceof Error ? e.message : 'No se pudo cargar el padrón.';
    } finally {
        if (seq === requestSeq) isLoading.value = false;
    }
}

let pollTimer: ReturnType<typeof setInterval> | null = null;

function startPolling(): void {
    stopPolling();
    if (props.pollMs > 0) {
        pollTimer = setInterval(() => {
            if (typeof document !== 'undefined' && document.hidden) return;
            void refresh();
        }, props.pollMs);
    }
}

function stopPolling(): void {
    if (pollTimer) clearInterval(pollTimer);
    pollTimer = null;
}

watch(
    () => props.session,
    () => {
        roster.value = null;
        void refresh(true);
    },
);

onMounted(() => {
    if (!roster.value) void refresh();
    startPolling();
});

onUnmounted(stopPolling);

// Cierre de la sesión: quienes solo tienen ingreso quedan «Sin salida».
const isCloseDialogOpen = ref(false);
const isClosing = ref(false);

async function closeSession(): Promise<void> {
    isClosing.value = true;
    try {
        const { ok, data } = await postJson<{ message: string }>(
            `/courses/${props.courseId}/sessions/${props.session}/close`,
        );
        if (!ok) {
            notify.error(
                'No se pudo cerrar la sesión',
                firstErrorMessage(data, 'Intente nuevamente.'),
                3500,
            );
            return;
        }
        notify.success('Sesión cerrada', data.message, 3000);
        isCloseDialogOpen.value = false;
        await refresh();
    } finally {
        isClosing.value = false;
    }
}

// Justificación con motivo (bitácora con usuario y hora).
const justifyTarget = ref<RosterParticipant | null>(null);
const justifyReason = ref('');
const justifyError = ref<string | null>(null);
const isJustifying = ref(false);

function openJustify(participant: RosterParticipant): void {
    justifyTarget.value = participant;
    justifyReason.value = '';
    justifyError.value = null;
}

async function submitJustify(): Promise<void> {
    if (!justifyTarget.value) return;

    isJustifying.value = true;
    justifyError.value = null;
    try {
        const { ok, data } = await postJson<{ message: string }>(
            `/courses/${props.courseId}/sessions/${props.session}/justify/${justifyTarget.value.id}`,
            { reason: justifyReason.value },
        );
        if (!ok) {
            justifyError.value = firstErrorMessage(
                data,
                'No se pudo registrar la justificación.',
            );
            return;
        }
        notify.success('Justificación registrada', data.message, 2500);
        justifyTarget.value = null;
        await refresh();
    } finally {
        isJustifying.value = false;
    }
}

function originLabel(origin: string | null): string {
    return origin ? (ORIGIN_LABELS[origin] ?? origin) : '';
}

defineExpose({ refresh });
</script>

<template>
    <div
        class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xs dark:border-slate-800 dark:bg-slate-950"
    >
        <div
            class="flex flex-col gap-3 border-b border-slate-100 bg-slate-50/80 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800 dark:bg-slate-900/80"
        >
            <div>
                <h3
                    class="flex items-center gap-2 text-sm font-black text-slate-900 dark:text-white"
                >
                    <Users class="size-4 text-rose-900" />
                    Lista en vivo · Sesión {{ session }}
                </h3>
                <p class="mt-0.5 text-[11px] text-slate-500">
                    Se actualiza cada {{ Math.round(pollMs / 1000) }} s.
                    <template v-if="roster">
                        Duración programada:
                        {{ roster.duration_minutes }} min · permanencia mínima
                        {{ roster.required_percent }} %.
                    </template>
                    <template v-if="lastUpdated">
                        Última actualización:
                        {{ formatLimaTime(lastUpdated.toISOString()) }}.
                    </template>
                </p>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                <Button
                    size="sm"
                    variant="outline"
                    class="h-8 text-xs font-bold"
                    :disabled="isLoading"
                    @click="refresh(true)"
                >
                    <RefreshCw
                        :class="[
                            'mr-1.5 size-3.5',
                            isLoading && 'animate-spin',
                        ]"
                    />
                    Actualizar
                </Button>
                <Button
                    v-if="canManage"
                    size="sm"
                    :class="[THEME_BUTTONS.primary, 'h-8 text-xs font-black']"
                    :disabled="!roster || roster.is_acta_closed"
                    @click="isCloseDialogOpen = true"
                >
                    <DoorClosed class="mr-1.5 size-3.5" />
                    Cerrar sesión
                </Button>
            </div>
        </div>

        <div class="space-y-3 p-4">
            <div
                v-if="loadError"
                class="rounded-lg border border-red-200 bg-red-50 p-2.5 text-xs font-bold text-red-800"
            >
                {{ loadError }}
            </div>

            <div class="flex flex-wrap gap-1.5">
                <button
                    v-for="f in filters"
                    :key="f.key"
                    type="button"
                    :class="[
                        'rounded-full border px-2.5 py-1 text-[11px] font-black transition-colors',
                        filter === f.key
                            ? 'border-rose-900 bg-rose-900 text-white'
                            : 'border-slate-200 bg-white text-slate-700 hover:border-rose-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300',
                    ]"
                    @click="filter = f.key"
                >
                    {{ f.label }} · {{ f.count }}
                </button>
            </div>

            <div class="relative w-full sm:w-72">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400"
                />
                <Input
                    v-model="search"
                    type="text"
                    placeholder="Buscar por DNI o nombre..."
                    class="h-8 pl-9 text-xs"
                />
            </div>

            <div
                v-if="!roster && isLoading"
                class="flex items-center justify-center p-6"
            >
                <Loader2 class="size-6 animate-spin text-rose-900" />
            </div>

            <div v-else class="w-full overflow-x-auto">
                <table class="w-full border-collapse text-left text-xs">
                    <thead>
                        <tr
                            class="border-b bg-slate-100/90 text-[10px] font-black tracking-wider text-slate-700 uppercase dark:bg-slate-900 dark:text-slate-300"
                        >
                            <th class="px-2.5 py-2">DNI</th>
                            <th class="px-2.5 py-2">Participante</th>
                            <th class="px-2.5 py-2">Estado</th>
                            <th class="px-2.5 py-2">Hora de marcación</th>
                            <th class="px-2.5 py-2">Ingreso</th>
                            <th class="px-2.5 py-2">Salida</th>
                            <th class="px-2.5 py-2 text-right">Min.</th>
                            <th v-if="canManage" class="px-2.5 py-2"></th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-slate-100 dark:divide-slate-800"
                    >
                        <tr v-if="visibleParticipants.length === 0">
                            <td
                                :colspan="canManage ? 8 : 7"
                                class="px-2.5 py-4 text-center text-slate-500"
                            >
                                No hay participantes en esta vista.
                            </td>
                        </tr>
                        <tr
                            v-for="p in visibleParticipants"
                            :key="p.id"
                            class="hover:bg-rose-50/40 dark:hover:bg-rose-950/20"
                        >
                            <td class="px-2.5 py-2 font-mono font-bold">
                                {{ p.dni }}
                            </td>
                            <td
                                class="px-2.5 py-2 font-bold text-slate-900 dark:text-white"
                            >
                                {{ p.full_name }}
                                <span
                                    v-if="p.justification"
                                    class="mt-0.5 block text-[10px] font-medium text-slate-500"
                                    :title="p.justification"
                                >
                                    Justificada:
                                    {{ p.justification }}
                                </span>
                            </td>
                            <td class="px-2.5 py-2">
                                <span
                                    :class="[
                                        'inline-flex rounded-full border px-2 py-0.5 text-[10px] font-black whitespace-nowrap',
                                        ROSTER_STATE_CLASSES[p.state],
                                    ]"
                                >
                                    {{ ROSTER_STATE_LABELS[p.state] }}
                                </span>
                            </td>
                            <td class="px-2.5 py-2 font-mono whitespace-nowrap">
                                {{
                                    formatLimaTime(p.recorded_at ?? p.entry_at)
                                }}
                                <span
                                    v-if="p.method"
                                    class="ml-1 text-[10px] text-slate-500"
                                    >({{ originLabel(p.method) }})</span
                                >
                            </td>
                            <td class="px-2.5 py-2 font-mono whitespace-nowrap">
                                {{ formatLimaTime(p.entry_at) }}
                                <span
                                    v-if="p.entry_origin"
                                    class="ml-1 text-[10px] text-slate-500"
                                    >{{ originLabel(p.entry_origin) }}</span
                                >
                            </td>
                            <td class="px-2.5 py-2 font-mono whitespace-nowrap">
                                {{ formatLimaTime(p.exit_at) }}
                                <span
                                    v-if="p.exit_origin"
                                    class="ml-1 text-[10px] text-slate-500"
                                    >{{ originLabel(p.exit_origin) }}</span
                                >
                            </td>
                            <td class="px-2.5 py-2 text-right font-mono">
                                {{ p.minutes_present ?? '—' }}
                            </td>
                            <td
                                v-if="canManage"
                                class="px-2.5 py-2 text-right whitespace-nowrap"
                            >
                                <Button
                                    v-if="p.state === 'sin_salida'"
                                    size="sm"
                                    variant="outline"
                                    class="h-7 border-rose-200 px-2 text-[11px] font-bold text-rose-900"
                                    :disabled="roster?.is_acta_closed"
                                    @click="openJustify(p)"
                                >
                                    Justificar
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-if="roster && roster.unenrolled.length > 0"
                class="rounded-xl border border-amber-200 bg-amber-50/70 p-3 dark:border-amber-900 dark:bg-amber-950/30"
            >
                <h4
                    class="mb-2 flex items-center gap-1.5 text-xs font-black text-amber-900 dark:text-amber-300"
                >
                    <UserX class="size-3.5" />
                    No inscritos ({{ roster.unenrolled.length }}) · para que
                    RR.HH. decida
                </h4>
                <ul
                    class="space-y-1 text-[11px] text-amber-950 dark:text-amber-200"
                >
                    <li
                        v-for="scan in roster.unenrolled"
                        :key="scan.id"
                        class="flex flex-wrap items-center gap-x-2"
                    >
                        <span class="font-mono font-black">{{ scan.dni }}</span>
                        <span>· {{ scan.type }}</span>
                        <span>· {{ formatLimaTime(scan.scanned_at) }}</span>
                        <span class="text-amber-800 dark:text-amber-400"
                            >· {{ scan.reason }}</span
                        >
                    </li>
                </ul>
            </div>
        </div>

        <!-- Confirmar cierre de sesión -->
        <Dialog
            :open="isCloseDialogOpen"
            @update:open="isCloseDialogOpen = $event"
        >
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <DoorClosed class="size-4 text-rose-900" />
                        Cerrar la sesión {{ session }}
                    </DialogTitle>
                    <DialogDescription>
                        Quien solo tenga marca de ingreso quedará «Sin salida» y
                        no sumará horas, salvo que luego se justifique con
                        motivo.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2">
                    <Button
                        variant="outline"
                        :disabled="isClosing"
                        @click="isCloseDialogOpen = false"
                    >
                        Cancelar
                    </Button>
                    <Button
                        :class="THEME_BUTTONS.primary"
                        :disabled="isClosing"
                        @click="closeSession"
                    >
                        <Loader2
                            v-if="isClosing"
                            class="mr-1.5 size-4 animate-spin"
                        />
                        Cerrar sesión
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Justificar «Sin salida» -->
        <Dialog
            :open="justifyTarget !== null"
            @update:open="(open: boolean) => !open && (justifyTarget = null)"
        >
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <ShieldAlert class="size-4 text-rose-900" />
                        Justificar sesión sin salida
                    </DialogTitle>
                    <DialogDescription>
                        {{ justifyTarget?.full_name }} · DNI
                        {{ justifyTarget?.dni }}. El motivo queda en la bitácora
                        con su usuario y la hora.
                    </DialogDescription>
                </DialogHeader>
                <form class="space-y-2" @submit.prevent="submitJustify">
                    <textarea
                        v-model="justifyReason"
                        rows="3"
                        maxlength="500"
                        required
                        placeholder="Ej.: salió por comisión de servicio autorizada por su jefe inmediato."
                        class="w-full rounded-xl border border-slate-300 bg-white p-2.5 text-xs dark:border-slate-700 dark:bg-slate-900"
                    ></textarea>
                    <p
                        v-if="justifyError"
                        class="text-xs font-bold text-red-700"
                    >
                        {{ justifyError }}
                    </p>
                    <DialogFooter class="gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="isJustifying"
                            @click="justifyTarget = null"
                        >
                            Cancelar
                        </Button>
                        <Button
                            type="submit"
                            :class="THEME_BUTTONS.primary"
                            :disabled="
                                isJustifying || justifyReason.trim().length < 5
                            "
                        >
                            <Loader2
                                v-if="isJustifying"
                                class="mr-1.5 size-4 animate-spin"
                            />
                            Guardar justificación
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
