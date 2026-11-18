/**
 * Decide qué hacer con una lectura del kiosco cuando no hay conexión
 * (SC-49 / HU-11.2): validar el DNI como lo hace el servidor, no repetir una
 * marcación que ya está en la cola y, si todo está bien, guardarla.
 */
import type { MarkOrigin, MarkType } from '@/types/attendance';
import {
    enqueueMark,
    extractDni,
    findPendingMark,
    type QueuedMark,
} from './markQueue';

export interface OfflineMarkInput {
    courseId: number;
    session: number;
    identifier: string;
    type: MarkType;
    origin: MarkOrigin;
}

export type OfflineMarkOutcome =
    | { kind: 'saved'; mark: QueuedMark }
    | { kind: 'already_pending'; mark: QueuedMark }
    | { kind: 'invalid_dni' };

export async function queueOfflineMark(
    input: OfflineMarkInput,
): Promise<OfflineMarkOutcome> {
    const dni = extractDni(input.identifier);

    if (dni === null) {
        return { kind: 'invalid_dni' };
    }

    const existing = await findPendingMark(
        input.courseId,
        input.session,
        dni,
        input.type,
    );

    if (existing) {
        return { kind: 'already_pending', mark: existing };
    }

    const mark = await enqueueMark({
        course_id: input.courseId,
        session: input.session,
        identifier: input.identifier,
        type: input.type,
        origin: input.origin,
    });

    return { kind: 'saved', mark };
}
