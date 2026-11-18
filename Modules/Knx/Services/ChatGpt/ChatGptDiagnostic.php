<?php

declare(strict_types=1);

namespace Modules\Knx\Services\ChatGpt;

use Modules\Knx\Models\KnxChatGptConnection;
use Modules\Knx\Models\KnxEmployee;
use Modules\Knx\Services\PlanAnalysis\ChatGptPlanAnalysisProvider;
use Modules\Knx\Services\PlanAnalysis\PlanAnalysisFailed;
use Modules\Knx\Services\PlanAnalysis\PlanAnalysisInput;
use Modules\Knx\Services\PlanAnalysis\PlanAnalysisValidator;

/**
 * The "Sign in with ChatGPT" test, step by step, for ONE employee's own account. It stops
 * at the first step that is not ok (the rest is `skipped`), says why, and never switches
 * to another billing: no API key, no `openai` provider. Nothing is stored.
 *
 * Steps: registration → sign_in → plan_permission → models → synthetic_analysis. The last
 * one sends ONLY the server's synthetic diagnostic plan; no document is involved.
 */
final class ChatGptDiagnostic
{
    public const STEPS = ['registration', 'sign_in', 'plan_permission', 'models', 'synthetic_analysis'];

    private const INPUT = __DIR__.'/../../resources/plan-analysis/diagnostic/plan-clean-page2';

    public function __construct(
        private readonly ChatGptSettings $settings,
        private readonly ChatGptConnections $connections,
        private readonly ChatGptModels $models,
        private readonly ChatGptPlanAnalysisProvider $provider,
        private readonly PlanAnalysisValidator $validator,
    ) {}

    /**
     * @return array{provider: string, billing: string, steps: list<array<string, mixed>>, stoppedAt: ?string}
     */
    public function run(KnxEmployee $employee, ?string $model): array
    {
        $model = $model !== null && trim($model) !== '' ? trim($model) : null;
        $context = ['connection' => null, 'models' => null];
        $steps = [];
        $stoppedAt = null;

        foreach (self::STEPS as $key) {
            if ($stoppedAt !== null) {
                $steps[] = $this->step($key, 'skipped');

                continue;
            }

            $step = $this->{'run'.str_replace('_', '', ucwords($key, '_'))}($employee, $model, $context);
            $steps[] = $step;
            if ($step['status'] !== 'ok') {
                $stoppedAt = $key;
            }
        }

        return ['provider' => 'chatgpt', 'billing' => 'subscription', 'steps' => $steps, 'stoppedAt' => $stoppedAt];
    }

    /** @param  array<string, mixed>  $context */
    private function runRegistration(KnxEmployee $employee, ?string $model, array &$context): array
    {
        $missing = $this->settings->missing();

        return $missing === []
            ? $this->step('registration', 'ok')
            : $this->step('registration', 'failed', 'not_configured', ['missingSettings' => $missing]);
    }

    /** @param  array<string, mixed>  $context */
    private function runSignIn(KnxEmployee $employee, ?string $model, array &$context): array
    {
        $connection = $this->connections->connectionFor($employee);

        if ($connection === null) {
            return $this->step('sign_in', 'failed', 'not_signed_in');
        }
        if ($connection->status !== KnxChatGptConnection::STATUS_CONNECTED) {
            return $this->step('sign_in', 'failed', 'needs_sign_in', ['identity' => ['email' => $connection->email, 'name' => $connection->name]]);
        }
        $context['connection'] = $connection;

        return $this->step('sign_in', 'ok', null, ['identity' => ['email' => $connection->email, 'name' => $connection->name]]);
    }

    /** @param  array<string, mixed>  $context */
    private function runPlanPermission(KnxEmployee $employee, ?string $model, array &$context): array
    {
        /** @var KnxChatGptConnection $connection */
        $connection = $context['connection'];

        if (! $connection->plan_permission) {
            return $this->step('plan_permission', 'failed', 'plan_permission_missing', ['scopes' => array_values($connection->scopes ?? [])]);
        }

        try {
            $connection = $this->connections->usableToken($connection)['connection'];
        } catch (ChatGptFailed $failure) {
            return $this->step('plan_permission', 'failed', $failure->reason === 'needs_sign_in' ? 'needs_sign_in' : 'refresh_failed');
        }
        if (! $connection->plan_permission) {
            return $this->step('plan_permission', 'failed', 'plan_permission_missing', ['scopes' => array_values($connection->scopes ?? [])]);
        }
        $context['connection'] = $connection;

        return $this->step('plan_permission', 'ok', null, ['scopes' => array_values($connection->scopes ?? [])]);
    }

    /** @param  array<string, mixed>  $context */
    private function runModels(KnxEmployee $employee, ?string $model, array &$context): array
    {
        try {
            $models = $this->models->list($context['connection']);
        } catch (ChatGptFailed $failure) {
            $api = $failure->api;

            return $this->step('models', 'failed', $api?->reason() ?? $failure->reason, $api === null ? null : [
                'status' => $api->status, 'code' => $api->code, 'param' => $api->param, 'requestId' => $api->requestId,
            ]);
        }
        $context['models'] = $models;
        $detail = ['models' => $models, 'imageGeneration' => 'unsupported'];

        if ($model === null) {
            return $this->step('models', 'needs_input', 'model_required', $detail);
        }
        if (! in_array($model, array_column($models, 'slug'), true)) {
            return $this->step('models', 'failed', 'model_not_available', $detail);
        }

        return $this->step('models', 'ok', null, $detail);
    }

    /** @param  array<string, mixed>  $context */
    private function runSyntheticAnalysis(KnxEmployee $employee, ?string $model, array &$context): array
    {
        $listed = array_column($context['models'], 'imageInput', 'slug');
        if (($listed[$model] ?? null) === 'no') {
            return $this->step('synthetic_analysis', 'failed', 'plan_unsupported_capability', ['imageInput' => 'no', 'model' => $model]);
        }

        try {
            $input = $this->input();
            $result = $this->provider->forEmployee($employee, $model)->analyze($input, fn (): bool => true);
            $proposal = $this->validator->validate($input, $result->proposal);
        } catch (PlanAnalysisFailed $failure) {
            return $this->step('synthetic_analysis', 'failed', $failure->reason, [
                'status' => $failure->providerStatus,
                'code' => $failure->providerCode,
                'param' => $failure->providerParam,
                'requestId' => $failure->requestId,
                'imageInput' => $failure->reason === 'plan_unsupported_capability' ? 'no' : 'unknown',
            ]);
        }

        $roles = [];
        foreach ($proposal['groupRoles'] as $entry) {
            $roles[$entry['role']] = ($roles[$entry['role']] ?? 0) + 1;
        }

        return $this->step('synthetic_analysis', 'ok', null, [
            'model' => $result->model,
            'imageInput' => 'yes',
            'groupRoles' => (object) $roles,
            'rooms' => count($proposal['rooms']),
            'namedRooms' => count(array_filter($proposal['rooms'], fn (array $room): bool => $room['name'] !== null)),
            'uncertain' => count($proposal['uncertain']),
            'dropped' => $proposal['dropped'],
        ]);
    }

    private function input(): PlanAnalysisInput
    {
        $json = json_decode((string) file_get_contents(self::INPUT.'.input.json'), true, 512, JSON_THROW_ON_ERROR);

        return new PlanAnalysisInput(
            page: (int) $json['page'],
            pageWidthPt: (float) $json['pageWidthPt'],
            pageHeightPt: (float) $json['pageHeightPt'],
            imagePng: (string) file_get_contents(self::INPUT.'.png'),
            groups: $json['groups'],
            texts: $json['texts'],
        );
    }

    /**
     * @param  array<string, mixed>|null  $detail
     * @return array{key: string, status: string, reason: ?string, detail: ?array<string, mixed>}
     */
    private function step(string $key, string $status, ?string $reason = null, ?array $detail = null): array
    {
        return ['key' => $key, 'status' => $status, 'reason' => $reason, 'detail' => $detail];
    }
}
