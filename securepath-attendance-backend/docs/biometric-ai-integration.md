# Biometric AI Integration

## Responsibilities

The React dashboard captures exactly three supervised enrollment stills: `front`, `left`, and `right`. Laravel authenticates the administrator, enforces biometric permissions, stores samples privately, tracks enrollment state, calls the face AI service, stores the returned template internally, and writes audit logs. The Python service owns face detection, alignment, preprocessing, embedding generation, similarity calibration, matching, and liveness.

The dashboard camera is not an attendance camera. Daily attendance belongs to the future Flutter client and the Python AI service.

## Private sample storage

The `biometrics` Laravel disk points to `storage/app/private/biometrics`. A supervised session uses:

```text
employee_{database_id}/{enrollment_uuid}/front.jpg
employee_{database_id}/{enrollment_uuid}/left.jpg
employee_{database_id}/{enrollment_uuid}/right.jpg
```

Paths are encrypted in `face_samples.image_path`. There is no public route or storage symlink for this disk. Production deployments must encrypt the storage volume, restrict host access, define retention, and include biometric deletion in backup-retention procedures.

## Enrollment API

`POST /api/v1/people/{personCode}/biometric-enrollment` requires `biometrics.enroll`.

`POST /api/v1/people/{personCode}/biometric-reenrollment` requires `biometrics.reenroll` and an existing active profile.

Both accept `multipart/form-data` fields `front`, `left`, `right`, and optional accepted `consentAccepted`. Images must be JPEG or WebP and no larger than 5 MB each. Re-enrollment leaves the old profile untouched until the replacement AI response is valid.

`GET /api/v1/people/{personCode}/biometric-status` requires `biometrics.view`. It returns only status, readiness, sample count, model version, quality score, and timestamps. It never returns embeddings or private paths.

`DELETE /api/v1/people/{personCode}/biometric-data` requires `biometrics.delete`. It revokes the template, deletes private samples, clears sensitive profile data, and leaves the employee and audit history intact.

## Python enrollment contract

Configure Laravel with:

```dotenv
FACE_AI_SERVICE_URL=http://ai-service:8080
FACE_AI_SERVICE_TOKEN=
FACE_AI_SERVICE_TIMEOUT=45
```

Laravel calls `POST {FACE_AI_SERVICE_URL}/enroll` as multipart data with `personId`, `front`, `left`, and `right`. The expected success body is:

```json
{
  "success": true,
  "embedding": [0.012, -0.044],
  "embeddingDimension": 256,
  "modelVersion": "face-cnn-v1",
  "qualityScore": 0.93
}
```

The adapter rejects empty vectors, dimension mismatches, missing model versions, and unsuccessful responses. Embeddings are encrypted through the Laravel model cast and hidden from serialization. When the URL is absent, samples remain private and the session remains `pending`; Laravel does not invent an embedding or mark the employee enrolled.

If a Python container is added later, attach it to the same Compose network as `api`, expose it only inside that network, and set `FACE_AI_SERVICE_URL=http://ai-service:<port>`. No placeholder AI container is included now.

## Verification and identification

Future Flutter verification is 1:1: it supplies an expected person identifier and a live capture. Future shared-kiosk identification is 1:N. Both should be implemented through the same `FaceAiServiceInterface` boundary, with Python returning a calibrated similarity, threshold, liveness result, and model version. Laravel must accept a match only when the employee is active, the biometric profile is ready, similarity meets the backend/AI threshold, liveness passes, and attendance rules permit the action.

Flutter must never insert attendance rows directly. After successful AI verification, Laravel calls the existing `AttendanceService`. The current recognition pipeline now prevents automatic biometric attendance when `biometricReady` is false.

Conceptual verification result:

```json
{
  "matched": true,
  "personId": "EMP-00015",
  "similarity": 0.91,
  "threshold": 0.78,
  "livenessPassed": true,
  "modelVersion": "face-cnn-v1"
}
```

A similarity score alone is not identity. Threshold calibration and liveness remain Python/backend responsibilities, never React responsibilities.

## Failure modes

- Missing/invalid samples: Laravel returns structured `422` validation errors.
- Inactive employee, absent consent, or wrong workflow: Laravel returns structured `409` errors.
- Missing permission: Laravel returns `403`.
- AI unavailable or invalid AI response: the session becomes `failed`, Laravel returns `503`, and an old usable re-enrollment profile remains active.
- AI not configured: the session remains `pending`; a future worker or retry endpoint can process it once the Python service exists.

Administrative audit actions are `biometric.enrollment_started`, `biometric.enrollment_completed`, `biometric.enrollment_failed`, `biometric.reenrolled`, and `biometric.revoked`. Audit values never include templates or private paths.
