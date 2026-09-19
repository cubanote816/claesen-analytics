<?php

declare(strict_types=1);

namespace Modules\FieldOps\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Modules\Core\Models\User;
use Modules\FieldOps\Enums\MaintenanceWorkOrderStatus;
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

        // CLA-500: a work order or maintenance record assigned/attributed to this
        // employee is theirs to view permanently, regardless of status — this is
        // ownership of one specific record, not equipment access, so it never
        // expires. Before CLA-500 this instead merged the employee's client_id
        // into the general allowed set, which also (incorrectly) let them view
        // every other work order/record for that client, not just their own.
        if ($model instanceof FoMaintenanceWorkOrder) {
            return $this->allowedClientIds($user)->contains((int) $model->client_id)
                || ($user->employee_id && (string) $model->assigned_employee_id === (string) $user->employee_id);
        }

        if ($model instanceof FoMaintenanceRecord) {
            return $this->allowedClientIds($user)->contains((int) $model->client_id)
                || ($user->employee_id && (string) $model->employee_id === (string) $user->employee_id);
        }

        $allowed = $this->allowedClientIds($user);
        $owners = $this->ownerClientIds($model);

        if ($owners->count() === 1 && $allowed->contains($owners->first())) {
            return true;
        }

        // CLA-500: equipment linked to an ACTIVE assigned work order stays
        // viewable outside the technician's client scope (the original CLA-375
        // need), narrowed to that order's own maintainable + ancestor chain
        // instead of the whole client, and only while the order is open —
        // closing it revokes this grant. The FoMaintenanceWorkOrder/
        // FoMaintenanceRecord ownership checks above are separate and never
        // expire.
        return $this->assignedWorkOrderEquipmentTargets($user)
            ->contains(fn (array $target): bool => $target['class'] === $model::class && $target['id'] === $model->getKey());
    }

    /** @return Collection<int, array{class: class-string, id: int}> */
    private function assignedWorkOrderEquipmentTargets(User $user): Collection
    {
        if (! $user->employee_id) {
            return collect();
        }

        return FoMaintenanceWorkOrder::query()
            ->where('assigned_employee_id', $user->employee_id)
            ->whereNotIn('status', [MaintenanceWorkOrderStatus::COMPLETED, MaintenanceWorkOrderStatus::CANCELLED])
            ->with('maintainable')
            ->get()
            ->flatMap(fn (FoMaintenanceWorkOrder $order) => $this->maintainableChain($order->maintainable))
            ->unique(fn (array $target): string => $target['class'].':'.$target['id'])
            ->values();
    }

    /** @return Collection<int, array{class: class-string, id: int}> */
    private function maintainableChain(?Model $maintainable): Collection
    {
        if (! $maintainable) {
            return collect();
        }

        $chain = collect([['class' => $maintainable::class, 'id' => $maintainable->getKey()]]);

        if ($maintainable instanceof Luminaire) {
            return $maintainable->luminaireFrame
                ? $chain->merge($this->maintainableChain($maintainable->luminaireFrame))
                : $chain;
        }

        if ($maintainable instanceof LuminaireFrame) {
            foreach ($maintainable->structures as $structure) {
                $chain->push(['class' => Structure::class, 'id' => $structure->id]);
                $chain = $chain->merge($this->terrainAndComplexChain($structure));
            }

            return $chain;
        }

        if ($maintainable instanceof ElectricalBoard) {
            foreach ($maintainable->complexes as $complex) {
                $chain->push(['class' => Complex::class, 'id' => $complex->id]);
            }
            foreach ($maintainable->terrains as $terrain) {
                $chain->push(['class' => Terrain::class, 'id' => $terrain->id]);
                $chain->push(['class' => Complex::class, 'id' => $terrain->complex_id]);
            }
            foreach ($maintainable->structures as $structure) {
                $chain->push(['class' => Structure::class, 'id' => $structure->id]);
                $chain = $chain->merge($this->terrainAndComplexChain($structure));
            }

            return $chain;
        }

        return $chain;
    }

    /** @return Collection<int, array{class: class-string, id: int}> */
    private function terrainAndComplexChain(Structure $structure): Collection
    {
        $chain = collect();

        foreach ($structure->terrains as $terrain) {
            $chain->push(['class' => Terrain::class, 'id' => $terrain->id]);
            $chain->push(['class' => Complex::class, 'id' => $terrain->complex_id]);
        }

        return $chain;
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
