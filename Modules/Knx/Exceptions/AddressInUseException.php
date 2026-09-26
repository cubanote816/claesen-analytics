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
    public function __construct(private readonly string $address, ?string $message = null)
    {
        parent::__construct($message ?? "The address [{$address}] is already in use.");
    }

    public function errorCode(): string
    {
        return 'address_in_use';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }

    public function errors(): array
    {
        return ['proposal' => [$this->getMessage()]];
    }

    public function address(): string
    {
        return $this->address;
    }
}
