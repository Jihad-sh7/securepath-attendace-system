# SecurePath Biometric Integration

## Architecture

The React dashboard captures three consented enrollment photos and sends them to Laravel. The Flutter kiosk captures a daily attendance photo and sends it to Laravel. Neither client calls Python or receives embeddings. Laravel authenticates and authorizes the caller, sends private image/template data to the internal FastAPI service, then applies the existing attendance rules and persists MySQL records.

## Services

- Laravel API: `http://localhost:8000`
- Face AI/OpenAPI: `http://localhost:8001/docs`
- Face AI health: `http://localhost:8001/health`
- MySQL host port: `3307` (`3306` inside Compose)

Run the stack with `docker compose up -d --build`. The first AI startup downloads `buffalo_l` into the persistent `insightface-models` volume. Use `docker compose ps` and `docker compose logs ai-service` to inspect readiness.

## Enrollment

An authorized admin captures front, slight-left, and slight-right samples in the existing dashboard. Laravel stores samples on the private `biometrics` disk, calls `/enroll`, encrypts the normalized embedding in `biometric_profiles.embedding`, and records the exact `model_version`. Re-enrollment replaces the template only after successful processing. Revocation deletes samples and clears the template.

Enrollment is synchronous. A successful response therefore always includes `status=enrolled` and a persisted embedding. AI or image-processing failures set the new profile/session to `failed`; they are never left permanently pending. Authorized admins can use `POST /api/v1/people/{person}/biometric-enrollment/retry` or the dashboard's **Retry Enrollment** action to process the existing private samples without recreating the employee.

## Kiosk

The development device seeded by Laravel is `MOBILE-KIOSK-01` with secret `KioskSecret123!`. Change this credential outside local development. Flutter exchanges it at `/api/v1/mobile/auth/login`, stores the short-lived bearer token in platform secure storage, and uploads only the captured image, requested operation, timestamp, and idempotency UUID to `/api/v1/mobile/attendance/recognize`.

Laravel selects active enrolled templates, asks Python to identify the best cosine match, records the recognition event, and delegates the actual check-in/check-out mutation to the existing recognition/attendance services. Unknown faces are rejected and sent to the existing review/alert flow. Captured kiosk images are temporary and are deleted by Flutter after the response.

## Calibration And Liveness

`FACE_AI_MATCH_THRESHOLD=0.45` is explicitly a development starting value. It must be calibrated with representative, consented images from the real cameras and environment before production. Image-quality checks are not anti-spoofing. This implementation stores `liveness=not_implemented` metadata and does not claim a liveness pass. Add a validated anti-spoofing provider before relying on liveness for security decisions.

## Configuration

- `FACE_AI_SERVICE_URL`: Laravel-to-Python private URL.
- `FACE_AI_SERVICE_TOKEN`: optional shared internal bearer token; set the same value for both services.
- `FACE_AI_SERVICE_TIMEOUT`: Laravel request timeout in seconds.
- `FACE_AI_MODEL_NAME`: defaults to `buffalo_l`.
- `FACE_AI_EXECUTION_PROVIDER`: defaults to CPU; use CUDA only with a compatible runtime/image.
- `FACE_AI_MATCH_THRESHOLD`: calibrated cosine threshold.
- `FACE_AI_MINIMUM_*`: image detection, size, and blur gates.

## Failure Handling

Unreadable, missing, multiple, small, or blurry faces return structured 422 codes. Connectivity/model failures return `FACE_AI_UNAVAILABLE` with HTTP 503. Laravel never falls back to accepting a client-supplied identity. Repeated `eventUuid` values are idempotent. Existing MySQL and model-cache volumes remain persistent; no reset or fresh migration is part of startup.

The API container launches Laravel's built-in router directly from `public/`. Do not replace that Compose command with `php artisan serve` in this image: its spawned child process did not inherit `FACE_AI_SERVICE_URL`, which caused HTTP enrollment requests to skip AI processing even though Artisan CLI commands could reach the service.

## Verification

```text
docker compose exec api php artisan test
docker compose exec ai-service pytest -q
curl http://localhost:8001/health
curl http://localhost:8001/model-info
```

Run `flutter analyze` and `flutter test` in the kiosk project. Run the dashboard's existing build/test commands in its own project.

## Licensing

Review InsightFace's current source-code and pretrained-model licensing before commercial deployment. Upstream has historically licensed code and pretrained model packs differently; do not assume that the code license alone grants production rights to `buffalo_l` weights.
