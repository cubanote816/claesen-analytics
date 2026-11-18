<?php

declare(strict_types=1);

namespace App\Filament\Bertels\Resources\Users\Pages;

use App\Filament\Bertels\Resources\Users\BertelsUserResource;
use App\Filament\Bertels\Resources\Users\Schemas\BertelsCreateUserForm;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\User;
use Modules\Core\Services\PasswordResetService;
use Spatie\Permission\Models\Role;

/**
 * CLA-704 S2-S4. Two account types, both without a password and always inside the
 * Bertels organization:
 *  - microsoft: an address on the corporate domain; signs in with Microsoft, no e-mail.
 *  - local: any other address; gets the activation e-mail with a /bertels reset link.
 * The domain/type split, the role allowlist and the organization are all decided here,
 * server-side, never by what the form happened to offer.
 */
class CreateBertelsUser extends CreateRecord
{
    protected static string $resource = BertelsUserResource::class;

    public function form(Schema $schema): Schema
    {
        return BertelsCreateUserForm::configure($schema);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Fail closed: no Bertels organization (or no configured domain) → nothing is created.
        abort_if(BertelsUserResource::organizationId() === null, 403);
        $domain = BertelsUserResource::microsoftEmailDomain();
        abort_if($domain === null, 403);

        $type = $data['account_type'] ?? null;
        $this->refuseIf(! in_array($type, ['microsoft', 'local'], true), 'account_type', 'Invalid account type.');

        $name = trim((string) ($data['name'] ?? ''));
        $this->refuseIf($name === '', 'name', 'A name is required.');

        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $this->refuseIf(! filter_var($email, FILTER_VALIDATE_EMAIL), 'email', 'Invalid email address.');

        $isCorporate = str_ends_with($email, '@'.$domain);
        $this->refuseIf($type === 'microsoft' && ! $isCorporate, 'email', "A Microsoft account needs an @{$domain} address.");
        $this->refuseIf($type === 'local' && $isCorporate, 'email', "Use the Microsoft account type for @{$domain} addresses.");
        $this->refuseIf(
            User::whereRaw('LOWER(TRIM(email)) = ?', [$email])->exists(),
            'email', 'A user with this email already exists.'
        );

        $roleIds = collect($data['role_ids'] ?? [])->filter()->unique()->values()->all();
        $this->refuseIf($roleIds === [], 'role_ids', 'At least one role is required.');
        $this->refuseIf(
            Role::query()->whereKey($roleIds)->whereIn('name', BertelsUserResource::ASSIGNABLE_ROLES)->count() !== count($roleIds),
            'role_ids', 'Only the Viewer and Admin roles can be assigned.'
        );

        // Only these keys ever reach the model: forged attributes are dropped here.
        return [
            'account_type' => $type,
            'name' => $name,
            'email' => $email,
            'password' => null,
            'password_set_at' => null,
            'role_ids' => $roleIds,
        ];
    }

    private function refuseIf(bool $condition, string $field, string $message): void
    {
        if ($condition) {
            throw ValidationException::withMessages(['data.'.$field => $message]);
        }
    }

    protected function handleRecordCreation(array $data): Model
    {
        $roleIds = $data['role_ids'];
        unset($data['role_ids'], $data['account_type']);

        return DB::transaction(function () use ($data, $roleIds): User {
            $data['organization_id'] = BertelsUserResource::organizationId();
            abort_if($data['organization_id'] === null, 403);

            $user = User::create($data);
            $user->syncRoles($roleIds);     // failure here rolls back the User::create

            return $user;
        });
    }

    /**
     * Only a local account is e-mailed its activation link (to /bertels); a Microsoft
     * account signs in with Microsoft. Deferred to the commit so a rolled-back creation
     * never e-mails anyone.
     */
    protected function afterCreate(): void
    {
        $user = $this->getRecord();

        if (! $user instanceof User || ($this->data['account_type'] ?? null) !== 'local') {
            return;
        }

        DB::afterCommit(fn () => app(PasswordResetService::class)->sendActivationEmail($user, 'bertels'));
    }
}
