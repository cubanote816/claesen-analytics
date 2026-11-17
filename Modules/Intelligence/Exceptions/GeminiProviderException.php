<?php

declare(strict_types=1);

namespace Modules\Intelligence\Exceptions;

/**
 * CLA-694: the Gemini provider could not produce a usable response
 * (no token, HTTP failure, transport error, or non-JSON answer).
 */
class GeminiProviderException extends \RuntimeException {}
