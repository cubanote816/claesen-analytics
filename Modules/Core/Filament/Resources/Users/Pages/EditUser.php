<?php

namespace Modules\Core\Filament\Resources\Users\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Core\Filament\Resources\Users\UserResource;
use Modules\Core\Models\User;
use Modules\Core\Services\StepUpAuthenticator;
use Spatie\Permission\Models\Role;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->changeRolesAction(),
            $this->revokeSessionsAction(),
            DeleteAction::make(),
        ];
    }

    /**
     * F2/CLA-464: "Reautenticación para cambio de rol" — roles are no
     * longer part of the general Edit form (see UserForm's own docblock);
     * this is the only way to change them, and it requires a fresh MFA
     * challenge on top of the panel's own session-level enforcement.
     */
    private function changeRolesAction(): Action
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

                DB::transaction(function () use ($record, $data): void {
                    $record->syncRoles($data['roles'] ?? []);
                });

                Notification::make()
                    ->title(__('users/resource.actions.change_roles_saved'))
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
    private function revokeSessionsAction(): Action
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
