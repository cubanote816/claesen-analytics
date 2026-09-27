<?php

namespace Modules\Knx\Exceptions;

use Symfony\Component\HttpFoundation\Response;

/**
 * The proposed address of a conflict is already taken (docs/BACKEND-API.md §4.5).
 *
 * The client already warns about this before submitting, but the backend is the
 * authority: `takenAddresses` is advisory, this is the rule. Two conflicts can
 * exist on the same address (that is the point of the module); a *proposal* may
 * not collide with a registered address.
 */
final class AddressInUseException extends KnxApiException
{
    /**
     * @param  array<string, mixed>  $context  extra top-level keys of the body
     */
    public function __construct(
        private readonly string $address,
        ?string $message = null,
        private readonly array $context = [],
    ) {
        parent::__construct($message ?? "The address [{$address}] is already in use.");
    }

    /**
     * The field app's shape: the device standing in the way travels next to `code`,
     * because the app shows "1.1.99 is already taken by …" without a second call.
     *
     * @param  array<string, mixed>  $existing  a rendered `FieldDevice`
     */
    public static function forFieldRegistration(string $address, array $existing): self
    {
        return new self($address, 'Address already in use', ['existing' => $existing]);
    }

    public function errorCode(): string
    {
        return 'address_in_use';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }

    /**
     * One code, two shapes, because the two callers read different keys:
     *
     *   - the office's `PATCH /conflicts/{id}` validates a *proposal*, so it reads
     *     `errors.proposal`;
     *   - the field's `POST …/devices` reports a *collision*, so it reads
     *     `details.existing` — and the field client builds `details` from `errors`
     *     (`data.errors ?? payload`), so the device has to be inside `errors` to be
     *     found at all. It is also sent at the top level, which is where the field
     *     contract's own example puts it.
     */
    public function errors(): array
    {
        return $this->context === []
            ? ['proposal' => [$this->getMessage()]]
            : $this->context;
    }

    public function context(): array
    {
        return $this->context;
    }

    public function address(): string
    {
        return $this->address;
    }
}
