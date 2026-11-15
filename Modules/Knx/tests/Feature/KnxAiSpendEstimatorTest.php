<?php

declare(strict_types=1);

namespace Modules\Knx\Tests\Feature;

use Modules\Knx\Services\AiSpend\AiSpendEstimator;
use Modules\Knx\Services\AiSpend\AiSpendRefused;
use Tests\TestCase;

/**
 * The conservative USD estimate and the real cost of one OpenAI call (daily spend limit).
 * Pure arithmetic over `knx.ai_spend`: no database, no network.
 */
final class KnxAiSpendEstimatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'knx.ai_spend.data_residency_multiplier' => 1.0,
            'knx.ai_spend.models' => [
                'm-test' => ['input' => 1.00, 'cached_input' => 0.05, 'output' => 5.00, 'image_input_tokens_max' => 30000],
                'img-test' => ['input' => 1.00, 'cached_input' => 0.05, 'output' => 5.00, 'per_image_usd' => 0.20],
            ],
        ]);
    }

    private function estimator(): AiSpendEstimator
    {
        return app(AiSpendEstimator::class);
    }

    public function test_the_shipped_price_table_has_the_four_models_and_the_long_context_rule(): void
    {
        $models = require base_path('Modules/Knx/config/config.php');
        $spend = $models['ai_spend'];

        $this->assertSame(['gpt-6.1-terra', 'gpt-6-luna', 'gpt-6.1-sol', 'gpt-6-astra'], array_keys($spend['models']));
        $this->assertSame(['input' => 1.00, 'cached_input' => 0.05, 'output' => 5.00], array_intersect_key($spend['models']['gpt-6.1-terra'], array_flip(['input', 'cached_input', 'output'])));
        $this->assertSame(30000, $spend['models']['gpt-6.1-terra']['image_input_tokens_max']);
        $this->assertSame('Europe/Brussels', $spend['timezone']);
        $this->assertSame(0.0, (float) $spend['daily_limit_usd']);
        $this->assertSame(2, $spend['max_calls_per_processing']);
        $this->assertSame(0.50, (float) $spend['max_cost_per_processing_usd']);
    }

    public function test_the_reservation_is_an_upper_bound_with_image_tokens_and_max_output(): void
    {
        // text: 2000 bytes / 2 = 1000 tokens; image: 30000; output: 4000.
        // (31000 * 1.00 + 4000 * 5.00) / 1e6 = 0.051
        $this->assertSame(0.051, $this->estimator()->reservationFor('m-test', 2000, 1, 4000));
    }

    public function test_the_text_estimate_rounds_up_and_the_result_rounds_up_to_six_decimals(): void
    {
        // 3 bytes -> 2 tokens; (2 * 1.00 + 1 * 5.00) / 1e6 = 7e-6
        $this->assertSame(0.000007, $this->estimator()->reservationFor('m-test', 3, 0, 1));
        // 1 token at $0.05/1M would be 5e-8: still rounded UP to a millionth.
        config(['knx.ai_spend.models.m-test' => ['input' => 0.05, 'cached_input' => 0.01, 'output' => 0.05]]);
        $this->assertSame(0.000001, $this->estimator()->reservationFor('m-test', 1, 0, 1));
    }

    public function test_above_272k_input_tokens_the_whole_request_uses_the_long_context_prices(): void
    {
        // 540000 bytes -> 270000 text tokens + 30000 image = 300000 > 272000.
        // input x2, output x1.5: (300000 * 2.00 + 1000 * 5.00 * 1.5) / 1e6 = 0.6075
        $this->assertSame(0.6075, $this->estimator()->reservationFor('m-test', 540000, 1, 1000));
        // Exactly at the threshold is NOT long context: 242000 + 30000 = 272000.
        $this->assertSame(0.272, $this->estimator()->reservationFor('m-test', 484000, 1, 0));
    }

    public function test_the_residency_multiplier_and_attempts_scale_the_reservation(): void
    {
        config(['knx.ai_spend.data_residency_multiplier' => 1.1]);
        $this->assertSame(0.0561, $this->estimator()->reservationFor('m-test', 2000, 1, 4000));
        $this->assertSame(0.1122, $this->estimator()->reservationFor('m-test', 2000, 1, 4000, attempts: 2));
    }

    public function test_an_image_operation_is_priced_per_generated_image(): void
    {
        $this->assertSame(0.4, $this->estimator()->reservationFor('img-test', 0, 2, 0, operation: 'image'));
        // A model without `per_image_usd` cannot be reserved for an image operation.
        $this->expectException(AiSpendRefused::class);
        $this->estimator()->reservationFor('m-test', 0, 1, 0, operation: 'image');
    }

    public function test_a_model_without_a_price_cannot_be_reserved(): void
    {
        try {
            $this->estimator()->reservationFor('no-such-model', 10, 1, 10);
            $this->fail('Expected AiSpendRefused.');
        } catch (AiSpendRefused $e) {
            $this->assertSame('pricing_unknown', $e->reason);
        }
        $this->assertNull($this->estimator()->actualCost('no-such-model', ['input_tokens' => 1, 'output_tokens' => 1]));
    }

    public function test_the_actual_cost_prices_cached_input_and_includes_reasoning_in_the_output(): void
    {
        $usage = [
            'input_tokens' => 10000,
            'input_tokens_details' => ['cached_tokens' => 4000],
            'output_tokens' => 2000,
            'output_tokens_details' => ['reasoning_tokens' => 1500],
        ];

        // uncached 6000 * 1.00 + cached 4000 * 0.05 + output 2000 * 5.00 = 6000 + 200 + 10000 = 16200 / 1e6
        $this->assertSame(0.0162, $this->estimator()->actualCost('m-test', $usage));
        $this->assertSame(
            ['inputTokens' => 10000, 'cachedTokens' => 4000, 'outputTokens' => 2000, 'costUsd' => 0.0162],
            $this->estimator()->breakdown('m-test', $usage),
        );
    }

    public function test_the_actual_cost_applies_long_context_and_residency(): void
    {
        config(['knx.ai_spend.data_residency_multiplier' => 1.1]);
        $usage = ['input_tokens' => 300000, 'input_tokens_details' => ['cached_tokens' => 100000], 'output_tokens' => 1000];

        // (200000 * 2.00 + 100000 * 0.05 * 2 + 1000 * 5.00 * 1.5) / 1e6 = 0.4175 ; x1.1 = 0.45925
        $this->assertSame(0.45925, $this->estimator()->actualCost('m-test', $usage));
    }

    public function test_missing_or_incomplete_usage_means_the_cost_is_uncertain(): void
    {
        $this->assertNull($this->estimator()->actualCost('m-test', []));
        $this->assertNull($this->estimator()->actualCost('m-test', ['input_tokens' => 10]));
        $this->assertNull($this->estimator()->actualCost('m-test', ['output_tokens' => 10]));
        $this->assertNull($this->estimator()->actualCost('m-test', ['input_tokens' => null, 'output_tokens' => 5]));
        $this->assertSame(
            ['inputTokens' => 10, 'cachedTokens' => null, 'outputTokens' => null, 'costUsd' => null],
            $this->estimator()->breakdown('m-test', ['input_tokens' => 10]),
        );
    }
}
