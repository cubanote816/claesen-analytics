<?php

namespace Modules\Employee\App\Http\Resources\Projects;

use Illuminate\Http\Resources\Json\JsonResource;

class ProjectDetailResource extends JsonResource
{
    public function toArray($request): array
    {
        if ($this->resource === null) {
            return ['project' => [], 'invoices' => [], 'employees' => []];
        }

        if (is_array($this->resource) && isset($this->resource['project'])) {
            return [
                'project'   => $this->resource['project'],
                'invoices'  => $this->resource['invoices'] ?? [],
                'employees' => $this->resource['employees'] ?? [],
            ];
        }

        $invoices  = $this->resource->relationLoaded('invoices') ? $this->resource->invoices : collect();
        $employees = $this->resource->relationLoaded('employees') ? $this->resource->employees : collect();

        return [
            'project'   => new ProjectResource($this->resource),
            'invoices'  => InvoiceResource::collection($invoices),
            'employees' => EmployeeHourResource::collection($employees),
        ];
    }
}
