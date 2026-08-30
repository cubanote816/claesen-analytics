<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicateActivePositions = DB::table('fo_luminaires')
            ->whereNull('deleted_at')
            ->select('luminaire_frame_id', 'frame_position', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('luminaire_frame_id', 'frame_position')
            ->having('aggregate', '>', 1)
            ->exists();

        if ($duplicateActivePositions) {
            throw new RuntimeException('Cannot create luminaire positions while active luminaires share the same frame position.');
        }

        Schema::create('fo_luminaire_positions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('luminaire_frame_id')->constrained('fo_luminaire_frames')->cascadeOnDelete();
            $table->unsignedInteger('frame_position');
            $table->decimal('frame_x', 8, 4)->default(0);
            $table->decimal('frame_y', 8, 4)->default(0);
            $table->float('scale_x')->nullable();
            $table->float('scale_y')->nullable();
            $table->unsignedInteger('position_version')->default(1);
            $table->string('position_source', 32)->nullable();
            $table->foreignId('position_verified_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('position_verified_at')->nullable();
            $table->timestamps();

            $table->unique(['luminaire_frame_id', 'frame_position'], 'fo_luminaire_positions_frame_slot_unique');
        });

        Schema::table('fo_luminaires', function (Blueprint $table): void {
            $table->foreignId('luminaire_position_id')
                ->nullable()
                ->after('luminaire_frame_id')
                ->constrained('fo_luminaire_positions')
                ->cascadeOnDelete();
            $table->foreignId('active_position_id')
                ->nullable()
                ->after('luminaire_position_id')
                ->constrained('fo_luminaire_positions')
                ->nullOnDelete();
            $table->timestamp('installed_at')->nullable()->after('cafca_material_id');
            $table->timestamp('removed_at')->nullable()->after('installed_at');
            $table->text('removal_reason')->nullable()->after('removed_at');
            $table->foreignId('replaced_by_luminaire_id')
                ->nullable()
                ->after('removal_reason')
                ->constrained('fo_luminaires')
                ->nullOnDelete();

            $table->unique('active_position_id', 'fo_luminaires_one_active_per_position');
        });

        DB::table('fo_luminaires')
            ->orderByRaw('deleted_at IS NULL DESC')
            ->orderBy('id')
            ->get()
            ->each(function (object $luminaire): void {
                $positionId = DB::table('fo_luminaire_positions')
                    ->where('luminaire_frame_id', $luminaire->luminaire_frame_id)
                    ->where('frame_position', $luminaire->frame_position)
                    ->value('id');

                if ($positionId === null) {
                    $positionId = DB::table('fo_luminaire_positions')->insertGetId([
                        'luminaire_frame_id' => $luminaire->luminaire_frame_id,
                        'frame_position' => $luminaire->frame_position,
                        'frame_x' => $luminaire->frame_x,
                        'frame_y' => $luminaire->frame_y,
                        'scale_x' => $luminaire->scale_x,
                        'scale_y' => $luminaire->scale_y,
                        'position_version' => max((int) ($luminaire->position_version ?? 1), 1),
                        'position_source' => $luminaire->position_source,
                        'position_verified_by_user_id' => $luminaire->position_verified_by_user_id,
                        'position_verified_at' => $luminaire->position_verified_at,
                        'created_at' => $luminaire->created_at ?? now(),
                        'updated_at' => $luminaire->updated_at ?? now(),
                    ]);
                }

                DB::table('fo_luminaires')->where('id', $luminaire->id)->update([
                    'luminaire_position_id' => $positionId,
                    'active_position_id' => $luminaire->deleted_at === null ? $positionId : null,
                    'installed_at' => $luminaire->created_at ?? now(),
                    'removed_at' => $luminaire->deleted_at,
                ]);
            });

        Schema::table('fo_maintenance_records', function (Blueprint $table): void {
            $table->foreignId('luminaire_position_id')
                ->nullable()
                ->after('maintainable_type')
                ->constrained('fo_luminaire_positions')
                ->nullOnDelete();
            $table->foreignId('replacement_from_luminaire_id')
                ->nullable()
                ->after('luminaire_position_id')
                ->constrained('fo_luminaires')
                ->nullOnDelete();
            $table->foreignId('replacement_to_luminaire_id')
                ->nullable()
                ->after('replacement_from_luminaire_id')
                ->constrained('fo_luminaires')
                ->nullOnDelete();
            $table->text('replacement_reason')->nullable()->after('replacement_to_luminaire_id');
        });

        DB::table('fo_maintenance_records')
            ->where('maintainable_type', \Modules\FieldOps\Models\Luminaire::class)
            ->orderBy('id')
            ->get()
            ->each(function (object $record): void {
                $positionId = DB::table('fo_luminaires')
                    ->where('id', $record->maintainable_id)
                    ->value('luminaire_position_id');

                DB::table('fo_maintenance_records')->where('id', $record->id)->update([
                    'luminaire_position_id' => $positionId,
                ]);
            });

        DB::table('fo_maintenance_types')->updateOrInsert(
            ['code' => 'replacement'],
            [
                'name' => json_encode([
                    'nl' => 'Vervanging',
                    'en' => 'Replacement',
                    'fr' => 'Remplacement',
                    'de' => 'Austausch',
                ], JSON_UNESCAPED_UNICODE),
                'ai_translation_status' => 'completed',
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        $replacementTypeId = DB::table('fo_maintenance_types')->where('code', 'replacement')->value('id');
        $correctiveTypeId = DB::table('fo_maintenance_types')->where('code', 'corrective')->value('id');

        if ($replacementTypeId && $correctiveTypeId) {
            DB::table('fo_maintenance_records')
                ->where('fo_maintenance_type_id', $replacementTypeId)
                ->update(['fo_maintenance_type_id' => $correctiveTypeId]);
            DB::table('fo_maintenance_types')->where('id', $replacementTypeId)->delete();
        }

        Schema::table('fo_maintenance_records', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('replacement_to_luminaire_id');
            $table->dropConstrainedForeignId('replacement_from_luminaire_id');
            $table->dropConstrainedForeignId('luminaire_position_id');
            $table->dropColumn('replacement_reason');
        });

        Schema::table('fo_luminaires', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('replaced_by_luminaire_id');
            $table->dropUnique('fo_luminaires_one_active_per_position');
            $table->dropConstrainedForeignId('active_position_id');
            $table->dropConstrainedForeignId('luminaire_position_id');
            $table->dropColumn(['installed_at', 'removed_at', 'removal_reason']);
        });

        Schema::dropIfExists('fo_luminaire_positions');
    }
};
