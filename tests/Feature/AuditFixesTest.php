<?php

namespace Tests\Feature;

use App\Enums\IpcrStatus;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\College;
use App\Models\Department;
use App\Models\Ipcr;
use App\Models\IpcrItem;
use App\Models\IpcrTemplate;
use App\Models\RatingPeriod;
use App\Models\User;
use App\Support\Csv;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Fixes from the November 2026 audit, exercised through the normal application routes. */
class AuditFixesTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function user(string $first): User
    {
        return User::where('name', 'like', $first.' %')->firstOrFail();
    }

    private function currentIpcr(User $user): Ipcr
    {
        return $user->ipcrs()->where('rating_period_id', RatingPeriod::current()->id)->firstOrFail();
    }

    /** Start an IPCR through the route and fill every accomplishment through the form. */
    private function startAndFill(User $employee): Ipcr
    {
        $this->actingAs($employee)->post(route('employee.ipcrs.store'))->assertRedirect();
        $ipcr = $this->currentIpcr($employee);
        $this->actingAs($employee)->put(route('employee.ipcrs.update', $ipcr), [
            'action' => 'save',
            'items' => $ipcr->items->mapWithKeys(fn ($i) => [$i->id => ['actual_accomplishment' => 'Done: '.$i->id]])->all(),
        ])->assertSessionHasNoErrors();

        return $ipcr->fresh();
    }

    private function ratings(Ipcr $ipcr, int $value, ?string $remarks = null): array
    {
        return $ipcr->items()->get()->mapWithKeys(fn ($i) => [$i->id => ['rating' => $value, 'remarks' => $remarks]])->all();
    }

    private function details(IpcrTemplate $t, array $override): array
    {
        return $override + [
            'college_id' => '',
            'designation' => '',
            'commitment_period' => $t->commitment_period,
            'commitment_statement' => $t->commitment_statement,
            'rating_scale' => $t->scale(),
            'signatories' => collect(IpcrTemplate::SIGNATORY_FIELDS)->map(fn ($l, $k) => $t->signatory($k))->all(),
        ];
    }

    // ------------------------------------------------------------------ 1. template assignment

    public function test_hr_can_change_template_college_and_designation_through_the_form(): void
    {
        $mika = $this->user('Mika');
        $period = RatingPeriod::current();
        $v1 = IpcrTemplate::activeFor($period->id, 'teaching');
        $this->actingAs($mika)->post(route('hr.templates.store'), ['period' => $period->id, 'type' => 'teaching', 'copy_from' => $v1->id]);
        $draft = IpcrTemplate::for($period->id, 'teaching')->where('status', 'draft')->firstOrFail();
        $college = College::where('name', 'College of Engineering')->firstOrFail();

        $this->actingAs($mika)->put(route('hr.templates.update', $draft), $this->details($draft, ['college_id' => $college->id, 'designation' => 'Program Chair']))
            ->assertRedirect()->assertSessionHasNoErrors();

        $draft->refresh();
        $this->assertSame($college->id, $draft->college_id);
        $this->assertSame('Program Chair', $draft->designation);
        $log = $draft->changeLogs()->latest('id')->value('description');
        $this->assertStringContainsString('college/office assignment (College of Engineering)', $log);
        $this->assertStringContainsString('administrative designation (Program Chair)', $log);
        $this->assertTrue(AuditLog::where('event', 'template.changed')->where('description', 'like', '%college/office assignment (College of Engineering)%')->exists());

        // Clearing both back to "applies to everyone" is logged too.
        $this->actingAs($mika)->put(route('hr.templates.update', $draft), $this->details($draft, []))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNull($draft->fresh()->college_id);
        $this->assertStringContainsString('all colleges and offices', $draft->changeLogs()->latest('id')->value('description'));
        $this->actingAs($mika)->get(route('hr.templates', ['period' => $period->id, 'type' => 'teaching', 'version' => $draft->version]))->assertOk();
    }

    // ------------------------------------------------------------------ 2. draft synchronisation

    public function test_publication_moves_drafts_and_keeps_entries_added_targets_and_retired_rows(): void
    {
        $mika = $this->user('Mika');
        $sofia = $this->user('Sofia');
        $period = RatingPeriod::current();
        $v1 = IpcrTemplate::activeFor($period->id, 'teaching');
        $submitted = $this->currentIpcr($this->user('Diana'));
        $this->assertSame($v1->id, $submitted->ipcr_template_id);

        $draft = $this->startAndFill($sofia);
        $kept = $draft->items->firstWhere('indicator', 'Teaches according to one\'s field of specialization based on university academic workload');
        $dropped = $draft->items->firstWhere('indicator', 'Prepares or revises syllabi for courses handled following the outcomes-based education format');
        $this->actingAs($sofia)->put(route('employee.ipcrs.update', $draft), [
            'action' => 'add_target',
            'items' => [
                $kept->id => ['actual_accomplishment' => 'Taught 18 units', 'evidence_url' => 'https://example.edu/kept'],
                $dropped->id => ['actual_accomplishment' => 'Revised 3 syllabi', 'evidence_url' => 'https://example.edu/dropped'],
            ],
            'new_target' => ['key' => (string) Str::uuid(), 'section' => 'support', 'indicator' => 'Mentors new faculty', 'target' => 'Mentors one new instructor'],
        ])->assertSessionHasNoErrors();
        $added = $draft->items()->whereNotNull('employee_target_key')->firstOrFail();
        $added->update(['actual_accomplishment' => 'Mentored Mia']);

        // Version 2: one indicator removed, one reworded.
        $this->actingAs($mika)->post(route('hr.templates.store'), ['period' => $period->id, 'type' => 'teaching', 'copy_from' => $v1->id]);
        $v2 = IpcrTemplate::for($period->id, 'teaching')->where('version', 2)->firstOrFail();
        $this->actingAs($mika)->delete(route('hr.templates.items.destroy', [$v2, $v2->items->firstWhere('indicator', $dropped->indicator)]))->assertRedirect();
        $this->actingAs($mika)->put(route('hr.templates.items.update', [$v2, $v2->items->firstWhere('indicator', $kept->indicator)]), [
            'program_outcome' => 'Instruction', 'indicator' => 'Teaches the assigned academic workload', 'target' => 'Teaches 18 units',
        ])->assertSessionHasNoErrors();
        $this->actingAs($mika)->post(route('hr.templates.publish', $v2))->assertRedirect()
            ->assertSessionHas('status', fn ($m) => str_contains($m, 'unsubmitted drafts'));

        $draft->refresh()->load('items', 'retiredTargets');
        $this->assertSame($v2->id, $draft->ipcr_template_id);
        $this->assertSame($v1->id, $submitted->fresh()->ipcr_template_id, 'A submitted IPCR keeps its version.');
        $kept->refresh();
        $this->assertFalse($kept->retired);
        $this->assertSame('Teaches the assigned academic workload', $kept->indicator);
        $this->assertSame('Taught 18 units', $kept->actual_accomplishment);
        $this->assertSame('https://example.edu/kept', $kept->evidence_url);
        $this->assertSame('Mentored Mia', $added->fresh()->actual_accomplishment);
        $this->assertFalse($added->fresh()->retired);
        $dropped->refresh();
        $this->assertTrue($dropped->retired);
        $this->assertSame('Revised 3 syllabi', $dropped->actual_accomplishment);
        $this->actingAs($sofia)->get(route('employee.ipcrs.edit', $draft))->assertOk()->assertSee('Targets from an earlier template')->assertSee('Revised 3 syllabi');

        // Version 3 restores the dropped indicator: the retired row comes back with its entries.
        $this->actingAs($mika)->post(route('hr.templates.store'), ['period' => $period->id, 'type' => 'teaching', 'copy_from' => $v2->id]);
        $v3 = IpcrTemplate::for($period->id, 'teaching')->where('version', 3)->firstOrFail();
        $this->actingAs($mika)->post(route('hr.templates.items.store', $v3), ['section' => 'core', 'program_outcome' => 'Instruction', 'indicator' => $dropped->indicator, 'target' => 'Submits syllabi'])->assertSessionHasNoErrors();
        $this->actingAs($mika)->post(route('hr.templates.publish', $v3))->assertRedirect();
        $dropped->refresh();
        $this->assertFalse($dropped->retired);
        $this->assertSame('Revised 3 syllabi', $dropped->actual_accomplishment);
        $this->assertSame('https://example.edu/dropped', $dropped->evidence_url);
        $this->assertSame(1, $draft->items()->where('indicator', $dropped->indicator)->count());
    }

    // ------------------------------------------------------------------ 3. missing reviewers

    public function test_submission_without_a_department_is_refused_with_the_reason(): void
    {
        $mia = $this->user('Mia');
        $mia->update(['department_id' => null]);
        $ipcr = $this->startAndFill($mia);

        $this->actingAs($mia)->get(route('employee.ipcrs.confirm', $ipcr))->assertOk()
            ->assertSee('cannot be submitted yet')->assertSee('has no department assigned');
        $this->actingAs($mia)->post(route('employee.ipcrs.submit', $ipcr))
            ->assertRedirect(route('employee.ipcrs.edit', $ipcr))
            ->assertSessionHasErrors(['ipcr' => 'Your IPCR cannot be submitted yet. No Department Head can review it because Mia Domingo\'s account has no department assigned. The Administrator must assign a department under Users. Your entries are saved.']);
        $this->assertSame(IpcrStatus::Draft, $ipcr->fresh()->status);
        $this->assertSame(0, $ipcr->reviews()->count());
    }

    public function test_stalled_records_are_listed_for_hr_and_admin_and_tracking_is_accurate(): void
    {
        $diana = $this->user('Diana');
        $ipcr = $this->currentIpcr($diana);
        $this->assertSame(IpcrStatus::Submitted, $ipcr->status);
        $this->assertFalse(Ipcr::stalled()->whereKey($ipcr->id)->exists());

        // The only Computer Engineering Department Head is deactivated after Diana submitted.
        $this->user('Daniel')->update(['active' => false]);
        $this->assertTrue(Ipcr::stalled()->whereKey($ipcr->id)->exists());

        $this->actingAs($this->user('Mika'))->get(route('hr.dashboard'))->assertOk()
            ->assertSee('IPCRs waiting for a reviewer to be assigned')->assertSee('Diana Torres')
            ->assertSee('No active Department Head (other than the IPCR owner) is assigned to Computer Engineering');
        $this->actingAs($this->user('Alex'))->get(route('admin.dashboard'))->assertOk()
            ->assertSee('IPCRs waiting for a reviewer to be assigned')->assertSee(route('admin.users.edit', $diana), false);
        $this->actingAs($diana)->get(route('employee.tracking', ['ipcr' => $ipcr->id]))->assertOk()
            ->assertSee('No reviewer assigned')->assertSee('Waiting for a reviewer to be assigned')->assertDontSee('Reviewing now');
        // Employees and reviewers do not get the list.
        $this->actingAs($this->user('Elena'))->get(route('dean.dashboard'))->assertOk()->assertDontSee('IPCRs waiting for a reviewer to be assigned');
    }

    public function test_approval_is_refused_when_the_next_stage_has_no_reviewer(): void
    {
        $ipcr = $this->currentIpcr($this->user('Diana'));
        $this->user('Elena')->update(['active' => false]); // the only College of Engineering Dean

        $this->actingAs($this->user('Daniel'))->from(route('depthead.review', $ipcr))
            ->post(route('depthead.review.decide', $ipcr), ['action' => 'approve', 'items' => $this->ratings($ipcr, 4)])
            ->assertSessionHasErrors(['ipcr' => 'This IPCR cannot be forwarded yet. No active Dean/Director (other than the IPCR owner) is assigned to College of Engineering. The Administrator must assign one under Users. Your ratings were not saved; you can still return it to the employee.']);
        $this->assertSame(IpcrStatus::Submitted, $ipcr->fresh()->status);
        $this->assertSame(0, $ipcr->items()->whereNotNull('rating')->count());
    }

    // ------------------------------------------------------------------ 4. history and saved details

    public function test_past_period_totals_do_not_change_after_deactivation_or_role_change(): void
    {
        $mika = $this->user('Mika');
        $completed = Ipcr::where('status', 'completed')->whereHas('period', fn ($q) => $q->where('name', 'January – June 2025'))->with('user')->firstOrFail();
        $period = $completed->period;
        $summary = fn () => $this->actingAs($mika)->get(route('hr.reports', ['period' => $period->id]))->viewData('summary');
        $records = fn () => $this->actingAs($mika)->get(route('hr.records', ['period' => $period->id]))->viewData('summary')['records'];
        $before = $summary();
        $recordsBefore = $records();

        $completed->user->update(['active' => false]);
        $this->assertEquals($before, $summary(), 'Deactivation must not change the period totals.');
        $completed->user->update(['active' => true, 'role' => Role::DepartmentHead, 'employee_type' => null]);
        $after = $summary();
        $this->assertEquals($before, $after, 'A role change must not change the period totals.');
        $this->assertSame($after['completed'], $records() > 0 ? $after['completed'] : 0);
        $this->assertSame($recordsBefore, $records());

        // The type filter still finds them (from the IPCR's template), and the CSV still lists them.
        $teaching = $this->actingAs($mika)->get(route('hr.reports', ['period' => $period->id, 'type' => $completed->template->employee_type]))->viewData('summary');
        $this->assertGreaterThanOrEqual(1, $teaching['completed']);
        $csv = $this->actingAs($mika)->get(route('hr.reports.export', ['period' => $period->id]))->streamedContent();
        $this->assertStringContainsString($completed->user->name, $csv);
    }

    public function test_saved_roster_keeps_members_without_an_ipcr_after_deactivation(): void
    {
        $mika = $this->user('Mika');
        $period = RatingPeriod::current();
        $ids = User::whereIn('name', ['Mia Domingo', 'Sofia Mendoza', 'Diana Torres'])->pluck('id')->all();
        $this->actingAs($mika)->put(route('hr.periods.roster.save', $period), ['employees' => $ids])->assertRedirect();
        $summary = fn () => $this->actingAs($mika)->get(route('hr.reports', ['period' => $period->id]))->viewData('summary');
        $before = $summary();
        $this->assertSame(3, $before['employees']);
        $this->assertSame(2, $before['not_submitted']);

        $this->user('Mia')->update(['active' => false]);
        $this->assertEquals($before, $summary());
    }

    public function test_submission_saves_employee_details_for_reports_and_printed_records(): void
    {
        $sofia = $this->user('Sofia');
        $ipcr = $this->startAndFill($sofia);
        $this->actingAs($sofia)->post(route('employee.ipcrs.submit', $ipcr))->assertRedirect(route('employee.tracking', ['ipcr' => $ipcr->id]));
        $ipcr->refresh();
        $this->assertNotNull($ipcr->snapshot_taken_at);
        $this->assertSame('Sofia Mendoza', $ipcr->snapshot_name);
        $this->assertSame('Computer Engineering', $ipcr->snapshot_department_name);
        $this->assertSame('teaching', $ipcr->snapshot_employee_type);

        // Later account changes: new name, position and department.
        $civil = Department::where('name', 'Civil Engineering')->firstOrFail();
        $sofia->update(['name' => 'Sofia Mendoza-Cruz', 'position' => 'Instructor II', 'department_id' => $civil->id]);

        $this->actingAs($sofia)->get(route('employee.ipcrs.print', $ipcr))->assertOk()
            ->assertSee('Sofia Mendoza</strong>, Instructor I of College of Engineering · Computer Engineering', false)
            ->assertDontSee('Sofia Mendoza-Cruz');
        $daniel = $this->actingAs($this->user('Daniel'))->get(route('depthead.analytics'));
        $this->assertTrue(collect($daniel->viewData('table')->items())->contains('name', 'Sofia Mendoza'));
        $ivan = $this->actingAs($this->user('Ivan'))->get(route('depthead.analytics'));
        $this->assertFalse(collect($ivan->viewData('table')->items())->contains('id', $sofia->id));

        // Older records without saved details say so (simulate a record submitted before the upgrade).
        $legacy = Ipcr::where('status', 'completed')->firstOrFail();
        DB::table('ipcrs')->where('id', $legacy->id)->update(['snapshot_taken_at' => null, 'snapshot_name' => null]);
        $this->actingAs($this->user('Mika'))->get(route('hr.records.print', $legacy))->assertOk()->assertSee('Employee details are from the current account');
    }

    // ------------------------------------------------------------------ 5. review snapshots

    public function test_each_decision_keeps_its_ratings_and_older_decisions_are_labelled(): void
    {
        $diana = $this->user('Diana');
        $ipcr = $this->currentIpcr($diana);
        $this->actingAs($this->user('Daniel'))->post(route('depthead.review.decide', $ipcr), ['action' => 'approve', 'items' => $this->ratings($ipcr, 5, 'Head note')])->assertSessionHasNoErrors();
        $this->actingAs($this->user('Elena'))->post(route('dean.review.decide', $ipcr), ['action' => 'approve', 'items' => $this->ratings($ipcr, 3, 'Dean note')])->assertSessionHasNoErrors();

        $head = $ipcr->reviews()->where('role', 'depthead')->where('action', 'approved')->latest('id')->firstOrFail();
        $dean = $ipcr->reviews()->where('role', 'dean')->where('action', 'approved')->latest('id')->firstOrFail();
        $this->assertSame([5], collect($head->item_ratings)->pluck('rating')->unique()->values()->all());
        $this->assertSame(['Head note'], collect($head->item_ratings)->pluck('remarks')->unique()->values()->all());
        $this->assertSame([3], collect($dean->item_ratings)->pluck('rating')->unique()->values()->all());
        $this->assertSame($ipcr->items()->count(), count($head->item_ratings));
        $this->assertSame([3], $ipcr->items()->pluck('rating')->unique()->values()->all(), 'Current ratings are the latest decision.');

        // A decision recorded before snapshots existed.
        DB::table('ipcr_reviews')->insert(['ipcr_id' => $ipcr->id, 'user_id' => $this->user('Daniel')->id, 'role' => 'depthead', 'action' => 'returned', 'remarks' => 'Old return', 'created_at' => now()->subYear(), 'updated_at' => now()->subYear()]);

        $this->actingAs($diana)->get(route('employee.tracking', ['ipcr' => $ipcr->id]))->assertOk()
            ->assertSee('Ratings and remarks given with this decision')->assertSee('Head note')->assertSee('Dean note')
            ->assertSee('Per-target ratings were not recorded for this decision');
    }

    // ------------------------------------------------------------------ 6. promoted owner

    public function test_promoted_owner_can_correct_and_resubmit_but_never_review_their_own_ipcr(): void
    {
        $nina = $this->user('Nina'); // Civil Engineering, IPCR returned
        $ipcr = $this->currentIpcr($nina);
        $this->assertSame(IpcrStatus::Returned, $ipcr->status);
        $nina->update(['role' => Role::DepartmentHead, 'employee_type' => null]);

        $this->actingAs($nina)->get(route('depthead.dashboard'))->assertOk()->assertSee('My IPCRs');
        $this->actingAs($nina)->get(route('employee.ipcrs.edit', $ipcr))->assertOk();
        $this->actingAs($nina)->put(route('employee.ipcrs.update', $ipcr), [
            'action' => 'save', 'items' => [$ipcr->items->first()->id => ['actual_accomplishment' => 'Corrected after promotion']],
        ])->assertSessionHasNoErrors();
        $this->assertSame('Corrected after promotion', $ipcr->items()->first()->actual_accomplishment);

        // She is now the only Civil Engineering head besides Ivan; with Ivan inactive nobody else could review it.
        $ivan = $this->user('Ivan');
        $ivan->update(['active' => false]);
        $this->actingAs($nina)->post(route('employee.ipcrs.submit', $ipcr))->assertSessionHasErrors('ipcr');
        $this->assertStringContainsString('other than the IPCR owner', session('errors')->first('ipcr'));
        $this->assertSame(IpcrStatus::Returned, $ipcr->fresh()->status);

        $ivan->update(['active' => true]);
        $this->actingAs($nina)->post(route('employee.ipcrs.submit', $ipcr))->assertRedirect();
        $ipcr->refresh();
        $this->assertSame(IpcrStatus::Submitted, $ipcr->status);
        $this->assertSame('teaching', $ipcr->snapshot_employee_type);

        // Never her own review.
        $this->assertFalse(Ipcr::awaiting($nina->fresh())->whereKey($ipcr->id)->exists());
        $this->actingAs($nina)->get(route('depthead.review', $ipcr))->assertForbidden();
        $this->actingAs($nina)->post(route('depthead.review.decide', $ipcr), ['action' => 'approve', 'items' => $this->ratings($ipcr, 5)])->assertForbidden();
        $this->assertTrue(Ipcr::awaiting($ivan)->whereKey($ipcr->id)->exists());

        // Ownership still applies, and creating new IPCRs stays employee-only.
        $this->actingAs($nina)->get(route('employee.ipcrs.show', $this->currentIpcr($this->user('Diana'))))->assertForbidden();
        $this->actingAs($nina)->post(route('employee.ipcrs.store'))->assertForbidden();
        $this->actingAs($this->user('Daniel'))->get(route('employee.ipcrs.index'))->assertForbidden();
    }

    // ------------------------------------------------------------------ 7. target removal

    public function test_employee_can_remove_only_their_own_added_targets(): void
    {
        $sofia = $this->user('Sofia');
        $ipcr = $this->startAndFill($sofia);
        $this->actingAs($sofia)->put(route('employee.ipcrs.update', $ipcr), [
            'action' => 'add_target', 'new_target' => ['key' => (string) Str::uuid(), 'section' => 'core', 'indicator' => 'Extra indicator', 'target' => 'Extra target'],
        ])->assertSessionHasNoErrors();
        $added = $ipcr->items()->whereNotNull('employee_target_key')->firstOrFail();
        $hrRow = $ipcr->items()->whereNull('employee_target_key')->firstOrFail();
        $this->actingAs($sofia)->get(route('employee.ipcrs.edit', $ipcr))->assertSee('Remove target')->assertSee('id="remove-target-'.$added->id.'"', false);

        // Another employee, and HR template rows, are refused.
        $this->actingAs($this->user('Mia'))->delete(route('employee.ipcrs.targets.destroy', [$ipcr, $added->id]))->assertForbidden();
        $this->actingAs($sofia)->delete(route('employee.ipcrs.targets.destroy', [$ipcr, $hrRow->id]))->assertForbidden();
        $this->assertTrue(IpcrItem::whereKey($hrRow->id)->exists());
        // Tampered edits to HR rows are refused or ignored.
        $this->actingAs($sofia)->put(route('employee.ipcrs.update', $ipcr), ['action' => 'save', 'personal' => [$hrRow->id => ['indicator' => 'Changed', 'target' => 'Changed']]])->assertForbidden();
        $this->actingAs($sofia)->put(route('employee.ipcrs.update', $ipcr), ['action' => 'save', 'items' => [$hrRow->id => ['indicator' => 'Changed']]])->assertSessionHasErrors();
        $this->assertNotSame('Changed', $hrRow->fresh()->indicator);

        // Unreviewed added target: deleted.
        $this->actingAs($sofia)->delete(route('employee.ipcrs.targets.destroy', [$ipcr, $added->id]))->assertRedirect(route('employee.ipcrs.edit', $ipcr));
        $this->assertFalse(IpcrItem::whereKey($added->id)->exists());

        // After submission nothing can be removed.
        $this->actingAs($sofia)->post(route('employee.ipcrs.submit', $ipcr));
        $this->assertSame(IpcrStatus::Submitted, $ipcr->fresh()->status);
        $this->actingAs($sofia)->delete(route('employee.ipcrs.targets.destroy', [$ipcr, $hrRow->id]))->assertForbidden();
    }

    public function test_removing_a_reviewed_added_target_keeps_it_in_the_record(): void
    {
        $nina = $this->user('Nina');
        $ipcr = $this->currentIpcr($nina); // returned, so reviewers have seen it
        $added = $ipcr->items()->create(['employee_target_key' => (string) Str::uuid(), 'section' => 'support', 'indicator' => 'Reviewed extra', 'target' => 'Extra', 'actual_accomplishment' => 'Did it', 'remarks' => 'Seen by the Dean', 'sort_order' => 99]);

        $this->actingAs($nina)->delete(route('employee.ipcrs.targets.destroy', [$ipcr, $added->id]))->assertRedirect();
        $added->refresh();
        $this->assertTrue($added->retired);
        $this->assertNotNull($added->removed_at);
        $this->assertSame('Did it', $added->actual_accomplishment);
        $this->assertFalse($ipcr->items()->whereKey($added->id)->exists(), 'Excluded from the current form and rating.');
        $this->actingAs($nina)->get(route('employee.ipcrs.edit', $ipcr))->assertSee('Added targets you removed')->assertSee('Reviewed extra');
        $this->assertTrue(AuditLog::where('event', 'ipcr.target_removed')->exists());
    }

    // ------------------------------------------------------------------ 8. validation messages

    public function test_rating_errors_name_the_section_and_indicator(): void
    {
        $ipcr = $this->currentIpcr($this->user('Diana'));
        $ratings = $this->ratings($ipcr, 4);
        $core = $ipcr->items()->where('section', 'core')->orderBy('sort_order')->orderBy('id')->first();
        unset($ratings[$core->id]['rating']);

        $this->actingAs($this->user('Daniel'))->from(route('depthead.review', $ipcr))
            ->post(route('depthead.review.decide', $ipcr), ['action' => 'approve', 'items' => $ratings]);
        $message = session('errors')->first("items.{$core->id}.rating");
        $this->assertStringStartsWith('Give a rating from 1 to 5 for Core Functions indicator 1 ("Teaches according', $message);
    }

    public function test_every_missing_accomplishment_is_highlighted_and_entries_are_kept(): void
    {
        $sofia = $this->user('Sofia');
        $this->actingAs($sofia)->post(route('employee.ipcrs.store'));
        $ipcr = $this->currentIpcr($sofia);
        $bySection = $ipcr->items->groupBy('section')->map->values();
        $emptyCore = $bySection['core'][0];
        $emptySupport = $bySection['support'][1];
        $items = $ipcr->items->reject(fn ($i) => in_array($i->id, [$emptyCore->id, $emptySupport->id]))
            ->mapWithKeys(fn ($i) => [$i->id => ['actual_accomplishment' => 'Typed '.$i->id]])->all();

        $this->followingRedirects()->actingAs($sofia)->put(route('employee.ipcrs.update', $ipcr), ['action' => 'submit', 'items' => $items])
            ->assertOk()
            ->assertSee('2 still empty')
            ->assertSee('Enter the actual accomplishment for Core Functions indicator 1')
            ->assertSee('Enter the actual accomplishment for Support Functions indicator 2')
            ->assertSee('Typed '.$bySection['core'][1]->id)
            ->assertSee('id="actual-error-'.$emptyCore->id.'"', false)
            ->assertSee('id="actual-error-'.$emptySupport->id.'"', false);
        $this->assertSame(IpcrStatus::Draft, $ipcr->fresh()->status);

        // An invalid MOV link keeps the typed (unsaved) text in the form.
        $first = $ipcr->items->first();
        $this->actingAs($sofia)->from(route('employee.ipcrs.edit', $ipcr))->put(route('employee.ipcrs.update', $ipcr), [
            'action' => 'save', 'items' => [$first->id => ['actual_accomplishment' => 'Unsaved typing', 'evidence_url' => 'not a link']],
        ])->assertSessionHasErrors("items.{$first->id}.evidence_url")->assertSessionHasInput("items.{$first->id}.actual_accomplishment", 'Unsaved typing');
    }

    // ------------------------------------------------------------------ MOV placement

    public function test_mov_link_sits_below_the_remarks_in_every_view(): void
    {
        $remark = 'Remark-ABC-123';
        $mov = 'https://example.edu/mov-123';
        $before = fn (string $html, string $a, string $b) => $this->assertTrue(
            ($pa = strpos($html, $a)) !== false && ($pb = strpos($html, $b)) !== false && $pa < $pb,
            "Expected \"{$a}\" before \"{$b}\"."
        );

        // Employee form (returned IPCR, remarks from the reviewer).
        $nina = $this->user('Nina');
        $returned = $this->currentIpcr($nina);
        $item = $returned->items()->first();
        $item->update(['remarks' => $remark, 'evidence_url' => $mov, 'actual_accomplishment' => 'Accomplished-XYZ']);
        $html = $this->actingAs($nina)->get(route('employee.ipcrs.edit', $returned))->getContent();
        $before($html, 'id="actual-'.$item->id.'"', $remark);
        $before($html, $remark, 'id="mov-'.$item->id.'"');

        foreach ([route('employee.ipcrs.show', $returned), route('employee.ipcrs.print', $returned)] as $url) {
            $html = $this->actingAs($nina)->get($url)->assertOk()->getContent();
            $before($html, 'Accomplished-XYZ', $remark);
            $before($html, $remark, $mov);
        }
        $pdfHtml = view('ipcr.pdf', ['ipcr' => $returned->fresh()->load(['user.college', 'user.department', 'period', 'template', 'items', 'reviews.user']), 'seal' => null])->render();
        $before($pdfHtml, 'Accomplished-XYZ', $remark);
        $before($pdfHtml, $remark, 'MOV: <a href="'.$mov.'"');
        $this->actingAs($nina)->get(route('employee.ipcrs.pdf', $returned))->assertOk()->assertHeader('content-type', 'application/pdf');

        // Reviewer screen.
        $diana = $this->currentIpcr($this->user('Diana'));
        $reviewed = $diana->items()->first();
        $reviewed->update(['remarks' => $remark, 'evidence_url' => $mov]);
        $html = $this->actingAs($this->user('Daniel'))->get(route('depthead.review', $diana))->assertOk()->getContent();
        $before($html, 'id="remarks-'.$reviewed->id.'"', 'href="'.$mov.'"');
    }

    // ------------------------------------------------------------------ 9. CSV

    public function test_csv_export_neutralises_formulas_and_keeps_readable_values(): void
    {
        $this->assertSame("'=HYPERLINK(\"http://x\")", Csv::cell('=HYPERLINK("http://x")'));
        $this->assertSame("'+63 912", Csv::cell('+63 912'));
        $this->assertSame("'@SUM(A1)", Csv::cell('@SUM(A1)'));
        $this->assertSame("'-cmd", Csv::cell('-cmd'));
        $this->assertSame('4.50', Csv::cell('4.50'));
        $this->assertSame('-1.5', Csv::cell('-1.5'));
        $this->assertSame('Sofia Mendoza', Csv::cell('Sofia Mendoza'));
        $this->assertSame('', Csv::cell(null));

        $this->user('Sofia')->update(['name' => '=HYPERLINK("http://evil.example","click")', 'employee_id' => '@2026-0001']);
        $csv = $this->actingAs($this->user('Mika'))->get(route('hr.reports.export'))->assertOk()->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK(\"\"http://evil.example\"\",\"\"click\"\")", $csv);
        $this->assertStringContainsString("'@2026-0001", $csv);
        $this->assertStringContainsString('Diana Torres', $csv);
        $this->assertStringNotContainsString(",=HYPERLINK", $csv);
        $this->assertStringNotContainsString('"=HYPERLINK', $csv);

        $analytics = $this->actingAs($this->user('Daniel'))->get(route('depthead.analytics.export'))->assertOk()->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $analytics);
    }
}
