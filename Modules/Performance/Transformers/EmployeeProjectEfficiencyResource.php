<?php
 
namespace Modules\Performance\Transformers;
 
use Illuminate\Http\Resources\Json\JsonResource;
 
class EmployeeProjectEfficiencyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        return [
            'employee_id' => $this->resource['employee_id'],
            'name' => $this->resource['name'],
            'efficiency_score' => round($this->resource['avg_project_efficiency'] ?? 0, 2),
            'ai_profile' => [
                'archetype' => $this->resource['archetype'],
                'icon' => $this->resource['icon'],
            ]
        ];
    }
}
