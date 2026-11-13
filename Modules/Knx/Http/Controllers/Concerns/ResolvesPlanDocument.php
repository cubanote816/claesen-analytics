<?php

declare(strict_types=1);

namespace Modules\Knx\Http\Controllers\Concerns;

use Modules\Knx\Models\KnxDocument;
use Modules\Knx\Models\KnxProject;
use Modules\Knx\Support\KnxTenant;

trait ResolvesPlanDocument
{
    /**
     * The document, resolved inside the project and the organization: another project's
     * or organization's document is a 404.
     */
    private function planDocument(string $code, string $documentId): KnxDocument
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
