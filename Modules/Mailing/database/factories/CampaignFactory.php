<?php

namespace Modules\Mailing\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Mailing\Enums\CampaignStatus;
use Modules\Mailing\Enums\TemplateCategory;
use Modules\Mailing\Models\Campaign;

class CampaignFactory extends Factory
{
    protected $model = Campaign::class;

    public function definition(): array
    {
        return [
            'created_by'                   => null,
            'template_name'                => fake()->words(3, true),
            'description'                  => fake()->sentence(),
            'subject_snapshot'             => fake()->sentence(6),
            'body_snapshot'                => fake()->paragraph(),
            'template_category_snapshot'   => TemplateCategory::COMMERCIAL->value,
            'preference_category_snapshot' => 'offers',
            'total_count'                  => 0,
            'sent_count'                   => 0,
            'failed_count'                 => 0,
            'skipped_count'                => 0,
            'status'                       => CampaignStatus::DRAFT,
        ];
    }

    public function approved(): static
    {
        return $this->state(['status' => CampaignStatus::APPROVED]);
    }

    public function inReview(): static
    {
        return $this->state(['status' => CampaignStatus::REVIEW]);
    }

    public function completed(): static
    {
        return $this->state(['status' => CampaignStatus::COMPLETED]);
    }

    /** Commercial campaign sending to the offers preference segment (default). */
    public function commercial(string $prefCategory = 'offers'): static
    {
        return $this->state([
            'template_category_snapshot'   => TemplateCategory::COMMERCIAL->value,
            'preference_category_snapshot' => $prefCategory,
        ]);
    }

    public function transactional(): static
    {
        return $this->state([
            'template_category_snapshot'   => TemplateCategory::TRANSACTIONAL->value,
            'preference_category_snapshot' => null,
        ]);
    }

    /** Campaign without snapshot data (pre-MAI-PREF-001 state for backfill tests). */
    public function withoutSnapshots(): static
    {
        return $this->state([
            'template_category_snapshot'   => null,
            'preference_category_snapshot' => null,
        ]);
    }
}
