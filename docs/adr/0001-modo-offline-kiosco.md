# ADR 0001: Modo offline del kiosco de asistencia

- **Estado:** Aceptada
- **Fecha:** 18/11/2026
- **Autor:** Aldo Yaranga Achahui
- **Historias:** SC-27 (HU-11) y sus partes SC-37, SC-38, SC-39 y SC-40
- **Base:** spike SC-37 (`docs/spikes/SC-37-almacenamiento-offline.md`)

## Contexto

El kiosco de ingreso y salida (`attendance/Kiosk.vue`, SC-32) registra cada
lectura del DNI con `POST /courses/{course}/sessions/{session}/marks`. Si la
red del auditorio se cae durante la sesión, la marcación se pierde y el
docente tiene que registrar la asistencia a mano después.

HU-11 pide que las marcaciones se guarden en el dispositivo sin conexión y se
envíen solas al volver la red, **sin duplicados**.

## Decisión

### 1. Almacenamiento local: IndexedDB con `idb`

- Base `sigc-kiosco-offline`, almacén `marks`, clave `client_uuid`, índice
  `by_session` sobre `[course_id, session]`.
- Módulo único de acceso: `resources/js/lib/offline/markQueue.ts`.
- Se descartó `localStorage` (síncrono, solo texto, ~5 MB) y Dexie
  (~32,7 KB gzip frente a ~1,4 KB de `idb`, sin necesidad de su lenguaje de
  consultas). Ver la comparación en el spike.

### 2. Cada marcación lleva un `client_uuid`

- Se genera en el kiosco **al escanear**, con `crypto.randomUUID()` o, si el
  kiosco se abre por HTTP en la red local (contexto no seguro), con
  `crypto.getRandomValues()`.
- Es la clave de idempotencia de punta a punta: en IndexedDB (no se duplica
  en el dispositivo) y en el servidor (columna única, SC-51).

### 3. Cuándo se guarda en la cola (SC-38)

El kiosco guarda la marcación en la cola en vez de enviarla cuando:

- el navegador informa que no hay red (`navigator.onLine === false`), o
- el envío falla por red: error de `fetch` o **sin respuesta en 8 segundos**.

Si el servidor **sí responde** (aunque sea con error 422 o 409), la respuesta
se muestra como siempre y **no** se guarda en la cola: el servidor ya decidió.

Antes de guardar, el kiosco revisa si ya hay en la cola una marcación del
mismo DNI y del mismo tipo (ingreso o salida) en esa sesión. Si la hay, avisa
«ya está guardada» y no la repite, igual que el servidor avisa «duplicada».

### 4. Hora de la marcación

- Se guarda `captured_at`: la hora del dispositivo **al escanear**.
- Al sincronizar, el servidor la usa como hora de la marca.
  `AttendancePermanenceService::registerMark()` ya acepta el parámetro `$at`.
- El servidor la rechaza si está en el futuro (más de 2 minutos de tolerancia
  por desfase de reloj) o si tiene más de 24 horas.

### 5. Sincronización (SC-39)

- Endpoint nuevo: `POST /courses/{course}/sessions/{session}/marks/sync`,
  con un lote de hasta 50 marcaciones.
- Responde **una línea por `client_uuid`** con el resultado del servicio
  (`ingreso_registrado`, `asistida`, `parcial`, `duplicada`, `no_inscrito`,
  etc.).
- Si el `client_uuid` ya existe en `attendance_marks`, se responde
  `duplicada` sin volver a registrar. Esto hace que reenviar el mismo lote
  sea seguro.
- El kiosco borra de la cola toda marcación que tenga respuesta del servidor,
  sea cual sea el resultado. Las rechazadas pasan, en la misma transacción,
  a un segundo almacén `rejected` (base v2) y se muestran al docente hasta que
  las descarte (SC-56).
- Si el lote falla por red, se reintenta con espera creciente (5 s, 10 s,
  20 s… hasta 2 minutos) y también al volver el evento `online`.

### 6. Sin service worker por ahora

El kiosco se abre al comenzar la sesión, cuando hay red; el problema es la
caída **durante** la sesión. La cola cubre ese caso sin service worker.
Abrir el kiosco sin red (PWA con `vite-plugin-pwa`) queda como mejora futura.

## Consecuencias

**A favor**

- Ninguna marcación se pierde por caídas de red durante la sesión.
- Reenviar es seguro gracias al `client_uuid`.
- Se reutiliza la lógica de permanencia existente; no hay reglas duplicadas
  en el navegador.

**En contra / riesgos**

- **Sin red no se puede validar el DNI**: el kiosco no sabe si la persona
  está matriculada hasta sincronizar. Por eso las rechazadas se avisan al
  docente (SC-56).
- **La hora depende del reloj del dispositivo.** Se mitiga con los límites
  del punto 4.
- **Los datos quedan en ese navegador.** Si se borran los datos del sitio o
  se cambia de PC antes de sincronizar, se pierden. El contador de pendientes
  (SC-55) hace visible que hay algo sin enviar.
- **Si se cierra el acta** antes de sincronizar, el servidor rechazará el
  lote. El docente debe sincronizar antes de cerrar el acta.

## Alternativas descartadas

| Alternativa                                          | Motivo                                                           |
| ---------------------------------------------------- | ---------------------------------------------------------------- |
| `localStorage`                                       | Síncrono, solo texto, límite bajo                                |
| Dexie                                                | ~23 veces más pesado, no se necesitan sus consultas              |
| Validar DNI en el navegador con el padrón descargado | Expone datos personales del padrón en el dispositivo (Ley 29733) |
| Background Sync API                                  | Solo Chrome/Edge y exige service worker                          |
