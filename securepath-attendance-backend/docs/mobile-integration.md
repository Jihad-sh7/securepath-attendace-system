# Mobile Integration

## Trust boundary

The mobile/kiosk client performs camera capture, liveness checks, and AI inference. Laravel validates and records submitted results, applies thresholds and duplicate rules, and decides whether an attendance check-in/check-out is created. Images and embeddings are not accepted by the ordinary recognition endpoint.

Never sign a kiosk in with a Super Admin account. Shared kiosks use a dedicated `MobileDevice`; personal employee apps use the normal Sanctum user token for self-service endpoints.

## Device authentication

```http
POST /api/v1/mobile/auth/login
Content-Type: application/json

{
  "identifier": "MOBILE-KIOSK-01",
  "secret": "KioskSecret123!"
}
```

The response contains a bearer token with only `recognition:submit`. Rotate the seeded secret before deployment and store the replacement in the operating system secure keystore.

## Recognition submission

```http
POST /api/v1/mobile/recognition-events
Authorization: Bearer <device-token>
X-Idempotency-Key: optional transport copy of eventUuid
```

```json
{
  "eventUuid": "98c4c2fa-5ec1-43bb-9848-cda7d29f21df",
  "personId": "EMP-00015",
  "confidence": 0.94,
  "livenessPassed": true,
  "occurredAt": "2026-08-15T08:31:22+03:00",
  "deviceIdentifier": "ignored-client-value",
  "location": "Main Entrance",
  "metadata": { "modelVersion": "mobile-face-v1" }
}
```

The backend replaces `deviceIdentifier` with the authenticated device identifier. `eventUuid` is unique. Retrying the same UUID returns the existing event and never repeats the attendance action.

Possible recognition statuses: `matched`, `unknown`, `low_confidence`, `rejected`, and `duplicate`. Verification statuses: `automatic`, `pending`, `verified`, and `rejected`. Unknown, low-confidence, and failed-liveness submissions create alerts and administrative notifications.

The server uses transactions, row locks, a unique person/date attendance constraint, and `duplicateRecognitionWindowSeconds` to guard concurrent requests. A valid first event checks in; a later valid event checks out; immediate repeats are stored as duplicate recognition traffic without changing attendance.

## Enrollment

1. An authorized dashboard admin records consent with `POST /people/{personCode}/biometric-consent`.
2. The admin creates an expiring request with `POST /people/{personCode}/enrollment-request`.
3. The mobile app receives the opaque 64-character token through an out-of-band handoff.
4. It reads `GET /mobile/enrollments/{token}`.
5. After capture and enrollment in the actual biometric service, it calls `POST /mobile/enrollments/{token}/complete` with a non-public `secureReference`, `modelVersion`, and non-sensitive metadata.

Only a SHA-256 token hash is stored. Tokens expire after 30 minutes and are single-use. `secureReference` is hidden from API Resources; it must identify encrypted private storage or the external biometric service, never a public URL.

## Personal app endpoints

Normal user tokens may call `GET /mobile/me`, `GET /mobile/schedule`, and `GET /mobile/attendance`. These endpoints derive the linked person from the token and do not accept another person identifier.

## Error handling

Retry network failures and `5xx` responses with exponential backoff while retaining the same `eventUuid`. Do not blindly retry `401`, `403`, `409`, or `422`. A `401` requires reauthentication; `403` means the token or actor is not allowed; `409` is a business conflict; `422` identifies invalid fields.

