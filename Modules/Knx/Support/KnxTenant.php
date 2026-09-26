<?php

namespace Modules\Knx\Support;

use Modules\Core\Models\Organization;
use RuntimeException;

/**
 * The tenant of the KNX domain.
 *
 * Every table in this module carries an `organization_id` and every route sits
 * behind `organization:{slug}` (Modules\Core\Http\Middleware\RequireOrganization),
 * so there is exactly one answer to "whose data is this" — and it is Electro
 * Bertels. Resolving it in one place is what keeps seeders, the future
 * employee-provisioning command and tests from each hard-coding a slug.
 */
final class KnxTenant
{
    public static function slug(): string
    {
        return (string) config('knx.organization_slug');
    }

    /**
     * @throws RuntimeException when Electro Bertels has not been created yet.
     *                          That is deliberate: KNX data without its
     *                          organization would be orphaned, so callers must
     *                          fail loudly instead of falling back to Claesen.
     */
    public static function organization(): Organization
    {
        $organization = Organization::query()->where('slug', self::slug())->first();

        if ($organization === null) {
            throw new RuntimeException(
                "The KNX tenant organization [".self::slug()."] does not exist. "
                .'It is created at P7 (and locally by core:qa-reset-environment); '
                .'no KNX row may be written before it.'
            );
        }

        return $organization;
    }

    public static function organizationId(): int
    {
        return self::organization()->id;
    }
}
