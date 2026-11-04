# SecurePath Attendance

**An Integrated Facial Recognition Attendance Management System**

SecurePath Attendance is a full-stack attendance management system designed to streamline employee attendance tracking, workforce administration, and payroll operations through facial recognition technology.

The system combines a Laravel REST API with a web-based administrative dashboard and a facial recognition client.

The backend manages business logic, authentication, authorization, attendance records, payroll calculations, and system auditing, while the dashboard provides an interface for administrators to manage and monitor daily operations.

## System Components

### 1. Backend API
**Technology:** Laravel, PHP, MySQL

A RESTful API responsible for:

- Authentication and authorization using Laravel Sanctum.
- Role-Based Access Control (RBAC).
- Employee and department management.
- Attendance tracking and corrections.
- Leave management.
- Payroll and overtime calculations.
- Recognition event processing.
- Notifications and audit logging.
- CSV imports and exports.
- OpenAPI documentation.

### 2. Admin Dashboard
**Technology:** [Insert actual frontend technologies]

A web-based administrative interface providing:

- Employee management.
- Attendance monitoring.
- Department administration.
- Payroll management.
- Attendance reports.
- User and permission management.
- System administration.

### 3. Facial Recognition Client

The recognition component handles image capture, facial recognition, and liveness processing before submitting recognition results to the backend API.

The Laravel backend handles recognition-event validation and attendance processing. It does not implement the facial recognition model itself.

## System Architecture

```text
          Mobile / Kiosk Client
                   |
                   v
          Facial Recognition
                   |
                   v
             Laravel API
                   |
          +--------+--------+
          |                 |
          v                 v
        MySQL         Business Logic
                            |
                     +------+------+
                     |             |
                     v             v
              Admin Dashboard   Reports
```

## Technology Stack

| Component | Technologies |
|---|---|
| Backend | Laravel, PHP |
| Database | MySQL |
| Authentication | Laravel Sanctum |
| Frontend | [Actual technologies] |
| Containerization | Docker |
| API Documentation | OpenAPI 3.1, Swagger UI |
| Testing | PHPUnit |
| API Communication | REST |

## Repository Structure

```text
securepath-attendance/
├── backend/
├── dashboard/
├── docs/
├── screenshots/
├── compose.yaml
└── README.md
```

## Getting Started

Each component has its own installation instructions.

- [Backend Setup](backend/README.md)
- [Dashboard Setup](dashboard/README.md)

## API Documentation

The backend provides interactive API documentation through Swagger UI.

Once the backend is running:

`http://localhost:8000/docs/api`

OpenAPI specification:

`http://localhost:8000/openapi.json`

## Documentation

Additional technical documentation:

- [System Architecture](docs/architecture.md)
- [Component Integration](docs/integration.md)
- [Deployment Guide](docs/deployment.md)

## Screenshots

Screenshots of the administrative dashboard and system interfaces will be available in the `screenshots/` directory.

## Security

- Authentication through Laravel Sanctum.
- Centralized permission management.
- Server-side authorization.
- Protected attendance records.
- Audit logging.
- Private file storage.
- Recognition-event validation.

Never commit environment files, API keys, production credentials, or real employee biometric data.

## Project Status

The project is under development. Individual components and integrations may evolve as development progresses.

## License

No license has been assigned yet.