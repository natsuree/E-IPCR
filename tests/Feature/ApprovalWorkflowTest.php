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

    public function test_ipcr_goes_through_department_head_dean_and_hr_to_completed(): void
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
        $this->assertSame(['submitted', 'approved', 'approved', 'completed'], $ipcr->reviews->pluck('action')->all());
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
