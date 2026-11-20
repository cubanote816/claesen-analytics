<?php

declare(strict_types=1);

namespace Modules\Intelligence\Exceptions;

/**
 * CLA-714: an AI text generation call could not produce a usable result
 * (configuration, credentials, provider failure or invalid output).
 * Messages never contain the prompt or the provider's answer.
 */
class TextGenerationException extends \RuntimeException {}
