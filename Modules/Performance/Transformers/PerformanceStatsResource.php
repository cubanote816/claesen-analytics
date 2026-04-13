<?php
 
namespace Modules\Performance\Transformers;
 
use Illuminate\Http\Resources\Json\JsonResource;
 
class PerformanceStatsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        return [
            'period' => $this->resource['period'] ?? 'unknown',
            'hours' => round($this->resource['hours'] ?? 0, 2),
            'achievement_percentage' => round($this->resource['achievement_rate'] ?? 0, 2),
            'meta' => [
                'date' => $this->resource['date'] ?? null,
                'start' => $this->resource['start'] ?? null,
                'end' => $this->resource['end'] ?? null,
                'month' => $this->resource['month'] ?? null,
            ]
        ];
    }
}
