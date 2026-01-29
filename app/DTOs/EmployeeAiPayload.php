<?php

namespace App\DTOs;

class EmployeeAiPayload
{
    public string $employeeId;
    public array $data;
    public string $hash;

    public function __construct(string $employeeId, array $data)
    {
        $this->employeeId = $employeeId;
        $this->data = $this->cleanData($data);
        $this->hash = $this->generateHash();
    }

    /**
     * Clean and normalize data for AI.
     */
    protected function cleanData(array $data): array
    {
        return array_filter($data, function ($value) {
            return !is_null($value) && $value !== '';
        });
    }

    /**
     * Generate a semantic hash of the data.
     */
    protected function generateHash(): string
    {
        return md5(serialize($this->data));
    }

    /**
     * Convert payload to array for API transport.
     */
    public function toArray(): array
    {
        return [
            'employee_id' => $this->employeeId,
            'performance_data' => $this->data,
        ];
    }
}
