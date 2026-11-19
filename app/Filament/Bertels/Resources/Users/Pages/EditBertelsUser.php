<?php

declare(strict_types=1);

namespace App\Filament\Bertels\Resources\Users\Pages;

use App\Filament\Bertels\Resources\Users\BertelsUserResource;
use Filament\Resources\Pages\EditRecord;
use Modules\Core\Filament\Resources\Users\Concerns\ManagesUserAccountActions;
use Modules\Core\Models\User;

/**
 * CLA-704 S5: Claesen's account actions (change roles with step-up, resend activation,
 * revoke sessions), narrowed to the Bertels role allowlist and the /bertels reset link.
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
        ];
    }

    protected function assignableRoleNames(): ?array
    {
        return BertelsUserResource::ASSIGNABLE_ROLES;
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
