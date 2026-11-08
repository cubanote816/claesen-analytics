<?php

namespace Modules\Prospects\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Prospects\DataObjects\ClubLocation;
use Modules\Prospects\DataObjects\NormalizedClub;
use Modules\Prospects\Models\Prospect;
use Modules\Prospects\Models\ProspectLocation;
use Modules\Prospects\Support\ContactType;
use Modules\Prospects\Traits\HandlesClubRegions;
use Throwable;

class ClubPersister
{
    use HandlesClubRegions;

    /**
     * Persist a club and return its prospect, or null when the club matches
     * several legacy rows and nothing was written (see reconcile()).
     */
    public function persist(NormalizedClub $club): ?Prospect
    {
        return $this->reconcile($club)['prospect'];
    }

    /**
     * Reconcile a club with the existing data. Existing rows (matched by
     * external_id or by a single legacy row) are fill-only: values already
     * present are never overwritten. Prospect and locations are written in one
     * transaction.
     *
     * @return array{prospect: ?Prospect, ambiguous: bool, skipped_locations: array<int, array{location: string, email: string}>, skipped_emails: array<int, array{location: string, email: string}>}
     */
    public function reconcile(NormalizedClub $club): array
    {
        $report = DB::transaction(function () use ($club): array {
            $prospect = Prospect::query()->where('external_id', $club->externalId)->first();

            if ($prospect === null) {
                $candidates = $this->legacyCandidates($club);

                if (count($candidates) > 1) {
                    return ['prospect' => null, 'ambiguous' => true, 'skipped_locations' => [], 'skipped_emails' => []];
                }

                $prospect = $candidates[0] ?? null;
            }

            $prospect = $prospect === null ? $this->createProspect($club) : $this->fillProspect($prospect, $club);

            $skippedLocations = [];
            $skippedEmails = [];
            $this->persistLocations($prospect, $club, $skippedLocations, $skippedEmails);

            return [
                'prospect' => $prospect,
                'ambiguous' => false,
                'skipped_locations' => $skippedLocations,
                'skipped_emails' => $skippedEmails,
            ];
        });

        if ($report['ambiguous']) {
            Log::warning("RBFA reconcile: {$club->externalId} matches several legacy prospects; nothing written.", [
                'external_id' => $club->externalId,
                'name' => $club->name,
            ]);
        }

        foreach ($report['skipped_locations'] as $skipped) {
            Log::warning("Location {$skipped['location']} skipped: email {$skipped['email']} already exists.", [
                'external_id' => $club->externalId,
                'location' => $skipped['location'],
                'email' => $skipped['email'],
            ]);
        }

        foreach ($report['skipped_emails'] as $skipped) {
            Log::warning("Email {$skipped['email']} skipped for location {$skipped['location']}: already used by another prospect.", [
                'external_id' => $club->externalId,
                'location' => $skipped['location'],
                'email' => $skipped['email'],
            ]);
        }

        return $report;
    }

    /**
     * @param  array<int, NormalizedClub>  $clubs
     * @return array{persisted: int, failed: int}
     */
    public function persistAll(array $clubs): array
    {
        $tally = ['persisted' => 0, 'failed' => 0];

        foreach ($clubs as $club) {
            try {
                $this->persist($club);
                $tally['persisted']++;
            } catch (Throwable $exception) {
                $tally['failed']++;
                Log::warning("Failed to persist club {$club->externalId}: {$exception->getMessage()}", [
                    'external_id' => $club->externalId,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return $tally;
    }

    private function createProspect(NormalizedClub $club): Prospect
    {
        return Prospect::create([
            'external_id' => $club->externalId,
            'name' => $club->name,
            'type' => $club->type,
            'federation' => $club->federation,
            'language' => $club->language,
            'website' => $club->website,
            'logo_url' => $club->logoUrl,
            'vat_number' => $club->vatNumber,
            'contact_person' => $club->contactPerson,
            'channel' => $club->channel,
            'region_id' => $this->resolveRegionId($club),
        ]);
    }

    private function fillProspect(Prospect $prospect, NormalizedClub $club): Prospect
    {
        $prospect->fill($this->fillable($prospect, [
            'name' => $club->name,
            'type' => $club->type,
            'federation' => $club->federation,
            'language' => $club->language,
            'website' => $club->website,
            'logo_url' => $club->logoUrl,
            'vat_number' => $club->vatNumber,
            'contact_person' => $club->contactPerson,
            'channel' => $club->channel,
        ]));

        // The region only moves from the 'Overige' fallback to a real region.
        $fallbackId = $this->getFallbackRegionId();
        $regionId = $this->resolveRegionId($club);
        if ($prospect->region_id === null || ($prospect->region_id === $fallbackId && $regionId !== $fallbackId)) {
            $prospect->region_id = $regionId;
        }

        $prospect->save();

        return $prospect;
    }

    private function resolveRegionId(NormalizedClub $club): int
    {
        return $this->findRegionIdFromPostalCode($club->postalCode)
            ?? $this->findRegionIdByName($club->regionHint)
            ?? $this->getFallbackRegionId();
    }

    /**
     * Legacy rows: same federation, no external_id, not a tester. Matched by VAT
     * digits when both sides have one, otherwise by normalized name.
     *
     * @return array<int, Prospect>
     */
    private function legacyCandidates(NormalizedClub $club): array
    {
        $clubVat = $this->vatDigits($club->vatNumber);
        $clubName = $this->normalizeName($club->name);

        // Narrow in SQL (VAT digits or normalized name), then apply the exact
        // rule in PHP: VAT decides when both sides have one, else the name.
        $matches = Prospect::query()
            ->where('federation', $club->federation)
            ->whereNull('external_id')
            ->where('is_tester', false)
            ->where(function ($query) use ($clubVat, $clubName): void {
                if ($clubVat !== '') {
                    $query->whereRaw("TRIM(LEADING '0' FROM REGEXP_REPLACE(COALESCE(vat_number, ''), '[^0-9]', '')) = ?", [$clubVat]);
                }

                $query->orWhereRaw("TRIM(REGEXP_REPLACE(UPPER(name), ?, ' ')) = ?", ['\\s+', $clubName]);
            })
            ->get()
            ->filter(function (Prospect $row) use ($clubVat, $clubName): bool {
                $rowVat = $this->vatDigits($row->vat_number);

                return $clubVat !== '' && $rowVat !== ''
                    ? $clubVat === $rowVat
                    : $clubName === $this->normalizeName($row->name);
            });

        return $matches->values()->all();
    }

    /** Canonical VAT: digits only, leading zeros dropped; '' means no VAT. */
    private function vatDigits(?string $vat): string
    {
        return ltrim(preg_replace('/\D+/', '', (string) $vat) ?? '', '0');
    }

    private function normalizeName(?string $name): string
    {
        return mb_strtoupper(trim(preg_replace('/\s+/u', ' ', (string) $name) ?? ''));
    }

    /**
     * @param  array<int, array{location: string, email: string}>  $skippedLocations
     * @param  array<int, array{location: string, email: string}>  $skippedEmails
     */
    private function persistLocations(Prospect $prospect, NormalizedClub $club, array &$skippedLocations, array &$skippedEmails): void
    {
        if ($club->headquarters !== null) {
            $this->persistLocation($prospect, $club->externalId.'::hq', ContactType::Headquarters, $club->headquarters, $skippedLocations, $skippedEmails);
        }

        foreach ($club->venues as $index => $venue) {
            $key = $club->externalId.'::'.($venue->sourceLocationId ?? 'v'.($index + 1));
            $this->persistLocation($prospect, $key, ContactType::Venue, $venue, $skippedLocations, $skippedEmails);
        }
    }

    /**
     * @param  array<int, array{location: string, email: string}>  $skippedLocations
     * @param  array<int, array{location: string, email: string}>  $skippedEmails
     */
    private function persistLocation(Prospect $prospect, string $externalId, ContactType $type, ClubLocation $location, array &$skippedLocations, array &$skippedEmails): void
    {
        $values = [
            'contact_type' => $type->value,
            'contact_name' => $location->contactName,
            'email' => $location->email,
            'phone' => $location->phone,
            'address' => $location->address,
        ];

        $existing = ProspectLocation::query()
            ->where('prospect_id', $prospect->id)
            ->where('external_id', $externalId)
            ->first();

        if ($existing !== null) {
            $fill = $this->fillable($existing, $values);

            if (isset($fill['email']) && ($holder = $this->emailHolder($fill['email'], $existing->id)) !== null) {
                // Held by another prospect: conflict, the email alone is skipped. Same prospect: same contact, silent.
                if ($holder->prospect_id !== $prospect->id) {
                    $skippedEmails[] = ['location' => $externalId, 'email' => $fill['email']];
                }
                unset($fill['email']);
            }

            $existing->fill($fill)->save();

            return;
        }

        if ($this->filled($location->email) && ($holder = $this->emailHolder($location->email)) !== null) {
            if ($holder->prospect_id !== $prospect->id) {
                $skippedLocations[] = ['location' => $externalId, 'email' => $location->email];
            }

            return;
        }

        ProspectLocation::create(['prospect_id' => $prospect->id, 'external_id' => $externalId] + $values);
    }

    private function emailHolder(string $email, ?int $exceptId = null): ?ProspectLocation
    {
        return ProspectLocation::query()
            ->where('email', $email)
            ->when($exceptId !== null, fn ($query) => $query->where('id', '!=', $exceptId))
            ->first();
    }

    /**
     * Subset of $values for attributes that are empty on the model and have a value.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function fillable(\Illuminate\Database\Eloquent\Model $model, array $values): array
    {
        return array_filter(
            $values,
            fn ($value, string $attribute) => $this->filled($value) && ! $this->filled($model->getAttribute($attribute)),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    private function filled(mixed $value): bool
    {
        return $value !== null && trim((string) $value) !== '';
    }
}
