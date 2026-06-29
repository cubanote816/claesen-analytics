<?php

namespace Modules\Employee\App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

class EmployeeModuleResumeResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'      => $this->id,
            'name'    => $this->name,
            'email'   => $this->email,
            'contact' => [
                'city'   => $this->city,
                'zip'    => $this->zip,
                'mobile' => $this->mobile,
            ],
            'profile' => [
                'avatar'     => $this->avatar_url ?? null,
                'birth_date' => $this->birth_date ? Carbon::parse($this->birth_date)->format('Y-m-d') : null,
                'age'        => $this->birth_date ? Carbon::parse($this->birth_date)->age : null,
            ],
            'status'   => [
                'is_active' => $this->fl_active,
            ],
        ];
    }
}

class EmployeeModuleResumeCollection extends ResourceCollection
{
    public function toArray($request): array
    {
        return [
            'data' => $this->collection,
            'meta' => [
                'current_page' => $this->resource->currentPage(),
                'last_page'    => $this->resource->lastPage(),
                'per_page'     => $this->resource->perPage(),
                'total'        => $this->resource->total(),
            ],
        ];
    }
}
