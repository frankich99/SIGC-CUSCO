# Spike SC-37 (HU-11.1): almacenamiento local para el modo offline del kiosco

**Historia origen:** SC-27 (HU-11, 21 SP) · **Responsable:** Aldo Yaranga Achahui
**Timebox:** 2 SP · **Rama:** `feature/SC-37-spike-offline`

## Problema

En el auditorio de la MDSJ la conexión puede caerse. Hoy, si el kiosco
(`resources/js/pages/attendance/Kiosk.vue`) no llega al servidor, la marcación
del DNI se pierde y se pide hacer el registro manual después.

El spike responde dos preguntas antes de implementar SC-38 y SC-39:

1. ¿Con qué guardamos las marcaciones en el navegador mientras no hay red?
2. ¿Hace falta un service worker (PWA) desde el inicio?

## SC-45: comparación de librerías para IndexedDB

`localStorage` se descartó de entrada: es síncrono, solo guarda texto y su
límite (~5 MB) es por origen. IndexedDB es asíncrono, guarda objetos, tiene
índices y transacciones, y está en todos los navegadores que usa la MDSJ.

La API nativa de IndexedDB se basa en eventos y es incómoda, así que
comparamos dos envoltorios:

| Criterio                                 | `idb` 8.0.4                        | `Dexie` 4.4.7                               |
| ---------------------------------------- | ---------------------------------- | ------------------------------------------- |
| Tamaño en el bundle (minificado / gzip)¹ | 3,3 KB / **1,4 KB**                | 100 KB / 32,7 KB                            |
| Estilo de API                            | La de IndexedDB, pero con promesas | Propia, tipo consultas (`where().equals()`) |
| TypeScript                               | Esquema tipado con `DBSchema`      | Tipado con clases `Table`                   |
| Índices y transacciones                  | Sí (los nativos)                   | Sí, con más azúcar                          |
| Licencia                                 | ISC                                | Apache-2.0                                  |
| Última publicación en npm                | octubre 2026                       | octubre 2026                                |

¹ Medido con `esbuild --bundle --minify` importando solo el punto de entrada
de cada librería (17/11/2026).

**Elección: `idb`.** La cola del kiosco es simple (guardar, listar por sesión,
borrar al sincronizar) y no necesita el lenguaje de consultas de Dexie. `idb`
pesa unas 20 veces menos, que importa en una PC de auditorio, y su API es la
de IndexedDB: lo que aprendamos sirve aunque cambiemos de librería.

### Service worker (PWA)

`vite-plugin-pwa` 2.0.0 permitiría abrir el kiosco sin red. **No es necesario
para el primer incremento:** el kiosco se abre al inicio de la sesión, cuando
sí hay conexión, y lo que se pierde es la red _durante_ la sesión. La cola en
IndexedDB cubre ese caso sin service worker. Se deja como mejora posible
(abrir el kiosco ya sin red) y se registra como riesgo en el ADR (SC-47).

## SC-46: prototipo

### Qué se construyó

- `resources/js/lib/offline/markQueue.ts`: cola en IndexedDB (base
  `sigc-kiosco-offline`, almacén `marks`, clave `client_uuid`, índice
  `by_session` por `[course_id, session]`). Funciones: `enqueueMark`,
  `listPendingMarks`, `countPendingMarks`, `removeMark`.
- `Kiosk.vue`: si el envío de la marcación falla por red, la marcación se
  guarda en la cola, el kiosco muestra un aviso celeste («quedó guardada en
  este dispositivo») y debajo del campo de lectura aparece cuántas
  marcaciones de la sesión están pendientes. El contador se lee de IndexedDB
  al abrir la página, así que sobrevive a una recarga.
- `markQueue.test.ts`: 8 pruebas con Vitest y `fake-indexeddb` (`npm test`).

### Hallazgos

1. **`crypto.randomUUID()` no sirve siempre.** Solo existe en contextos
   seguros (HTTPS o `localhost`). Si el kiosco se abre por
   `http://192.168.x.x`, no está; por eso `generateClientUuid()` arma el UUID
   v4 con `crypto.getRandomValues()`, que sí está disponible.
2. **La hora que cuenta es la del escaneo**, no la del envío. Se guarda
   `captured_at` con la hora del dispositivo; el servidor (SC-52) deberá
   usarla para calcular la permanencia y validar que no sea absurda (p. ej.
   posterior a la hora del servidor).
3. **El `client_uuid` como clave evita duplicados en el propio dispositivo**:
   guardar dos veces la misma marcación la sobrescribe (lo cubre una prueba).
   Para el servidor hace falta la columna única de SC-51.
4. **Sin conexión el kiosco no puede validar el DNI** (si está matriculado,
   si ya marcó). Esa respuesta llega al sincronizar; por eso SC-56 debe
   avisar al docente de las marcaciones rechazadas.

### Cómo probarlo a mano

1. Abrir el kiosco de una sesión (`/courses/{id}/kiosco/{sesión}`).
2. En DevTools → Network, elegir **Offline**.
3. Pasar un DNI: aparece el aviso celeste y el contador «1 marcación
   guardada…».
4. Volver a **No throttling** y recargar la página: el contador se mantiene
   (DevTools → Application → IndexedDB → `sigc-kiosco-offline` muestra el
   registro). Todavía no se envía solo; eso es SC-39.

La decisión técnica para SC-38 y SC-39 queda en el ADR (SC-47).
