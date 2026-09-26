<?php

declare(strict_types=1);

namespace Modules\Knx\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Modules\Core\Models\Organization;
use Modules\Core\Models\User;
use Spatie\Permission\Models\Role;
use Modules\Knx\Models\KnxAcceptanceTest;
use Modules\Knx\Models\KnxBoard;
use Modules\Knx\Models\KnxClient;
use Modules\Knx\Models\KnxConflict;
use Modules\Knx\Models\KnxConflictLog;
use Modules\Knx\Models\KnxDevice;
use Modules\Knx\Models\KnxDocument;
use Modules\Knx\Models\KnxEmployee;
use Modules\Knx\Models\KnxExport;
use Modules\Knx\Models\KnxFunctionSpec;
use Modules\Knx\Models\KnxNotification;
use Modules\Knx\Models\KnxPlanningAssignment;
use Modules\Knx\Models\KnxProject;
use Modules\Knx\Models\KnxProjectRoom;
use Modules\Knx\Models\KnxZone;
use Modules\Knx\Models\KnxZoneCheck;
use Modules\Knx\Support\KnxTenant;

/**
 * The demo data of the office app, ported 1:1 from Kantoor's own mock
 * (`electro-bertels-kantoor/src/api/mock/db.ts`).
 *
 * Why port it at all: the front is already built against this data, so seeding
 * the backend with the same rows is what makes `VITE_API_MODE=real` visually
 * identical to `VITE_API_MODE=mock`. Every screen (Overzicht, Projecten,
 * Conflictencentrum, Planning, Plannen, Zonas, Functies & testen) has something
 * real in it, including the awkward cases the UI is designed around: a blocked
 * zone, a conflict mid-workflow, a superseded document revision and a
 * functional spec that changed after approval.
 *
 * It REPLACES the tenant's KNX data on every run (children first), so it can be
 * re-run on a used database. Local/testing only — it is a demo fixture, not a
 * production import.
 */
class KnxDemoSeeder extends Seeder
{
    /** The mock's ROOMS, in order — devices cycle through them by index. */
    private const ROOMS = ['Inkomhal', 'Gang gelijkvloers', 'Vergaderzaal', 'Bureau 0.04', 'Sanitair', 'Technische ruimte'];

    private const DEVICE_TYPES = ['Drukknop 4-voudig', 'Aanwezigheidsdetector', 'Dimmer 2-kanaals', 'Schakelactor 8-v', 'Thermostaat', 'Armatuur DALI'];

    private const BOARDS = ['E10', 'E11', 'HB'];

    /** The mock's TAKEN_ADDRESSES. */
    private const TAKEN_ADDRESSES = ['1.1.100', '1.1.101', '1.1.102'];

    /**
     * Password of the seeded Kantoor accounts, documented in
     * docs/Knx/knx-kantoor-backend.md. Demo fixture only.
     */
    public const DEMO_PASSWORD = 'Kantoor123!';

    /** The accounts this seeder owns; removed on re-run like the rows are. */
    private const DEMO_EMAILS = [
        'lien.smet@electrobertels.be',
        'pieter.aerts@electrobertels.be',
    ];

    /** @var array<string, KnxEmployee> keyed by display short name ("L. Smet") */
    private array $people = [];

    /** @var array<string, KnxProject> keyed by code */
    private array $projects = [];

    public function run(): void
    {
        $organization = KnxTenant::organization();

        $this->purge($organization);

        $this->seedPeople($organization);
        $clients = $this->seedClients();
        $this->seedProjects($clients);
        $this->seedRoomsAndBoards();
        $this->seedDevices();
        $this->seedPlanning();
        $this->seedConflicts();
        $this->seedNotifications();
        $this->seedDocuments();
        $zones = $this->seedZones();
        $functions = $this->seedFunctionSpecs($zones);
        $this->seedAcceptanceTests($functions);
        $this->seedExports();
    }

    /**
     * Children first: no foreign key is left dangling, and re-running the seeder
     * on a used database is safe.
     *
     * Note which tables are NOT reached by organization_id: devices, rooms,
     * boards, zone checks and conflict logs have no tenant column on purpose
     * (they hang off a project/zone/conflict, which does), so they are deleted
     * through their parent.
     */
    private function purge(Organization $organization): void
    {
        $projectIds = KnxProject::query()->where('organization_id', $organization->id)->pluck('id');
        $zoneIds = KnxZone::query()->whereIn('project_id', $projectIds)->pluck('id');
        $conflictIds = KnxConflict::query()->whereIn('project_id', $projectIds)->pluck('id');

        KnxZoneCheck::query()->whereIn('zone_id', $zoneIds)->delete();
        KnxConflictLog::query()->whereIn('conflict_id', $conflictIds)->delete();

        KnxAcceptanceTest::query()->whereIn('project_id', $projectIds)->delete();
        KnxFunctionSpec::query()->whereIn('project_id', $projectIds)->delete();
        KnxZone::query()->whereIn('project_id', $projectIds)->delete();
        KnxExport::query()->whereIn('project_id', $projectIds)->delete();
        KnxDocument::query()->whereIn('project_id', $projectIds)->delete();
        KnxNotification::query()->whereIn('project_id', $projectIds)->delete();
        KnxConflict::query()->whereIn('project_id', $projectIds)->delete();
        KnxPlanningAssignment::query()->whereIn('project_id', $projectIds)->delete();
        KnxDevice::query()->whereIn('project_id', $projectIds)->delete();
        KnxBoard::query()->whereIn('project_id', $projectIds)->delete();
        KnxProjectRoom::query()->whereIn('project_id', $projectIds)->delete();

        KnxProject::query()->where('organization_id', $organization->id)->delete();
        KnxClient::query()->where('organization_id', $organization->id)->delete();
        KnxEmployee::query()->where('organization_id', $organization->id)->delete();
        User::query()->whereIn('email', self::DEMO_EMAILS)->delete();
    }

    /**
     * The mock's people. Kantoor's one signed-in user is Lien Smet; P. Aerts is
     * the planner the mock shows on two projects. The five technicians are the
     * `Technician[]` list. Technicians also appear as `updatedBy` on zone checks —
     * which is why the person table is shared by both apps instead of split.
     */
    private function seedPeople(Organization $organization): void
    {
        // Office people get an account, because without one nobody can sign into
        // Kantoor at all — a seed that leaves the app unreachable is not a demo.
        // The technicians stay account-less on purpose: they are planned work,
        // not logins (Veld will give them accounts when it ships).
        $people = [
            ['name' => 'Lien Smet', 'kind' => KnxEmployee::KIND_OFFICE, 'role' => 'lead', 'email' => 'lien.smet@electrobertels.be'],
            ['name' => 'Pieter Aerts', 'kind' => KnxEmployee::KIND_OFFICE, 'role' => 'planner', 'email' => 'pieter.aerts@electrobertels.be'],
            ['name' => 'Jan Van Dyck', 'kind' => KnxEmployee::KIND_FIELD, 'role' => 'technician', 'email' => null],
            ['name' => 'Mira Claes', 'kind' => KnxEmployee::KIND_FIELD, 'role' => 'technician', 'email' => null],
            ['name' => 'Stijn Wouters', 'kind' => KnxEmployee::KIND_FIELD, 'role' => 'technician', 'email' => null],
            ['name' => 'Tom Janssens', 'kind' => KnxEmployee::KIND_FIELD, 'role' => 'technician', 'email' => null],
            ['name' => 'Kobe Peeters', 'kind' => KnxEmployee::KIND_FIELD, 'role' => 'technician', 'email' => null],
        ];

        Role::findOrCreate('knx_office', 'web');

        foreach ($people as $person) {
            $employee = KnxEmployee::create([
                'organization_id' => $organization->id,
                'name' => $person['name'],
                'initials' => KnxEmployee::initialsFromName($person['name']),
                'kind' => $person['kind'],
                'knx_role' => $person['role'],
            ]);

            if ($person['email'] !== null) {
                $user = User::updateOrCreate(
                    ['email' => $person['email']],
                    [
                        'name' => $person['name'],
                        'password' => Hash::make(self::DEMO_PASSWORD),
                        'password_set_at' => now(),
                        'is_active' => true,
                        'organization_id' => $organization->id,
                    ],
                );
                $user->syncRoles(['knx_office']);

                $employee->update(['user_id' => $user->id]);
            }

            $this->people[$employee->shortName()] = $employee;
        }
    }

    /** @return array<string, KnxClient> keyed by the mock's client id ("c1") */
    private function seedClients(): array
    {
        $rows = [
            'c1' => ['UV Vastgoed', 'Heverlee', 'Dirk Maes', '0476 12 34 56', 'd.maes@uv-vastgoed.be', 'Kapeldreef 60, 3001 Heverlee', 'BE 0412.345.678'],
            'c2' => ['Hectaar NV', 'Mechelen', 'Ann De Smet', '0495 22 10 04', 'ann@hectaar.be', 'Zandpoortvest 12, 2800 Mechelen', 'BE 0634.221.905'],
            'c3' => ['Gemeente Westerlo', 'Westerlo', 'Els Wouters', '014 53 11 00', 'facility@westerlo.be', 'Boerenkrijgstraat 133, 2260 Westerlo', 'BE 0207.466.012'],
            'c4' => ['Bouwgroep Nijs', 'Geel', 'Koen Nijs', '0472 88 41 20', 'koen@bouwgroepnijs.be', 'Pas 22, 2440 Geel', 'BE 0455.781.330'],
            'c5' => ['Scholengroep Kempen', 'Herentals', 'Hilde Peeters', '014 21 70 70', 'infra@sgkempen.be', 'Lierseweg 4, 2200 Herentals', 'BE 0877.105.642'],
        ];

        $clients = [];

        foreach ($rows as $id => [$name, $city, $contact, $phone, $email, $address, $vat]) {
            $clients[$id] = KnxClient::create(compact('name', 'city', 'contact', 'phone', 'email', 'address', 'vat'));
        }

        return $clients;
    }

    /**
     * @param  array<string, KnxClient>  $clients
     */
    private function seedProjects(array $clients): void
    {
        $rows = [
            ['C1618', 'UV Campus · Gelijkvloers', 'c1', 'Heverlee', 'L. Smet', 24, 17, 38, 'busy', '2026-10-10'],
            ['239870', 'Kantoor Hectaar · 2e verdieping', 'c2', 'Mechelen', 'L. Smet', 46, 31, 52, 'busy', '2026-10-24'],
            ['232146', 'Sporthal De Wissel · bord E10', 'c3', 'Westerlo', 'P. Aerts', 12, 0, 0, 'planned', '2026-11-15'],
            ['240512', 'Residentie Linde · app. 2.1', 'c4', 'Geel', 'P. Aerts', 8, 8, 21, 'delivery', '2026-09-30'],
            ['228104', 'School De Linde · renovatie', 'c5', 'Herentals', 'L. Smet', 60, 60, 140, 'done', '2026-08-28'],
        ];

        foreach ($rows as [$code, $name, $clientId, $city, $lead, $planned, $done, $photos, $status, $deadline]) {
            $project = KnxProject::create([
                'client_id' => $clients[$clientId]->id,
                'code' => $code,
                'name' => $name,
                'city' => $city,
                'lead_employee_id' => $this->people[$lead]->id,
                'devices_planned' => $planned,
                'devices_done' => $done,
                'photos' => $photos,
                'status' => $status,
                'deadline' => $deadline,
            ]);

            $this->projects[$code] = $project;
        }
    }

    private function seedRoomsAndBoards(): void
    {
        foreach ($this->projects as $project) {
            foreach (self::ROOMS as $name) {
                KnxProjectRoom::create([
                    'project_id' => $project->id,
                    'name' => $name,
                    'floor' => str_contains($project->name, '2e verdieping') ? '2e verdieping' : 'Gelijkvloers',
                ]);
            }

            foreach (self::BOARDS as $code) {
                KnxBoard::create([
                    'project_id' => $project->id,
                    'code' => $code,
                    'name' => $code === 'HB' ? 'Hoofdbord' : 'Verdeelbord '.$code,
                ]);
            }
        }
    }

    /**
     * The mock derives its device list from each project's `devicesDone` with a
     * fixed formula; the same formula here means the office app shows the same
     * addresses, types, rooms, boards and serials it shows in mock mode.
     */
    private function seedDevices(): void
    {
        $technicians = array_values(array_filter($this->people, fn (KnxEmployee $e): bool => $e->isField()));

        foreach ($this->projects as $index => $project) {
            $rooms = KnxProjectRoom::query()->where('project_id', $project->id)->get()->values();
            $boards = KnxBoard::query()->where('project_id', $project->id)->get()->values();

            for ($i = 0; $i < $project->devices_done; $i++) {
                $address = $project->code === 'C1618'
                    ? '1.1.'.(100 + $i)
                    : '1.'.($index + 1).'.'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT);

                // i % 4 === 0 → 'ets', matching the mock's mix of planned and
                // field-registered devices.
                $source = $i % 4 === 0 ? KnxDevice::SOURCE_ETS : KnxDevice::SOURCE_FIELD;

                KnxDevice::create([
                    'project_id' => $project->id,
                    'room_id' => $rooms[$i % count(self::ROOMS)]->id,
                    'board_id' => $boards[$i % count(self::BOARDS)]->id,
                    'type' => self::DEVICE_TYPES[$i % count(self::DEVICE_TYPES)],
                    'address' => $address,
                    'serial' => $this->mockSerial($i),
                    'source' => $source,
                    'registered_by_employee_id' => $technicians[$i % count($technicians)]->id,
                    // Field registrations arrive acknowledged in the demo: only
                    // today's two notifications are still pending (see the mock).
                    'registered_at' => $source === KnxDevice::SOURCE_FIELD ? now()->subDays(2) : null,
                    'acknowledged_at' => $source === KnxDevice::SOURCE_FIELD ? now()->subDays(2) : null,
                ]);
            }
        }
    }

    /** `((i * 7919) % 255)` / `((i * 104729) % 65535)`, as the mock computes it. */
    private function mockSerial(int $i): string
    {
        return '00'.strtoupper(str_pad(dechex(($i * 7919) % 255), 2, '0', STR_PAD_LEFT))
            .'-'.strtoupper(str_pad(dechex(($i * 104729) % 65535), 4, '0', STR_PAD_LEFT));
    }

    /** The mock's `plan` seed map: technician index + day index → project. */
    private function seedPlanning(): void
    {
        $technicians = array_keys(array_filter($this->people, fn (KnxEmployee $e): bool => $e->isField()));
        $monday = now()->startOfWeek();

        $map = [
            [0, 0, 'C1618'], [0, 1, 'C1618'], [0, 2, 'C1618'], [0, 3, 'C1618'], [0, 4, '232146'],
            [1, 0, '239870'], [1, 1, '239870'], [1, 3, '239870'], [1, 4, '239870'],
            [2, 0, 'C1618'], [2, 2, '240512'], [2, 3, '240512'],
            [3, 1, '240512'], [3, 3, '240512'],
            [4, 2, '232146'], [4, 3, '232146'],
        ];

        foreach ($map as [$techIndex, $dayIndex, $code]) {
            KnxPlanningAssignment::create([
                'employee_id' => $this->people[$technicians[$techIndex]]->id,
                'project_id' => $this->projects[$code]->id,
                'date' => $monday->copy()->addDays($dayIndex)->format('Y-m-d'),
            ]);
        }
    }

    private function seedConflicts(): void
    {
        $rows = [
            ['k1', 'critical', 'duplicate_address', '1.1.116', 'C1618', 'Aanwezigheidsdetector · Vergaderzaal · rij 3', 'KNX-drukknop 4-voudig · Gang gelijkvloers · rij 5', 'J. Van Dyck', 0, [9, 42], 'Zit naast de deur, niet in de vergaderzaal.', 'open', '1.1.133', [['reported', 0, [9, 42]]]],
            ['k2', 'critical', 'duplicate_address', '1.1.108', 'C1618', 'Dimmer 2-kanaals · Bureau 0.04', 'Schakelactor 8-v · Technische ruimte', 'J. Van Dyck', 0, [8, 15], 'Label op actor toont 1.1.108, ETS zegt dimmer.', 'open', '1.1.134', [['reported', 0, [8, 15]]]],
            ['k3', 'warning', 'plan_mismatch', '1.2.021', '239870', 'Thermostaat · Zaal 2.03', 'Thermostaat · Zaal 2.05 (volgens werf)', 'M. Claes', 1, [14, 5], 'Wand verplaatst, plan niet aangepast.', 'in_review', '1.2.021', [['reported', 1, [14, 5]]]],
            ['k4', 'warning', 'missing_device', '1.2.044', '239870', 'Armatuur DALI · Gang 2e', '— niet gevonden ter plaatse', 'M. Claes', 1, [16, 20], 'Plafond nog niet dicht, armatuur niet geleverd.', 'open', '1.2.044', [['reported', 1, [16, 20]]]],
            ['k5', 'info', 'damaged', '1.1.121', 'C1618', 'Drukknop 2-voudig · Sanitair', 'Afdekraam gebarsten', 'S. Wouters', 3, [10, 30], 'Enkel afdekraam, toestel werkt.', 'verified', '1.1.121', [
                ['reported', 3, [10, 30]], ['ets_listed', 3, [11, 5]], ['applied', 2, [9, 15]], ['downloaded', 2, [9, 40]], ['verified', 0, [8, 10]],
            ]],
        ];

        foreach ($rows as [$id, $severity, $type, $address, $code, $existing, $field, $by, $daysAgo, [$hour, $minute], $note, $status, $proposal, $history]) {
            $project = $this->projects[$code];

            $conflict = KnxConflict::create([
                'project_id' => $project->id,
                'severity' => $severity,
                'type' => $type,
                'address' => $address,
                'device_existing' => $existing,
                'device_field' => $field,
                'reported_by_employee_id' => $this->people[$by]->id,
                'reported_at' => now()->subDays($daysAgo)->setTime($hour, $minute),
                'note' => $note,
                'status' => $status,
                'proposal' => $proposal,
            ]);

            foreach ($history as [$action, $logDaysAgo, [$logHour, $logMinute]]) {
                KnxConflictLog::create([
                    'conflict_id' => $conflict->id,
                    'at' => now()->subDays($logDaysAgo)->setTime($logHour, $logMinute),
                    'action' => $action,
                    'address' => $action === 'reported' ? null : $proposal,
                ]);
            }

            unset($id);
        }
    }

    private function seedNotifications(): void
    {
        $rows = [
            ['1.2.047', 'Aanwezigheidsdetector', 'Zaal 2.07', 'E21', 'Verdeelbord E21', '00C1-9A20', '239870', 'M. Claes', [8, 52], false],
            ['1.1.131', 'Drukknop 4-voudig', 'Cafetaria', 'E11', 'Verdeelbord E11', '00B2-4471', 'C1618', 'L. Smet', [8, 31], false],
        ];

        foreach ($rows as [$address, $type, $roomName, $boardCode, $boardName, $serial, $code, $by, [$hour, $minute], $acked]) {
            $project = $this->projects[$code];

            // The mock reports a board the project may not have yet; E21 is
            // exactly that case, so it is created on the fly (the office then
            // sees a board it has never seen before).
            $board = KnxBoard::firstOrCreate(
                ['project_id' => $project->id, 'code' => $boardCode],
                ['name' => $boardName],
            );

            // Same for the room: the field app reports a name, and a space the
            // office has not modelled yet becomes a room row. Without it the
            // device's `room` would be null, and the contract types it as a string.
            $room = KnxProjectRoom::firstOrCreate(
                ['project_id' => $project->id, 'name' => $roomName],
                ['floor' => str_contains($project->name, '2e verdieping') ? '2e verdieping' : 'Gelijkvloers'],
            );

            // The notification is the *event*; the device is the *registration*.
            // Both exist, and they are linked, which is what lets the dossier show
            // the apparatus while the office inbox still shows the item to confirm.
            $device = KnxDevice::create([
                'project_id' => $project->id,
                'room_id' => $room->id,
                'board_id' => $board->id,
                'type' => $type,
                'address' => $address,
                'serial' => $serial,
                'source' => KnxDevice::SOURCE_FIELD,
                'registered_by_employee_id' => $this->people[$by]->id,
                'registered_at' => now()->setTime($hour, $minute),
                'acknowledged_at' => $acked ? now() : null,
            ]);

            KnxNotification::create([
                'project_id' => $project->id,
                'device_id' => $device->id,
                'board_id' => $board->id,
                'type' => $type,
                'address' => $address,
                'room' => $roomName,
                'serial' => $serial,
                'reported_by_employee_id' => $this->people[$by]->id,
                'reported_at' => now()->setTime($hour, $minute),
                'acknowledged_at' => $acked ? now() : null,
            ]);
        }
    }

    private function seedDocuments(): void
    {
        $rows = [
            ['UV_C1618_Gelijkvloers_Wayfinding.pdf', 'C1618', 'Plan', '4,2 MB', '2026-09-11', 'L. Smet', 'Rev. C', true, 'L. Smet'],
            ['232146-T00-H ELEK SWI-E10.pdf', '232146', 'Schema', '1,8 MB', '2026-09-09', 'P. Aerts', 'Rev. A', true, 'P. Aerts'],
            ['C1618_ETS-export_0409.knxproj', 'C1618', 'ETS', '860 kB', '2026-09-04', 'K. Peeters', 'Rev. B', false, null],
            ['Hectaar_2e_verdieping_verlichting.pdf', '239870', 'Plan', '3,1 MB', '2026-09-02', 'L. Smet', 'Rev. B', true, 'L. Smet'],
            ['Linde_app21_keuringsverslag.pdf', '240512', 'Keuring', '420 kB', '2026-09-22', 'P. Aerts', 'Rev. A', true, null],
            ['Linde_app21_foto’s_oplevering.zip', '240512', 'Foto’s', '38 MB', '2026-09-23', 'T. Janssens', null, true, null],
        ];

        foreach ($rows as [$name, $code, $kind, $size, $uploadedAt, $uploadedBy, $revision, $isCurrent, $approvedBy]) {
            KnxDocument::create([
                'project_id' => $this->projects[$code]->id,
                'name' => $name,
                'kind' => $kind,
                // The mock stores display text; the API re-formats from bytes, so
                // the seed converts the same way the contract documents.
                'size_bytes' => $this->parseSize($size),
                'path' => 'knx/documents/'.$name,
                'revision' => $revision,
                'is_current' => $isCurrent,
                'uploaded_by_employee_id' => $this->people[$uploadedBy]->id,
                'approved_by_employee_id' => $approvedBy === null ? null : $this->people[$approvedBy]->id,
                'uploaded_at' => $uploadedAt,
            ]);
        }
    }

    /** "4,2 MB" → bytes, so the API can render the same text back. */
    private function parseSize(string $size): int
    {
        [$value, $unit] = explode(' ', $size);
        $number = (float) str_replace(',', '.', $value);

        return (int) round($number * match ($unit) {
            'kB' => 1024,
            'MB' => 1024 ** 2,
            'GB' => 1024 ** 3,
            default => 1,
        });
    }

    /** @return array<string, KnxZone> keyed by the mock's zone id */
    private function seedZones(): array
    {
        $pass = ['status' => KnxZoneCheck::STATUS_PASSED];
        $pending = ['status' => KnxZoneCheck::STATUS_PENDING];

        $rows = [
            ['z-c1618-inkomhal', 'C1618', 'Inkomhal', null, null, [
                'installed' => $pass, 'power' => $pass, 'bus' => $pass, 'device_ids' => $pass,
                'loads' => $pass, 'materials' => $pass, 'function_approved' => $pass, 'blockers' => $pass,
            ]],
            ['z-c1618-vergaderzaal', 'C1618', 'Vergaderzaal', 2, null, [
                'installed' => $pass, 'power' => $pass, 'bus' => $pass, 'device_ids' => $pass,
                'loads' => $pending,
                'function_approved' => ['status' => KnxZoneCheck::STATUS_FAILED, 'by' => 'S. Wouters', 'note' => 'DALI-driver niet geleverd — kan verlichting niet testen'],
                'blockers' => ['status' => KnxZoneCheck::STATUS_FAILED, 'by' => 'S. Wouters', 'note' => 'Levering driver gepland, elektricien volgt op'],
            ]],
            ['z-c1618-gang', 'C1618', 'Gang gelijkvloers', 4, null, [
                'installed' => $pass, 'power' => $pass, 'device_ids' => $pass, 'materials' => $pass,
            ]],
            ['z-c1618-bureau', 'C1618', 'Bureau 0.04', null, null, ['installed' => $pass]],
            ['z-239870-zaal203', '239870', 'Zaal 2.03', 1, null, [
                'installed' => $pass, 'power' => $pass, 'bus' => $pass, 'device_ids' => $pass,
                'materials' => ['status' => KnxZoneCheck::STATUS_FAILED, 'by' => 'M. Claes', 'note' => 'Plafondelementen ontbreken — 4 armaturen niet geplaatst'],
            ]],
            ['z-239870-gang2', '239870', 'Gang 2e', null, null, ['installed' => $pass, 'power' => $pass]],
            ['z-232146-bord-e10', '232146', 'Bord E10 — Sporthal', null, null, [
                'blockers' => ['status' => KnxZoneCheck::STATUS_FAILED, 'by' => 'P. Aerts', 'note' => 'Wacht op goedkeuring functielijst door gemeente'],
            ]],
        ];

        $zones = [];

        foreach ($rows as [$id, $code, $name, $reviewInDays, $floor, $checks]) {
            $project = $this->projects[$code];
            $room = KnxProjectRoom::query()->where('project_id', $project->id)->where('name', $name)->first();

            $zone = KnxZone::create([
                'project_id' => $project->id,
                'room_id' => $room?->id,
                'name' => $name,
                'floor' => $floor ?? 'Gelijkvloers',
                'next_review_at' => $reviewInDays === null ? null : now()->addDays($reviewInDays)->format('Y-m-d'),
            ]);

            foreach (KnxZoneCheck::keys() as $key) {
                $spec = $checks[$key] ?? $pending;

                KnxZoneCheck::create([
                    'zone_id' => $zone->id,
                    'key' => $key,
                    'status' => $spec['status'],
                    'note' => $spec['note'] ?? null,
                    'updated_by_employee_id' => isset($spec['by']) ? $this->people[$spec['by']]->id : null,
                    'updated_at' => $spec['status'] === KnxZoneCheck::STATUS_PENDING
                        ? null
                        : now()->subDays(1)->setTime(14, 0),
                ]);
            }

            $zones[$id] = $zone;
        }

        return $zones;
    }

    /**
     * @param  array<string, KnxZone>  $zones
     * @return array<string, KnxFunctionSpec> keyed by the mock's function id
     */
    private function seedFunctionSpecs(array $zones): array
    {
        $rows = [
            ['f-c1618-1', 'C1618', 'z-c1618-vergaderzaal', 'Verlichting vergaderzaal', 'Verlichting aan bij gebruik, uit bij verlaten van de ruimte.', 'approved', 3, 'L. Smet', 'L. Smet', '2026-09-15 10:00:00'],
            ['f-c1618-2', 'C1618', 'z-c1618-gang', 'Aanwezigheid + verlichting gang', 'Gangverlichting volgt beweging; handmatige bediening gaat voor.', 'proposed', 1, 'P. Aerts', null, null],
            ['f-c1618-3', 'C1618', 'z-c1618-bureau', 'Zonwering zuidgevel', 'Zonwering sluit bij hoge zoninstraling.', 'draft', 1, 'L. Smet', null, null],
            ['f-239870-1', '239870', 'z-239870-zaal203', 'Verlichting zaal 2.03', 'Traploze verlichting met aanwezigheidssturing.', 'changed', 2, 'M. Claes', null, null],
        ];

        $extras = [
            'f-c1618-1' => [
                'triggers' => ['Aanwezigheidsdetector', 'Drukknop 1 (manueel)', 'Drukknop 2 (scènes)'],
                'conditions' => ['Alleen tussen 06:00 en 22:00 voor automatische inschakeling'],
                'manual_controls' => ['Kort drukken = aan/uit', 'Dubbel drukken = scène vergaderen'],
                'automations' => ['Automatisch uit 10 minuten na laatste beweging'],
                'timings' => 'Nalooptijd 10 min · dimniveau 80% · scène 40%',
                'priorities' => 'Manuele bediening heeft voorrang op automatisering',
                'failure_behaviour' => 'Na stroomherstel: vorige toestand hersteld binnen 5 s',
                'dependencies' => ['DALI-gateway verdieping 1'],
                'acceptance_criteria' => ['Licht aan binnen 1 s na detectie', 'Handmatige uitschakeling blijft behouden'],
                'group_addresses' => ['1/1/10', '1/1/11', '1/1/12'],
                'dpts' => ['DPT 1.001', 'DPT 5.001'],
                'knx_objects' => ['Schakelen', 'Dimmen relatief', 'Status'],
            ],
            'f-c1618-2' => [
                'triggers' => ['Aanwezigheidsdetector gang'],
                'conditions' => ['Nachtmodus na 22:00 op 20%'],
                'manual_controls' => ['Drukknop bij inkomhal'],
                'automations' => ['Nachtmodus 22:00–06:00'],
                'timings' => 'Nalooptijd 5 min',
                'priorities' => 'Handmatig > automatisch',
                'failure_behaviour' => 'Blijft uit na stroomherstel (veiligheid)',
                'acceptance_criteria' => ['Handmatige uitschakeling niet overschreven door automatisering'],
                'group_addresses' => ['1/1/20'],
                'dpts' => ['DPT 1.001'],
                'knx_objects' => ['Schakelen'],
            ],
            'f-c1618-3' => [
                'conditions' => ['Alleen bij zoninstraling > 60 klx'],
                'triggers' => ['Weerstation zuidgevel'],
                'automations' => ['Automatisch sluiten bij overtemperatuur'],
                'dependencies' => ['Weerstation dak', 'Buitentemperatuursensor'],
            ],
            'f-239870-1' => [
                'triggers' => ['Aanwezigheidsdetector', 'Drukknop wand'],
                'manual_controls' => ['Kort drukken = aan/uit'],
                'automations' => ['Automatisch uit 15 min na laatste beweging'],
                'timings' => 'Nalooptijd 15 min · dimniveau 100%',
                'failure_behaviour' => 'Na stroomherstel: uit',
                'acceptance_criteria' => ['Dimmen werkt vloeiend van 10% tot 100%'],
                'group_addresses' => ['2/1/01'],
                'dpts' => ['DPT 5.001'],
                'knx_objects' => ['Dimmen absoluut'],
            ],
        ];

        $functions = [];

        foreach ($rows as [$id, $code, $zoneId, $name, $objective, $status, $version, $author, $approvedBy, $approvedAt]) {
            $function = KnxFunctionSpec::create(array_merge([
                'project_id' => $this->projects[$code]->id,
                'zone_id' => $zones[$zoneId]->id,
                'name' => $name,
                'objective' => $objective,
                'status' => $status,
                'version' => $version,
                'author_employee_id' => $this->people[$author]->id,
                'approved_by_employee_id' => $approvedBy === null ? null : $this->people[$approvedBy]->id,
                'approved_at' => $approvedAt,
            ], $extras[$id] ?? []));

            $functions[$id] = $function;
        }

        return $functions;
    }

    /**
     * @param  array<string, KnxFunctionSpec>  $functions
     */
    private function seedAcceptanceTests(array $functions): void
    {
        $rows = [
            ['f-c1618-1', 'Beweging in de ruimte', 'Licht aan binnen 1 s op 80%', null, 'pending', null, true, null, null, null],
            ['f-c1618-1', 'Kort drukken op knop 1', 'Handmatige bediening krijgt voorrang, licht uit', null, 'pending', null, true, null, null, null],
            ['f-c1618-1', 'Status terugmelden naar bedienpaneel', 'Status binnen 2 s correct op het paneel', null, 'pending', null, false, null, null, null],
            ['f-c1618-1', 'Stroomuitval en herstel', 'Vorige toestand hersteld binnen 5 s', null, 'pending', null, true, null, null, null],
            ['f-c1618-2', 'Handmatig uitschakelen tijdens automatisering', 'Licht blijft uit, automatisering wordt niet hervat', 'Automatisering schakelt het licht na 5 min opnieuw in', 'failed', 'Automatisme overschrijft manuele uitschakeling — prioriteit controleren', true, 'S. Wouters', '2026-09-24 11:20:00', 'iss-t-c1618-5'],
            ['f-239870-1', 'Dimmen van 10% naar 100%', 'Vloeiend dimmen zonder flikkering', null, 'blocked', 'Wacht op DALI-driver (materiaal ontbreekt)', true, 'M. Claes', '2026-09-23 09:00:00', null],
        ];

        foreach ($rows as [$functionId, $action, $expected, $observed, $status, $note, $physical, $executor, $executedAt, $issueId]) {
            $function = $functions[$functionId];

            KnxAcceptanceTest::create([
                'project_id' => $function->project_id,
                'function_id' => $function->id,
                'action' => $action,
                'expected' => $expected,
                'observed' => $observed,
                'status' => $status,
                'note' => $note,
                'physical_check' => $physical,
                'executor_employee_id' => $executor === null ? null : $this->people[$executor]->id,
                'executed_at' => $executedAt,
                'evidence_urls' => [],
                'issue_id' => $issueId,
            ]);
        }
    }

    private function seedExports(): void
    {
        $rows = [
            ['ets', 'C1618', 1, [16, 10]],
            ['delivery', '228104', null, null],
            ['dossier', '228104', null, null],
        ];

        foreach ($rows as [$type, $code, $daysAgo, $time]) {
            $createdAt = $daysAgo === null
                ? now()->subDays(29)->setTime(10, 0)
                : now()->subDays($daysAgo)->setTime($time[0], $time[1]);

            KnxExport::create([
                'project_id' => $this->projects[$code]->id,
                'type' => $type,
                'status' => KnxExport::STATUS_READY,
                'path' => 'knx/exports/'.$code.'-'.$type.'.pdf',
                'created_by_employee_id' => $this->people['L. Smet']->id,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }
    }
}
