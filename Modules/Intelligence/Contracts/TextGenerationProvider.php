<?php

declare(strict_types=1);

namespace Modules\Intelligence\Contracts;

use Modules\Intelligence\Exceptions\TextGenerationException;
use Modules\Intelligence\TextGeneration\TextGenerationRequest;
use Modules\Intelligence\TextGeneration\TextGenerationResult;

/** CLA-714: one AI provider bound to one model. */
interface TextGenerationProvider
{
    /** @throws TextGenerationException */
    public function generate(TextGenerationRequest $request): TextGenerationResult;
}
