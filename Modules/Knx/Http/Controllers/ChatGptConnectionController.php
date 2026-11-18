<?php

declare(strict_types=1);

namespace Modules\Knx\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Knx\Models\KnxEmployee;
use Modules\Knx\Services\ChatGpt\ChatGptConnections;
use Modules\Knx\Services\ChatGpt\ChatGptFailed;

/**
 * "Sign in with ChatGPT" (prueba): each office employee connects their OWN account.
 * Sign-in and permission to use the plan are reported separately. Tokens never leave
 * the server.
 */
class ChatGptConnectionController extends Controller
{
    public function __construct(private readonly ChatGptConnections $connections) {}

    public function show(Request $request): array
    {
        return $this->connections->status($this->employee($request));
    }

    public function start(Request $request): JsonResponse
    {
        try {
            $url = $this->connections->authorizationUrl($this->employee($request));
        } catch (ChatGptFailed $failure) {
            return $this->failure($failure);
        }

        return response()->json(['authorizationUrl' => $url]);
    }

    public function callback(Request $request): JsonResponse
    {
        $input = $request->validate([
            'state' => ['nullable', 'string', 'max:512'],
            'code' => ['nullable', 'string', 'max:4096'],
            'error' => ['nullable', 'string', 'max:255'],
            'error_description' => ['nullable', 'string', 'max:2000'],
        ]);

        $employee = $this->employee($request);

        try {
            $result = $this->connections->complete(
                $employee,
                (string) ($input['state'] ?? ''),
                $input['code'] ?? null,
                $input['error'] ?? null,
            );
        } catch (ChatGptFailed $failure) {
            return $this->failure($failure);
        }

        return response()->json($result + ['status' => $this->connections->status($employee)]);
    }

    public function destroy(Request $request): array
    {
        $employee = $this->employee($request);
        $this->connections->disconnect($employee);

        return $this->connections->status($employee);
    }

    private function employee(Request $request): KnxEmployee
    {
        return $request->attributes->get('knx_employee');
    }

    private function failure(ChatGptFailed $failure): JsonResponse
    {
        $status = match ($failure->reason) {
            'not_configured' => 409,
            'token_exchange_failed', 'discovery_failed' => 502,
            default => 422,
        };

        $code = 'chatgpt_'.$failure->reason;
        $body = ['message' => $failure->getMessage(), 'code' => $code, 'errors' => []];

        if ($failure->missingSettings !== []) {
            $body['missingSettings'] = $failure->missingSettings;
        }

        return response()->json($body, $status);
    }
}
