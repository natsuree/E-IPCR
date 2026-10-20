<?php

namespace Tests\Feature;

use App\Models\College;
use App\Models\Department;
use App\Models\Ipcr;
use App\Models\IpcrTemplate;
use App\Models\RatingPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Known dataset (open period), separate from the demo data:
 *
 *   College of Testing
 *     Dept A: a1 completed 4.00 · a2 completed 3.00 · a3 submitted (stray 1.00 rating, must be ignored)
 *             a4 returned · a5 draft · a6 not started · a7 (non-teaching) with HR
 *     Dept B: b1 completed 5.00 · b2 with Dean/Director · b3 not started
 *   College of Elsewhere
 *     Dept Z: z1 completed 1.00
 */
class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private College $testing;
    private College $elsewhere;
    private Department $deptA;
    private Department $deptB;
    private Department $deptZ;
    private User $head;
    private User $dean;
    private RatingPeriod $period;
    private string $password;

    protected function setUp(): void
    {
        parent::setUp();

        $this->password = Hash::make('password');
        $this->period = RatingPeriod::open();
        $this->testing = College::create(['name' => 'College of Testing', 'code' => 'CT', 'kind' => 'college']);
        $this->elsewhere = College::create(['name' => 'College of Elsewhere', 'code' => 'CE', 'kind' => 'college']);
        $this->deptA = $this->testing->departments()->create(['name' => 'Department A']);
        $this->deptB = $this->testing->departments()->create(['name' => 'Department B']);
        $this->deptZ = $this->elsewhere->departments()->create(['name' => 'Department Z']);

        $this->head = $this->person('Head of A', 'depthead', $this->deptA);
        $this->dean = $this->person('Dean of Testing', 'dean', null, $this->testing);

        $this->employee('Alpha One', $this->deptA, 'completed', 4.00);
        $this->employee('Alpha Two', $this->deptA, 'completed', 3.00);
        $this->employee('Alpha Three', $this->deptA, 'submitted', 1.00);
        $this->employee('Alpha Four', $this->deptA, 'returned');
        $this->employee('Alpha Five', $this->deptA, 'draft');
        $this->employee('Alpha Six', $this->deptA, null);
        $this->employee('Alpha Seven', $this->deptA, 'with_hr', type: 'nonteaching');
        $this->employee('Beta One', $this->deptB, 'completed', 5.00);
        $this->employee('Beta Two', $this->deptB, 'with_dean');
        $this->employee('Beta Three', $this->deptB, null);
        $this->employee('Zed One', $this->deptZ, 'completed', 1.00);
    }

    private function person(string $name, string $role, ?Department $department, ?College $college = null): User
    {
        $college ??= $department?->college;

        return User::create([
            'name' => $name, 'email' => str($name)->slug('.').'@test.mmsu', 'password' => $this->password,
            'role' => $role, 'employee_type' => in_array($role, ['teaching', 'nonteaching'], true) ? $role : null,
            'college_id' => $college?->id, 'department_id' => $department?->id, 'active' => true,
        ]);
    }

    private function employee(string $name, Department $department, ?string $status, ?float $rating = null, string $type = 'teaching'): User
    {
        $user = $this->person($name, $type, $department);
        if ($status) {
            $completed = $status === 'completed';
            Ipcr::create([
                'user_id' => $user->id,
                'rating_period_id' => $this->period->id,
                'ipcr_template_id' => IpcrTemplate::activeFor($this->period->id, $type)->id,
                'status' => $status,
                'final_rating' => $rating,
                'adjectival_rating' => $rating ? \App\Services\FinalRating::adjectival($rating) : null,
                'submitted_at' => $status === 'draft' ? null : now()->subDays(5),
                'completed_at' => $completed ? now() : null,
            ]);
        }

        return $user;
    }

    private function summary(User $user, string $route, array $query = []): array
    {
        return $this->actingAs($user)->get(route($route, $query))->assertOk()->viewData('summary');
    }

    public function test_department_head_sees_only_their_department(): void
    {
        $summary = $this->summary($this->head, 'depthead.analytics');

        $this->assertSame(7, $summary['employees']);
        $this->assertSame(4, $summary['submitted']);      // 2 completed + submitted + with HR
        $this->assertSame(2, $summary['completed']);
        $this->assertSame(1, $summary['returned']);
        $this->assertSame(2, $summary['not_submitted']);  // draft + not started
        $this->assertSame(3.5, $summary['average']);
        $this->assertSame('Very Satisfactory', $summary['adjectival']);
        $this->assertSame(3, $summary['passed_head']);    // with HR + 2 completed

        $page = $this->actingAs($this->head)->get(route('depthead.analytics'));
        $page->assertSee('Alpha One')->assertDontSee('Beta One')->assertDontSee('Zed One');

        // Passing another department (or college) is refused; their own is fine.
        $this->actingAs($this->head)->get(route('depthead.analytics', ['department' => $this->deptB->id]))->assertForbidden();
        $this->actingAs($this->head)->get(route('depthead.analytics', ['college' => $this->elsewhere->id]))->assertForbidden();
        $this->actingAs($this->head)->get(route('depthead.analytics', ['department' => $this->deptA->id]))->assertOk();
    }

    public function test_dean_sees_their_whole_college_and_can_filter_to_one_department(): void
    {
        $summary = $this->summary($this->dean, 'dean.analytics');
        $this->assertSame(10, $summary['employees']);
        $this->assertSame(3, $summary['completed']);
        $this->assertSame(4.0, $summary['average']);       // (4 + 3 + 5) / 3; Zed One's 1.00 is outside

        $departments = $this->actingAs($this->dean)->get(route('dean.analytics'))->viewData('byDepartment');
        $this->assertSame(['Department A', 'Department B'], $departments->pluck('name')->all());
        $this->assertSame(1, $departments->firstWhere('name', 'Department B')['in_review']);

        $onlyB = $this->summary($this->dean, 'dean.analytics', ['department' => $this->deptB->id]);
        $this->assertSame(3, $onlyB['employees']);
        $this->assertSame(1, $onlyB['completed']);
        $this->assertSame(5.0, $onlyB['average']);

        $this->actingAs($this->dean)->get(route('dean.analytics', ['department' => $this->deptZ->id]))->assertForbidden();
        $this->actingAs($this->dean)->get(route('dean.analytics', ['college' => $this->elsewhere->id]))->assertForbidden();
    }

    public function test_type_filter_changes_the_denominator_but_search_and_status_do_not(): void
    {
        $teaching = $this->summary($this->head, 'depthead.analytics', ['type' => 'teaching']);
        $this->assertSame(6, $teaching['employees']);

        $searched = $this->actingAs($this->head)->get(route('depthead.analytics', ['q' => 'Alpha One', 'status' => 'completed']));
        $this->assertSame(7, $searched->viewData('summary')['employees']);
        $this->assertSame(1, $searched->viewData('table')->total());
    }

    public function test_average_rating_counts_completed_ipcrs_only(): void
    {
        // Alpha Three's IPCR is only submitted but carries a 1.00 rating; it must not pull the average down.
        $this->assertSame(3.5, $this->summary($this->head, 'depthead.analytics')['average']);

        // With no completed IPCRs the page says so instead of showing 0.00.
        Ipcr::whereIn('user_id', User::where('department_id', $this->deptA->id)->pluck('id'))->where('status', 'completed')->update(['status' => 'with_hr']);
        $response = $this->actingAs($this->head)->get(route('depthead.analytics'));
        $this->assertNull($response->viewData('summary')['average']);
        $response->assertSee('No completed IPCRs yet')->assertDontSee('0.00');
    }

    public function test_csv_export_contains_only_in_scope_employees_and_respects_filters(): void
    {
        $csv = $this->actingAs($this->head)->get(route('depthead.analytics.export'))->assertOk()->streamedContent();
        foreach (['Alpha One', 'Alpha Six', 'Alpha Seven'] as $name) {
            $this->assertStringContainsString($name, $csv);
        }
        $this->assertStringNotContainsString('Beta One', $csv);
        $this->assertStringNotContainsString('Zed One', $csv);
        $this->assertSame(8, count(array_filter(explode("\n", trim($csv))))); // header + 7

        $completed = $this->actingAs($this->head)->get(route('depthead.analytics.export', ['status' => 'completed']))->streamedContent();
        $this->assertStringContainsString('Alpha Two', $completed);
        $this->assertStringNotContainsString('Alpha Four', $completed);

        $deanB = $this->actingAs($this->dean)->get(route('dean.analytics.export', ['department' => $this->deptB->id, 'type' => 'teaching']))->streamedContent();
        $this->assertStringContainsString('Beta Two', $deanB);
        $this->assertStringNotContainsString('Alpha One', $deanB);

        $this->assertDatabaseHas('audit_logs', ['event' => 'ipcr.exported', 'user_id' => $this->head->id]);
        $this->actingAs($this->head)->get(route('depthead.analytics.export', ['department' => $this->deptB->id]))->assertForbidden();
    }

    public function test_employees_hr_and_other_roles_cannot_open_these_analytics(): void
    {
        $sofia = User::where('email', 'sofia.mendoza@mmsu.test')->first();
        $mika = User::where('email', 'mika.santos@mmsu.test')->first();

        foreach (['depthead.analytics', 'dean.analytics', 'depthead.analytics.export', 'dean.analytics.export'] as $route) {
            $this->actingAs($sofia)->get(route($route))->assertForbidden();
            $this->actingAs($mika)->get(route($route))->assertForbidden();
        }
        $this->actingAs($this->head)->get(route('dean.analytics'))->assertForbidden();
        $this->actingAs($this->dean)->get(route('depthead.analytics'))->assertForbidden();
    }

    public function test_rows_open_the_ipcr_read_only_through_the_policy(): void
    {
        $submitted = User::where('name', 'Alpha Three')->first()->ipcrs()->first();
        $draft = User::where('name', 'Alpha Five')->first()->ipcrs()->first();
        $otherDept = User::where('name', 'Beta Two')->first()->ipcrs()->first();

        $this->actingAs($this->head)->get(route('depthead.ipcrs.show', $submitted))->assertOk()->assertSee('Alpha Three');
        $this->actingAs($this->head)->get(route('depthead.ipcrs.show', $draft))->assertForbidden();
        $this->actingAs($this->head)->get(route('depthead.ipcrs.show', $otherDept))->assertForbidden();
        $this->actingAs($this->dean)->get(route('dean.ipcrs.show', $otherDept))->assertOk();
    }

    public function test_demo_analytics_pages_render_every_section(): void
    {
        $daniel = User::where('email', 'daniel.ramos@mmsu.test')->first();
        $elena = User::where('email', 'elena.flores@mmsu.test')->first();

        $this->actingAs($daniel)->get(route('depthead.analytics'))->assertOk()
            ->assertSee('Submission progress')->assertSee('Five lowest-rated indicators')->assertSee('Your review step')
            ->assertSee('Latest return remarks')->assertSee('Not yet submitted')->assertDontSee('Department comparison');

        $response = $this->actingAs($elena)->get(route('dean.analytics'));
        $response->assertOk()->assertSee('Department comparison')->assertSee('Civil Engineering');
        $this->assertGreaterThanOrEqual(3, $response->viewData('trends')->count());
    }

    public function test_page_stays_fast_with_500_employees_in_a_college(): void
    {
        $now = now();
        $template = IpcrTemplate::activeFor($this->period->id, 'teaching')->id;
        $statuses = ['completed', 'completed', 'submitted', 'with_dean', 'with_hr', 'returned', 'draft', null];

        $users = [];
        for ($i = 0; $i < 500; $i++) {
            $users[] = [
                'name' => "Load Test {$i}", 'email' => "load{$i}@test.mmsu", 'password' => $this->password,
                'role' => 'teaching', 'employee_type' => 'teaching', 'active' => true,
                'college_id' => $this->testing->id, 'department_id' => $i % 2 ? $this->deptA->id : $this->deptB->id,
                'created_at' => $now, 'updated_at' => $now,
            ];
        }
        foreach (array_chunk($users, 100) as $chunk) {
            DB::table('users')->insert($chunk);
        }

        $ipcrs = [];
        foreach (User::where('email', 'like', 'load%')->pluck('id') as $n => $id) {
            $status = $statuses[$n % count($statuses)];
            if ($status) {
                $rating = $status === 'completed' ? 3 + ($n % 3) * 0.5 : null;
                $ipcrs[] = [
                    'user_id' => $id, 'rating_period_id' => $this->period->id, 'ipcr_template_id' => $template, 'status' => $status,
                    'final_rating' => $rating, 'adjectival_rating' => $rating ? \App\Services\FinalRating::adjectival($rating) : null,
                    'submitted_at' => $now, 'created_at' => $now, 'updated_at' => $now,
                ];
            }
        }
        foreach (array_chunk($ipcrs, 100) as $chunk) {
            DB::table('ipcrs')->insert($chunk);
        }

        DB::enableQueryLog();
        $start = microtime(true);
        $response = $this->actingAs($this->dean)->get(route('dean.analytics'));
        $seconds = microtime(true) - $start;
        $queries = count(DB::getQueryLog());

        $response->assertOk();
        $this->assertSame(510, $response->viewData('summary')['employees']);
        $this->assertLessThan(60, $queries, "The analytics page ran {$queries} queries; it should not grow with the number of employees.");
        $this->assertLessThan(5, $seconds, "The analytics page took {$seconds}s for 510 employees.");
    }
}
