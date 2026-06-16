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

    private const ORELVYS_EMAIL = 'orelvys.cuellar@claesen-verlichting.be';
    private const BERT_EMAIL    = 'bert.Bertels@claesen-verlichting.be';

    // Timestamps to simulate the three production groups
    private const PRE_SUNDAY_CREATED  = '2026-05-13 13:33:35'; // created before 2026-06-14 (group 1)
    private const SUNDAY_IN_WINDOW    = '2026-06-14 10:00:00'; // UTC within Brussels 2026-06-14 window
    private const OUTSIDE_WINDOW      = '2026-06-15 00:00:00'; // UTC after Sunday window

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'project_manager', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    }

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

    // -- Group 1: pre-Sunday questions (created before 2026-06-14) --

    public function test_pre_sunday_questions_get_orelvys_as_created_by(): void
    {
        [$orelvys] = $this->createAuthors();
        $checklist = Checklist::factory()->create();
        $q = Question::factory()->create(['checklist_id' => $checklist->id]);

        DB::table('safety_questions')->where('id', $q->id)->update([
            'created_at' => self::PRE_SUNDAY_CREATED,
            'updated_at' => self::SUNDAY_IN_WINDOW, // edited on Sunday by Bert
        ]);

        $this->artisan('safety:backfill-question-authors --apply')->assertExitCode(0);

        $this->assertEquals($orelvys->id, DB::table('safety_questions')->where('id', $q->id)->value('created_by_user_id'));
    }

    public function test_pre_sunday_questions_modified_on_sunday_get_bert_as_updated_by(): void
    {
        [$orelvys, $bert] = $this->createAuthors();
        $checklist = Checklist::factory()->create();
        $q = Question::factory()->create(['checklist_id' => $checklist->id]);

        DB::table('safety_questions')->where('id', $q->id)->update([
            'created_at' => self::PRE_SUNDAY_CREATED,
            'updated_at' => self::SUNDAY_IN_WINDOW,
        ]);

        $this->artisan('safety:backfill-question-authors --apply')->assertExitCode(0);

        $this->assertEquals($bert->id, DB::table('safety_questions')->where('id', $q->id)->value('updated_by_user_id'));
    }

    // -- Group 2: created AND edited on Sunday (created_at ≠ updated_at) --

    public function test_sunday_created_and_edited_questions_get_bert_as_created_by(): void
    {
        [$orelvys, $bert] = $this->createAuthors();
        $checklist = Checklist::factory()->create();
        $q = Question::factory()->create(['checklist_id' => $checklist->id]);

        DB::table('safety_questions')->where('id', $q->id)->update([
            'created_at' => '2026-06-14 12:46:22',
            'updated_at' => '2026-06-14 12:51:26', // different — was edited
        ]);

        $this->artisan('safety:backfill-question-authors --apply')->assertExitCode(0);

        $row = DB::table('safety_questions')->where('id', $q->id)->first();
        $this->assertEquals($bert->id, $row->created_by_user_id);
        $this->assertEquals($bert->id, $row->updated_by_user_id);
    }

    // -- Group 3: created on Sunday, never edited (created_at = updated_at) --

    public function test_sunday_created_never_edited_gets_bert_as_created_by_and_null_updated_by(): void
    {
        [$orelvys, $bert] = $this->createAuthors();
        $checklist = Checklist::factory()->create();
        $q = Question::factory()->create(['checklist_id' => $checklist->id]);

        DB::table('safety_questions')->where('id', $q->id)->update([
            'created_at' => self::SUNDAY_IN_WINDOW,
            'updated_at' => self::SUNDAY_IN_WINDOW, // identical — never edited
        ]);

        $this->artisan('safety:backfill-question-authors --apply')->assertExitCode(0);

        $row = DB::table('safety_questions')->where('id', $q->id)->first();
        $this->assertEquals($bert->id, $row->created_by_user_id);
        $this->assertNull($row->updated_by_user_id);
    }

    // -- General behaviour --

    public function test_dry_run_shows_counts_without_modifying_data(): void
    {
        $this->createAuthors();
        $checklist = Checklist::factory()->create();
        Question::factory()->count(3)->create(['checklist_id' => $checklist->id]);

        $this->artisan('safety:backfill-question-authors')
            ->expectsOutputToContain('DRY-RUN')
            ->assertExitCode(0);

        $this->assertEquals(3, DB::table('safety_questions')->whereNull('created_by_user_id')->count());
        $this->assertEquals(3, DB::table('safety_questions')->whereNull('updated_by_user_id')->count());
    }

    public function test_apply_does_not_modify_updated_at_timestamps(): void
    {
        $this->createAuthors();
        $checklist = Checklist::factory()->create();
        $q = Question::factory()->create(['checklist_id' => $checklist->id]);

        DB::table('safety_questions')->where('id', $q->id)->update([
            'created_at' => self::PRE_SUNDAY_CREATED,
            'updated_at' => self::SUNDAY_IN_WINDOW,
        ]);

        $this->artisan('safety:backfill-question-authors --apply')->assertExitCode(0);

        $this->assertEquals(
            self::SUNDAY_IN_WINDOW,
            DB::table('safety_questions')->where('id', $q->id)->value('updated_at')
        );
    }

    public function test_apply_does_not_overwrite_existing_created_by(): void
    {
        [$orelvys, $bert] = $this->createAuthors();
        $checklist = Checklist::factory()->create();

        Question::factory()->create([
            'checklist_id'       => $checklist->id,
            'created_by_user_id' => $bert->id,
        ]);

        $this->artisan('safety:backfill-question-authors --apply')->assertExitCode(0);

        $this->assertEquals($bert->id, DB::table('safety_questions')->value('created_by_user_id'));
    }

    public function test_apply_is_idempotent(): void
    {
        [$orelvys, $bert] = $this->createAuthors();
        $checklist = Checklist::factory()->create();
        $q = Question::factory()->create(['checklist_id' => $checklist->id]);

        DB::table('safety_questions')->where('id', $q->id)->update([
            'created_at' => self::PRE_SUNDAY_CREATED,
            'updated_at' => self::SUNDAY_IN_WINDOW,
        ]);

        $this->artisan('safety:backfill-question-authors --apply')->assertExitCode(0);
        $this->artisan('safety:backfill-question-authors --apply')->assertExitCode(0);

        $row = DB::table('safety_questions')->where('id', $q->id)->first();
        $this->assertEquals($orelvys->id, $row->created_by_user_id);
        $this->assertEquals($bert->id, $row->updated_by_user_id);
    }

    public function test_questions_outside_sunday_window_do_not_get_updated_by(): void
    {
        [$orelvys] = $this->createAuthors();
        $checklist = Checklist::factory()->create();
        $q = Question::factory()->create(['checklist_id' => $checklist->id]);

        DB::table('safety_questions')->where('id', $q->id)->update([
            'created_at' => self::PRE_SUNDAY_CREATED,
            'updated_at' => self::OUTSIDE_WINDOW,
        ]);

        $this->artisan('safety:backfill-question-authors --apply')->assertExitCode(0);

        $this->assertNull(DB::table('safety_questions')->where('id', $q->id)->value('updated_by_user_id'));
    }

    public function test_fails_gracefully_when_orelvys_not_found(): void
    {
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
        \Database\Factories\UserFactory::new()->create([
            'name'  => 'Orelvys Cuellar (Claesen Outdoor Lighting)',
            'email' => self::ORELVYS_EMAIL,
        ]);

        $this->artisan('safety:backfill-question-authors --apply')
            ->expectsOutputToContain(self::BERT_EMAIL)
            ->assertExitCode(1);
    }
}
