<?php

declare(strict_types=1);

namespace App\Filament\Bertels\Resources\Users\Pages;

use App\Filament\Bertels\Resources\Users\BertelsUserResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;
use Modules\Core\Filament\Resources\Users\Concerns\ManagesUserAccountActions;
use Modules\Core\Models\User;

/**
 * CLA-704 S5: Claesen's account actions (change roles with step-up, resend activation,
 * revoke sessions), narrowed to the Bertels forbidden-role list and the /bertels reset link.
 */
class EditBertelsUser extends EditRecord
{
    use ManagesUserAccountActions;

    protected static string $resource = BertelsUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->changeRolesAction(),
            $this->resendActivationAction(),
            $this->revokeSessionsAction(),
            DeleteAction::make(),
        ];
    }

    protected function assignableRoleNames(): ?array
    {
        return BertelsUserResource::assignableRoleNames();
    }

    /**
     * S10/L9: the e-mail is editable like Claesen's, but the address decides the account
     * type (Microsoft vs local), so a change that crosses the corporate-domain boundary is
     * refused here, server-side, before anything is stored.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var User $record */
        $record = $this->getRecord();
        $current = strtolower(trim((string) $record->email));
        $new = strtolower(trim((string) ($data['email'] ?? $current)));

        if ($new !== $current) {
            $domain = BertelsUserResource::microsoftEmailDomain();
            abort_if($domain === null, 403);

            $isCorporate = fn (string $email): bool => str_ends_with($email, '@'.$domain);

            if ($isCorporate($current) !== $isCorporate($new)) {
                throw ValidationException::withMessages([
                    'data.email' => $isCorporate($current)
                        ? "A Microsoft account needs an @{$domain} address."
                        : "Use the Microsoft account type for @{$domain} addresses.",
                ]);
            }
        }

        return $data;
    }

    protected function activationPanelId(): string
    {
        return 'bertels';
    }

    /** A Microsoft account (corporate domain) signs in with Microsoft: it never gets an activation link. */
    protected function canResendActivationTo(User $record): bool
    {
        $domain = BertelsUserResource::microsoftEmailDomain();

        return $domain === null || ! str_ends_with(strtolower(trim($record->email)), '@'.$domain);
    }
}
