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

_Ver la sección «Resultado del prototipo» (se completa con SC-46)._
