<?php

namespace Modules\Knx\Exceptions;

use RuntimeException;

/**
 * Base for the KNX API's own failures: they carry the contract's `code` and the
 * HTTP status, and ApiErrorEnvelope renders them without guessing.
 */
abstract class KnxApiException extends RuntimeException
{
    /**
     * The `code` of docs/BACKEND-API.md §2.5 (e.g. "address_in_use").
     */
    abstract public function errorCode(): string;

    public function status(): int
    {
        return 400;
    }

    /**
     * Field errors, validation-style. Empty for everything that is not a
     * validation failure.
     *
     * @return array<string, array<int, string>>
     */
    public function errors(): array
    {
        return [];
    }

    /**
     * Extra keys the contract puts on the error body itself, next to `code`.
     *
     * Almost every failure is `{message, code, errors}`. One documented shape is not:
     * the field app's `address_in_use` carries the device that is in the way as
     * `existing` beside `code`, because the app shows it directly.
     *
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return [];
    }
}
