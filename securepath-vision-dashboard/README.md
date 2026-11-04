# SecurePath Vision Dashboard

React/Vite administration dashboard connected to the SecurePath Laravel attendance API. The dashboard covers authentication, people, attendance, organization, reports, recognition review, administration, alerts, settings, notifications, profile management, and the regular-user attendance portal.

## Requirements

- Docker Desktop with the engine running
- Node.js 20 or newer
- pnpm through Corepack, or pnpm installed globally

The Laravel project is expected at the sibling path `../securepath-attendance-api`.

## First-time Backend Setup

Open Command Prompt in `securepath-attendance-api`:

```bat
if not exist .env copy .env.example .env
docker compose build
docker compose run --rm api php artisan key:generate
docker compose up -d
docker compose exec api php artisan db:seed
docker compose exec api php artisan storage:link
```

`db:seed` loads disposable development data and the accounts below. Run it only for a new development database. Do not use `migrate:fresh` against data you need to keep.

For normal daily startup, only this command is required:

```bat
docker compose up -d
```

The API is available at `http://127.0.0.1:8000`, Swagger at `http://127.0.0.1:8000/docs/api`, and MySQL is exposed to Windows on port `3307`.

## Frontend Setup

Open a second Command Prompt in `securepath-vision-dashboard`:

```bat
corepack pnpm install
corepack pnpm run dev
```

Open `http://127.0.0.1:5173`.

The checked-in local `.env` uses:

```env
VITE_API_BASE_URL=http://localhost:8000/api/v1
```

`.env.example` intentionally contains only an empty public configuration key. Restart the Vite server after changing `.env`.

## Development Accounts

| Role | Email | Password |
| --- | --- | --- |
| Super Admin | `superadmin@attendance.local` | `SuperAdmin123!` |
| Limited Admin | `admin@attendance.local` | `Admin123!` |
| Regular User | `employee@attendance.local` | `Employee123!` |

These accounts are Laravel seed data, not frontend authentication bypasses. Replace them before a shared or production deployment.

## Authentication and Permissions

Laravel Sanctum issues a bearer token from `POST /api/v1/auth/login`. The frontend stores the session under `securepath_session`, attaches the token centrally, restores the user through `GET /api/v1/auth/me`, and revokes the active token on logout.

Laravel returns the current role and permission strings. The existing `hasPermission()` architecture controls routes and buttons, while Laravel middleware remains the final authorization authority. A `401` clears the invalid session; a `403` keeps the user signed in and displays the existing access-denied state.

## Verification

Frontend:

```bat
corepack pnpm run build
```

Backend:

```bat
docker compose exec api php artisan test
docker compose exec api php artisan route:list --path=api/v1
docker compose ps
```

The production build may report a non-blocking Vite chunk-size warning. No lint script is configured.

## Documentation

See `docs/api-integration.md` for the service-to-endpoint map, request behavior, pagination, uploads, environment switching, and error handling.
