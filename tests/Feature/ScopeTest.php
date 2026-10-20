<?php

namespace Tests\Feature;

use App\Models\Ipcr;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScopeTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function user(string $first): User
    {
        return User::where('name', 'like', $first.' %')->firstOrFail();
    }

    private function currentIpcrOf(string $first): Ipcr
    {
        return $this->user($first)->ipcrs()->whereHas('period', fn ($q) => $q->where('status', 'open'))->firstOrFail();
    }

    public function test_review_queues_only_show_ipcrs_at_that_step_and_in_scope(): void
    {
        // Seeded: Diana (Computer Engineering) is with the Department Head, Leo is with the Dean,
        // Grace (Finance Office) and Bea (Mathematics) are with HR.
        $this->actingAs($this->user('Daniel'))->get(route('depthead.queue'))
            ->assertOk()->assertSee('Diana Torres')->assertDontSee('Leo Pascual')->assertDontSee('Grace Abad');

        $this->actingAs($this->user('Elena'))->get(route('dean.queue'))
            ->assertOk()->assertSee('Leo Pascual')->assertDontSee('Diana Torres')->assertDontSee('Bea Fernandez');

        $this->actingAs($this->user('Mika'))->get(route('hr.queue'))
            ->assertOk()->assertSee('Grace Abad')->assertSee('Bea Fernandez')->assertSee('Finance Office')->assertDontSee('Leo Pascual');
    }

    public function test_reviewers_cannot_open_ipcrs_outside_their_step_or_scope(): void
    {
        $leo = $this->currentIpcrOf('Leo');     // with Dean
        $diana = $this->currentIpcrOf('Diana'); // with Department Head
        $grace = $this->currentIpcrOf('Grace'); // with HR, Finance Office

        $this->actingAs($this->user('Daniel'))->get(route('depthead.review', $diana))->assertOk();
        $this->actingAs($this->user('Daniel'))->get(route('depthead.review', $leo))->assertForbidden();
        $this->actingAs($this->user('Elena'))->get(route('dean.review', $grace))->assertForbidden();
        $this->actingAs($this->user('Elena'))->get(route('dean.review', $leo))->assertOk();
        $this->actingAs($this->user('Mika'))->get(route('hr.review', $grace))->assertOk();
    }

    public function test_a_department_head_cannot_act_on_another_departments_ipcr(): void
    {
        // A second Department Head, for Civil Engineering, cannot approve Diana's Computer Engineering IPCR.
        $daniel = $this->user('Daniel');
        $civil = \App\Models\Department::where('name', 'Civil Engineering')->first();
        $other = User::create([
            'name' => 'Paolo Reyes', 'email' => 'paolo.reyes@mmsu.test', 'password' => 'password',
            'role' => 'depthead', 'college_id' => $daniel->college_id, 'department_id' => $civil->id,
        ]);
        $diana = $this->currentIpcrOf('Diana');

        $this->actingAs($other)->get(route('depthead.queue'))->assertDontSee('Diana Torres');
        $this->actingAs($other)->post(route('depthead.review.decide', $diana), ['action' => 'return', 'remarks' => 'Not mine'])
            ->assertForbidden();
    }

    public function test_records_pages_show_only_records_in_scope(): void
    {
        $count = fn (User $u) => $this->actingAs($u)->get(route($u->routePrefix().'.records'))->assertOk()->viewData('summary')['records'];

        $this->assertSame(28, $count($this->user('Mika')));
        $this->assertSame(25, $count($this->user('Elena')));
        $this->assertSame(15, $count($this->user('Daniel')));

        // The Department Head cannot widen the scope with filters.
        $math = \App\Models\Department::where('name', 'Mathematics')->first();
        $records = $this->actingAs($this->user('Daniel'))
            ->get(route('depthead.records', ['department' => $math->id, 'college' => $math->college_id]))
            ->viewData('records');
        $this->assertSame(15, $records->total());
        $this->assertTrue($records->every(fn (Ipcr $r) => $r->user->department?->name === 'Computer Engineering'));

        // The Dean sees Civil Engineering records, the Department Head does not.
        $ninaRecord = $this->user('Nina')->ipcrs()->where('status', 'completed')->first();
        $this->actingAs($this->user('Elena'))->get(route('dean.records.show', $ninaRecord))->assertOk();
        $this->actingAs($this->user('Daniel'))->get(route('depthead.records.show', $ninaRecord))->assertForbidden();

        // Records are completed IPCRs only.
        $this->actingAs($this->user('Mika'))->get(route('hr.records.show', $this->currentIpcrOf('Grace')))->assertForbidden();
    }

    public function test_dashboard_counts_cover_only_the_reviewers_unit(): void
    {
        $this->assertSame(7, $this->actingAs($this->user('Daniel'))->get(route('depthead.dashboard'))->viewData('summary')['employees']);
        $this->assertSame(12, $this->actingAs($this->user('Elena'))->get(route('dean.dashboard'))->viewData('summary')['employees']);
        $this->assertSame(15, $this->actingAs($this->user('Mika'))->get(route('hr.dashboard'))->viewData('summary')['employees']);
    }
}
