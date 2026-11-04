# Security Architecture

## Authentication and tokens

Laravel Sanctum issues scoped bearer tokens. Passwords and mobile device secrets use Laravel's configured password hasher. Logout revokes the current token, password changes revoke other tokens, disabled actors cannot sign in, and login endpoints are rate-limited by normalized email/device identifier and IP address.

Use HTTPS in every non-local environment. Tokens belong in an OS keystore for mobile clients and an intentionally chosen browser storage strategy for the dashboard. Shorten `SANCTUM_TOKEN_EXPIRATION` for higher-risk deployments and add server-side token cleanup.

## Authorization

`PermissionCatalog` is the single source of permission names. Middleware protects routes, Form Requests authorize privileged forms, and services enforce invariants. Laravel Gate has a before-hook that grants all abilities to `super_admin`; the Super Admin has no stored permission list.

Normal admins cannot access `/admins` or `/permissions` because those routes require `role:super_admin`. `AdminService` separately refuses mutation of any non-Admin record, protecting the Super Admin even if a controller is reused later. Admin creation always forces `role=admin`; clients cannot submit a role.

Regular users can read only attendance and leave records linked to their own `person_id`. Mobile devices have a distinct polymorphic Sanctum identity and only the `recognition:submit` ability.

## Input and output protection

Form Requests validate query parameters, writable fields, enums, identifiers, dates, uploads, and permission names. Eloquent fillable lists limit mass assignment. API Resources expose camelCase fields and omit passwords, reset tokens, device secrets, enrollment token hashes, and biometric secure references.

Avatar uploads permit only validated image MIME types/extensions up to 5 MB, use generated filenames, and live on the private disk. Authenticated controllers stream them after authorization.

## Recognition and biometrics

The API never claims to perform AI inference. It validates mobile results and applies server-side confidence, liveness, duplicate, idempotency, and attendance rules. Device identifiers are derived from the authenticated device, not trusted from JSON.

Biometric consent is explicit and auditable. Enrollment tokens are random, expire after 30 minutes, are stored only as SHA-256 hashes, and are single-use. Raw embeddings must be encrypted in isolated private storage or an external biometric system; only an opaque secure reference may be stored. Dashboard Resources never expose it. Deletion revokes the profile, removes supported private storage, clears metadata, and records an audit event.

## Transactions and auditing

Transactions protect admin plus permission creation, attendance corrections, leave approval, biometric changes, enrollment completion, and recognition plus attendance processing. Row locks and unique constraints prevent concurrent duplicate attendance. Audit logs record actor, operation, resource, old/new non-sensitive values, IP, user agent, result, and time. No API permits editing or deleting audit logs.

## Production configuration

- Set `APP_ENV=production`, `APP_DEBUG=false`, and generate a unique `APP_KEY`.
- Restrict `CORS_ALLOWED_ORIGINS`; never use a wildcard with credentials.
- Use unique MySQL credentials with least privilege, encrypted backups, and tested restore procedures.
- Configure TLS termination, secure headers, request-size limits, and proxy trust deliberately.
- Replace every seeded credential and mobile secret before deployment.
- Configure a real mail provider and supervised queue workers.
- Keep application/framework/Sanctum dependencies patched and run security scanning in CI.
- Protect logs and private files, define retention, and monitor failed login, permission, biometric, and recognition-review events.

