<?php

declare(strict_types=1);

namespace Modules\Knx\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\Knx\Http\Controllers\Concerns\ResolvesPlanDocument;
use Modules\Knx\Http\Resources\PlanDeviceResource;
use Modules\Knx\Models\KnxDocument;
use Modules\Knx\Models\KnxEmployee;
use Modules\Knx\Models\KnxPlanDevice;

/**
 * The KNX devices of one page of a plan revision (KNX-48, plano IA v2).
 *
 * Addressed like the markers and the clean profile: by project code and document id, with
 * the document resolved INSIDE the project and organization (anything else is a 404).
 * `PUT` replaces the page's whole set in one transaction: a failing request changes nothing.
 */
class PlanDeviceController extends Controller
{
    use ResolvesPlanDocument;

    public function show(Request $request, string $code, string $documentId): array
    {
        $validated = $request->validate(['page' => ['sometimes', 'nullable', 'integer', 'min:1']]);
        $document = $this->planDocument($code, $documentId);

        return ['devices' => $this->devices($document, (int) ($validated['page'] ?? 1))];
    }

    public function update(Request $request, string $code, string $documentId): array
    {
        $document = $this->planDocument($code, $documentId);

        $validated = $request->validate([
            'page' => ['required', 'integer', 'min:1'],
            'devices' => ['present', 'array', 'max:1000'],
            'devices.*.provisionalId' => ['required', 'string', 'regex:/^D\d{1,6}$/', 'distinct'],
            'devices.*.type' => ['required', 'string', Rule::in(KnxPlanDevice::TYPES)],
            'devices.*.nx' => ['required', 'numeric', 'between:0,1'],
            'devices.*.ny' => ['required', 'numeric', 'between:0,1'],
            'devices.*.bbox' => ['required', 'array:x,y,w,h'],
            'devices.*.bbox.x' => ['required', 'numeric', 'between:0,1'],
            'devices.*.bbox.y' => ['required', 'numeric', 'between:0,1'],
            'devices.*.bbox.w' => ['required', 'numeric', 'between:0,1'],
            'devices.*.bbox.h' => ['required', 'numeric', 'between:0,1'],
            'devices.*.rotationDeg' => ['nullable', 'numeric', 'between:-360,360'],
            'devices.*.symbolId' => ['required', 'string', 'regex:/^s\d{1,9}$/'],
            'devices.*.label' => ['nullable', 'string', 'max:255'],
            'devices.*.status' => ['required', 'string', Rule::in(KnxPlanDevice::STATUSES)],
            'devices.*.doubts' => ['present', 'array', 'max:20'],
            'devices.*.doubts.*' => ['string', 'max:200'],
            // A proposal of THIS document, never of another one.
            'devices.*.analysisId' => ['nullable', 'integer', Rule::exists('knx_plan_analyses', 'id')->where('document_id', $document->getKey())],
        ]);

        /** @var KnxEmployee $employee */
        $employee = $request->attributes->get('knx_employee');
        $page = (int) $validated['page'];

        DB::transaction(function () use ($document, $employee, $page, $validated): void {
            KnxPlanDevice::query()
                ->where('document_id', $document->getKey())
                ->where('page', $page)
                ->delete();

            foreach ($validated['devices'] as $device) {
                KnxPlanDevice::query()->create([
                    'project_id' => $document->project_id,
                    'document_id' => $document->getKey(),
                    'page' => $page,
                    'document_revision' => $document->revision,
                    'provisional_id' => $device['provisionalId'],
                    'type' => $device['type'],
                    'nx' => (float) $device['nx'],
                    'ny' => (float) $device['ny'],
                    'bbox' => array_map('floatval', [
                        'x' => $device['bbox']['x'], 'y' => $device['bbox']['y'],
                        'w' => $device['bbox']['w'], 'h' => $device['bbox']['h'],
                    ]),
                    'rotation_deg' => isset($device['rotationDeg']) ? (float) $device['rotationDeg'] : null,
                    'symbol_id' => $device['symbolId'],
                    'label' => $device['label'] ?? null,
                    'status' => $device['status'],
                    'doubts' => array_values($device['doubts']),
                    'analysis_id' => isset($device['analysisId']) ? (int) $device['analysisId'] : null,
                    'updated_by_employee_id' => $employee->getKey(),
                ]);
            }
        });

        return ['devices' => $this->devices($document, $page)];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function devices(KnxDocument $document, int $page): array
    {
        return KnxPlanDevice::query()
            ->where('document_id', $document->getKey())
            ->where('page', $page)
            ->orderBy('id')
            ->get()
            ->map(fn (KnxPlanDevice $device): array => PlanDeviceResource::make($device)->resolve())
            ->all();
    }
}
