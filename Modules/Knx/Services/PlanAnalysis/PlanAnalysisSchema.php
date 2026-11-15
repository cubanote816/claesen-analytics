<?php

declare(strict_types=1);

namespace Modules\Knx\Services\PlanAnalysis;

use Modules\Knx\Models\KnxPlanCleanProfile;
use Modules\Knx\Models\KnxPlanDevice;

/**
 * The JSON Schema every provider answer must follow (OpenAI Structured Outputs, strict).
 * It is the provisional `plan-analysis/2` proposal (not a definitive contract).
 *
 * Strict mode needs every property listed in `required` and `additionalProperties: false`;
 * optional values are expressed as nullable types instead.
 */
final class PlanAnalysisSchema
{
    public const NAME = 'plan_analysis';

    public const PLAN_KINDS = ['dwelling', 'other', 'unknown'];

    public const SYMBOL_ROLES = ['furniture', 'device', 'hidden', 'unknown'];

    public const ROOM_KINDS = ['room', 'corridor', 'hall', 'storage', 'toilet', 'stair', 'lift', 'other'];

    /** Below this confidence a device is kept but must be reviewed. */
    public const REVIEW_CONFIDENCE = 0.6;

    /**
     * @return array<string, mixed>
     */
    public static function schema(): array
    {
        $fraction = ['type' => 'number', 'minimum' => 0, 'maximum' => 1];
        $confidence = ['type' => 'number', 'minimum' => 0, 'maximum' => 1];
        $reason = ['type' => 'string'];
        $nullableString = ['type' => ['string', 'null']];

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['planKind', 'groupRoles', 'symbols', 'legend', 'rooms', 'uncertain'],
            'properties' => [
                'planKind' => ['type' => 'string', 'enum' => self::PLAN_KINDS],
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
                'symbols' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['symbolId', 'role', 'deviceType', 'labelTextId', 'confidence', 'reason'],
                        'properties' => [
                            'symbolId' => ['type' => 'string'],
                            'role' => ['type' => 'string', 'enum' => self::SYMBOL_ROLES],
                            'deviceType' => ['type' => ['string', 'null'], 'enum' => [...KnxPlanDevice::TYPES, null]],
                            'labelTextId' => $nullableString,
                            'confidence' => $confidence,
                            'reason' => $reason,
                        ],
                    ],
                ],
                'legend' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['symbolId', 'textId', 'deviceType'],
                        'properties' => [
                            'symbolId' => $nullableString,
                            'textId' => ['type' => 'string'],
                            'deviceType' => ['type' => 'string', 'enum' => KnxPlanDevice::TYPES],
                        ],
                    ],
                ],
                'rooms' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['name', 'textId', 'x', 'y', 'kind', 'confidence', 'reason', 'geometryNullReason'],
                        'properties' => [
                            'name' => $nullableString,
                            'textId' => $nullableString,
                            'x' => $fraction,
                            'y' => $fraction,
                            'kind' => ['type' => 'string', 'enum' => self::ROOM_KINDS],
                            'confidence' => $confidence,
                            'reason' => $reason,
                            'geometryNullReason' => $nullableString,
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
