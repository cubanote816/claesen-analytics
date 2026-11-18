<?php

declare(strict_types=1);

namespace Modules\Knx\Services\PlanAnalysis;

use Modules\Knx\Models\KnxPlanCleanProfile;

/**
 * The JSON Schema every provider answer must follow (OpenAI Structured Outputs, strict).
 *
 * Strict mode needs every property listed in `required` and `additionalProperties: false`;
 * optional values are expressed as nullable types instead.
 */
final class PlanAnalysisSchema
{
    public const NAME = 'plan_analysis';

    /**
     * @return array<string, mixed>
     */
    public static function schema(): array
    {
        $fraction = ['type' => 'number', 'minimum' => 0, 'maximum' => 1];
        $confidence = ['type' => 'number', 'minimum' => 0, 'maximum' => 1];
        $reason = ['type' => 'string'];

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['groupRoles', 'rooms', 'uncertain'],
            'properties' => [
                'groupRoles' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['groupId', 'role', 'confidence', 'reason'],
                        'properties' => [
                            'groupId' => ['type' => 'string'],
                            'role' => ['type' => 'string', 'enum' => KnxPlanCleanProfile::ROLES],
                            'confidence' => $confidence,
                            'reason' => $reason,
                        ],
                    ],
                ],
                'rooms' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['name', 'textId', 'x', 'y', 'confidence', 'reason'],
                        'properties' => [
                            'name' => ['type' => ['string', 'null']],
                            'textId' => ['type' => ['string', 'null']],
                            'x' => $fraction,
                            'y' => $fraction,
                            'confidence' => $confidence,
                            'reason' => $reason,
                        ],
                    ],
                ],
                'uncertain' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['x', 'y', 'reason'],
                        'properties' => ['x' => $fraction, 'y' => $fraction, 'reason' => $reason],
                    ],
                ],
            ],
        ];
    }
}
