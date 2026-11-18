<?php

declare(strict_types=1);

namespace Modules\Knx\Services\AiSpend;

/**
 * Prices an OpenAI call in USD from `knx.ai_spend.models` (USD per 1M tokens).
 *
 * `reservationFor` is a CONSERVATIVE UPPER BOUND made before sending (all input uncached,
 * the whole `max_output_tokens`, every image at its maximum); `actualCost` is the real cost
 * from the Responses `usage` object, or null when that cannot be computed.
 * Above 272,000 input tokens the whole request is priced input x2, cached x2, output x1.5.
 */
class AiSpendEstimator
{
    public const LONG_CONTEXT_TOKENS = 272000;

    public const OPERATION_PLAN_ANALYSIS = 'plan_analysis';

    public const OPERATION_IMAGE = 'image';

    /**
     * @param  int  $textBytes  size of the request body WITHOUT the image data
     * @param  int  $images  input images (plan analysis) or generated images (`image`)
     *
     * @throws AiSpendRefused `pricing_unknown` when the model has no price
     */
    public function reservationFor(
        string $model,
        int $textBytes,
        int $images,
        int $maxOutputTokens,
        int $attempts = 1,
        string $operation = self::OPERATION_PLAN_ANALYSIS,
    ): float {
        $price = $this->price($model);
        $isImage = $operation === self::OPERATION_IMAGE;
        if ($isImage && ! isset($price['per_image_usd'])) {
            throw new AiSpendRefused('pricing_unknown', "No image price is configured for `{$model}`.");
        }

        // A byte-level BPE token decodes to at least one byte: the byte count is a true upper
        // bound of the text tokens (bytes / 2 was only a heuristic).
        $inputTokens = max(0, $textBytes);
        if (! $isImage) {
            $inputTokens += (int) ($price['image_input_tokens_max'] ?? 30000) * max(0, $images);
        }
        [$inputMultiplier, $outputMultiplier] = $this->multipliers($inputTokens);

        $usd = ($inputTokens * (float) $price['input'] * $inputMultiplier
            + max(0, $maxOutputTokens) * (float) $price['output'] * $outputMultiplier) / 1_000_000;
        if ($isImage) {
            $usd += (float) $price['per_image_usd'] * max(0, $images);
        }

        return $this->roundUp($usd * $this->residency() * max(1, $attempts));
    }

    /**
     * Real cost from a Responses `usage` object; null when it is missing or incomplete.
     *
     * @param  array<string, mixed>  $usage
     */
    public function actualCost(string $model, array $usage): ?float
    {
        return $this->breakdown($model, $usage)['costUsd'];
    }

    /**
     * Token counts and cost of a call; `costUsd` is null when it cannot be computed (no
     * price for the model, or input/output tokens missing).
     *
     * @param  array<string, mixed>  $usage
     * @return array{inputTokens: ?int, cachedTokens: ?int, outputTokens: ?int, costUsd: ?float}
     */
    public function breakdown(string $model, array $usage): array
    {
        $input = isset($usage['input_tokens']) && is_numeric($usage['input_tokens']) ? (int) $usage['input_tokens'] : null;
        $output = isset($usage['output_tokens']) && is_numeric($usage['output_tokens']) ? (int) $usage['output_tokens'] : null;
        $cached = isset($usage['input_tokens_details']['cached_tokens']) && is_numeric($usage['input_tokens_details']['cached_tokens'])
            ? (int) $usage['input_tokens_details']['cached_tokens'] : null;

        $result = ['inputTokens' => $input, 'cachedTokens' => $cached, 'outputTokens' => $output, 'costUsd' => null];
        $price = $this->priceOrNull($model);
        if ($input === null || $output === null || $price === null) {
            return $result;
        }

        // Without a cached count every token is priced as uncached: never below the real cost.
        $cachedTokens = min(max(0, $cached ?? 0), $input);
        [$inputMultiplier, $outputMultiplier] = $this->multipliers($input);
        $usd = (($input - $cachedTokens) * (float) $price['input'] * $inputMultiplier
            + $cachedTokens * (float) $price['cached_input'] * $inputMultiplier
            + $output * (float) $price['output'] * $outputMultiplier) / 1_000_000;
        $result['costUsd'] = $this->roundUp($usd * $this->residency());

        return $result;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws AiSpendRefused
     */
    private function price(string $model): array
    {
        return $this->priceOrNull($model)
            ?? throw new AiSpendRefused('pricing_unknown', "No price is configured for model `{$model}`.");
    }

    /**
     * @return array<string, mixed>|null
     */
    private function priceOrNull(string $model): ?array
    {
        $price = ((array) config('knx.ai_spend.models', []))[$model] ?? null;
        if (! is_array($price) || ! isset($price['input'], $price['output'])) {
            return null;
        }

        return $price + ['cached_input' => $price['input']];
    }

    /**
     * @return array{0: float, 1: float} input/cached multiplier, output multiplier
     */
    private function multipliers(int $inputTokens): array
    {
        return $inputTokens > self::LONG_CONTEXT_TOKENS ? [2.0, 1.5] : [1.0, 1.0];
    }

    private function residency(): float
    {
        return max(1.0, (float) config('knx.ai_spend.data_residency_multiplier', 1.0));
    }

    private function roundUp(float $usd): float
    {
        return round(ceil(round($usd * 1_000_000, 4)) / 1_000_000, 6);
    }
}
