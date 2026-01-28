<?php

namespace App\DTOs;

class ProjectAiPayload
{
    public string $projectId;
    public array $data;
    public string $hash;

    public function __construct(string $projectId, array $data)
    {
        $this->projectId = $projectId;
        $this->data = $this->sanitize($data);
        $this->hash = $this->generateHash();
    }

    /**
     * Remove nulls and empty strings to save tokens and ensure hygiene.
     */
    private function sanitize(array $data): array
    {
        return array_filter($data, function ($value) {
            return !is_null($value) && $value !== '';
        });
    }

    /**
     * Generate a semantic MD5 hash of the data.
     */
    private function generateHash(): string
    {
        // Sort keys to ensure consistent hashing
        ksort($this->data);
        return md5(json_encode($this->data));
    }

    public function toArray(): array
    {
        return [
            'project_id' => $this->projectId,
            'data' => $this->data,
            'hash' => $this->hash,
        ];
    }
}
