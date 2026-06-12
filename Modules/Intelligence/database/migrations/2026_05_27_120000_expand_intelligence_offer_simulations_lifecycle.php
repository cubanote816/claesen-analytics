<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds the simulation_hash column (used by BudgetAssistantService but
     * missing from the original migration) and a full lifecycle state machine
     * so simulations can be tracked from draft through CAFCA export and win/loss.
     */
    public function up(): void
    {
        Schema::table('intelligence_offer_simulations', function (Blueprint $table) {
            // Semantic fingerprint — nullable so existing records are not broken.
            if (!Schema::hasColumn('intelligence_offer_simulations', 'simulation_hash')) {
                $table->string('simulation_hash', 64)->nullable()->unique()->after('id');
            }

            if (!Schema::hasColumn('intelligence_offer_simulations', 'status')) {
                $table->enum('status', [
                    'draft',        // AI generated, not yet reviewed
                    'reviewed',     // Manager has looked at it
                    'approved',     // Director validated
                    'exported',     // Sent to CAFCA (manually)
                    'won',          // Offer accepted by client
                    'lost',         // Offer rejected
                ])->default('draft')->index()->after('simulation_hash');
            }

            if (!Schema::hasColumn('intelligence_offer_simulations', 'client_id')) {
                // Logical FK to intelligence_mirror_relations.id (no hard constraint across engines)
                $table->string('client_id')->nullable()->index()->after('status');
            }

            if (!Schema::hasColumn('intelligence_offer_simulations', 'variant')) {
                $table->enum('variant', ['economy', 'standard', 'premium'])
                    ->nullable()
                    ->after('client_id');
            }

            if (!Schema::hasColumn('intelligence_offer_simulations', 'parent_simulation_id')) {
                // Links the three variants (economy/standard/premium) to each other
                $table->unsignedBigInteger('parent_simulation_id')->nullable()->after('variant');
            }

            if (!Schema::hasColumn('intelligence_offer_simulations', 'approved_by')) {
                $table->string('approved_by')->nullable()->after('parent_simulation_id');
            }

            if (!Schema::hasColumn('intelligence_offer_simulations', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_by');
            }

            if (!Schema::hasColumn('intelligence_offer_simulations', 'exported_at')) {
                $table->timestamp('exported_at')->nullable()->after('approved_at');
            }

            if (!Schema::hasColumn('intelligence_offer_simulations', 'cafca_ref')) {
                // Filled manually by staff after entering the offer in CAFCA
                $table->string('cafca_ref')->nullable()->after('exported_at');
            }

            if (!Schema::hasColumn('intelligence_offer_simulations', 'outcome_notes')) {
                $table->text('outcome_notes')->nullable()->after('cafca_ref');
            }
        });
    }

    public function down(): void
    {
        Schema::table('intelligence_offer_simulations', function (Blueprint $table) {
            $columns = [
                'simulation_hash', 'status', 'client_id', 'variant',
                'parent_simulation_id', 'approved_by', 'approved_at',
                'exported_at', 'cafca_ref', 'outcome_notes',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('intelligence_offer_simulations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
