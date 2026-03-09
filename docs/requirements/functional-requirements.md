# Requisitos Funcionales — Appointment Booking Backend

> **Versión:** 1.1
> **Alcance actual:** Un único proveedor (dueño y administrador).
> **Diseño abierto a:** múltiples proveedores, catálogo de servicios con duraciones y precios distintos, canales de notificación adicionales.

---

## Actores del Sistema

| Actor | Descripción |
|---|---|
| **Proveedor** | Profesional que ofrece el servicio. Administra su horario, configuración y citas. Su cuenta se crea mediante un comando Artisan / seeder — no se registra vía web. |
| **Cliente** | Usuario final que reserva citas. Se registra por su propia cuenta. |
| **Sistema** | Componente interno que valida disponibilidad, gestiona estados y despacha notificaciones. |

---

## RF-AUTH — Autenticación y Autorización

### RF-AUTH-01 · Registro de clientes
- El cliente puede registrarse mediante formulario con: **nombre completo**, **correo electrónico**, **número de teléfono** y **contraseña**.
- El correo electrónico debe ser único en el sistema.
- La contraseña debe cumplir requisitos mínimos de seguridad (mínimo 8 caracteres).
- Tras el registro, el cliente recibe un email de verificación de dirección (opcional en v1, obligatorio en v2).

### RF-AUTH-02 · Inicio y cierre de sesión
- Tanto el proveedor como los clientes pueden iniciar y cerrar sesión mediante credenciales (email + contraseña).
- El sistema devuelve un token de acceso (API token / Bearer token) al autenticarse correctamente.
- Al cerrar sesión, el token queda invalidado.

### RF-AUTH-03 · Control de acceso basado en roles
- Existen dos roles: `provider` y `client`.
- El acceso a cada endpoint está restringido por rol.
- El proveedor tiene acceso a todas las operaciones sobre citas y horario.
- El cliente solo puede operar sobre sus propias citas.
- Un cliente no puede acceder a los endpoints de gestión de horario ni ver citas de otros clientes.

### RF-AUTH-04 · Creación de cuenta del proveedor
- La cuenta del proveedor **no se crea mediante registro web**.
- Se provee un comando Artisan o seeder para crear y gestionar la cuenta del proveedor.

---

## RF-SCHEDULE — Gestión del Horario Laboral

### RF-SCHEDULE-01 · Horario semanal recurrente con múltiples sesiones
- El proveedor puede definir su horario semanal: para cada día de la semana (lunes a domingo) puede indicar si es un día laborable y, en tal caso, una o más **sesiones de trabajo** (cada sesión con hora de inicio y hora de fin).
- Esto permite modelar jornadas partidas: por ejemplo, una sesión de mañana (09:00–13:00) y una sesión de tarde (15:00–19:00), excluyendo el horario de almuerzo.
- Este horario es recurrente: aplica a todas las semanas salvo que exista una excepción específica para una fecha concreta.
- El proveedor puede añadir, editar o eliminar sesiones individuales para un día de la semana sin afectar al resto de los días.
- El proveedor puede desactivar un día completo sin borrar sus sesiones configuradas.

### RF-SCHEDULE-02 · Excepciones de días (overrides)
- El proveedor puede marcar una fecha concreta como **día libre** (override off), aunque según el horario semanal sea laborable; todas sus sesiones quedan anuladas para esa fecha.
- El proveedor puede marcar una fecha concreta como **día laborable extra** (override on), aunque según el horario semanal sea libre (ej. festivo trabajado), definiendo una o más sesiones específicas para esa fecha.
- El proveedor puede añadir una razón opcional al crear un override.
- El proveedor puede eliminar un override, restaurando el comportamiento del horario semanal.

### RF-SCHEDULE-04 · Notificación a clientes afectados por cambio de horario
- Cuando el proveedor modifica o elimina sesiones de su horario semanal (o elimina un día activo), el sistema detecta automáticamente las citas en estado `pending` o `confirmed` que queden fuera del nuevo horario efectivo.
- Dichas citas pasan automáticamente al estado `reschedule_requested`.
- Los clientes afectados reciben una notificación indicando que su cita ha sido invalidada por un cambio de horario y que deben reprogramarla.

### RF-SCHEDULE-03 · Consulta de disponibilidad de fechas
- El sistema expone un endpoint que, dado un rango de fechas, devuelve qué días son laborables, con su horario efectivo (resolviendo overrides sobre el horario semanal).
- Este endpoint es accesible para clientes autenticados.

---

## RF-AVAIL — Validación de Disponibilidad

### RF-AVAIL-01 · Reserva en tiempo libre (sin slots fijos)
- El cliente puede solicitar una cita en **cualquier fecha y hora** dentro del horario laboral del proveedor.
- No existen slots pre-generados ni franjas horarias fijas; el cliente elige libremente el momento de inicio.

### RF-AVAIL-02 · Reglas de validación al reservar
El sistema rechaza una solicitud de cita si no se cumple alguna de las siguientes condiciones:
1. El día solicitado es laborable (según horario semanal + overrides).
2. La hora de inicio de la cita está **dentro** de alguna de las sesiones de trabajo del día.
3. La cita (hora de inicio + duración calculada) finaliza **dentro de la misma sesión** en la que comienza; no puede cruzar el límite entre dos sesiones (ej. no puede comenzar antes del almuerzo y terminar después).
4. El intervalo `[inicio, inicio + duración)` **no se solapa** con ninguna cita activa existente del proveedor.
5. La fecha y hora solicitadas son **futuras** (no se pueden crear citas en el pasado).

### RF-AVAIL-03 · Duración de cita y número de personas
- La duración base de una cita es un parámetro global configurable por el proveedor (en minutos).
- Al reservar, el cliente puede indicar el **número de personas** (`party_size`, mínimo 1). La duración total de la cita se calcula como `duración_base × party_size`.
- El sistema valida disponibilidad utilizando la duración calculada, no la base.
- Se aplica de manera uniforme mientras no exista un catálogo de servicios.

### RF-AVAIL-04 · Consulta de franjas ocupadas
- El sistema expone un endpoint que, dado un día concreto, devuelve:
  - Si el día es laborable.
  - Las sesiones de trabajo del día (cada una con hora de inicio y fin).
  - Las franjas de tiempo ya ocupadas por citas activas dentro de cada sesión.
- Esto permite al cliente calcular visualmente qué momentos están disponibles dentro de cada sesión.

---

## RF-APPT — Gestión de Citas

### RF-APPT-01 · Reservar cita (Cliente)
- El cliente autenticado puede solicitar una cita especificando: **fecha y hora de inicio**, **número de personas** (`party_size`, por defecto 1), y opcionalmente **notas**.
- El sistema calcula la duración total como `duración_base × party_size` y valida disponibilidad (RF-AVAIL-02).
- Al crear correctamente, la cita queda en estado `pending` o `confirmed` (configurable).

### RF-APPT-02 · Consultar mis citas (Cliente)
- El cliente puede listar sus citas, pudiendo filtrar por estado (activas, historial).
- Puede ver el detalle de cada cita: fecha, hora, duración, estado, notas.

### RF-APPT-03 · Cancelar cita (Cliente)
- El cliente puede cancelar una cita propia que esté en estado `pending` o `confirmed`.
- Solo puede cancelar con una antelación mínima configurable (en horas) respecto a la hora de la cita.
- Al cancelar, la cita pasa al estado `cancelled`.

### RF-APPT-04 · Reprogramar cita (Cliente)
- El cliente puede solicitar un nuevo horario para una cita propia en estado `pending`, `confirmed` o **`reschedule_requested`**.
- El sistema valida disponibilidad para el nuevo horario (RF-AVAIL-02).
- Al reprogramar correctamente, la cita original pasa al estado `rescheduled` y se crea una nueva.
- Solo puede reprogramar con una antelación mínima configurable respecto a la cita original (excepto cuando la cita está en estado `reschedule_requested`, donde la restricción de antelación no aplica).

### RF-APPT-05 · Ver todas las citas (Proveedor)
- El proveedor puede listar todas las citas, con filtros por: **fecha o rango de fechas**, **estado**, **cliente**.
- Puede ver el detalle completo de cada cita, incluyendo el número de personas.

### RF-APPT-06 · Confirmar cita (Proveedor)
- El proveedor puede confirmar una cita en estado `pending`.
- La cita pasa al estado `confirmed`.
- Se notifica al cliente la confirmación.

### RF-APPT-07 · Cancelar cualquier cita (Proveedor)
- El proveedor puede cancelar cualquier cita activa, independientemente del cliente.
- La cita pasa al estado `cancelled`.

### RF-APPT-08 · Reprogramar cualquier cita (Proveedor)
- El proveedor puede reprogramar cualquier cita activa a un nuevo horario directamente.
- El sistema valida disponibilidad para el nuevo horario (RF-AVAIL-02), excluyendo la propia cita que se reprograma.
- La cita original pasa al estado `rescheduled` y se crea una nueva.

### RF-APPT-09 · Solicitar reprogramación al cliente (Proveedor)
- El proveedor puede solicitar al cliente que sea **él** quien elija el nuevo horario, sin fijarlo el proveedor directamente.
- La cita pasa al estado `reschedule_requested`. El horario original queda registrado pero la cita ya no ocupa ese slot de disponibilidad para nuevas reservas.
- El cliente recibe una notificación invitándole a reprogramar.
- El cliente puede entonces reprogramar (RF-APPT-04) o cancelar (RF-APPT-03) la cita.

### RF-APPT-10 · Marcar cita como completada (Proveedor)
- El proveedor puede marcar una cita `confirmed` como `completed` una vez realizada.

### RF-APPT-11 · Ciclo de vida de una cita

```
pending ──► confirmed ──► completed
    │            │
    └────────────┴──► cancelled
                 │
                 ├──► rescheduled ──► (nueva cita: pending/confirmed)
                 │
                 └──► reschedule_requested ──► rescheduled (cliente reprograma)
                                            └──► cancelled (cliente cancela)
```

| Estado | Descripción |
|---|---|
| `pending` | Cita creada, pendiente de confirmación por el proveedor |
| `confirmed` | Cita confirmada por el proveedor |
| `cancelled` | Cita cancelada por cliente o proveedor |
| `rescheduled` | Cita reprogramada (registro histórico; se crea una nueva cita vinculada) |
| `reschedule_requested` | El proveedor o el sistema solicita al cliente que reprograme; la cita ya no bloquea disponibilidad |
| `completed` | Cita realizada |

---

## RF-NOTIF — Notificaciones

### RF-NOTIF-01 · Eventos que disparan notificaciones
Las notificaciones se envían al **cliente** y/o al **proveedor** ante los siguientes eventos:

| Evento | Destinatarios |
|---|---|
| Cita creada | Cliente + Proveedor |
| Cita confirmada | Cliente |
| Cita cancelada | Cliente + Proveedor |
| Cita reprogramada (directamente) | Cliente + Proveedor |
| Reprogramación solicitada al cliente | Cliente |
| Recordatorio de cita (antelación configurable) | Cliente |
| Cambio de horario que invalida citas | Clientes afectados |

### RF-NOTIF-02 · Canales de notificación
- **Email**: canal obligatorio en v1.
- **In-app (base de datos)**: notificaciones almacenadas en la BD, accesibles vía API por el usuario autenticado.
- **Canal adicional** (SMS, WhatsApp, push): la arquitectura debe permitir añadir nuevos canales sin cambios estructurales (usando el sistema de Notificaciones de Laravel).

### RF-NOTIF-03 · Consulta de notificaciones in-app (Usuario)
- El usuario autenticado puede listar sus notificaciones no leídas.
- Puede marcar notificaciones como leídas (individualmente o todas).

### RF-NOTIF-04 · Preferencias de notificación (Cliente)
- Cada cliente puede configurar, para cada tipo de evento y canal, si desea o no recibir esa notificación.
- Ejemplo: un cliente puede activar el recordatorio por email pero desactivarlo in-app, o desactivar completamente la notificación de confirmación.
- Las preferencias se gestionan por combinación `(event_type, channel)`.
- Si un cliente no ha configurado una preferencia, se aplica la configuración por defecto del sistema (todos los canales activados para todos los eventos).

### RF-NOTIF-05 · Antelación del recordatorio (Cliente)
- Cada cliente puede configurar el tiempo de antelación con el que quiere recibir el recordatorio de su cita (en horas).
- Este parámetro aplica a todas sus citas, independientemente del canal habilitado para recordatorios.
- El valor por defecto es configurable a nivel del sistema.

---

## RF-PROFILE — Gestión de Perfiles

### RF-PROFILE-01 · Perfil del cliente
- El cliente puede ver y actualizar su perfil: **nombre**, **email**, **teléfono**.
- El cambio de email puede requerir re-verificación (v2).
- El cliente puede cambiar su contraseña.
- El cliente puede gestionar sus **preferencias de notificación** (RF-NOTIF-04) y su **antelación de recordatorio** (RF-NOTIF-05).

### RF-PROFILE-02 · Perfil del proveedor
- El proveedor puede ver y actualizar su perfil profesional: **nombre**, **bio**, **foto de perfil**.
- El proveedor puede configurar la **duración por defecto de las citas** (en minutos).
- El proveedor puede configurar la **antelación mínima para cancelación/reprogramación** (en horas).

---

## Consideraciones de diseño para escalabilidad futura

| Área | Decisión actual | Extensión futura |
|---|---|---|
| Proveedores | Un único proveedor | `provider_id` como FK en horarios y citas |
| Servicios | Citas genéricas con duración global | `service_type_id` nullable en `appointments`; catálogo de servicios con duración y precio propios |
| Canales de notificación | Email + in-app | Nuevos canales via Laravel Notification drivers |
| Duración de cita | `duración_base × party_size` | Duración por tipo de servicio × party_size |
| Roles | `provider` y `client` | Roles adicionales (staff, admin multi-negocio) |
