<?php

namespace Modules\Mailing\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Mailing\Enums\TemplateCategory;
use Modules\Mailing\Models\EmailTemplate;

class EmailTemplateFactory extends Factory
{
    protected $model = EmailTemplate::class;

    public function definition(): array
    {
        return [
            'name'                => fake()->unique()->words(4, true),
            'subject'             => fake()->sentence(6),
            'body'                => '<p>' . implode('</p><p>', fake()->paragraphs(2)) . '</p>',
            'category'            => TemplateCategory::COMMERCIAL,
            'preference_category' => 'offers',
            'variables'           => [],
            'version'             => 1,
        ];
    }

    /** Commercial template targeting the offers preference category (default). */
    public function asOffers(): static
    {
        return $this->state([
            'category'            => TemplateCategory::COMMERCIAL,
            'preference_category' => 'offers',
        ]);
    }

    public function asNewsletter(): static
    {
        return $this->state([
            'category'            => TemplateCategory::COMMERCIAL,
            'preference_category' => 'newsletter',
        ]);
    }

    public function asEvents(): static
    {
        return $this->state([
            'category'            => TemplateCategory::COMMERCIAL,
            'preference_category' => 'events',
        ]);
    }

    public function transactional(): static
    {
        return $this->state([
            'category'            => TemplateCategory::TRANSACTIONAL,
            'preference_category' => null,
        ]);
    }
}
