<?php

namespace Modules\Knx\Services;

use Modules\Knx\Models\KnxConflict;
use Modules\Knx\Models\KnxDevice;
use Modules\Knx\Models\KnxExport;
use Modules\Knx\Models\KnxProject;
use Modules\Knx\Models\KnxZone;

/**
 * What each report actually contains.
 *
 * Every type is written as **CSV** in this version, and that is a declared
 * deviation: §4.9 describes the *presentation* (dossier and delivery as PDF, hours
 * as XLSX), and those templates do not exist yet. What matters is that the file
 * carries the real data and says what it is — a `.pdf` extension on a CSV would be
 * a lie, and an empty file would be worse.
 *
 *   - `ets`      → the ETS correction **worklist**: one line per conflict with the
 *                  address in play and the last workflow step. This is the module's
 *                  reason to exist, and it is complete.
 *   - `dossier`  → every registered apparatus of the project.
 *   - `delivery` → the zones and their readiness, plus the acceptance-test counts.
 *   - `hours`    → **not produced**: nothing in this domain tracks time (Veld does
 *                  not report it yet), so the endpoint refuses the type instead of
 *                  inventing numbers. See KnxReportType.
 */
class ExportService
{
    public function csvFor(KnxExport $export): string
    {
        $project = $export->project;

        if ($project === null) {
            return '';
        }

        return match ($export->type) {
            KnxExport::TYPE_ETS => $this->etsWorklist($project),
            KnxExport::TYPE_DOSSIER => $this->dossier($project),
            KnxExport::TYPE_DELIVERY => $this->delivery($project),
            default => '',
        };
    }

    public function filenameFor(KnxExport $export): string
    {
        return sprintf(
            '%s-%s-%d.csv',
            $export->project?->code ?? 'onbekend',
            $export->type,
            $export->getKey(),
        );
    }

    /**
     * Semicolon-separated and CRLF-terminated: this file is opened in Excel on a
     * Belgian machine, where the comma is the decimal separator.
     */
    private function csv(array $header, iterable $rows): string
    {
        $lines = [implode(';', $header)];

        foreach ($rows as $row) {
            $lines[] = implode(';', array_map(static function ($value): string {
                $value = (string) ($value ?? '');

                return str_contains($value, ';') || str_contains($value, '"')
                    ? '"'.str_replace('"', '""', $value).'"'
                    : $value;
            }, $row));
        }

        return implode("\r\n", $lines)."\r\n";
    }

    private function etsWorklist(KnxProject $project): string
    {
        $conflicts = KnxConflict::query()
            ->where('project_id', $project->getKey())
            ->with(['logs', 'reportedBy'])
            ->ordered()
            ->get();

        return $this->csv(
            ['conflict', 'severity', 'type', 'address', 'proposal', 'status', 'last_step', 'last_step_at', 'device_existing', 'device_field', 'reported_by'],
            $conflicts->map(function (KnxConflict $conflict): array {
                $last = $conflict->logs->last();

                return [
                    $conflict->getKey(),
                    $conflict->severity,
                    $conflict->type,
                    $conflict->address,
                    $conflict->proposal,
                    $conflict->status,
                    $last?->action,
                    $last?->at->format('Y-m-d H:i'),
                    $conflict->device_existing,
                    $conflict->device_field,
                    $conflict->reportedBy?->shortName(),
                ];
            }),
        );
    }

    private function dossier(KnxProject $project): string
    {
        $devices = KnxDevice::query()
            ->where('project_id', $project->getKey())
            ->with(['room', 'board', 'registeredBy'])
            ->orderBy('address')
            ->get();

        return $this->csv(
            ['address', 'type', 'room', 'board', 'serial', 'source', 'registered_by', 'registered_at'],
            $devices->map(fn (KnxDevice $device): array => [
                $device->address,
                $device->type,
                $device->room?->name,
                $device->board?->code,
                $device->serial,
                $device->source,
                $device->registeredBy?->shortName(),
                $device->registered_at?->format('Y-m-d'),
            ]),
        );
    }

    private function delivery(KnxProject $project): string
    {
        $zones = KnxZone::query()
            ->where('project_id', $project->getKey())
            ->with(['checks', 'checks.updatedBy'])
            ->orderBy('id')
            ->get();

        $rows = $zones->map(function (KnxZone $zone): array {
            $blocker = $zone->blockingCheck();

            return [
                $zone->name,
                $zone->derivedStatus(),
                $zone->floor,
                $blocker?->note,
                $blocker?->updatedBy?->shortName(),
                $zone->next_review_at?->format('Y-m-d'),
            ];
        });

        $tests = $project->acceptanceTests()->get()->countBy('status');

        return $this->csv(
            ['zone', 'status', 'floor', 'blocking_reason', 'blocked_by', 'next_review_at'],
            $rows,
        )
        ."\r\n"
        .$this->csv(
            ['tests_pending', 'tests_passed', 'tests_failed', 'tests_blocked', 'tests_na'],
            [[
                $tests->get('pending', 0),
                $tests->get('passed', 0),
                $tests->get('failed', 0),
                $tests->get('blocked', 0),
                $tests->get('not_applicable', 0),
            ]],
        );
    }
}
