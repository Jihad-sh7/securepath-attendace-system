<?php

namespace App\Services;

use App\Models\Person;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use SplFileObject;
use Throwable;

class PeopleImportService
{
    private const REQUIRED_HEADERS = ['person_code', 'first_name', 'last_name', 'email', 'type'];

    public function __construct(private readonly PeopleService $people) {}

    public function import(UploadedFile $file, bool $updateExisting = false): array
    {
        $csv = new SplFileObject($file->getRealPath());
        $csv->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);
        $rawHeaders = $csv->fgetcsv();
        if (! is_array($rawHeaders)) {
            return ['created' => 0, 'updated' => 0, 'failed' => 1, 'errors' => [['row' => 1, 'message' => 'The CSV file is empty.']]];
        }
        $headers = array_map(fn ($header) => strtolower(trim((string) $header)), $rawHeaders);
        $missing = array_diff(self::REQUIRED_HEADERS, $headers);
        if ($missing) {
            return ['created' => 0, 'updated' => 0, 'failed' => 1, 'errors' => [['row' => 1, 'message' => 'Missing required headers: '.implode(', ', $missing)]]];
        }

        $summary = ['created' => 0, 'updated' => 0, 'failed' => 0, 'errors' => []];
        foreach ($csv as $index => $values) {
            if (! is_array($values) || count(array_filter($values, fn ($value) => $value !== null && $value !== '')) === 0) continue;
            $row = array_combine($headers, array_slice(array_pad($values, count($headers), null), 0, count($headers)));
            $existing = Person::withTrashed()->where('person_code', $row['person_code'])->first();
            $validator = Validator::make($row, ['person_code' => ['required', 'string'], 'first_name' => ['required', 'string'], 'last_name' => ['required', 'string'], 'email' => ['nullable', 'email', Rule::unique('people', 'email')->ignore($existing?->id)], 'type' => ['required', 'in:employee,student,staff,contractor']]);
            if ($validator->fails()) {
                $summary['failed']++;
                $summary['errors'][] = ['row' => $index + 2, 'errors' => $validator->errors()->toArray()];
                continue;
            }
            if ($existing && ! $updateExisting) {
                $summary['failed']++;
                $summary['errors'][] = ['row' => $index + 2, 'message' => 'Duplicate person_code.'];
                continue;
            }
            $payload = ['personCode' => $row['person_code'], 'firstName' => $row['first_name'], 'lastName' => $row['last_name'], 'email' => $row['email'] ?: null, 'phone' => $row['phone'] ?? null, 'type' => $row['type'], 'jobTitle' => $row['job_title'] ?? null];
            try {
                if ($existing) {
                    if ($existing->trashed()) $existing->restore();
                    $this->people->update($existing, $payload);
                    $summary['updated']++;
                } else {
                    $this->people->create($payload);
                    $summary['created']++;
                }
            } catch (Throwable $exception) {
                $summary['failed']++;
                $summary['errors'][] = ['row' => $index + 2, 'message' => 'The row could not be saved.', 'detail' => config('app.debug') ? $exception->getMessage() : null];
            }
        }
        return $summary;
    }
}
