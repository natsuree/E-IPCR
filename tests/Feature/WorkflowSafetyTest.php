<?php

namespace Tests\Feature;

use App\Enums\IpcrStatus;
use App\Models\Ipcr;
use App\Models\RatingPeriod;
use App\Models\User;
use App\Services\IpcrWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WorkflowSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function user(string $first): User
    {
        return User::where('name', 'like', $first.' %')->firstOrFail();
    }

    /** Diana's seeded IPCR is waiting at the Department Head. */
    private function dianaIpcr(): Ipcr
    {
        return $this->user('Diana')->ipcrs()->where('rating_period_id', RatingPeriod::open()->id)->firstOrFail();
    }

    private function ratings(Ipcr $ipcr): array
    {
        return $ipcr->items->mapWithKeys(fn ($item) => [$item->id => ['rating' => 4, 'remarks' => null]])->all();
    }

    public function test_a_second_approval_of_the_same_step_is_refused(): void
    {
        $ipcr = $this->dianaIpcr();
        $daniel = $this->user('Daniel');
        $payload = ['action' => 'approve', 'items' => $this->ratings($ipcr)];

        $this->actingAs($daniel)->post(route('depthead.review.decide', $ipcr), $payload)->assertRedirect(route('depthead.queue'));
        // The double click: the IPCR is no longer at this step.
        $this->actingAs($daniel)->post(route('depthead.review.decide', $ipcr), $payload)->assertForbidden();

        $ipcr->refresh();
        $this->assertSame(IpcrStatus::WithDean, $ipcr->status);
        $this->assertSame(1, $ipcr->reviews()->where('action', 'approved')->where('role', 'depthead')->count());
    }

    public function test_two_reviewers_acting_on_a_stale_copy_cannot_both_succeed(): void
    {
        $workflow = app(IpcrWorkflow::class);
        $daniel = $this->user('Daniel');

        // Two requests loaded the IPCR while it was still "Submitted".
        $first = $this->dianaIpcr();
        $second = $this->dianaIpcr();

        $workflow->approve($first, $daniel, $this->ratings($first), null);

        try {
            $workflow->return($second, $daniel, [], 'Second reviewer, same moment');
            $this->fail('The second action should have been refused.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('no longer waiting for your review', $e->errors()['ipcr'][0]);
        }

        $this->assertSame(IpcrStatus::WithDean, $first->fresh()->status);
        $this->assertSame(0, $first->reviews()->where('action', 'returned')->count());
    }

    public function test_employee_cannot_submit_twice(): void
    {
        $sofia = $this->user('Sofia');
        $this->actingAs($sofia)->post(route('employee.ipcrs.store'));
        $ipcr = $sofia->ipcrs()->latest('id')->first();
        $ipcr->items()->update(['actual_accomplishment' => 'Done']);

        $this->actingAs($sofia)->post(route('employee.ipcrs.submit', $ipcr))->assertRedirect();
        $this->actingAs($sofia)->post(route('employee.ipcrs.submit', $ipcr))->assertForbidden();

        $this->assertSame(1, $ipcr->reviews()->where('action', 'submitted')->count());
    }

    public function test_another_users_ipcr_id_in_the_url_is_forbidden(): void
    {
        $sofia = $this->user('Sofia');
        $diana = $this->dianaIpcr();

        foreach (['employee.ipcrs.show', 'employee.ipcrs.edit', 'employee.ipcrs.print', 'employee.ipcrs.pdf', 'employee.ipcrs.confirm'] as $route) {
            $this->actingAs($sofia)->get(route($route, $diana))->assertForbidden();
        }
        $this->actingAs($sofia)->get(route('employee.tracking', ['ipcr' => $diana->id]))->assertForbidden();
        $this->actingAs($sofia)->post(route('employee.ipcrs.submit', $diana))->assertForbidden();
        $this->actingAs($sofia)->put(route('employee.ipcrs.update', $diana), ['action' => 'save'])->assertForbidden();

        // A Dean cannot open a record from outside their college by changing the ID.
        $grace = $this->user('Grace')->ipcrs()->where('status', 'completed')->firstOrFail();
        $this->actingAs($this->user('Elena'))->get(route('dean.records.show', $grace))->assertForbidden();
        $this->actingAs($this->user('Elena'))->get(route('dean.records.pdf', $grace))->assertForbidden();
    }

    public function test_workflow_actions_send_database_and_queued_email_notifications(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        $ipcr = $this->dianaIpcr();

        $this->actingAs($this->user('Daniel'))->post(route('depthead.review.decide', $ipcr), ['action' => 'return', 'remarks' => 'Add the class sizes.']);

        \Illuminate\Support\Facades\Notification::assertSentTo($this->user('Diana'), \App\Notifications\IpcrNotification::class, function ($n, $channels) {
            return in_array('mail', $channels, true) && in_array('database', $channels, true)
                && str_contains($n->message, 'returned by the Department Head');
        });
    }

    public function test_employee_cannot_create_an_ipcr_when_no_period_is_open(): void
    {
        RatingPeriod::query()->update(['status' => 'closed']);
        $mia = $this->user('Mia');

        $this->actingAs($mia)->post(route('employee.ipcrs.store'))->assertSessionHasErrors('ipcr');
        $this->assertSame(0, $mia->ipcrs()->count());
        $this->actingAs($mia)->get(route('employee.ipcrs.current'))->assertOk()->assertSee('No rating period is open');
    }
}
