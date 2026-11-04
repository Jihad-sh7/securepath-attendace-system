# SecurePath Attendance API

Production-oriented Laravel REST API for the SecurePath facial-recognition attendance dashboard and its mobile/kiosk recognition client. Laravel owns identity, authorization, attendance rules, recognition-event intake, reporting, auditing, and persistence. It does not implement or claim to implement the facial-recognition model.

## Stack

- Laravel 13.x on PHP 8.3-8.5
- MySQL 8.0+ as the primary data store
- Laravel Sanctum bearer tokens
- Database queues and notifications
- PHPUnit 12 feature and domain tests
- OpenAPI 3.1 with Swagger UI

Required PHP extensions: `bcmath`, `ctype`, `dom`, `fileinfo`, `filter`, `hash`, `intl`, `mbstring`, `openssl`, `pdo_mysql`, `session`, `tokenizer`, `xml`, `xmlwriter`, and `zip`. Tests additionally require `pdo_sqlite`.

## Native installation

```bash
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan storage:link
php artisan serve
```

On macOS or Linux use `cp .env.example .env`. Create the MySQL database named by `DB_DATABASE` before migrating. The default API URL is `http://localhost:8000/api/v1`.

Run the queue worker in a second terminal:

```bash
php artisan queue:work --tries=3 --timeout=120
```

Run the scheduler in a third terminal. This is required for department overtime auto-checkout:

```bash
php artisan schedule:work
```

## Docker installation

Docker is optional but supplies PHP 8.4, Composer, and MySQL 8.4:

```bash
copy .env.example .env
docker compose build
docker compose run --rm api php artisan key:generate
docker compose up -d
docker compose exec api php artisan migrate
docker compose exec api php artisan db:seed
```

The API is available on port `8000` and MySQL is exposed to the host on port `3307`. Docker Compose also starts the queue and scheduler workers.

## Verification

```bash
php artisan migrate
php artisan test
php artisan route:list --path=api/v1
php artisan serve
```

Tests use an in-memory SQLite database and do not alter the MySQL development database.

## Demo accounts

| Account | Email | Password | Role |
| --- | --- | --- | --- |
| Super Admin | `superadmin@attendance.local` | `SuperAdmin123!` | `super_admin` |
| Limited Admin | `admin@attendance.local` | `Admin123!` | `admin` |
| Employee | `employee@attendance.local` | `Employee123!` | `user` |

The seeded kiosk credential is `MOBILE-KIOSK-01` / `KioskSecret123!`. Replace every demo password and secret before any shared or production deployment.

## Authentication

Call `POST /api/v1/auth/login`, then send the returned token on protected requests:

```http
Authorization: Bearer <accessToken>
Accept: application/json
```

Logout revokes the current token. Password changes revoke all other tokens. Token expiration is controlled by `SANCTUM_TOKEN_EXPIRATION`.

## Roles and permissions

`super_admin` bypasses permission checks through Laravel Gate and cannot be edited, disabled, deleted, or assigned a normal permission list. An `admin` receives only explicitly assigned permissions from the centralized catalog in `app/Support/PermissionCatalog.php`. A `user` is limited to self-service attendance access.

Route middleware enforces permissions even when a client knows the URL. Form Requests validate input, services own transactions and business rules, and API Resources transform MySQL snake_case fields to frontend-friendly camelCase JSON.

## Attendance and recognition

Attendance is one record per person and date. Manual changes always create an `attendance_corrections` history entry. Approved leave creates `on_leave` attendance records. Recognition submissions use a unique `eventUuid`, server-side confidence/liveness rules, a configurable duplicate window, transactions, and row locks.

The mobile app performs image capture, liveness, and AI inference. It submits only recognition results. Raw embeddings are never returned by dashboard resources; any secure storage reference remains hidden and isolated in `biometric_profiles`.

## Payroll and overtime

Payroll periods are calculated from completed attendance records using the historical hourly rate effective for each attendance date. Rates are assigned by department and employee role, monetary calculations use integer cents, and generated entries retain employee, department, role, rate, and attendance breakdown snapshots. Approved and closed periods cannot be recalculated.

Departments may enable automatic checkout and set a maximum overtime duration in minutes. `attendance:auto-checkout` runs every minute through Laravel Scheduler, closes eligible open records at the exact schedule-end deadline, and creates a linked alert, notification, and system audit entry. See `docs/payroll-overtime.md` for assumptions and operations.

## Imports, exports, and files

People CSV import validates required headers and returns row-level failures. People and report exports stream CSV without loading the entire result set. Avatars are validated, renamed, stored privately, and served only through authenticated routes.

## CORS and frontend

Set `FRONTEND_URL` and `CORS_ALLOWED_ORIGINS` to explicit origins. Never use `*` for a production dashboard. Set the React variable:

```env
VITE_API_BASE_URL=http://localhost:8000/api/v1
```

See `docs/frontend-integration.md` for the endpoint contract.

## OpenAPI

- Swagger UI: `http://localhost:8000/docs/api`
- JSON document: `http://localhost:8000/openapi.json`
- Source: `public/openapi.json`

## Documentation

- `docs/frontend-integration.md`
- `docs/mobile-integration.md`
- `docs/database.md`
- `docs/security.md`
- `docs/payroll-overtime.md`

## Environment variables

The required variables are documented in `.env.example`: application URL/key/environment, frontend/CORS origins, MySQL connection, queue/cache/session drivers, mail transport, Sanctum expiration, login rate limit, duplicate recognition window, and recognition thresholds.

## Production checklist

Set `APP_ENV=production`, `APP_DEBUG=false`, HTTPS URLs, a generated `APP_KEY`, unique database credentials, a real mail transport, secure queue supervision, encrypted backups, log retention, and explicit CORS origins. Rotate seeded credentials, terminate unused tokens, protect private storage, run `php artisan optimize`, and keep the queue worker supervised. The backend must be deployed behind TLS; mobile device secrets must never be embedded in a public web bundle.
