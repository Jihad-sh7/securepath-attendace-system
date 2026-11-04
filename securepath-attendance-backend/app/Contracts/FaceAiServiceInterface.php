<?php

namespace App\Contracts;

use App\Models\Person;
use Illuminate\Http\UploadedFile;

interface FaceAiServiceInterface
{
    public function isConfigured(): bool;

    /** @param array<string, string> $samplePaths */
    public function enroll(Person $person, array $samplePaths): array;

    /** @param array<int, array{personId: string, embedding: array<int, float>, modelVersion: string}> $candidates */
    public function identify(UploadedFile $image, array $candidates): array;
}
