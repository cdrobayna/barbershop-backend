# Bruno Collection — Barbershop Backend API

## Uso rápido

1. Abrir Bruno y cargar la carpeta `bruno/` como colección.
2. Seleccionar el ambiente `environments/local.bru`.
3. Cargar datos demo: `php artisan db:seed`.
4. Ejecutar `auth/login` o `auth/register-client` + `auth/login`.
5. Copiar el token de login y asignarlo a la variable `token` del ambiente.
6. Ejecutar los endpoints protegidos por módulo.

## Credenciales demo (Seeder)

- Cliente: `frontend.client@example.com` / `password123`
- Proveedor: `provider@example.com` / `password123`

## Orden sugerido para frontend

1. `auth` (register/login/logout)
2. `profile` + `notifications`
3. `availability`
4. `appointments`
5. `provider-schedule` (solo rol provider)

## Variables importantes

- `baseUrl`: URL base del backend (por defecto `http://localhost:8000`)
- `token`: Bearer token obtenido en login
- `providerId`, `appointmentId`, `notificationId`, `overrideId`, `dayOfWeek`: placeholders para requests con path/query params

## Notas de roles

- Endpoints bajo `profile/*` son de cliente.
- Endpoints bajo `provider/profile` y `schedule/*` son de proveedor.
- Si usas token de cliente en rutas de proveedor (o viceversa), la API devolverá `403 Forbidden`.
