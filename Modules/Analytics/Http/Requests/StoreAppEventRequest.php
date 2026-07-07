<?php

declare(strict_types=1);

namespace Modules\Analytics\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Analytics\Enums\AppSource;
use Modules\Analytics\Enums\EventName;

class StoreAppEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Ingestion is public by design — auth (if any) is resolved from the
        // session/token in the controller, not gated here. See CLA-229: apps
        // must be able to emit pre-session events (e.g. a failed login).
        return true;
    }

    public function rules(): array
    {
        return [
            'event_name' => ['required', Rule::enum(EventName::class)],
            'app' => ['required', Rule::enum(AppSource::class)],
            'session_id' => ['required', 'string', 'max:64'],
            'entity_type' => ['nullable', 'string', 'max:100'],
            'entity_id' => ['nullable', 'string', 'max:100'],
            'properties' => ['nullable', 'array', $this->propertiesSizeRule()],
            'duration_ms' => ['nullable', 'integer', 'min:0'],
            'occurred_at' => ['nullable', 'date'],
        ];
    }

    // Free-form JSON with no size cap is an easy way to bloat app_events or
    // abuse the endpoint as a storage sink — cap it well above any real
    // event's needs (the biggest transversal event today carries a handful
    // of scalar keys).
    private function propertiesSizeRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if (is_array($value) && strlen(json_encode($value)) > 5000) {
                $fail('The properties payload may not exceed 5KB.');
            }
        };
    }
}
