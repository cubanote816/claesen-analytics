<?php

namespace Modules\Core\Services;

use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Auth\MultiFactor\Email\EmailAuthentication;
use Filament\Schemas\Components\Component;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * F2/CLA-464 of the multi-organization program — docs/ai/adr-multi-organization.md.
 *
 * "Reautenticación para cambio de rol, empresa, exportación y acciones
 * destructivas" — a fresh MFA challenge required immediately before a
 * sensitive Filament action runs, independent of and in addition to the
 * initial-login MFA already enforced by
 * Modules\Core\Http\Middleware\EnsureAdminRoleMultiFactorAuthenticationIsEnabled.
 *
 * Deliberately does NOT reimplement code verification — every component
 * returned by challengeFormComponents() is one of Filament's own
 * AppAuthentication/EmailAuthentication::getChallengeFormComponents(),
 * whose fields already carry a self-contained `->rule()` closure that
 * calls the real verifyCode() and fails the form field with Filament's own
 * translated message. Attaching these to any Filament Action's ->schema()
 * gets full, correct verification for free through the normal form
 * validation pipeline — no custom verify() method to get wrong.
 *
 * Scope decision (2026-09-21, explicit user choice via AskUserQuestion):
 * wired into role changes (Users), the CSV export, and GDPR erasure — the
 * three sensitive mutations this program actually built. NOT wired into
 * every Delete action across FieldOps/Safety/Catalogs/etc. — those already
 * have their own tenant-scoped authorization system (CLA-496), unrelated
 * to MFA, and blanket-wrapping dozens of existing resources was judged
 * disproportionate risk/effort for this ticket.
 */
class StepUpAuthenticator
{
    public function __construct(
        private readonly AppAuthentication $appAuthentication,
        private readonly EmailAuthentication $emailAuthentication,
    ) {}

    /**
     * False only when the user has neither factor configured — this should
     * not happen for super_admin/admin in a production environment (the
     * panel-wide setup-required redirect already forces one), but a step-up
     * action must never assume it and silently skip verification for a user
     * with nothing configured. Callers hide/disable the action entirely in
     * that case rather than let it through unchallenged.
     */
    public function isAvailableFor(Authenticatable $user): bool
    {
        return $this->appAuthentication->isEnabled($user) || $this->emailAuthentication->isEnabled($user);
    }

    /**
     * App authentication (TOTP) needs no round trip. Only send a fresh
     * e-mail code when e-mail is the user's only configured factor — call
     * this once, when the challenge modal is mounted, before the form (and
     * therefore the code input) is ever shown.
     */
    public function sendChallengeIfNeeded(Authenticatable $user): void
    {
        if ((! $this->appAuthentication->isEnabled($user)) && $this->emailAuthentication->isEnabled($user)) {
            $this->emailAuthentication->sendCode($user);
        }
    }

    /**
     * Exactly ONE provider's components — never both. Both Filament\Auth\
     * MultiFactor\App\AppAuthentication::getChallengeFormComponents() and
     * its Email counterpart key their code input identically
     * (OneTimeCodeInput::make('code')) — Filament's own login challenge
     * page never needs to combine them because a user only ever undertakes
     * ONE provider's challenge per login attempt, but this class calls
     * both providers directly, so concatenating them for a user who has
     * both factors enabled would silently produce two same-keyed
     * components in the same schema (the second overwriting the first in
     * both form state and the rendered DOM). Same preference as
     * sendChallengeIfNeeded(): app authentication (TOTP) needs no round
     * trip, so it wins when both are configured.
     *
     * @return array<Component>
     */
    public function challengeFormComponents(Authenticatable $user): array
    {
        if ($this->appAuthentication->isEnabled($user)) {
            return $this->appAuthentication->getChallengeFormComponents($user);
        }

        if ($this->emailAuthentication->isEnabled($user)) {
            return $this->emailAuthentication->getChallengeFormComponents($user);
        }

        return [];
    }

    /**
     * Prepends a fresh MFA challenge to $ownComponents — the schema an
     * action would otherwise use unguarded. Deliberately a plain array
     * builder rather than something that inspects/wraps an Action's
     * already-set schema (Filament's Action::schema() replaces its stored
     * closure rather than composing with a previous one — there is no
     * "append to what's already there" to hook into), so every call site
     * builds its own full schema through this one pass-through method
     * instead of fighting that API.
     *
     * @param  array<Component>  $ownComponents
     * @return array<Component>
     */
    public function guardedSchema(Authenticatable $user, array $ownComponents): array
    {
        if (! $this->isAvailableFor($user)) {
            // Should not happen for super_admin/admin in production (the
            // panel-wide setup-required redirect already forces a factor)
            // — never fabricate a pass, but also never hard-crash the
            // action for a misconfigured environment; the caller is
            // responsible for hiding/disabling the action when this is
            // false if it wants to enforce it strictly.
            return $ownComponents;
        }

        return [...$this->challengeFormComponents($user), ...$ownComponents];
    }
}
