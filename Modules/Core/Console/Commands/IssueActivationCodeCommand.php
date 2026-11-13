<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Filament\Facades\Filament;
use Illuminate\Console\Command;
use Modules\Core\Models\User;
use Modules\Core\Services\PasswordResetService;

/**
 * CLA-653 — closes the gap USR-001 left open.
 *
 * An admin creates a backoffice account from the Users resource and it is stored
 * with `password = null, password_set_at = null` (CreateUser::mutateFormDataBeforeCreate),
 * waiting for its owner to set a password with a one-time activation code. Nothing
 * issued that code: `PasswordResetService::sendResetLink()` refuses any account that
 * has not completed setup, which is exactly an account that never had a password. So
 * a freshly created non-Azure account could never sign in, and "forgot password"
 * turned it away too.
 *
 * This hands an operator the code for such an account. It does NOT create accounts:
 * internal ones are still created from a real, active employee with a corporate
 * address, and nothing here bypasses that check.
 *
 * The code is printed rather than emailed on purpose — the account being provisioned
 * may not have a working mailbox yet, and whoever runs this already has server access.
 * Treat the output as a credential: it is one-time, but until it is used or expires it
 * is enough to set that account's password.
 */
class IssueActivationCodeCommand extends Command
{
    protected $signature = 'core:issue-activation-code
        {email : The address of the account that needs to set its first password}
        {--panel=admin : Which panel the reset link should point at}';

    protected $description = 'Issue the one-time activation code a provisioned account needs to set its first password.';

    public function handle(PasswordResetService $passwordReset): int
    {
        $email = strtolower(trim((string) $this->argument('email')));

        // Matched the way the Users resource stores and checks addresses.
        $matches = User::query()->whereRaw('LOWER(TRIM(email)) = ?', [$email])->limit(2)->get();

        // Stored addresses differing only by case or spaces would make the match a guess;
        // a credential must never go to the wrong account, so refuse instead of picking one.
        if ($matches->count() > 1) {
            $this->error("[{$email}] matches more than one account. Fix the duplicate addresses first.");

            return self::FAILURE;
        }

        $user = $matches->first();

        if (! $user instanceof User) {
            $this->error("No account found for [{$email}].");

            return self::FAILURE;
        }

        if (! $user->is_active) {
            $this->error("[{$email}] is deactivated. Reactivate it before handing out a code.");

            return self::FAILURE;
        }

        if ($user->microsoft_id !== null) {
            $this->error("[{$email}] signs in with Microsoft. Those accounts authenticate through Entra and never hold a local password.");

            return self::FAILURE;
        }

        if ($user->hasCompletedPasswordSetup()) {
            $this->error("[{$email}] already has a password. Use the password reset flow on the login screen instead.");

            return self::FAILURE;
        }

        $panelId = (string) $this->option('panel');
        $panel = Filament::getPanels()[$panelId] ?? null;

        if ($panel === null) {
            $this->error("Unknown panel [{$panelId}]. Available: ".implode(', ', array_keys(Filament::getPanels())).'.');

            return self::FAILURE;
        }

        $code = $passwordReset->issueActivationCode($user);

        $this->info("Activation code issued for {$user->email}.");
        $this->newLine();
        $this->line('Code: '.$code);
        $this->line('Link: '.$panel->getResetPasswordUrl($code, $user));
        $this->newLine();
        $this->warn(sprintf(
            'One-time use, valid for %d minutes. Anyone holding it can set this account\'s password — send it over a private channel.',
            PasswordResetService::CODE_TTL_MINUTES,
        ));

        return self::SUCCESS;
    }
}
