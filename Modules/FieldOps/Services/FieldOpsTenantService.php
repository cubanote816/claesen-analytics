<?php

declare(strict_types=1);

namespace Modules\FieldOps\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Modules\Core\Models\User;
use Modules\FieldOps\Models\Complex;
use Modules\FieldOps\Models\ElectricalBoard;
use Modules\FieldOps\Models\FoClient;
use Modules\FieldOps\Models\FoMaintenanceRecord;
use Modules\FieldOps\Models\FoMaintenanceRequest;
use Modules\FieldOps\Models\FoMaintenanceWorkOrder;
use Modules\FieldOps\Models\Luminaire;
use Modules\FieldOps\Models\LuminaireFrame;
use Modules\FieldOps\Models\Structure;
use Modules\FieldOps\Models\Terrain;

class FieldOpsTenantService
{
    public function isClientUser(User $user): bool
    {
        return $user->hasRole('client');
    }

    // CLA-364: distinct from isClientUser() — this is about *scope*, not the
    // Client Portal specifically. A technician/project_manager without this
    // permission is scoped the same way a client is (allowedClientIds() below),
    // but keeps every other client-only restriction (isClientUser() call sites
    // elsewhere, e.g. the maintenance-work-order block) untouched.
    public function hasBroadAccess(User $user): bool
    {
        return $user->can('fieldops.view-all-clients');
    }

    /** @return Collection<int, int> */
    public function allowedClientIds(User $user): Collection
    {
        if ($this->hasBroadAccess($user)) {
            return collect();
        }

        return $user->fieldOpsClients()
            ->wherePivot('is_active', true)
            ->wherePivot('can_view', true)
            ->pluck('fo_clients.id')
            ->map(fn ($id): int => (int) $id)
            ->values();
    }

    public function scopeForUser(Builder $query, User $user, string $modelClass): Builder
    {
        if ($this->hasBroadAccess($user)) {
            return $query;
        }

        $clientIds = $this->allowedClientIds($user)->all();

        if ($clientIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return match ($modelClass) {
            FoClient::class => $query->whereIn('fo_clients.id', $clientIds),
            Complex::class => $query->whereIn('client_id', $clientIds),
            Terrain::class => $query->whereHas('complex', fn (Builder $builder) => $builder->whereIn('client_id', $clientIds)),
            Structure::class => $this->scopeThroughTerrains($query, $clientIds),
            LuminaireFrame::class => $this->scopeFrames($query, $clientIds),
            Luminaire::class => $this->scopeLuminaires($query, $clientIds),
            ElectricalBoard::class => $this->scopeElectricalBoards($query, $clientIds),
            FoMaintenanceRecord::class => $query->whereIn('client_id', $clientIds),
            FoMaintenanceRequest::class => $query->whereIn('client_id', $clientIds),
            default => $query->whereRaw('1 = 0'),
        };
    }

    public function canView(User $user, Model $model): bool
    {
        // CLA-369: Client Portal-only rule, independent of scope — clients never
        // see work orders (an internal concept), but a scoped technician/
        // project_manager must still be able to view their own, so this check
        // stays tied to isClientUser() rather than the general scoping gate below.
        if ($model instanceof FoMaintenanceWorkOrder && $this->isClientUser($user)) {
            return false;
        }

        if ($this->hasBroadAccess($user)) {
            return true;
        }

        if ($model instanceof FoMaintenanceRequest) {
            return $this->allowedClientIds($user)->contains((int) $model->client_id);
        }

        $allowed = $this->allowedClientIds($user);
        $owners = $this->ownerClientIds($model);

        return $owners->count() === 1 && $allowed->contains($owners->first());
    }

    /** @return Collection<int, int> */
    public function ownerClientIds(Model $model): Collection
    {
        $ids = match (true) {
            $model instanceof FoClient => collect([$model->getKey()]),
            $model instanceof Complex => collect([$model->client_id]),
            $model instanceof Terrain => collect([$model->complex()->value('client_id')]),
            $model instanceof Structure => $model->terrains()->with('complex:id,client_id')->get()->pluck('complex.client_id'),
            $model instanceof LuminaireFrame => $model->structures()
                ->with('terrains.complex:id,client_id')->get()
                ->flatMap(fn (Structure $structure) => $structure->terrains->pluck('complex.client_id')),
            $model instanceof Luminaire => $model->luminaireFrame
                ? $this->ownerClientIds($model->luminaireFrame)
                : collect(),
            $model instanceof ElectricalBoard => $model->complexes()->pluck('fo_complexes.client_id')
                ->merge($model->terrains()->with('complex:id,client_id')->get()->pluck('complex.client_id'))
                ->merge($model->structures()->with('terrains.complex:id,client_id')->get()
                    ->flatMap(fn (Structure $structure) => $structure->terrains->pluck('complex.client_id'))),
            $model instanceof FoMaintenanceRecord => collect([$model->client_id]),
            $model instanceof FoMaintenanceRequest => collect([$model->client_id]),
            $model instanceof FoMaintenanceWorkOrder => collect([$model->client_id]),
            default => collect(),
        };

        return collect($ids)->filter()->map(fn ($id): int => (int) $id)->unique()->values();
    }

    private function scopeThroughTerrains(Builder $query, array $clientIds): Builder
    {
        return $query
            ->whereHas('terrains.complex', fn (Builder $builder) => $builder->whereIn('client_id', $clientIds))
            ->whereDoesntHave('terrains.complex', fn (Builder $builder) => $builder->whereNotIn('client_id', $clientIds));
    }

    private function scopeFrames(Builder $query, array $clientIds): Builder
    {
        return $query
            ->whereHas('structures.terrains.complex', fn (Builder $builder) => $builder->whereIn('client_id', $clientIds))
            ->whereDoesntHave('structures.terrains.complex', fn (Builder $builder) => $builder->whereNotIn('client_id', $clientIds));
    }

    private function scopeLuminaires(Builder $query, array $clientIds): Builder
    {
        return $query
            ->whereHas('luminaireFrame.structures.terrains.complex', fn (Builder $builder) => $builder->whereIn('client_id', $clientIds))
            ->whereDoesntHave('luminaireFrame.structures.terrains.complex', fn (Builder $builder) => $builder->whereNotIn('client_id', $clientIds));
    }

    private function scopeElectricalBoards(Builder $query, array $clientIds): Builder
    {
        return $query
            ->where(function (Builder $builder) use ($clientIds): void {
                $builder->whereHas('complexes', fn (Builder $related) => $related->whereIn('client_id', $clientIds))
                    ->orWhereHas('terrains.complex', fn (Builder $related) => $related->whereIn('client_id', $clientIds))
                    ->orWhereHas('structures.terrains.complex', fn (Builder $related) => $related->whereIn('client_id', $clientIds));
            })
            ->whereDoesntHave('complexes', fn (Builder $builder) => $builder->whereNotIn('client_id', $clientIds))
            ->whereDoesntHave('terrains.complex', fn (Builder $builder) => $builder->whereNotIn('client_id', $clientIds))
            ->whereDoesntHave('structures.terrains.complex', fn (Builder $builder) => $builder->whereNotIn('client_id', $clientIds));
    }
}
