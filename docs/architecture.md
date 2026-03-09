# Arquitectura y Convenciones — Appointment Booking Backend

> Este documento es la referencia canónica de arquitectura para el proyecto.
> Las instrucciones derivadas de este documento están escritas en `AGENTS.md` y `CLAUDE.md` para su consumo por agentes de IA.

---

## Filosofía

Estructura estándar de Laravel 12 con **una única adición**: la capa `Services`.
Sin repositorios, sin DTOs, sin hexagonal. Simple y predecible.

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
