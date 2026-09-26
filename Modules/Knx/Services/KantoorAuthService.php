<?php

namespace Modules\Knx\Services;

use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\User;
use Modules\Knx\Models\KnxEmployee;
use Modules\Knx\Support\KnxTenant;

/**
 * Who may sign into the office app, and as whom.
 *
 * Two conditions, both required, and they answer different questions:
 *
 *   1. the account carries the `knx_office` role → *app access*. This mirrors how
 *      the rest of the repo gates its apps (Client Portal asks for `client`,
 *      Sport for `technician`/`project_manager`), so access is revocable without
 *      touching data.
 *   2. the account is linked to an active `office` person → *identity*. The
 *      contract's `Session` is a person (name, initials, business role); without
 *      that row there is nothing to put in it.
 *
 * A Bertels employee is deliberately NOT a backoffice user: `knx_office` is
 * absent from User::hasPanelAccess(), so this role opens Kantoor and nothing else.
 *
 * The account's organization is checked HERE, unconditionally, and that is not
 * redundant with the `organization:electro-bertels` route middleware: that
 * middleware is a no-op while config('organizations.enforce') is false (the
 * default in every environment today), so on its own it would let a Claesen
 * account paired with a Bertels person row sign in and then be refused by every
 * subsequent call. For a single-tenant app like this one there is no reason to
 * depend on a flag being flipped, so the tenant rule is enforced in the module.
 *
 * Every failure answers the same way (one ValidationException on `email`), so the
 * endpoint cannot be used to find out which accounts exist or which of the two
 * conditions failed.
 */
class KantoorAuthService
{
    /**
     * @return array{0: User, 1: KnxEmployee}|null
     */
    public function attempt(string $email, string $password): ?array
    {
        $user = User::query()->where('email', $email)->first();

        // Hash::check runs on a dummy when the user does not exist so a missing
        // account and a wrong password take the same time (no timing oracle).
        $passwordMatches = $user !== null
            ? Hash::check($password, $user->password)
            : Hash::check($password, '$2y$12$'.str_repeat('0', 53));

        if ($user === null || ! $passwordMatches) {
            return null;
        }

        $employee = $this->authorize($user);

        return $employee === null ? null : [$user, $employee];
    }

    /**
     * The single answer to "may this account use Kantoor, and as whom".
     *
     * Used by login, by /me/session and by refresh, so a token cannot outlive the
     * right it was issued for: deactivating the account, removing the role or
     * unlinking the person all invalidate an existing token at the next call.
     *
     * @return KnxEmployee|null the office person behind the account
     */
    public function authorize(User $user): ?KnxEmployee
    {
        if (! $user->is_active || ! $user->hasRole('knx_office')) {
            return null;
        }

        // Strict on purpose: no super_admin exception. The multi-organization ADR
        // is explicit that even a super_admin works inside one company, and the
        // office app must never show Claesen's site data to a Bertels login.
        if ($user->organization_id !== KnxTenant::organizationId()) {
            return null;
        }

        return $this->officeEmployee($user);
    }

    /**
     * The office person behind an account, in this tenant. Null when the account
     * has no person row (or it is a field technician, or it is inactive).
     */
    public function officeEmployee(User $user): ?KnxEmployee
    {
        return KnxEmployee::query()
            ->where('user_id', $user->id)
            ->where('organization_id', KnxTenant::organizationId())
            ->office()
            ->active()
            ->first();
    }

    /** The single failure shape of the login endpoint. */
    public function fail(): never
    {
        throw ValidationException::withMessages([
            'email' => __('knx::auth.failed'),
        ]);
    }
}
