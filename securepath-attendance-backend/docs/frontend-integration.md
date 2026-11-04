# Frontend Integration

## Base URL and headers

Development base URL: `http://localhost:8000/api/v1`.

```http
Accept: application/json
Content-Type: application/json
Authorization: Bearer <accessToken>
```

Set `VITE_API_BASE_URL=http://localhost:8000/api/v1`. Keep the token in the current authentication context and clear it after a `401` or logout. Do not put kiosk credentials in React.

## Login

```http
POST /auth/login
```

```json
{
  "email": "admin@attendance.local",
  "password": "Admin123!",
  "deviceName": "react-dashboard"
}
```

```json
{
  "success": true,
  "message": "Signed in successfully.",
  "data": {
    "accessToken": "1|token",
    "tokenType": "Bearer",
    "expiresAt": "2026-08-16T12:00:00+03:00",
    "user": {
      "id": "USR-002",
      "name": "Nadia Brooks",
      "email": "admin@attendance.local",
      "role": "admin",
      "permissions": ["dashboard.view", "people.view", "attendance.view"]
    }
  }
}
```

Use `GET /auth/me` or `GET /profile` to restore a session, `POST /auth/logout` to revoke the current token, and `POST /auth/change-password` to change the password.

## Response conventions

Successful single-resource responses use `{ success, message, data }`. Paginated responses use:

```json
{
  "success": true,
  "data": [],
  "pagination": { "page": 1, "limit": 20, "total": 142, "totalPages": 8 }
}
```

Errors use `{ success: false, message, code, errors? }`. Handle `401` by ending the local session, `403` with the dashboard Access Denied view, `409` as a business conflict, and `422` by mapping `errors` to form fields.

## Query standard

Collection endpoints consistently accept `page`, `limit`, `search`, `sortBy`, and `sortOrder`. Domain filters include `date`, `dateFrom`, `dateTo`, `personId`, `departmentId`, `location`, `status`, `type`, `faceRegistered`, `attendanceStatus`, `verificationMethod`, `minConfidence`, `recognitionStatus`, `verificationStatus`, and `severity` where relevant.

## Dashboard endpoints

| Method | Endpoint | Permission |
| --- | --- | --- |
| GET | `/dashboard` | `dashboard.view` |
| GET | `/dashboard/summary` | `dashboard.view` |
| GET | `/dashboard/attendance-trend` | `dashboard.view` |
| GET | `/dashboard/attendance-by-department` | `dashboard.view` |
| GET | `/dashboard/recent-activity` | `dashboard.view` |

The consolidated `/dashboard` response already matches the current React cards and charts: `stats`, `attendanceTrend`, `statusBreakdown`, `recognitionAccuracy`, `departments`, and `recentActivity`.

## Module endpoints

| Module | Endpoints |
| --- | --- |
| People | `GET/POST /people`, `GET/PUT/DELETE /people/{personCode}`, `PATCH /people/{personCode}/status`, profile and paginated attendance subresources |
| Import/export | `POST /people/import`, `GET /people/export` |
| Departments | CRUD plus `/members` and `/statistics`; writes accept `maxOvertimeMinutes` and `autoCheckoutEnabled` |
| Roles and rates | `GET/POST/PUT/DELETE /roles` and `/pay-rates` with historical effective dates |
| Payroll | overview, periods, employee entries, summary, detail, CSV export, generate, approve, and close endpoints under `/payroll` |
| Schedules | CRUD with `days`, `personIds`, and `departmentIds` assignments |
| Holidays | `GET/POST /holidays`, `PUT/DELETE /holidays/{id}` |
| Attendance | CRUD, `POST /attendance/manual`, correction request/review endpoints |
| Leave | collection, detail, create, approve, reject, cancel |
| Recognition | list/detail plus verify, reject, and assign-person |
| Biometrics | status, consent, delete data, enrollment request/status |
| Reports | `GET /reports/{type}` and `/reports/{type}/export` |
| Alerts | list/detail, status, resolve, notes |
| Users | CRUD and status |
| Admins | CRUD, status, password reset, permissions; Super Admin only |
| Permissions | `GET /permissions`; Super Admin only |
| Audit logs | list/detail |
| Settings | `GET/PUT /settings` |
| Notifications | list, unread count, read one, read all |
| Profile/files | profile update/password and authenticated avatar routes |

Report types are `attendance`, `absences`, `late-arrivals`, `working-hours`, `departments`, and `recognition-accuracy`.

## Replacing mock services

Create one API client that unwraps `data`, preserves `pagination`, attaches the bearer token, serializes query filters, and normalizes errors. Replace each existing service body rather than changing page components. The backend Resources already return the current mock keys such as `person.id`, `person.name`, `department`, `faceStatus`, attendance `method`, `confidence`, `status`, and prefixed IDs.

The only deliberate semantic change is that write operations are asynchronous from the UI perspective and can return `409` or field-level `422` responses. Attendance edits return the correction record because changes are audit-preserving.
