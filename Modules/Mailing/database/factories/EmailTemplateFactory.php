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
            'name'      => fake()->unique()->words(4, true),
            'subject'   => fake()->sentence(6),
            'body'      => '<p>' . implode('</p><p>', fake()->paragraphs(2)) . '</p>',
            'category'  => TemplateCategory::COMMERCIAL,
            'variables' => [],
            'version'   => 1,
        ];
    }
}
