<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Users\Concerns;

use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Core\Models\User;
use Modules\Core\Services\PasswordResetService;
use Modules\Core\Services\StepUpAuthenticator;
use Spatie\Permission\Models\Role;

/**
 * The account actions of a Users edit page (change roles with step-up, resend the
 * activation email, revoke sessions), extracted from Claesen's EditUser (CLA-704) so
 * the Electro Bertels Users section runs the very same implementations. The defaults
 * below are Claesen's behaviour, unchanged; a panel narrows them by overriding the hooks.
 */
trait ManagesUserAccountActions
{
    /**
     * Role names this page may assign. Null = every role (Claesen). A list is both the
     * set the form offers and the server-side allowlist the action enforces.
     *
     * @return array<int, string>|null
     */
    protected function assignableRoleNames(): ?array
    {
        return null;
    }

    /**
     * Extra eligibility check for "resend activation email", on top of Claesen's rule
     * (active, not Microsoft-linked, no completed password setup).
     */
    protected function canResendActivationTo(User $record): bool
    {
        return true;
    }

    /** Panel whose reset page the activation link opens. */
    protected function activationPanelId(): string
    {
        return 'admin';
    }

    /**
     * F2/CLA-464: "Reautenticación para cambio de rol" — roles are no
     * longer part of the general Edit form (see UserForm's own docblock);
     * this is the only way to change them, and it requires a fresh MFA
     * challenge on top of the panel's own session-level enforcement.
     */
    protected function changeRolesAction(): Action
    {
        return Action::make('changeRoles')
            ->label(__('users/resource.actions.change_roles'))
            ->icon('heroicon-o-shield-check')
            ->mountUsing(function (): void {
                if ($user = auth()->user()) {
                    app(StepUpAuthenticator::class)->sendChallengeIfNeeded($user);
                }
            })
            ->schema(function (): array {
                /** @var User $record */
                $record = $this->getRecord();
                $authenticator = app(StepUpAuthenticator::class);

                return $authenticator->guardedSchema(auth()->user(), [
                    CheckboxList::make('roles')
                        ->label(__('users/resource.fields.roles'))
                        ->options(fn () => Role::query()
                            ->when($this->assignableRoleNames() !== null, fn ($query) => $query->whereIn('name', $this->assignableRoleNames()))
                            ->orderBy('sort')
                            ->pluck('name', 'id')
                            ->mapWithKeys(fn ($name, $id) => [$id => Str::headline($name)]))
                        ->default(fn () => $record->roles->pluck('id')->all())
                        ->columns(2)
                        ->gridDirection('row'),
                ]);
            })
            ->action(function (array $data): void {
                /** @var User $record */
                $record = $this->getRecord();
                $roleIds = $data['roles'] ?? [];

                // CLA-704: server-side allowlist, independent of the options the form
                // offered — a posted id outside it never reaches syncRoles().
                $allowed = $this->assignableRoleNames();
                abort_if(
                    $allowed !== null
                        && Role::query()->whereKey($roleIds)->whereNotIn('name', $allowed)->exists(),
                    422,
                    'One or more selected roles cannot be assigned here.',
                );

                DB::transaction(function () use ($record, $roleIds): void {
                    $record->syncRoles($roleIds);
                });

                Notification::make()
                    ->title(__('users/resource.actions.change_roles_saved'))
                    ->success()
                    ->send();
            });
    }

    /**
     * CLA-685: re-emails the first-password link to an account that is still waiting
     * for it (active, local, no password yet) — the 72-hour link may have expired or
     * been lost. Issuing a new code replaces the stored hash, so the old link dies.
     */
    protected function resendActivationAction(): Action
    {
        return Action::make('resendActivationEmail')
            ->label(__('users/resource.actions.resend_activation'))
            ->icon('heroicon-o-envelope')
            ->requiresConfirmation()
            ->modalHeading(__('users/resource.actions.resend_activation_confirm_title'))
            ->modalDescription(__('users/resource.actions.resend_activation_confirm_body'))
            ->visible(function (): bool {
                /** @var User $record */
                $record = $this->getRecord();

                return $record->is_active
                    && $record->microsoft_id === null
                    && ! $record->hasCompletedPasswordSetup()
                    && $this->canResendActivationTo($record);
            })
            ->action(function (): void {
                /** @var User $record */
                $record = $this->getRecord();

                app(PasswordResetService::class)->sendActivationEmail($record, $this->activationPanelId());

                Notification::make()
                    ->title(__('users/resource.actions.resend_activation_done'))
                    ->success()
                    ->send();
            });
    }

    /**
     * F2/CLA-464: "Sesiones revocables" — deletes every row in the
     * `sessions` table for this user (session.driver = database), which
     * invalidates them immediately on their next request. No step-up here
     * on purpose: this is itself the incident-response action (e.g. a
     * suspected compromised account) and should not be slowed down by an
     * extra challenge.
     */
    protected function revokeSessionsAction(): Action
    {
        return Action::make('revokeSessions')
            ->label(__('users/resource.actions.revoke_sessions'))
            ->icon('heroicon-o-no-symbol')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(__('users/resource.actions.revoke_sessions_confirm_title'))
            ->modalDescription(__('users/resource.actions.revoke_sessions_confirm_body'))
            ->visible(fn (): bool => config('session.driver') === 'database')
            ->action(function (): void {
                /** @var User $record */
                $record = $this->getRecord();

                DB::table(config('session.table', 'sessions'))
                    ->where('user_id', $record->getKey())
                    ->delete();

                Notification::make()
                    ->title(__('users/resource.actions.revoke_sessions_done'))
                    ->success()
                    ->send();
            });
    }
}
