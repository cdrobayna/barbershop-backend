# Barbershop Backend (Laravel 12)

Backend API para gestión de citas, horarios y notificaciones.

## Stack

- Laravel 12
- PostgreSQL
- Redis
- Sanctum (auth por token)
- Pest (testing)
- FrankenPHP (servidor HTTP en Docker)

## Levantar entorno Docker (desarrollo)

### 1) Preparar entorno

```bash
cp .env.docker .env
docker compose build
docker compose up -d
```

Si es primera vez, genera la key:

```bash
docker compose exec app php artisan key:generate
```

> El contenedor `app` ejecuta migraciones al iniciar (`RUN_MIGRATIONS=1`).

### 2) Servicios y puertos

- API: `http://localhost:8000`
- PostgreSQL: `localhost:5432`
- Redis: `localhost:6379`
- Mailpit SMTP: `localhost:1025`
- Mailpit UI: `http://localhost:8025`

### 3) Comandos útiles

```bash
# ver logs
docker compose logs -f app
docker compose logs -f queue
docker compose logs -f scheduler

# correr tests dentro del contenedor
docker compose exec app php artisan test --compact

# ejecutar migraciones manualmente
docker compose exec app php artisan migrate

# bajar stack
docker compose down
```

Para borrar también volúmenes de datos:

```bash
docker compose down -v
```

## Servicios de aplicación

- `app`: servidor FrankenPHP sirviendo Laravel.
- `queue`: worker de colas (`php artisan queue:work`).
- `scheduler`: scheduler continuo (`php artisan schedule:work`).
- `postgres`: base de datos principal.
- `redis`: cache/infra de soporte.
- `mailpit`: inspección local de emails.

## Notas para frontend

- Usar `http://localhost:8000/api/v1` como base URL.
- El backend requiere autenticación con Bearer token en rutas protegidas.
- Los correos de prueba (confirmaciones, recordatorios, etc.) se ven en Mailpit UI.
