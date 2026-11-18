<?php

declare(strict_types=1);

namespace Modules\Knx\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Knx\Http\Resources\PlanCleanProfileResource;
use Modules\Knx\Models\KnxDocument;
use Modules\Knx\Models\KnxEmployee;
use Modules\Knx\Models\KnxPlanCleanProfile;
use Modules\Knx\Models\KnxProject;
use Modules\Knx\Services\PlanCleanProfileService;
use Modules\Knx\Support\KnxTenant;

/**
 * The clean-plan profile (group → role) of one page of a plan revision (KNX-48).
 *
 * Addressed like the room model: by project code and document id, with the document
 * resolved INSIDE the project, so another project's or organization's document is a 404.
 * No stored profile is a normal `200` with empty `roles`, not an error.
 */
class PlanCleanProfileController extends Controller
{
    public function __construct(private readonly PlanCleanProfileService $profiles) {}

    public function show(Request $request, string $code, string $documentId): array
    {
        $validated = $request->validate([
            'page' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ]);

        $document = $this->document($code, $documentId);

        return $this->present($request, $this->profiles->forDocument($document, (int) ($validated['page'] ?? 1)));
    }

    public function update(Request $request, string $code, string $documentId): array
    {
        $document = $this->document($code, $documentId);

        $validated = $request->validate([
            'page' => ['required', 'integer', 'min:1'],
            // `present`: an empty object clears the overrides. The entries are checked
            // by hand below because group ids carry dots (`layer:A-WALL.1`), which the
            // dotted wildcard rules would read as nesting.
            'roles' => ['present', 'array', 'max:500'],
            'processingVersion' => ['sometimes', 'nullable', 'string', 'max:32'],
            // Virtual boundaries (detection-only barriers): at most 200 segments of two
            // page-fraction points each. Omitted = none (the profile is replaced whole).
            'boundaries' => ['sometimes', 'array', 'max:200'],
            'boundaries.*' => ['array', 'size:2'],
            'boundaries.*.*' => ['array', 'size:2'],
            'boundaries.*.*.*' => ['numeric', 'between:0,1'],
            // Roles of single symbols (`s<index>`, plano IA v2). Replaced whole like the
            // boundaries: omitted = none. The entries are checked by hand below.
            'symbolRoles' => ['sometimes', 'array', 'max:3000'],
        ]);

        $this->assertValidRoles($validated['roles']);
        $this->assertValidSymbolRoles($validated['symbolRoles'] ?? []);
        $this->assertNoDegenerateBoundary($validated['boundaries'] ?? []);

        /** @var KnxEmployee $employee */
        $employee = $request->attributes->get('knx_employee');

        $saved = DB::transaction(function () use ($document, $employee, $validated): array {
            $saved = $this->profiles->replace($document, $employee, $validated);
            $saved['profile']?->forceFill(['symbol_roles' => $validated['symbolRoles'] ?? []])->save();

            return $saved;
        });

        return $this->present($request, $saved);
    }

    /**
     * The profile payload, with the roles of symbols next to the roles of groups. Always a
     * JSON object (`{}` when empty), like `roles`.
     *
     * @param  array{document: KnxDocument, page: int, profile: KnxPlanCleanProfile|null}  $saved
     * @return array<string, mixed>
     */
    private function present(Request $request, array $saved): array
    {
        $payload = PlanCleanProfileResource::make($saved)->resolve($request);
        $symbolRoles = (object) ($saved['profile']?->symbol_roles ?? []);

        $out = [];
        foreach ($payload as $key => $value) {
            if ($key === 'updatedAt') {
                $out['symbolRoles'] = $symbolRoles;
            }
            $out[$key] = $value;
        }

        return $out;
    }

    /**
     * @param  array<int|string, mixed>  $symbolRoles
     */
    private function assertValidSymbolRoles(array $symbolRoles): void
    {
        foreach ($symbolRoles as $symbolId => $role) {
            if (preg_match('/^s\d{1,9}$/', (string) $symbolId) !== 1
                || ! is_string($role) || ! in_array($role, KnxPlanCleanProfile::SYMBOL_ROLES, true)) {
                throw ValidationException::withMessages(['symbolRoles' => [
                    'Every key must be a symbol id (s<digits>) and every role one of: '.implode(', ', KnxPlanCleanProfile::SYMBOL_ROLES).'.',
                ]]);
            }
        }
    }

    /**
     * A boundary whose two ends coincide closes nothing and is rejected.
     *
     * @param  array<int, array<int, array<int, int|float>>>  $boundaries
     */
    private function assertNoDegenerateBoundary(array $boundaries): void
    {
        foreach ($boundaries as $i => [$a, $b]) {
            if ((float) $a[0] === (float) $b[0] && (float) $a[1] === (float) $b[1]) {
                throw ValidationException::withMessages(["boundaries.{$i}" => ['The two ends of a boundary must differ.']]);
            }
        }
    }

    /**
     * @param  array<int|string, mixed>  $roles
     */
    private function assertValidRoles(array $roles): void
    {
        $errors = [];

        foreach ($roles as $groupId => $role) {
            $key = (string) $groupId;

            if ($key === '' || mb_strlen($key) > 255) {
                $errors['roles'] = ['A group id must be a string of 1 to 255 characters.'];

                continue;
            }

            if (! is_string($role) || ! in_array($role, KnxPlanCleanProfile::ROLES, true)) {
                $errors["roles.{$key}"] = ['The role must be one of: '.implode(', ', KnxPlanCleanProfile::ROLES).'.'];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * The document, resolved inside the project and the organization.
     */
    private function document(string $code, string $documentId): KnxDocument
    {
        $project = KnxProject::query()
            ->where('code', $code)
            ->where('organization_id', KnxTenant::organizationId())
            ->firstOrFail();

        return KnxDocument::query()
            ->where('project_id', $project->getKey())
            ->findOrFail($documentId);
    }
}
