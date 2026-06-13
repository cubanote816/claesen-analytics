<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intelligence_mirror_project_links', function (Blueprint $table) {
            $table->string('project_id', 20);
            $table->string('estimate_id', 20);
            $table->smallInteger('link_type');  // project_estimates.type (1, 2, 3 — meaning managed via bi_config)
            $table->timestamps();

            $table->primary(['project_id', 'estimate_id']);
            $table->index('estimate_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intelligence_mirror_project_links');
    }
};
