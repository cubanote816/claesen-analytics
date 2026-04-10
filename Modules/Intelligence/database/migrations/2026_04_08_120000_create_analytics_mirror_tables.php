<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Projects Mirror
        Schema::create('intelligence_mirror_projects', function (Blueprint $blueprint) {
            $blueprint->string('id', 15)->primary(); // Match legacy ID (trim applied in sync)
            $blueprint->string('name')->nullable();
            $blueprint->integer('relation_id')->nullable()->index();
            $blueprint->string('category')->nullable()->index(); // 'aard'
            $blueprint->string('zipcode', 15)->nullable()->index();
            $blueprint->string('city')->nullable();
            $blueprint->boolean('fl_active')->default(true);
            $blueprint->timestamp('last_modified_at')->nullable();
            $blueprint->timestamps();
        });

        // 2. Employees/Technicians Mirror
        Schema::create('intelligence_mirror_employees', function (Blueprint $blueprint) {
            $blueprint->integer('id')->primary();
            $blueprint->string('name')->nullable();
            $blueprint->string('zipcode', 15)->nullable()->index();
            $blueprint->string('specialty')->nullable();
            $blueprint->boolean('fl_active')->default(true);
            $blueprint->timestamps();
        });

        // 3. Labor Types (WU Codes)
        Schema::create('intelligence_mirror_labor_types', function (Blueprint $blueprint) {
            $blueprint->integer('id')->primary();
            $blueprint->string('ref', 15)->nullable()->index(); // e.g., WU NORM, WU VERPL
            $blueprint->string('name')->nullable();
            $blueprint->timestamps();
        });

        // 4. Labor Logs Mirror
        Schema::create('intelligence_mirror_labor', function (Blueprint $blueprint) {
            $blueprint->string('id', 50)->primary(); // Match legacy ID type
            $blueprint->string('project_id', 15)->index();
            $blueprint->integer('employee_id')->index();
            $blueprint->integer('labor_id')->index();
            $blueprint->float('hours')->default(0);
            $blueprint->date('date')->index();
            $blueprint->timestamps();
        });

        // 5. Materials Mirror
        Schema::create('intelligence_mirror_materials', function (Blueprint $blueprint) {
            $blueprint->integer('id')->primary();
            $blueprint->string('ref', 25)->nullable()->index();
            $blueprint->text('description')->nullable();
            $blueprint->float('cost_price')->default(0);
            $blueprint->date('last_price_date')->nullable();
            $blueprint->boolean('fl_active')->default(true);
            $blueprint->timestamps();
        });

        // 6. Costs Mirror (MAMO)
        Schema::create('intelligence_mirror_costs', function (Blueprint $blueprint) {
            $blueprint->string('id', 50)->primary(); // Match legacy ID type
            $blueprint->string('project_id', 15)->index();
            $blueprint->integer('art_id')->nullable()->index()->comment('FK to MirrorMaterial');
            $blueprint->string('descr')->nullable();
            $blueprint->string('type', 1)->comment('M=Material, A=Arbeid, M=Materieel, O=Onderaanneming');
            $blueprint->float('cost_price')->default(0);
            $blueprint->float('quantity')->default(1);
            $blueprint->string('extra_type')->nullable()->comment('extra costs like Insurance, Drawings');
            $blueprint->date('date')->nullable()->index();
            $blueprint->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intelligence_mirror_costs');
        Schema::dropIfExists('intelligence_mirror_materials');
        Schema::dropIfExists('intelligence_mirror_labor');
        Schema::dropIfExists('intelligence_mirror_labor_types');
        Schema::dropIfExists('intelligence_mirror_employees');
        Schema::dropIfExists('intelligence_mirror_projects');
    }
};
