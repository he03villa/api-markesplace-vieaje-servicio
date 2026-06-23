# AGENTS.md — Backend Marketplace Viaje Servicio

## Stack

- **Laravel 12.x** / PHP 8.2+ / Composer
- **JWT auth** (`php-open-source-saver/jwt-auth`, guard: `api`) — primary. Sanctum (SPA) secondary.
- **Octane** (FrankenPHP) for app server. **Reverb** for WebSocket (port 8004).
- **Database-driven** queue, cache, session by default (Redis available via config).
- **Vite** + TailwindCSS 4 for frontend assets (vanilla JS, no framework).
- **Docker**: FrankenPHP + nginx + Redis + supervisord (octane, reverb, queue-worker, scheduler).

## Docker

All commands run inside the app container.

Define:
```
alias APP='docker compose exec api-marketplace-viajes-servicio'
```

## Architecture

- **API-only** — no Blade views except welcome page and email templates.
- Routes split into 6 files under `routes/`: `api.php` (core), `api_chats.php`, `api_deliveries.php`, `api_reviews.php`, `api_rides.php`, `channels.php` (broadcast).
- **`ApiResponseTrait`** on all controllers. Standard shape:
  `{ "success": bool, "message": string, "data": ... }`
  Helper methods:
  `successResponse($data, $message, $code)` — default 200
  `errorResponse($message, $code, $errors)` — default 400
  `validationErrorResponse($errors)` — always 422
  `notFoundResponse($message)` — always 404
  `unauthorizedResponse($message)` — always 401
  `forbiddenResponse($message)` — always 403
- 19 Eloquent models, 13 controllers, 14 service classes (business logic in `app/Services/`, not controllers).
- **Polymorphic `Publication` model** aggregates ServiceRequest + RideRequest into a single feed.
- **`HasPublication` trait** on ServiceRequest and RideRequest — syncs the publication on model events.
- Geolocation via OpenStreetMap Nominatim (reverse geocode) with fallback distance calculation.
- Observers registered in `AppServiceProvider::boot()`, not in `EventServiceProvider` (no such file).
- `isAdmin()` exists on User model (used in state machine permission checks).
- `worker` on ServiceRequest is a `hasOneThrough` via accepted Offer (not a stored column).
- All custom business exceptions return HTTP 400 (including `UnauthorizedActionException`).
- Dual-auth: `GET /api/user` uses Sanctum (`auth:sanctum`); everything else uses JWT (`auth:api`).

## Directory structure

```
.
├── app/
│   ├── Console/Commands/     # ExpireOldRequests, SyncPublications
│   ├── Events/               # 9 broadcast events
│   ├── Exceptions/           # CustomExceptions + Handler
│   ├── Http/
│   │   ├── Controllers/      # 13 controllers
│   │   ├── Requests/         # 18 form requests
│   │   └── Resources/        # 17 API resources
│   ├── Mail/                 # 2 mailables
│   ├── Models/               # 19 Eloquent models
│   ├── Observers/            # 4 observers
│   ├── Providers/            # AppServiceProvider
│   ├── Services/             # 14 service classes
│   ├── Traits/               # ApiResponseTrait, HasPublication
│   └── Utils/
├── config/                   # 17 config files
├── database/
│   ├── factories/
│   ├── migrations/           # ~24 migrations
│   └── seeders/
├── resources/views/          # welcome + email templates
├── routes/                   # 6 route files
│   ├── api.php
│   ├── api_chats.php
│   ├── api_deliveries.php
│   ├── api_reviews.php
│   ├── api_rides.php
│   └── channels.php
├── tests/                    # Unit + Feature (SQLite :memory:)
├── public/                   # HTTP entrypoint
├── docker-compose.yml
├── Dockerfile
├── start.sh
└── supervisord.conf
```

## Key workflows

1. **Service marketplace**: Client posts ServiceRequest → Workers submit Offers → Client accepts → Worker delivers → Client approves/rejects → Reviews.
2. **Ride sharing**: Driver creates RideRequest → Passengers join → Driver manages pickup/dropoff → Complete → Reviews.
3. **Chat**: Two-user Conversation model, messages with file attachments. Real-time via Reverb.
4. **Status transitions**: Both ServiceRequest and RideRequest have state machines validated via `canTransitionTo()` / `transitionTo()` in `HasPublication` trait.

## Commands

| Comando | Qué hace |
|---|---|
| `APP composer run test` | Tests (PHPUnit, SQLite :memory:) |
| `APP php artisan jwt:secret` | Regenera JWT secret |
| `APP php artisan config:cache && APP php artisan route:cache` | Cache bootstrap tras config/routes |
| `APP php artisan octane:reload` | Recarga Octane sin bajar el server |
| `APP php artisan migrate` | Ejecuta migraciones |
| `APP php artisan db:seed` | Ejecuta seeders |
| `APP php artisan make:migration create_xxx_table` | Crear migración |
| `APP php artisan make:model Xxx -m` | Modelo + migración |
| `APP php artisan make:controller XxxController` | Controlador |
| `APP php artisan make:request XxxRequest` | Form request |
| `APP php artisan make:resource XxxResource` | API resource |
| `APP php artisan make:test XxxTest` | Test |
| `APP php artisan make:job XxxJob` | Job |
| `APP php artisan make:policy XxxPolicy --model=Xxx` | Policy |
| `APP php artisan l5-swagger:generate` | Generar documentación Swagger |
| `APP php artisan config:clear` | Limpiar cache de config |
| `APP php artisan route:clear` | Limpiar cache de rutas |
| `APP php artisan storage:link` | Vincular storage público |
| `APP php artisan requests:expire` | Custom command; scheduled cada 30min (expira service requests viejos y no-show rides) |

## Plugins / Paquetes

```bash
# Instalar nuevo paquete
APP composer require vendor/package

# Publicar config / assets / migrations
APP php artisan vendor:publish --provider="Vendor\Package\ServiceProvider"

# Correr migraciones del paquete
APP php artisan migrate

# Recargar Octane si agregó config nueva
APP php artisan octane:reload

# Generar documentación Swagger (l5-swagger)
APP php artisan l5-swagger:generate
```

## Testing

- **SQLite in-memory** (`DB_DATABASE=:memory:`) — no external DB needed for tests.
- Test suites: `tests/Unit`, `tests/Feature`.
- Currently only example tests exist. Add tests matching existing patterns.
- Only `UserFactory` exists in `database/factories/` — create factories for other models as needed.
- Run focused: `APP php artisan test --filter=MethodName` or `APP php artisan test tests/Feature/SomeTest.php`.

## Broadcast events (9 total)

All in `app/Events/`: `DeliveryStatusChanged`, `MessageRead`, `MessageSent`, `OfferAccepted`, `OfferCreated`, `PassengerJoined`, `PassengerStatusChanged`, `RideStatusChanged`, `UserTyping`.
Channels: `service.{id}`, `ride.{id}`, `conversation.{id}`, `App.Models.User.{id}`.

## Notable config quirks

- `config/session.php` — driver: `database`, lifetime: 120min, same-site: `lax`.
- `config/queue.php` — driver: `database`, failed jobs: `database-uuids`.
- `config/jwt.php` — TTL: 60min, refresh TTL: 20160min (14d), algo: HS256.
- `config/cache.php` — default: `database`.
- `.env.example` defaults to SQLite + log mailer — good for local dev.
- `AppServiceProvider::boot()` forces `Carbon::setLocale('es')` (Spanish dates) and `URL::forceRootUrl(config('app.url'))`. 
- `.env` has `APP_DEEP_LINK` — URL de redirección tras verificación de email.
- **Tests**: Run via `composer run test` (config:clear + phpunit). 23 tests pass (Unit + Feature, SQLite :memory:). Auth tests require proper route paths (`/api/auth/*`). HasPublication tests require geolocation fallback arrays to include `country_name`/`state_name`/`city_name` keys.
- **GeolocationService**: `findLocationByCoordinates()` caches Nominatim results for 1 day; fallback nearest-* methods cache for 7 days. Fallback returns all 6 keys (`country_id`, `state_id`, `city_id`, `country_name`, `state_name`, `city_name`).
- **Status transitions**: Use `transitionTo()` on the model (validates via `canTransitionTo()`). Don't change `status` directly in controller `update()` — use the dedicated `PATCH /{id}/status` route on `ServiceRequestController`.
- **Observer timing**: Observers (`ServiceRequestObserver`, `RideRequestObserver`) call `$model->refresh()` after geolocation so the `HasPublication` trait sees fresh location IDs.
- Geolocation auto-runs on create/update via ServiceRequestObserver/RideRequestObserver (Nominatim). The User-Agent header in `GeolocationService.php` is a placeholder — set a real value before production.
- No CI/CD, no static analysis (PHPStan/Psalm), no pre-commit hooks configured.
- Laravel Pint available for formatting (`vendor/bin/pint`) — no custom config file, uses defaults.

## Gotchas

- Controller `update()` allows changing `status` directly via validation rules, **bypassing** the `transitionTo()` state machine — use the dedicated status routes instead.
- No custom exception handler for JSON: if a business exception escapes the controller `try/catch`, Laravel returns HTML, not JSON.
- `HasPublication` trait events fire **after** the explicit observer, so the first `syncPublication()` runs before geolocation assigns location IDs.

## Deployment

- `start.sh` runs `config:cache`, `route:cache`, `view:cache` before Octane.
- Supervisord manages: octane (2 workers, 250 max-requests), reverb, queue-worker (`--queue=exports,default --sleep=3 --tries=3 --max-time=3600`), scheduler.
- Upload limit: 50M (`custom-php.ini`).

## Sensitive files (never commit)

`.env`, `custom-php.ini`, `docker-compose.yml`, `Dockerfile`, `nginx.conf`, `supervisord.conf` are gitignored. Use `.example` counterparts as templates.
