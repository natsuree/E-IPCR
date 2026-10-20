<?php

namespace Tests\Feature;

use App\Models\Ipcr;
use App\Models\IpcrItem;
use App\Models\RatingPeriod;
use App\Models\Setting;
use App\Models\User;
use App\Services\FinalRating;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RatingComputationTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function items(array $bySection)
    {
        $items = collect();
        foreach ($bySection as $section => $ratings) {
            foreach ($ratings as $rating) {
                $items->push(new IpcrItem(['section' => $section, 'rating' => $rating]));
            }
        }

        return $items;
    }

    public function test_default_method_averages_every_rated_indicator(): void
    {
        $result = FinalRating::compute($this->items(['strategic' => [5, 4, 4], 'core' => [5, 5, 5], 'support' => [3, 4, 4]]));

        $this->assertSame(['strategic' => 4.33, 'core' => 5.0, 'support' => 3.67], $result['sections']);
        $this->assertSame(4.33, $result['overall']); // 39 / 9
        $this->assertSame('Very Satisfactory', $result['adjectival']);
    }

    public function test_unrated_indicators_are_left_out(): void
    {
        $result = FinalRating::compute($this->items(['strategic' => [5, null], 'core' => [4], 'support' => []]));

        $this->assertSame(4.5, $result['overall']);
        $this->assertNull($result['sections']['support']);
        $this->assertSame('Outstanding', $result['adjectival']);
    }

    public function test_adjectival_boundaries(): void
    {
        $this->assertSame('Outstanding', FinalRating::adjectival(4.50));
        $this->assertSame('Very Satisfactory', FinalRating::adjectival(4.49));
        $this->assertSame('Very Satisfactory', FinalRating::adjectival(3.50));
        $this->assertSame('Satisfactory', FinalRating::adjectival(3.49));
        $this->assertSame('Satisfactory', FinalRating::adjectival(2.50));
        $this->assertSame('Unsatisfactory', FinalRating::adjectival(1.50));
        $this->assertSame('Poor', FinalRating::adjectival(1.49));
    }

    public function test_weighted_method_uses_the_section_weights(): void
    {
        $settings = ['method' => 'weighted', 'weights' => ['strategic' => 20, 'core' => 70, 'support' => 10], 'bands' => FinalRating::DEFAULT_BANDS];
        $result = FinalRating::compute($this->items(['strategic' => [3], 'core' => [5], 'support' => [4]]), $settings);

        // 3×0.20 + 5×0.70 + 4×0.10 = 4.50
        $this->assertSame(4.5, $result['overall']);
        $this->assertSame('Outstanding', $result['adjectival']);
    }

    public function test_hr_can_change_settings_and_each_change_is_logged(): void
    {
        $mika = User::where('email', 'mika.santos@mmsu.test')->first();

        $this->actingAs($mika)->put(route('hr.settings.update'), [
            'method' => 'weighted',
            'weights' => ['strategic' => 20, 'core' => 70, 'support' => 10],
            'bands' => [4.6, 3.6, 2.6, 1.6],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $saved = Setting::get(FinalRating::SETTING);
        $this->assertSame('weighted', $saved['method']);
        $this->assertSame(4.6, $saved['bands']['Outstanding']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'settings.rating_updated', 'user_id' => $mika->id]);
    }

    public function test_settings_are_validated(): void
    {
        $mika = User::where('email', 'mika.santos@mmsu.test')->first();

        $this->actingAs($mika)->put(route('hr.settings.update'), [
            'method' => 'weighted', 'weights' => ['strategic' => 20, 'core' => 50, 'support' => 10], 'bands' => [4.5, 3.5, 2.5, 1.5],
        ])->assertSessionHasErrors('weights');

        $this->actingAs($mika)->put(route('hr.settings.update'), [
            'method' => 'average', 'weights' => ['strategic' => 0, 'core' => 0, 'support' => 0], 'bands' => [3.5, 4.5, 2.5, 1.5],
        ])->assertSessionHasErrors('bands');

        $this->actingAs(User::where('email', 'daniel.ramos@mmsu.test')->first())
            ->put(route('hr.settings.update'), ['method' => 'average'])->assertForbidden();
    }

    public function test_completed_ipcrs_keep_their_rating_when_settings_change(): void
    {
        $record = Ipcr::where('status', 'completed')->firstOrFail();
        $before = [$record->final_rating, $record->adjectival_rating];

        Setting::put(FinalRating::SETTING, ['method' => 'average', 'weights' => ['strategic' => 0, 'core' => 0, 'support' => 0], 'bands' => ['Outstanding' => 1.2, 'Very Satisfactory' => 1.15, 'Satisfactory' => 1.1, 'Unsatisfactory' => 1.05]]);

        $record->refresh();
        $this->assertSame($before, [$record->final_rating, $record->adjectival_rating]);
        $this->assertSame('average', $record->rating_basis['method']);

        // A newly completed IPCR uses the new settings.
        $grace = User::where('email', 'grace.abad@mmsu.test')->first()->ipcrs()->where('rating_period_id', RatingPeriod::open()->id)->first();
        $ratings = $grace->items->mapWithKeys(fn ($i) => [$i->id => ['rating' => 2, 'remarks' => null]])->all();
        $this->actingAs(User::where('email', 'mika.santos@mmsu.test')->first())
            ->post(route('hr.review.decide', $grace), ['action' => 'approve', 'items' => $ratings]);

        $this->assertSame('Outstanding', $grace->fresh()->adjectival_rating); // 2.00 is above the test's 1.2 threshold
    }
}
