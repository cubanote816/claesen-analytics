<?php

declare(strict_types=1);

namespace Modules\Knx\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
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

        return PlanCleanProfileResource::make(
            $this->profiles->forDocument($document, (int) ($validated['page'] ?? 1)),
        )->resolve($request);
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
        ]);

        $this->assertValidRoles($validated['roles']);

        /** @var KnxEmployee $employee */
        $employee = $request->attributes->get('knx_employee');

        return PlanCleanProfileResource::make(
            $this->profiles->replace($document, $employee, $validated),
        )->resolve($request);
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
