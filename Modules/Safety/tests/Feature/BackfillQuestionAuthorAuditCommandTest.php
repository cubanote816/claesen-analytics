<?php

declare(strict_types=1);

namespace Modules\Safety\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Safety\Models\Checklist;
use Modules\Safety\Models\Question;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BackfillQuestionAuthorAuditCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'project_manager', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    }

    private const ORELVYS_EMAIL = 'orelvys.cuellar@claesen-verlichting.be';
    private const BERT_EMAIL    = 'bert.Bertels@claesen-verlichting.be';

    // UTC equivalents of Europe/Brussels 2026-06-14 (UTC+2 in summer)
    private const SUNDAY_IN_WINDOW     = '2026-06-14 10:00:00'; // UTC — falls within window
    private const SUNDAY_OUTSIDE_UTC   = '2026-06-15 00:00:00'; // UTC — outside window

    private function createAuthors(): array
    {
        $orelvys = \Database\Factories\UserFactory::new()->create([
            'name'  => 'Orelvys Cuellar (Claesen Outdoor Lighting)',
            'email' => self::ORELVYS_EMAIL,
        ]);
        $bert = \Database\Factories\UserFactory::new()->create([
            'name'  => 'Bert Bertels',
            'email' => self::BERT_EMAIL,
        ]);

        return [$orelvys, $bert];
    }

    public function test_dry_run_shows_counts_without_modifying_data(): void
    {
        [$orelvys, $bert] = $this->createAuthors();
        $checklist = Checklist::factory()->create();

        // 3 questions with no author info
        Question::factory()->count(3)->create(['checklist_id' => $checklist->id]);

        // 1 question that was "modified on Sunday" — stamp updated_at directly
        $sundayQuestion = Question::factory()->create(['checklist_id' => $checklist->id]);
        DB::table('safety_questions')
            ->where('id', $sundayQuestion->id)
            ->update(['updated_at' => self::SUNDAY_IN_WINDOW]);

        $this->artisan('safety:backfill-question-authors')
            ->expectsOutputToContain('DRY-RUN')
            ->assertExitCode(0);

        // No data modified
        $this->assertEquals(4, DB::table('safety_questions')->whereNull('created_by_user_id')->count());
        $this->assertEquals(4, DB::table('safety_questions')->whereNull('updated_by_user_id')->count());
    }

    public function test_apply_sets_created_by_user_id_for_all_null_questions(): void
    {
        [$orelvys] = $this->createAuthors();
        $checklist = Checklist::factory()->create();
        Question::factory()->count(3)->create(['checklist_id' => $checklist->id]);

        $this->artisan('safety:backfill-question-authors --apply')->assertExitCode(0);

        $this->assertEquals(0, DB::table('safety_questions')->whereNull('created_by_user_id')->count());
        $this->assertEquals(3, DB::table('safety_questions')->where('created_by_user_id', $orelvys->id)->count());
    }

    public function test_apply_sets_updated_by_user_id_for_sunday_questions(): void
    {
        [$orelvys, $bert] = $this->createAuthors();
        $checklist = Checklist::factory()->create();

        $sundayQuestion = Question::factory()->create(['checklist_id' => $checklist->id]);
        DB::table('safety_questions')
            ->where('id', $sundayQuestion->id)
            ->update(['updated_at' => self::SUNDAY_IN_WINDOW]);

        $otherQuestion = Question::factory()->create(['checklist_id' => $checklist->id]);
        DB::table('safety_questions')
            ->where('id', $otherQuestion->id)
            ->update(['updated_at' => self::SUNDAY_OUTSIDE_UTC]);

        $this->artisan('safety:backfill-question-authors --apply')->assertExitCode(0);

        $this->assertEquals($bert->id, DB::table('safety_questions')->where('id', $sundayQuestion->id)->value('updated_by_user_id'));
        $this->assertNull(DB::table('safety_questions')->where('id', $otherQuestion->id)->value('updated_by_user_id'));
    }

    public function test_apply_does_not_modify_updated_at_timestamps(): void
    {
        $this->createAuthors();
        $checklist = Checklist::factory()->create();
        $question  = Question::factory()->create(['checklist_id' => $checklist->id]);

        DB::table('safety_questions')
            ->where('id', $question->id)
            ->update(['updated_at' => self::SUNDAY_IN_WINDOW]);

        $this->artisan('safety:backfill-question-authors --apply')->assertExitCode(0);

        $storedUpdatedAt = DB::table('safety_questions')->where('id', $question->id)->value('updated_at');
        $this->assertEquals(self::SUNDAY_IN_WINDOW, $storedUpdatedAt);
    }

    public function test_apply_does_not_overwrite_existing_created_by(): void
    {
        [$orelvys, $bert] = $this->createAuthors();
        $checklist = Checklist::factory()->create();

        // Question already has created_by set to bert
        Question::factory()->create([
            'checklist_id'       => $checklist->id,
            'created_by_user_id' => $bert->id,
        ]);

        $this->artisan('safety:backfill-question-authors --apply')->assertExitCode(0);

        // Must NOT be overwritten with orelvys
        $this->assertEquals($bert->id, DB::table('safety_questions')->value('created_by_user_id'));
    }

    public function test_apply_is_idempotent(): void
    {
        [$orelvys, $bert] = $this->createAuthors();
        $checklist = Checklist::factory()->create();

        $q = Question::factory()->create(['checklist_id' => $checklist->id]);
        DB::table('safety_questions')
            ->where('id', $q->id)
            ->update(['updated_at' => self::SUNDAY_IN_WINDOW]);

        $this->artisan('safety:backfill-question-authors --apply')->assertExitCode(0);
        $this->artisan('safety:backfill-question-authors --apply')->assertExitCode(0);

        // Same result after two runs
        $row = DB::table('safety_questions')->where('id', $q->id)->first();
        $this->assertEquals($orelvys->id, $row->created_by_user_id);
        $this->assertEquals($bert->id, $row->updated_by_user_id);
    }

    public function test_fails_gracefully_when_orelvys_not_found(): void
    {
        // Only create bert, not orelvys
        \Database\Factories\UserFactory::new()->create([
            'name'  => 'Bert Bertels',
            'email' => self::BERT_EMAIL,
        ]);

        $this->artisan('safety:backfill-question-authors --apply')
            ->expectsOutputToContain(self::ORELVYS_EMAIL)
            ->assertExitCode(1);
    }

    public function test_fails_gracefully_when_bert_not_found(): void
    {
        // Only create orelvys, not bert
        \Database\Factories\UserFactory::new()->create([
            'name'  => 'Orelvys Cuellar (Claesen Outdoor Lighting)',
            'email' => self::ORELVYS_EMAIL,
        ]);

        $this->artisan('safety:backfill-question-authors --apply')
            ->expectsOutputToContain(self::BERT_EMAIL)
            ->assertExitCode(1);
    }
}
