<?php

namespace Tests\Feature;

use App\Models\IpcrTemplate;
use App\Models\RatingPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TemplateVersionTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function user(string $first): User
    {
        return User::where('name', 'like', $first.' %')->firstOrFail();
    }

    public function test_published_template_change_applies_to_new_ipcrs_only(): void
    {
        $mika = $this->user('Mika');
        $sofia = $this->user('Sofia');
        $mia = $this->user('Mia');
        $period = RatingPeriod::current();
        $v1 = IpcrTemplate::activeFor($period->id, 'teaching');

        // Sofia starts her IPCR on Version 1.
        $this->actingAs($sofia)->post(route('employee.ipcrs.store'));
        $sofiaIpcr = $sofia->ipcrs()->where('rating_period_id', $period->id)->firstOrFail();
        $this->assertSame($v1->id, $sofiaIpcr->ipcr_template_id);

        // HR drafts Version 2 from Version 1, adds an indicator and publishes.
        $this->actingAs($mika)->post(route('hr.templates.store'), ['period' => $period->id, 'type' => 'teaching', 'copy_from' => $v1->id])
            ->assertRedirect();
        $v2 = IpcrTemplate::for($period->id, 'teaching')->where('version', 2)->firstOrFail();
        $this->assertTrue($v2->isDraft());
        $this->assertSame($v1->items()->count(), $v2->items()->count());

        $this->actingAs($mika)->post(route('hr.templates.items.store', $v2), [
            'section' => 'core', 'program_outcome' => 'Instruction',
            'indicator' => 'Conducts remedial sessions for students at risk of failing',
            'target' => 'Holds at least four remedial sessions per semester',
        ])->assertRedirect();

        // Drafts are not used before publishing.
        $this->assertTrue(IpcrTemplate::activeFor($period->id, 'teaching')->is($v1));

        $this->actingAs($mika)->post(route('hr.templates.publish', $v2))->assertRedirect();
        $this->assertTrue(IpcrTemplate::activeFor($period->id, 'teaching')->is($v2));

        // Mia's new IPCR uses Version 2; Sofia's keeps Version 1 and its original items.
        $this->actingAs($mia)->post(route('employee.ipcrs.store'));
        $miaIpcr = $mia->ipcrs()->where('rating_period_id', $period->id)->firstOrFail();
        $this->assertSame($v2->id, $miaIpcr->ipcr_template_id);
        $this->assertTrue($miaIpcr->items->contains('indicator', 'Conducts remedial sessions for students at risk of failing'));

        $sofiaIpcr->refresh();
        $this->assertSame($v1->id, $sofiaIpcr->ipcr_template_id);
        $this->assertFalse($sofiaIpcr->items->contains('indicator', 'Conducts remedial sessions for students at risk of failing'));

        // Every change is logged.
        $logs = $v2->changeLogs()->pluck('description')->implode(' | ');
        $this->assertStringContainsString('Created Version 2', $logs);
        $this->assertStringContainsString('Added to Core Functions', $logs);
        $this->assertStringContainsString('Published Version 2', $logs);
    }

    public function test_published_versions_cannot_be_edited(): void
    {
        $period = RatingPeriod::current();
        $v1 = IpcrTemplate::activeFor($period->id, 'teaching');
        $item = $v1->items()->first();

        $this->actingAs($this->user('Mika'))
            ->put(route('hr.templates.items.update', [$v1, $item]), ['program_outcome' => 'X', 'indicator' => 'Changed', 'target' => 'Changed'])
            ->assertSessionHasErrors('template');
        $this->assertNotSame('Changed', $item->fresh()->indicator);
    }

    public function test_seeded_teaching_template_has_the_required_indicators(): void
    {
        $v1 = IpcrTemplate::activeFor(RatingPeriod::current()->id, 'teaching');
        $indicators = $v1->items->pluck('indicator');

        $this->assertTrue($indicators->contains('Percentage of first-time licensure-exam takers who pass the licensure exam'));
        $this->assertTrue($indicators->contains("Teaches according to one's field of specialization based on university academic workload"));
        $this->assertTrue($indicators->contains('Performs required tasks in the preparation of accreditation and assessment documents'));
        $this->assertSame(3, $v1->items->where('section', 'strategic')->count());
        $this->assertSame(3, $v1->items->where('section', 'core')->count());
        $this->assertSame(3, $v1->items->where('section', 'support')->count());
    }
}
