<?php

declare(strict_types=1);

namespace Modules\Knx\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Knx\Models\KnxEmployee;
use Modules\Knx\Services\ChatGpt\ChatGptDiagnostic;

/**
 * "Sign in with ChatGPT" test run: registration, sign-in, plan permission, models and a
 * synthetic plan, in that order, stopping at the first step that is not ok. Always HTTP
 * 200: a stopped test is a result, not an error. Never uses an API key.
 */
class ChatGptDiagnosticController extends Controller
{
    public function __construct(private readonly ChatGptDiagnostic $diagnostic) {}

    public function run(Request $request): array
    {
        $input = $request->validate(['model' => ['nullable', 'string', 'max:128']]);

        /** @var KnxEmployee $employee */
        $employee = $request->attributes->get('knx_employee');

        return $this->diagnostic->run($employee, $input['model'] ?? null);
    }
}
