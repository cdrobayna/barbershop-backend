# Arquitectura y Convenciones — Appointment Booking Backend

> Este documento es la referencia canónica de arquitectura para el proyecto.
> Las instrucciones derivadas de este documento están escritas en `AGENTS.md` y `CLAUDE.md` para su consumo por agentes de IA.

---

## Filosofía

Estructura estándar de Laravel 12 con **una única adición**: la capa `Services`.
Sin repositorios, sin DTOs, sin hexagonal. Simple y predecible.

---

## Requerimientos de base de datos

El proyecto usa **PostgreSQL** como única base de datos soportada. Algunas queries dependen de funciones específicas de PostgreSQL:

- `EXTRACT(DOW FROM scheduled_at)` — en `Appointment::scopeForDayOfWeek()` para filtrar citas por día de la semana.

No se soporta MySQL ni SQLite en v1.

---

## Estructura de directorios

```
app/
├── Console/
│   └── Commands/                     # Comandos Artisan del proyecto
├── Enums/                            # Backed enums tipados (AppointmentStatus, etc.)
├── Exceptions/                       # Excepciones de dominio del proyecto
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       └── V1/                   # Todos los controladores de la API v1
│   │           ├── Auth/
│   │           │   └── AuthController.php
│   │           ├── AppointmentController.php
│   │           ├── AvailabilityController.php
│   │           ├── ScheduleController.php
│   │           ├── NotificationController.php
│   │           └── ProfileController.php
│   ├── Requests/
│   │   └── Api/
│   │       └── V1/                   # Form Requests por módulo
│   │           ├── Auth/
│   │           ├── Appointment/
│   │           ├── Schedule/
│   │           └── Profile/
│   └── Resources/
│       └── Api/
│           └── V1/                   # Eloquent API Resources
├── Jobs/                             # Jobs en cola (ShouldQueue)
├── Models/                           # Modelos Eloquent
├── Notifications/                    # Clases de notificación Laravel
├── Policies/                         # Políticas de autorización
├── Providers/
└── Services/                         # ← ÚNICA ADICIÓN: lógica de negocio
    ├── AvailabilityService.php
    ├── AppointmentService.php
    └── ScheduleService.php
```

---

## Capas y responsabilidades

### Routes — `routes/api.php`
- Solo enrutamiento. Cero lógica.
- Prefijo: `v1` → todas las rutas bajo `/api/v1/`.
- Nombradas: `api.v1.<recurso>.<acción>` (ej. `api.v1.appointments.store`).
- Agrupadas con middleware `auth:sanctum` y validación de rol.

### Controllers — `app/Http/Controllers/Api/V1/`
- Capa HTTP pura: **recibir → validar (FormRequest) → delegar al Service → devolver Resource**.
- Máximo ~5 líneas de lógica por método.
- Inyectar Services por constructor (constructor property promotion).
- Nunca acceder a modelos directamente; siempre a través del Service.

```php
public function store(StoreAppointmentRequest $request): AppointmentResource
{
    $appointment = $this->appointmentService->create(
        auth()->user(),
        $request->validated()
    );

    return new AppointmentResource($appointment);
}
```

### Form Requests — `app/Http/Requests/Api/V1/`
- Solo `rules()` y `authorize()`. Sin lógica de negocio.
- No acceden a Services ni a otros modelos más allá de lo necesario para autorización básica.

### Services — `app/Services/`
- **Toda la lógica de negocio** que excede una operación simple de modelo.
- Reciben **modelos Eloquent o primitivos** — nunca un objeto `Request`.
- Devuelven modelos, colecciones Eloquent, o lanzan excepciones de dominio.
- Registrados en `AppServiceProvider` como singletons para inyección por contenedor.
- Sin lógica HTTP (sin `Request`, sin `Response`, sin `abort()`).
- Las operaciones que modifican múltiples tablas se envuelven en `DB::transaction()`.

**Servicios del dominio y sus dependencias:**

```
ScheduleService
    └── AppointmentService
            └── AvailabilityService
```

**API pública de cada servicio:**

#### `AvailabilityService`
| Método | Descripción |
|---|---|
| `getEffectiveScheduleForDate(User $provider, Carbon $date): array` | Resuelve override vs. semanal. Retorna `{is_working, sessions, source}` |
| `getOccupiedSlots(User $provider, Carbon $date, ?int $excludeId): array` | Franjas ocupadas por citas activas (blocking) del día |
| `getAvailabilityForDate(User $provider, Carbon $date): array` | Horario efectivo + franjas ocupadas por sesión |
| `isSlotAvailable(User $provider, Carbon $start, int $durationMinutes, ?int $excludeId): bool` | Valida las 5 reglas de RF-AVAIL-02. Lanza `AppointmentNotAvailableException` si falla |

#### `AppointmentService`
| Método | Descripción |
|---|---|
| `create(User $client, User $provider, array $data): Appointment` | Valida disponibilidad y crea la cita |
| `confirm(Appointment $appt): Appointment` | `pending → confirmed` |
| `cancel(Appointment $appt, User $actor): Appointment` | Cualquier estado activo → `cancelled`. Aplica notice mínima para clientes |
| `reschedule(Appointment $appt, User $actor, array $newData): Appointment` | Marca original como `rescheduled`, crea nueva cita. Exime de notice si status es `reschedule_requested` |
| `requestReschedule(Appointment $appt, RescheduleRequestedBy $by): Appointment` | Activo → `reschedule_requested`. El slot queda libre para nuevas reservas |
| `complete(Appointment $appt): Appointment` | `confirmed → completed` |
| `markAffectedAsRescheduleRequested(array $ids, RescheduleRequestedBy $by): int` | Bulk update para cambios de horario. Retorna count de filas afectadas |

#### `ScheduleService`
| Método | Descripción |
|---|---|
| `getWeeklySchedule(User $provider): Collection` | Los 7 días con sus sesiones |
| `updateDay(User $provider, int $day, bool $isActive, array $sessions): WeeklySchedule` | Upsert día + sync de work_sessions + detecta/invalida citas afectadas |
| `getOverrides(User $provider): Collection` | Todos los overrides del proveedor |
| `createOverride(User $provider, array $data): ScheduleOverride` | Crea override + work_sessions. Si `is_working=false`, invalida citas del día |
| `updateOverride(ScheduleOverride $override, array $data): ScheduleOverride` | Actualiza override + re-sincroniza work_sessions |
| `deleteOverride(ScheduleOverride $override): void` | Elimina override y sus work_sessions (restaura comportamiento semanal) |

### Models — `app/Models/`
- Relaciones Eloquent con return type hints.
- Scopes locales para filtros comunes: `scopeActive()`, `scopeForDate()`, `scopePending()`.
- Casts a Enums para columnas de estado.
- Sin lógica de negocio compleja — esa va en Services.
- Método `casts()` (no propiedad `$casts`).

**Métodos helper en modelos (no lógica de negocio — solo conveniencia):**

| Modelo | Método | Descripción |
|---|---|---|
| `User` | `isProvider(): bool` | Atajo para `$this->role === UserRole::Provider` |
| `User` | `isClient(): bool` | Atajo para `$this->role === UserRole::Client` |
| `ProviderProfile` | `getEffectiveDurationMinutes(): int` | Duración del perfil o config default si no está configurada |
| `ProviderProfile` | `getEffectiveMinCancelNoticeHours(): int` | Notice mínima del perfil o config default |
| `NotificationPreference` | `isEnabled(User, NotificationEventType, NotificationChannel): bool` | Estático. `true` si no existe preferencia (modelo opt-out) |
| `NotificationPreference` | `getReminderLeadTime(User): int` | Antelación configurada del cliente o config default |

### Policies — `app/Policies/`
- Una policy por recurso que requiere autorización.
- `AppointmentPolicy`: `cancel`, `reschedule`, `requestReschedule`, `confirm`, `complete`.

### Resources — `app/Http/Resources/Api/V1/`
- Toda respuesta JSON pasa por un `JsonResource`.
- Listas paginadas con `ResourceCollection`.

### Notifications — `app/Notifications/`
- Una clase por evento de notificación.
- Implementan `via()` consultando las `NotificationPreference` del usuario destinatario.
- Despachadas desde Services (no desde Controllers).

### Jobs — `app/Jobs/`
- Para operaciones costosas o diferidas: envío de recordatorios, procesado masivo de citas afectadas.
- Siempre implementan `ShouldQueue`.

### Enums — `app/Enums/`
- Backed enums PHP 8.1+. Keys en TitleCase.
- Usados como casts en los modelos correspondientes.
- `AppointmentStatus` expone métodos helper de estado:
  - `isActive(): bool` — true para `Pending`, `Confirmed`, `RescheduleRequested`
  - `blocksAvailability(): bool` — true para `Pending`, `Confirmed` (los estados que ocupan un slot)
  - `canBeRescheduledBy(UserRole $role): bool` — true si el rol puede reprogramar según el estado actual

### Exceptions — `app/Exceptions/`
- Excepciones tipadas para errores de dominio, lanzadas desde Services.
- Capturadas en `bootstrap/app.php` y formateadas como `{"message": "..."}` con HTTP 422.
- Sólo se formatean como JSON cuando la request incluye `Accept: application/json`.

---

## Configuración del dominio — `config/booking.php`

| Clave | Env var | Default | Descripción |
|---|---|---|---|
| `booking.default_appointment_duration_minutes` | `BOOKING_DEFAULT_DURATION_MINUTES` | `30` | Duración base por persona en minutos |
| `booking.default_reminder_hours` | `BOOKING_DEFAULT_REMINDER_HOURS` | `24` | Antelación del recordatorio si el cliente no la configura |
| `booking.default_min_cancel_notice_hours` | `BOOKING_DEFAULT_MIN_CANCEL_NOTICE_HOURS` | `2` | Notice mínima de cancelación/reprogramación si el proveedor no la configura |
| `booking.initial_appointment_status` | `BOOKING_INITIAL_APPOINTMENT_STATUS` | `'pending'` | Estado inicial de las citas nuevas (`pending` o `confirmed`) |

---

## Relación polimórfica WorkSession

`WorkSession` pertenece polimórficamente a `WeeklySchedule` (cuando `schedule_type = 'weekly'`) o a `ScheduleOverride` (cuando `schedule_type = 'override'`).

El morph map se registra explícitamente en `AppServiceProvider::boot()`:

```php
Relation::enforceMorphMap([
    'users'    => User::class,
    'weekly'   => WeeklySchedule::class,
    'override' => ScheduleOverride::class,
]);
```

Esto asegura que los valores almacenados en `schedule_type` sean las claves cortas (`'weekly'`, `'override'`) en vez de nombres de clase completos. El modelo `User` se incluye en el mapa porque Sanctum registra tokens con una relación polimórfica `tokenable` que apunta a `User`.

---

## Convenciones de nombrado

| Componente | Patrón | Ejemplos |
|---|---|---|
| Modelos | `SingularPascalCase` | `ProviderProfile`, `WorkSession`, `Appointment` |
| Controladores | `PluralRecurso + Controller` | `AppointmentController`, `ScheduleController` |
| Form Requests | `Verbo + Recurso + Request` | `StoreAppointmentRequest`, `RescheduleAppointmentRequest`, `UpdateWeeklyScheduleRequest` |
| Services | `Dominio + Service` | `AvailabilityService`, `AppointmentService` |
| Resources | `Singular + Resource` | `AppointmentResource`, `UserResource` |
| Resource Collections | `Singular + Collection` | `AppointmentCollection` |
| Notifications | `EventoPasado + Notification` | `AppointmentCreatedNotification`, `RescheduleRequestedNotification` |
| Jobs | `Acción + Sustantivo + Job` | `SendAppointmentReminderJob`, `ProcessScheduleChangeJob` |
| Policies | `Recurso + Policy` | `AppointmentPolicy` |
| Enums | `NombreConcepto` (keys: TitleCase) | `AppointmentStatus::Confirmed`, `UserRole::Provider` |
| Exceptions | `Descripción + Exception` | `AppointmentNotAvailableException`, `ScheduleConflictException` |
| Rutas nombradas | `api.v1.<recurso>.<acción>` | `api.v1.appointments.store`, `api.v1.schedule.update` |
| Variables | `camelCase descriptivo` | `$activeAppointments`, `$effectiveSessions` |
| Scopes de Modelo | `scopePascalCase` | `scopeActiveForDate()`, `scopePending()` |
| Constantes de Config | `dot.notation` | `config('booking.default_reminder_hours')` |

---

## Modelos del dominio

| Modelo | Tabla | Notas |
|---|---|---|
| `User` | `users` | Rol via `UserRole` enum; columna `role` almacenada como `string` (Laravel string-backed enum) |
| `ProviderProfile` | `provider_profiles` | FK a `users` |
| `WeeklySchedule` | `weekly_schedule` | Un registro por día de semana por proveedor |
| `WorkSession` | `work_sessions` | Polimórfico via `schedule_id` + `schedule_type` (`'weekly'` o `'override'`) |
| `ScheduleOverride` | `schedule_overrides` | Override por fecha concreta |
| `Appointment` | `appointments` | Estado vía `AppointmentStatus` enum; columnas de estado almacenadas como `string` |
| `NotificationPreference` | `notification_preferences` | Por (user, event_type, channel). Opt-out: si no existe registro, el canal está habilitado por defecto |

> **Nota sobre tipos enum:** Las columnas de tipo `enum` en el ER diagram se implementan como `string` en las migraciones. Los valores válidos se controlan mediante los backed enums de PHP y los casts de Eloquent, sin restricciones a nivel de base de datos.

---

## Autenticación

- **Laravel Sanctum** — Bearer tokens para API (instalado via `php artisan install:api`).
- Middleware `auth:sanctum` en todas las rutas protegidas.
- Middleware `EnsureUserHasRole` con alias `role` — acepta uno o varios roles: `->middleware('role:provider')` o `->middleware('role:provider,client')`.

---

## Enums del dominio

```
AppointmentStatus:  Pending, Confirmed, Cancelled, Rescheduled, RescheduleRequested, Completed
UserRole:           Provider, Client
RescheduleRequestedBy: Provider, System
CancelledBy:        Provider, Client
NotificationEventType: AppointmentCreated, AppointmentConfirmed, AppointmentCancelled,
                       AppointmentRescheduled, RescheduleRequested, AppointmentReminder,
                       ScheduleChanged
NotificationChannel: Email ('mail'), InApp ('database')
```

Los valores string de `NotificationChannel` corresponden a los nombres de driver de Laravel Notifications (`mail`, `database`).


---

## Estructura de directorios

```
app/
├── Console/
│   └── Commands/                     # Comandos Artisan del proyecto
├── Enums/                            # Backed enums tipados (AppointmentStatus, etc.)
├── Exceptions/                       # Excepciones de dominio del proyecto
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       └── V1/                   # Todos los controladores de la API v1
│   │           ├── Auth/
│   │           │   └── AuthController.php
│   │           ├── AppointmentController.php
│   │           ├── AvailabilityController.php
│   │           ├── ScheduleController.php
│   │           ├── NotificationController.php
│   │           └── ProfileController.php
│   ├── Requests/
│   │   └── Api/
│   │       └── V1/                   # Form Requests por módulo
│   │           ├── Auth/
│   │           ├── Appointment/
│   │           └── Schedule/
│   └── Resources/
│       └── Api/
│           └── V1/                   # Eloquent API Resources
├── Jobs/                             # Jobs en cola (ShouldQueue)
├── Models/                           # Modelos Eloquent
├── Notifications/                    # Clases de notificación Laravel
├── Policies/                         # Políticas de autorización
├── Providers/
└── Services/                         # ← ÚNICA ADICIÓN: lógica de negocio
    ├── AvailabilityService.php
    ├── AppointmentService.php
    └── ScheduleService.php
```

---

## Capas y responsabilidades

### Routes — `routes/api.php`
- Solo enrutamiento. Cero lógica.
- Prefijo: `v1` → todas las rutas bajo `/api/v1/`.
- Nombradas: `api.v1.<recurso>.<acción>` (ej. `api.v1.appointments.store`).
- Agrupadas con middleware `auth:sanctum` y validación de rol.

### Controllers — `app/Http/Controllers/Api/V1/`
- Capa HTTP pura: **recibir → validar (FormRequest) → delegar al Service → devolver Resource**.
- Máximo ~5 líneas de lógica por método.
- Inyectar Services por constructor (constructor property promotion).
- Nunca acceder a modelos directamente; siempre a través del Service.

```php
public function store(StoreAppointmentRequest $request): AppointmentResource
{
    $appointment = $this->appointmentService->create(
        auth()->user(),
        $request->validated()
    );

    return new AppointmentResource($appointment);
}
```

### Form Requests — `app/Http/Requests/Api/V1/`
- Solo `rules()` y `authorize()`. Sin lógica de negocio.
- No acceden a Services ni a otros modelos más allá de lo necesario para autorización básica.

### Services — `app/Services/`
- **Toda la lógica de negocio** que excede una operación simple de modelo.
- Reciben **modelos Eloquent o primitivos** — nunca un objeto `Request`.
- Devuelven modelos, colecciones Eloquent, o lanzan excepciones de dominio.
- Registrados en `AppServiceProvider` para inyección por contenedor.
- Sin lógica HTTP (sin `Request`, sin `Response`, sin `abort()`).

**Servicios del dominio:**

| Servicio | Responsabilidad |
|---|---|
| `AvailabilityService` | Resolver horario efectivo del día (semanal + overrides), validar solapamientos, calcular franjas libres |
| `AppointmentService` | Ciclo de vida completo de citas: crear, confirmar, cancelar, reprogramar, solicitar reprogramación |
| `ScheduleService` | Gestionar horario semanal y work sessions, gestionar overrides, detectar citas afectadas por cambios de horario |

### Models — `app/Models/`
- Relaciones Eloquent con return type hints.
- Scopes locales para filtros comunes: `scopeActive()`, `scopeForDate()`, `scopePending()`.
- Casts a Enums para columnas de estado.
- Sin lógica de negocio compleja — esa va en Services.
- Método `casts()` (no propiedad `$casts`).

### Policies — `app/Policies/`
- Una policy por recurso que requiere autorización.
- `AppointmentPolicy`: `cancel`, `reschedule`, `requestReschedule`, `confirm`, `complete`.

### Resources — `app/Http/Resources/Api/V1/`
- Toda respuesta JSON pasa por un `JsonResource`.
- Listas paginadas con `ResourceCollection`.

### Notifications — `app/Notifications/`
- Una clase por evento de notificación.
- Implementan `via()` consultando las `NotificationPreference` del usuario destinatario.
- Despachadas desde Services (no desde Controllers).

### Jobs — `app/Jobs/`
- Para operaciones costosas o diferidas: envío de recordatorios, procesado masivo de citas afectadas.
- Siempre implementan `ShouldQueue`.

### Enums — `app/Enums/`
- Backed enums PHP 8.1+. Keys en TitleCase.
- Usados como casts en los modelos correspondientes.

### Exceptions — `app/Exceptions/`
- Excepciones tipadas para errores de dominio, lanzadas desde Services.
- Capturadas en `bootstrap/app.php` para formatear respuestas JSON consistentes.

---

## Convenciones de nombrado

| Componente | Patrón | Ejemplos |
|---|---|---|
| Modelos | `SingularPascalCase` | `ProviderProfile`, `WorkSession`, `Appointment` |
| Controladores | `PluralRecurso + Controller` | `AppointmentController`, `ScheduleController` |
| Form Requests | `Verbo + Recurso + Request` | `StoreAppointmentRequest`, `RescheduleAppointmentRequest`, `UpdateWeeklyScheduleRequest` |
| Services | `Dominio + Service` | `AvailabilityService`, `AppointmentService` |
| Resources | `Singular + Resource` | `AppointmentResource`, `UserResource` |
| Resource Collections | `Singular + Collection` | `AppointmentCollection` |
| Notifications | `EventoPasado + Notification` | `AppointmentCreatedNotification`, `RescheduleRequestedNotification` |
| Jobs | `Acción + Sustantivo + Job` | `SendAppointmentReminderJob`, `ProcessScheduleChangeJob` |
| Policies | `Recurso + Policy` | `AppointmentPolicy` |
| Enums | `NombreConcepto` (keys: TitleCase) | `AppointmentStatus::Confirmed`, `UserRole::Provider` |
| Exceptions | `Descripción + Exception` | `AppointmentNotAvailableException`, `ScheduleConflictException` |
| Rutas nombradas | `api.v1.<recurso>.<acción>` | `api.v1.appointments.store`, `api.v1.schedule.update` |
| Variables | `camelCase descriptivo` | `$activeAppointments`, `$effectiveSessions` |
| Scopes de Modelo | `scopePascalCase` | `scopeActiveForDate()`, `scopePending()` |
| Constantes de Config | `dot.notation` | `config('booking.default_reminder_hours')` |

---

## Modelos del dominio

| Modelo | Tabla | Notas |
|---|---|---|
| `User` | `users` | Rol via `UserRole` enum |
| `ProviderProfile` | `provider_profiles` | FK a `users` |
| `WeeklySchedule` | `weekly_schedule` | Un registro por día de semana por proveedor |
| `WorkSession` | `work_sessions` | Polimórfico: pertenece a `WeeklySchedule` o `ScheduleOverride` |
| `ScheduleOverride` | `schedule_overrides` | Override por fecha concreta |
| `Appointment` | `appointments` | Estado vía `AppointmentStatus` enum |
| `NotificationPreference` | `notification_preferences` | Por (user, event_type, channel) |

---

## Autenticación

- **Laravel Sanctum** — Bearer tokens para API.
- Middleware `auth:sanctum` en todas las rutas protegidas.
- Verificación de rol mediante Gate o middleware personalizado `EnsureUserHasRole`.

---

## Enums del dominio

```
AppointmentStatus:  Pending, Confirmed, Cancelled, Rescheduled, RescheduleRequested, Completed
UserRole:           Provider, Client
RescheduleRequestedBy: Provider, System
CancelledBy:        Provider, Client
NotificationEventType: AppointmentCreated, AppointmentConfirmed, AppointmentCancelled,
                       AppointmentRescheduled, RescheduleRequested, AppointmentReminder,
                       ScheduleChanged
NotificationChannel: Email, InApp
```
