<?php

declare(strict_types=1);

namespace Modules\FieldOps\Http\Requests\Concerns;

use Illuminate\Validation\Validator;
use Modules\FieldOps\Services\FieldOpsTenantService;

trait ValidatesTenantScopedIds
{
    private function assertTenantScopedIds(Validator $validator, string $field, string $modelClass, int|array|null $value): void
    {
        if ($value === null || $validator->errors()->has($field)) {
            return;
        }

        $ids = collect(is_array($value) ? $value : [$value])
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return;
        }

        $scopedCount = app(FieldOpsTenantService::class)
            ->scopeForUser($modelClass::query(), $this->user(), $modelClass)
            ->whereIn('id', $ids)
            ->count();

        if ($scopedCount !== $ids->count()) {
            $validator->errors()->add($field, __('fieldops::resource.validation.out_of_tenant_scope'));
        }
    }
}
