<?php

namespace Tests\Feature;

use App\Enums\IpcrStatus;
use App\Models\Ipcr;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApprovalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function user(string $first): User
    {
        return User::where('name', 'like', $first.' %')->firstOrFail();
    }

    /** Sofia creates and fills her IPCR for the current period. */
    private function createFilledIpcr(): Ipcr
    {
        $sofia = $this->user('Sofia');
        $this->actingAs($sofia)->post(route('employee.ipcrs.store'))->assertRedirect();

        $ipcr = $sofia->ipcrs()->whereHas('period', fn ($q) => $q->where('status', 'open'))->firstOrFail();
        $items = $ipcr->items->mapWithKeys(fn ($item) => [$item->id => ['actual_accomplishment' => 'Done for '.$item->id]])->all();

        $this->actingAs($sofia)
            ->put(route('employee.ipcrs.update', $ipcr), ['action' => 'submit', 'items' => $items])
            ->assertRedirect(route('employee.ipcrs.confirm', $ipcr));

        return $ipcr->fresh();
    }

    private function ratings(Ipcr $ipcr, int $rating = 4): array
    {
        return $ipcr->items->mapWithKeys(fn ($item) => [$item->id => ['rating' => $rating, 'remarks' => null]])->all();
    }

    public function test_ipcr_goes_through_department_head_dean_vpaa_and_hr_to_completed(): void
    {
        $ipcr = $this->createFilledIpcr();
        $sofia = $this->user('Sofia');

        $this->actingAs($sofia)->post(route('employee.ipcrs.submit', $ipcr))
            ->assertRedirect(route('employee.tracking', ['ipcr' => $ipcr->id]));
        $this->assertSame(IpcrStatus::Submitted, $ipcr->fresh()->status);

        // Department Head rates 4 everywhere except one 5.
        $ratings = $this->ratings($ipcr);
        $ratings[array_key_first($ratings)]['rating'] = 5;

        $this->actingAs($this->user('Daniel'))
            ->post(route('depthead.review.decide', $ipcr), ['action' => 'approve', 'items' => $ratings])
            ->assertRedirect(route('depthead.queue'));
        $this->assertSame(IpcrStatus::WithDean, $ipcr->fresh()->status);

        $this->actingAs($this->user('Elena'))
            ->post(route('dean.review.decide', $ipcr), ['action' => 'approve', 'items' => $ratings])
            ->assertRedirect(route('dean.queue'));
        $this->assertSame(IpcrStatus::WithVpaa, $ipcr->fresh()->status);
        $this->actingAs($this->user('Mika'))->post(route('hr.review.decide', $ipcr), ['action' => 'approve', 'items' => $ratings])->assertForbidden();
        $this->actingAs($this->user('Andrea'))->get(route('vpaa.queue'))->assertOk()->assertSee($sofia->name);
        $this->post(route('vpaa.review.decide', $ipcr), ['action' => 'approve', 'items' => $ratings])->assertRedirect(route('vpaa.queue'));
        $this->assertSame(IpcrStatus::WithHr, $ipcr->fresh()->status);

        $this->actingAs($this->user('Mika'))
            ->post(route('hr.review.decide', $ipcr), ['action' => 'approve', 'items' => $ratings])
            ->assertRedirect(route('hr.queue'));

        $ipcr->refresh();
        $this->assertSame(IpcrStatus::Completed, $ipcr->status);
        // 8 ratings of 4 and one 5 over 9 indicators = 4.11.
        $this->assertSame('4.11', $ipcr->final_rating);
        $this->assertSame('Very Satisfactory', $ipcr->adjectival_rating);
        $this->assertNotNull($ipcr->completed_at);
        $this->assertSame(['submitted', 'approved', 'approved', 'approved', 'completed'], $ipcr->reviews->pluck('action')->all());
        $this->assertSame(1, $sofia->notifications()->where('data', 'like', '%completed%')->count());
    }

    public function test_return_needs_remarks_and_resubmission_restarts_at_department_head(): void
    {
        $ipcr = $this->createFilledIpcr();
        $sofia = $this->user('Sofia');
        $daniel = $this->user('Daniel');
        $elena = $this->user('Elena');

        $this->actingAs($sofia)->post(route('employee.ipcrs.submit', $ipcr));
        $this->actingAs($daniel)->post(route('depthead.review.decide', $ipcr), ['action' => 'approve', 'items' => $this->ratings($ipcr)]);
        $this->assertSame(IpcrStatus::WithDean, $ipcr->fresh()->status);

        // Remarks are required to return.
        $this->actingAs($elena)
            ->post(route('dean.review.decide', $ipcr), ['action' => 'return', 'remarks' => ''])
            ->assertSessionHasErrors('remarks');
        $this->assertSame(IpcrStatus::WithDean, $ipcr->fresh()->status);

        $this->actingAs($elena)
            ->post(route('dean.review.decide', $ipcr), ['action' => 'return', 'remarks' => 'Add the number of sections handled.'])
            ->assertRedirect(route('dean.queue'));
        $this->assertSame(IpcrStatus::Returned, $ipcr->fresh()->status);

        // The employee sees the remarks and can edit again.
        $this->actingAs($sofia)->get(route('employee.tracking', ['ipcr' => $ipcr->id]))
            ->assertOk()->assertSee('Add the number of sections handled.')->assertSee('Revise and resubmit');
        $this->actingAs($sofia)->get(route('employee.ipcrs.edit', $ipcr))->assertOk();

        $this->actingAs($sofia)->post(route('employee.ipcrs.submit', $ipcr))->assertRedirect();
        $ipcr->refresh();
        $this->assertSame(IpcrStatus::Submitted, $ipcr->status);
        $this->assertSame('resubmitted', $ipcr->reviews->last()->action);

        // It is back in the Department Head's queue, not the Dean's.
        $this->actingAs($daniel)->get(route('depthead.queue'))->assertSee('Sofia Mendoza');
        $this->actingAs($elena)->get(route('dean.review', $ipcr))->assertForbidden();
    }

    public function test_each_reviewer_can_return_and_resubmission_keeps_previous_decisions(): void
    {
        $ipcr = $this->createFilledIpcr();
        $owner = $this->user('Sofia');
        $steps = ['depthead' => 'Daniel', 'dean' => 'Elena', 'vpaa' => 'Andrea', 'hr' => 'Mika'];
        foreach (array_keys($steps) as $returnAt) {
            $this->actingAs($owner)->post(route('employee.ipcrs.submit', $ipcr))->assertRedirect();
            $this->assertSame(IpcrStatus::Submitted, $ipcr->fresh()->status);
            foreach ($steps as $stage => $name) {
                $payload = ['action' => $stage === $returnAt ? 'return' : 'approve', 'remarks' => 'Verify the target evidence.', 'items' => $this->ratings($ipcr)];
                $this->actingAs($this->user($name))->post(route($stage.'.review.decide', $ipcr), $payload)->assertRedirect(route($stage.'.queue'));
                if ($stage === $returnAt) {
                    break;
                }
            }
            $this->assertSame(IpcrStatus::Returned, $ipcr->fresh()->status);
            $this->assertNull($ipcr->fresh()->final_rating);
            $this->actingAs($this->user('Andrea'))->get(route('vpaa.review', $ipcr))->assertForbidden();
        }
        $this->assertSame(array_keys($steps), $ipcr->reviews()->where('action', 'returned')->pluck('role')->map->value->all());
    }

    public function test_approval_requires_a_rating_for_every_indicator(): void
    {
        $ipcr = $this->createFilledIpcr();
        $this->actingAs($this->user('Sofia'))->post(route('employee.ipcrs.submit', $ipcr));

        $ratings = $this->ratings($ipcr);
        $ratings[array_key_first($ratings)]['rating'] = null;

        $this->actingAs($this->user('Daniel'))
            ->post(route('depthead.review.decide', $ipcr), ['action' => 'approve', 'items' => $ratings])
            ->assertSessionHasErrors('items.'.array_key_first($ratings).'.rating');
        $this->assertSame(IpcrStatus::Submitted, $ipcr->fresh()->status);
    }

    public function test_target_evidence_is_saved_and_printed_with_its_accomplishment(): void
    {
        $ipcr = $this->createFilledIpcr();
        $target = $ipcr->items()->firstOrFail();
        $url = 'https://example.edu/evidence/target-one';
        $this->actingAs($this->user('Sofia'))->put(route('employee.ipcrs.update', $ipcr), [
            'action' => 'save', 'items' => [$target->id => ['evidence_url' => $url]],
        ])->assertSessionHasNoErrors();
        $this->assertSame($url, $target->fresh()->evidence_url);
        $this->assertSame($target->actual_accomplishment, $target->fresh()->actual_accomplishment);
        $this->assertSame(1, $ipcr->items()->whereNotNull('evidence_url')->count());
        $this->get(route('employee.ipcrs.print', $ipcr))->assertOk()->assertSee($url);
    }

    public function test_employee_target_is_local_to_the_ipcr_and_template_rows_are_locked(): void
    {
        $ipcr = $this->createFilledIpcr();
        $owner = $this->user('Sofia');
        $original = $ipcr->items()->firstOrFail();
        $count = $ipcr->template->items()->count();
        $url = route('employee.ipcrs.update', $ipcr);
        $payload = ['action' => 'add_target', 'new_target' => [
            'key' => (string) \Illuminate\Support\Str::uuid(), 'section' => 'core',
            'indicator' => 'Extra tutorials', 'target' => 'Conduct two sessions',
        ]];
        $this->actingAs($owner)->put($url, $payload)->assertSessionHasNoErrors();
        $this->put($url, $payload)->assertSessionHasNoErrors();
        $this->assertSame($count + 1, $ipcr->items()->count());
        $this->assertSame($count, $ipcr->template->items()->count());
        $this->get(route('employee.ipcrs.print', $ipcr))->assertOk()->assertSee('Conduct two sessions');
        $fields = ['indicator' => 'Tutorials', 'target' => 'Three sessions'];
        $this->put($url, ['action' => 'save', 'personal' => [$original->id => $fields]])->assertForbidden();
        $this->assertSame($original->target, $original->fresh()->target);
        $extra = $ipcr->items()->whereNotNull('employee_target_key')->firstOrFail();
        $this->put($url, ['action' => 'save', 'personal' => [$extra->id => $fields]])->assertSessionHasNoErrors();
        $this->assertSame('Three sessions', $extra->fresh()->target);
        $this->actingAs($this->user('Diana'))->put($url, $payload)->assertForbidden();
        $ipcr->update(['status' => IpcrStatus::Submitted]);
        $this->actingAs($owner)->put($url, $payload)->assertForbidden();
    }

    public function test_cannot_submit_with_empty_accomplishments(): void
    {
        $sofia = $this->user('Sofia');
        $this->actingAs($sofia)->post(route('employee.ipcrs.store'));
        $ipcr = $sofia->ipcrs()->latest('id')->first();

        $this->actingAs($sofia)->post(route('employee.ipcrs.submit', $ipcr))->assertSessionHasErrors('ipcr');
        $this->assertSame(IpcrStatus::Draft, $ipcr->fresh()->status);
    }

    public function test_submitted_ipcr_cannot_be_edited_by_the_employee(): void
    {
        $ipcr = $this->createFilledIpcr();
        $sofia = $this->user('Sofia');
        $this->actingAs($sofia)->post(route('employee.ipcrs.submit', $ipcr));

        $this->actingAs($sofia)
            ->put(route('employee.ipcrs.update', $ipcr), ['action' => 'save', 'items' => []])
            ->assertForbidden();
    }
}
