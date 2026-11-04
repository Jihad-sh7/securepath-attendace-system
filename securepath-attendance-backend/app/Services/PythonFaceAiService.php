<?php

namespace App\Services;

use App\Contracts\FaceAiServiceInterface;
use App\Models\Person;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class PythonFaceAiService implements FaceAiServiceInterface
{
    public function isConfigured(): bool
    {
        return filled(config('services.face_ai.url'));
    }

    public function enroll(Person $person, array $samplePaths): array
    {
        if (! $this->isConfigured()) throw new RuntimeException('The face AI service is not configured.');

        $request = Http::acceptJson()->timeout((int) config('services.face_ai.timeout', 45));
        if (filled(config('services.face_ai.token'))) $request = $request->withToken(config('services.face_ai.token'));

        foreach (['front', 'left', 'right'] as $pose) {
            $path = $samplePaths[$pose] ?? null;
            if (! $path || ! Storage::disk('biometrics')->exists($path)) throw new RuntimeException("The {$pose} enrollment sample is unavailable.");
            $request = $this->attach($request, $pose, $path);
        }

        $response = $request->post(rtrim(config('services.face_ai.url'), '/').'/enroll', ['personId' => $person->person_code]);
        Log::info('Face AI enrollment response received.', [
            'personId' => $person->person_code,
            'httpStatus' => $response->status(),
            'aiCode' => $response->json('code'),
            'modelVersion' => $response->json('modelVersion'),
            'sampleCount' => $response->json('sampleCount'),
        ]);
        if ($response->status() === 422) throw new \App\Exceptions\FaceProcessingException((string) ($response->json('message') ?: 'The face images could not be processed.'), (string) ($response->json('code') ?: 'FACE_NOT_PROCESSABLE'));
        if (! $response->successful() || ! (bool) $response->json('success')) throw new RuntimeException($response->json('message') ?: 'The face AI service could not process the enrollment.');

        $embedding = $response->json('embedding');
        $dimension = (int) $response->json('embeddingDimension');
        $modelVersion = $response->json('modelVersion');
        if (! is_array($embedding) || $dimension < 1 || count($embedding) !== $dimension || ! is_string($modelVersion) || $modelVersion === '') {
            throw new RuntimeException('The face AI service returned an invalid enrollment template.');
        }

        return ['embedding' => array_map('floatval', $embedding), 'embeddingDimension' => $dimension, 'modelVersion' => $modelVersion, 'qualityScore' => $response->json('qualityScore')];
    }

    public function identify(UploadedFile $image, array $candidates): array
    {
        if (! $this->isConfigured()) throw new \App\Exceptions\FaceAiUnavailableException('The face AI service is not configured.');

        try {
            $request = $this->request()->attach('image', fopen($image->getRealPath(), 'rb'), $image->getClientOriginalName(), [
                'Content-Type' => $image->getMimeType() ?: 'application/octet-stream',
            ]);
            $response = $request->post(rtrim(config('services.face_ai.url'), '/').'/identify', [
                'candidates' => json_encode($candidates, JSON_THROW_ON_ERROR),
            ]);
            Log::info('Face AI identify response received.', [
                'httpStatus' => $response->status(),
                'candidateCount' => count($candidates),
                'matched' => $response->json('matched'),
                'personId' => $response->json('personId'),
                'confidence' => $response->json('confidence'),
                'threshold' => $response->json('threshold'),
                'modelVersion' => $response->json('modelVersion'),
                'aiCode' => $response->json('code'),
            ]);
        } catch (\App\Exceptions\FaceAiUnavailableException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new \App\Exceptions\FaceAiUnavailableException('The face recognition service could not be reached.', previous: $exception);
        }

        if ($response->status() === 422) {
            $code = (string) ($response->json('code') ?: 'FACE_NOT_PROCESSABLE');
            throw new \App\Exceptions\FaceProcessingException((string) ($response->json('message') ?: 'The face image could not be processed.'), $code);
        }
        if (! $response->successful() || ! $response->json('success')) {
            throw new \App\Exceptions\FaceAiUnavailableException('The face recognition service returned an invalid response.');
        }

        return [
            'matched' => (bool) $response->json('matched'),
            'personId' => $response->json('personId'),
            'confidence' => (float) $response->json('confidence'),
            'threshold' => (float) $response->json('threshold'),
            'modelVersion' => (string) $response->json('modelVersion'),
            'qualityScore' => (float) $response->json('qualityScore'),
        ];
    }

    private function request(): PendingRequest
    {
        $request = Http::acceptJson()->timeout((int) config('services.face_ai.timeout', 45));
        return filled(config('services.face_ai.token')) ? $request->withToken(config('services.face_ai.token')) : $request;
    }

    private function attach(PendingRequest $request, string $pose, string $path): PendingRequest
    {
        $stream = Storage::disk('biometrics')->readStream($path);
        if (! is_resource($stream)) throw new RuntimeException("The {$pose} enrollment sample could not be read.");
        return $request->attach($pose, $stream, basename($path), ['Content-Type' => Storage::disk('biometrics')->mimeType($path) ?: 'application/octet-stream']);
    }
}
