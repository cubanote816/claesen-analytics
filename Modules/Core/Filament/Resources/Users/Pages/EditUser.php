<?php

namespace Modules\Core\Filament\Resources\Users\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Filament\Resources\Users\UserResource;
use Modules\Core\Models\User;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var User $record */
        $record = $this->getRecord();

        // CLA-553: fieldOpsClients isn't a plain relationship() field anymore (see
        // UserForm), so its current state has to be hydrated by hand. A client user
        // is always attached with the same pivot values across every FoClient (the
        // only writer, CreateUser::handleRecordCreation(), applies them uniformly),
        // so the first membership row is a faithful representative of all of them.
        $membership = $record->fieldOpsClients()->first();

        $data['client_ids'] = $record->fieldOpsClients()->pluck('fo_clients.id')->all();
        $data['client_pivot_is_active'] = $membership?->pivot->is_active ?? true;
        $data['client_can_view'] = $membership?->pivot->can_view ?? true;
        $data['client_can_report'] = $membership?->pivot->can_report ?? true;
        $data['client_can_manage_contacts'] = $membership?->pivot->can_manage_contacts ?? false;

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $clientIds = collect($data['client_ids'] ?? [])->filter()->unique()->values()->all();
        $pivotData = [
            'is_active' => (bool) ($data['client_pivot_is_active'] ?? true),
            'can_view' => (bool) ($data['client_can_view'] ?? true),
            'can_report' => (bool) ($data['client_can_report'] ?? true),
            'can_manage_contacts' => (bool) ($data['client_can_manage_contacts'] ?? false),
        ];
        unset(
            $data['client_ids'],
            $data['client_pivot_is_active'],
            $data['client_can_view'],
            $data['client_can_report'],
            $data['client_can_manage_contacts'],
        );

        $record->update($data);

        // Only client-role users carry FieldOps client memberships — an internal
        // account's client_ids is always empty (the fields are hidden for them),
        // so this sync would otherwise be a harmless no-op; the role check just
        // makes the intent explicit and matches the fields' own ->visible() gate.
        if ($record->hasRole('client')) {
            $record->fieldOpsClients()->sync(
                collect($clientIds)->mapWithKeys(fn ($id) => [$id => $pivotData])->all(),
            );
        }

        return $record;
    }
}
