# API Integration

## Base URL

The dashboard reads one public Vite variable:

```env
VITE_API_BASE_URL=http://localhost:8000/api/v1
```

`src/config.js` normalizes the URL and `src/services/apiClient.js` is the only HTTP entry point. Restart Vite after changing the variable. Use a different `.env` value for staging or production and never place backend secrets in a Vite variable.

Laravel CORS explicitly allows `http://localhost:5173` and `http://127.0.0.1:5173` in development.

## Authentication

- `POST /auth/login` returns a Laravel Sanctum bearer token and user.
- `GET /auth/me` restores the authenticated user, role, status, and permissions.
- `POST /auth/logout` revokes the active token.
- `POST /auth/forgot-password` starts the backend password-reset flow.
- `POST /profile/change-password` changes the current password.

The session is stored under `securepath_session`. `apiClient` adds `Authorization: Bearer <token>` and `Accept: application/json` to protected requests. JSON content type is added only for JSON bodies; the browser supplies multipart boundaries for `FormData`.

A protected `401` dispatches the global unauthorized event, clears the session, and returns the application to login. A `403` becomes an `ApiError` and does not clear authentication. Super Admin retains the frontend full-access override that matches Laravel's Gate behavior; all other permissions come from `/auth/me`.

## Service Map

| Frontend service | Laravel endpoints |
| --- | --- |
| `authService` | `/auth/*`, `/profile`, `/users`, `/admins`, `/permissions` |
| `peopleService` | `/people`, `/people/{id}`, profile, attendance, import/export, biometric and enrollment subroutes |
| `attendanceService` | `/attendance`, `/attendance/manual`, `/attendance-corrections`, `/recognition-events` |
| `organizationService` | `/departments`, department members/statistics, `/schedules`, `/holidays` |
| `reportService` | `/dashboard`, `/dashboard/*`, `/reports/{type}`, `/reports/{type}/export` |
| `alertService` | `/alerts`, `/audit-logs`, `/notifications` |
| `settingsService` | `/settings` |

Leave requests use `/leave-requests` plus the backend approve and reject actions. Avatar reads use authenticated `/people/{id}/avatar` or `/users/{id}/avatar` requests.

## Response Normalization

Laravel returns single resources in `{ success, message, data }` and lists in `{ success, data, pagination }`. `apiData()` unwraps `data`; `apiCollection()` returns `{ items, pagination }`. Domain services map API camelCase fields into the existing UI models, including people profile summaries, attendance display values, recognition percentages, notification display times, and settings percentages.

Recognition and settings thresholds are decimal values in Laravel and percentages in the UI. Raw biometric embeddings are never requested or exposed.

## Pagination, Search, and Filters

Lists pass Laravel's `page`, `limit`, `search`, `sortBy`, and `sortOrder` query values through the centralized client. People search is debounced before requesting. Module-specific filters use only implemented keys such as `departmentId`, `attendanceStatus`, `status`, `date`, `dateFrom`, `dateTo`, `location`, `recognitionStatus`, `verificationStatus`, and `minConfidence`.

`DataTable` accepts controlled Laravel pagination and sorting. Empty arrays remain valid successful responses and display the existing empty state.

## Mutations and Uploads

Create, update, delete, approve, reject, resolve, assignment, consent, and status actions wait for Laravel success before refreshing the visible data and showing feedback. Laravel `422` field errors are available through `ApiError.errors` and are displayed by connected forms.

People CSV import and profile/avatar update use `FormData`. The client does not manually set a multipart boundary. People and report exports use authenticated Blob downloads and preserve a server-provided filename from `Content-Disposition` when present.

## Error Handling

`ApiError` preserves the HTTP status, backend code, backend message, field errors, and response payload. Default handling is:

| Status | Behavior |
| --- | --- |
| `400` | Request message |
| `401` | Clear invalid session and return to login |
| `403` | Keep session and show access denied |
| `404` | Resource-not-found message |
| `409` | Conflict message |
| `422` | Field validation messages |
| `429` | Rate-limit message |
| `500` | Server error without exposing a stack trace |

Network failures report that the SecurePath API cannot be reached. Loading, saving, deleting, importing, and exporting operations use the existing page state and toast components.

## Backend Contract Notes

Laravel implementation, route output, Resources, Form Requests, and `public/openapi.json` were compared during integration. Attendance initially lacked the `location` query used by the existing filter; the backend received the smallest compatible change in `ApiIndexRequest` and `AttendanceService`, with an OpenAPI update and a feature test.

Docker process variables also caused `php artisan test` to use the development MySQL database despite the PHPUnit environment entries. The shared backend test case now forces SQLite, array cache/session, and a synchronous queue before database-refresh traits run. A regression test verifies this isolation. Docker Compose now waits for the API health check, and therefore completed migrations, before starting the queue worker.

No websocket, SSE, or broadcasting contract exists, so the dashboard does not invent real-time behavior. Mobile recognition intake and camera control remain separate from the web dashboard.
