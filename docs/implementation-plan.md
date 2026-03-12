# Plan: Implementación — Appointment Booking Backend

## Stack
Laravel 12.53 · PHP 8.5.3 · PostgreSQL · Sanctum · Pest

## Arquitectura
- Controllers → Form Requests → Services → Eloquent Models → Notifications
- Sin repositorios. Sin DTOs. Una sola adición a Laravel estándar: `app/Services/`.
- Ver `docs/architecture.md` para convenciones completas.

---

## Fase 1 — Foundation

*Prerequisito para todas las demás fases.*

- [x] **f1-config** — Crear `config/booking.php`: `default_appointment_duration_minutes`, `default_reminder_hours`, `default_min_cancel_notice_hours`, `initial_appointment_status`
- [x] **f1-enums** — Crear en `app/Enums/`: `UserRole`, `AppointmentStatus`, `RescheduleRequestedBy`, `CancelledBy`, `NotificationEventType`, `NotificationChannel`
- [x] **f1-migrations** — Migraciones en orden de dependencia: users (añadir role + phone) → provider_profiles → weekly_schedule → schedule_overrides → work_sessions (polimórfico) → appointments → notification_preferences → notifications
- [x] **f1-models** — Modelos Eloquent con relaciones, casts a Enums, scopes: `User` (update), `ProviderProfile`, `WeeklySchedule`, `ScheduleOverride`, `WorkSession`, `Appointment`, `NotificationPreference`
- [x] **f1-exceptions** — Crear `AppointmentNotAvailableException`, `AppointmentActionNotAllowedException`, `ScheduleConflictException` (HTTP 422). Registrar en `bootstrap/app.php` para JSON response
- [x] **f1-middleware** — Crear `EnsureUserHasRole`, registrar alias `role` en `bootstrap/app.php`
- [x] **f1-seeder** — Artisan command `provider:create` (crea User role=Provider + ProviderProfile con defaults)
- [x] **f1-provider** — Bind de `AvailabilityService`, `AppointmentService`, `ScheduleService` en `AppServiceProvider`

---

## Fase 2 — Autenticación (RF-AUTH)

- [x] **f2-requests** — `RegisterUserRequest` (name, email unique, phone, password min:8 confirmed), `LoginRequest`
- [x] **f2-resource** — `UserResource` (id, name, email, phone, role)
- [x] **f2-auth** — `AuthController` con `register()`, `login()`, `logout()`. Rutas `POST /api/v1/auth/register|login|logout`
- [x] **f2-tests** — Registro exitoso/fallido, login correcto/fallido, logout (token invalidado)

---

## Fase 3 — Gestión de Horario (RF-SCHEDULE)

- [x] **f3-resources** — `WeeklyScheduleResource`, `WorkSessionResource`, `ScheduleOverrideResource`
- [x] **f3-requests** — `UpdateWeeklyDayRequest` (is_active + sessions[] sin solapamiento), `StoreScheduleOverrideRequest`, `UpdateScheduleOverrideRequest`
- [x] **f3-service** — `ScheduleService`: `getWeeklySchedule()`, `updateDay()` (upsert + sync + detecta afectadas), `getOverrides()`, `createOverride()`, `updateOverride()`, `deleteOverride()`
- [x] **f3-controller** — `ScheduleController` con 6 rutas bajo `middleware role:provider`
- [x] **f3-tests** — CRUD horario semanal, validación sesiones solapadas, CRUD overrides

---

## Fase 4 — Disponibilidad (RF-AVAIL)

*Core del negocio — lógica más crítica del sistema.*

- [x] **f4-resource** — `AvailabilityResource` (is_working, sessions[] con occupied_slots[])
- [x] **f4-service** — `AvailabilityService`: `getEffectiveScheduleForDate()`, `getOccupiedSlots()`, `getAvailabilityForDate()`, `isSlotAvailable()` con las 5 reglas de RF-AVAIL-02 + `excludeAppointmentId`
- [x] **f4-controller** — `AvailabilityController`, `GET /api/v1/availability?date=YYYY-MM-DD` (client + provider)
- [x] **f4-tests** — Día no laborable, override off/on, slot libre/ocupado, solapamiento exacto, cruce entre sesiones, party_size×duración, exclusión cita propia

---

## Fase 5 — Citas (RF-APPT)

- [ ] **f5-policy** — `AppointmentPolicy`: `view`, `cancel`, `reschedule`, `requestReschedule`, `confirm`, `complete`
- [ ] **f5-resources** — `AppointmentResource`, `AppointmentCollection`
- [ ] **f5-requests** — `StoreAppointmentRequest`, `CancelAppointmentRequest`, `RescheduleAppointmentRequest`, `RequestRescheduleRequest`, `ConfirmAppointmentRequest`, `CompleteAppointmentRequest`
- [ ] **f5-service** — `AppointmentService`: `create()`, `confirm()`, `cancel()` (notice), `reschedule()` (con/sin notice según estado), `requestReschedule()`, `complete()`, `markAffectedAsRescheduleRequested()`
- [ ] **f5-controller** — `AppointmentController` con 5 rutas cliente + 6 rutas proveedor
- [ ] **f5-tests** — Ciclo de vida completo, cancelación con/sin notice, reprogramación (estados válidos/inválidos), requestReschedule (slot liberado), aislamiento entre roles

---

## Fase 6 — Notificaciones (RF-NOTIF)

- [ ] **f6-notifs** — 7 clases en `app/Notifications/`: `AppointmentCreatedNotification`, `AppointmentConfirmedNotification`, `AppointmentCancelledNotification`, `AppointmentRescheduledNotification`, `RescheduleRequestedNotification`, `AppointmentReminderNotification`, `ScheduleChangedNotification`. Cada una: `via()` consulta `NotificationPreference::isEnabled()`, `toMail()`, `toArray()`
- [ ] **f6-jobs** — `SendAppointmentReminderJob` (scheduled, consulta lead_time por cliente) + `ProcessScheduleChangeJob` (queued, bulk update + notifica). Programar reminder en `bootstrap/app.php`
- [ ] **f6-controller** — `NotificationController`: `GET /api/v1/notifications`, `POST /api/v1/notifications/{id}/read`, `POST /api/v1/notifications/read-all`
- [ ] **f6-tests** — `NotificationPreference::isEnabled` con/sin preferencia (default=enabled), despacho al crear cita, omitida si desactivada

---

## Fase 7 — Perfiles y Preferencias (RF-PROFILE)

- [ ] **f7-resources** — `ProviderProfileResource`
- [ ] **f7-requests** — `UpdateClientProfileRequest`, `UpdatePasswordRequest`, `UpdateNotificationPreferencesRequest`, `UpdateProviderProfileRequest`
- [ ] **f7-profile** — `ProfileController`: cliente (GET/PUT perfil, PUT password, GET/PUT preferencias), proveedor (GET/PUT perfil profesional + duración + notice mínima)
- [ ] **f7-tests** — Actualización perfil, cambio contraseña, guardar/actualizar preferencias, antelación de recordatorio

---

## Progreso

| Fase | Tareas | Completadas |
|---|---|---|
| Fase 1 — Foundation | 8 | 8 ✅ |
| Fase 2 — Auth | 4 | 0 |
| Fase 3 — Schedule | 5 | 0 |
| Fase 4 — Availability | 4 | 0 |
| Fase 5 — Appointments | 6 | 0 |
| Fase 6 — Notifications | 4 | 0 |
| Fase 7 — Profile | 4 | 0 |
| **Total** | **35** | **0** |
