<?php

namespace Tests\Feature;

use App\Models\Ipcr;
use App\Models\RatingPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RatingPeriodAndDocumentTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function mika(): User
    {
        return User::where('email', 'mika.santos@mmsu.test')->firstOrFail();
    }

    public function test_hr_creates_opens_and_closes_rating_periods(): void
    {
        $this->actingAs($this->mika())->post(route('hr.periods.store'), [
            'name' => 'January – June 2027', 'starts_on' => '2027-01-01', 'ends_on' => '2027-06-30', 'submission_deadline' => '2027-07-15',
        ])->assertRedirect(route('hr.periods.index'));
        $next = RatingPeriod::where('name', 'January – June 2027')->firstOrFail();
        $this->assertSame('draft', $next->status);

        // Only one period can be open.
        $this->actingAs($this->mika())->post(route('hr.periods.open', $next))->assertSessionHasErrors('template');
        $this->assertSame('draft', $next->fresh()->status);

        $current = RatingPeriod::open();
        $this->actingAs($this->mika())->post(route('hr.periods.close', $current))->assertSessionHasNoErrors();
        $this->actingAs($this->mika())->post(route('hr.periods.open', $next))->assertSessionHasNoErrors();

        $this->assertSame('closed', $current->fresh()->status);
        $this->assertTrue(RatingPeriod::open()->is($next));
        $this->assertDatabaseHas('audit_logs', ['event' => 'period.opened', 'subject_id' => $next->id]);
    }

    public function test_period_dates_are_validated(): void
    {
        $this->actingAs($this->mika())->post(route('hr.periods.store'), [
            'name' => 'Bad period', 'starts_on' => '2027-06-30', 'ends_on' => '2027-01-01', 'submission_deadline' => '2026-01-01',
        ])->assertSessionHasErrors(['ends_on', 'submission_deadline']);
    }

    public function test_dashboards_flag_employees_after_the_deadline(): void
    {
        RatingPeriod::open()->update(['submission_deadline' => now()->subDay()]);

        $this->actingAs($this->mika())->get(route('hr.dashboard'))
            ->assertOk()->assertSee('Past the deadline')->assertSee('Sofia Mendoza');
        $this->actingAs(User::where('email', 'daniel.ramos@mmsu.test')->first())->get(route('depthead.dashboard'))
            ->assertOk()->assertSee('Past the deadline');
    }

    public function test_deadline_reminders_go_to_employees_who_have_not_submitted(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        RatingPeriod::open()->update(['submission_deadline' => now()->addDays(2)]);

        $this->artisan('eipcr:remind-deadline')->assertSuccessful();

        $sofia = User::where('email', 'sofia.mendoza@mmsu.test')->first(); // not started
        $luis = User::where('email', 'luis.navarro@mmsu.test')->first();   // draft
        $diana = User::where('email', 'diana.torres@mmsu.test')->first();  // submitted
        \Illuminate\Support\Facades\Notification::assertSentTo([$sofia, $luis], \App\Notifications\IpcrNotification::class);
        \Illuminate\Support\Facades\Notification::assertNotSentTo($diana, \App\Notifications\IpcrNotification::class);
    }

    public function test_completed_ipcr_downloads_as_pdf_for_people_in_scope(): void
    {
        $record = Ipcr::whereHas('user', fn ($q) => $q->where('email', 'sofia.mendoza@mmsu.test'))->where('status', 'completed')->firstOrFail();

        $this->actingAs(User::where('email', 'daniel.ramos@mmsu.test')->first())
            ->get(route('depthead.records.pdf', $record))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs(User::where('email', 'sofia.mendoza@mmsu.test')->first())
            ->get(route('employee.ipcrs.pdf', $record))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs(User::where('email', 'diana.torres@mmsu.test')->first())
            ->get(route('employee.ipcrs.pdf', $record))
            ->assertForbidden();
    }

    public function test_lists_paginate_and_search(): void
    {
        $this->actingAs(User::where('email', 'alex.rivera@mmsu.test')->first())
            ->get(route('admin.users.index', ['q' => 'Navarro']))
            ->assertOk()->assertSee('Luis Navarro')->assertDontSee('Sofia Mendoza');

        $this->actingAs($this->mika())->get(route('hr.records', ['q' => 'Nina']))
            ->assertOk()->assertSee('Nina Aquino')->assertDontSee('Leo Pascual');

        $this->actingAs($this->mika())->get(route('hr.queue', ['q' => 'Grace']))
            ->assertOk()->assertSee('Grace Abad')->assertDontSee('Bea Fernandez');

        $this->actingAs($this->mika())->get(route('hr.queue', ['q' => 'nobody']))
            ->assertOk()->assertSee('No IPCRs waiting for your review match this search.');
    }
}
