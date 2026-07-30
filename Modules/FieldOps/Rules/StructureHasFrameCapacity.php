<?php

declare(strict_types=1);

namespace Modules\FieldOps\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Modules\FieldOps\Models\Structure;

class StructureHasFrameCapacity implements ValidationRule
{
    public function __construct(private readonly ?int $excludingFrameId = null)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $structure = Structure::find($value);

        if ($structure && ! $structure->hasLuminaireFrameCapacity($this->excludingFrameId)) {
            $fail(__('fieldops::resource.luminaire_frames.validation.structure_capacity_exceeded'));
        }
    }
}
