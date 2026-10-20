<?php

namespace Database\Seeders;

use App\Models\College;
use App\Models\IpcrTemplate;
use App\Models\RatingPeriod;
use App\Models\Setting;
use App\Services\FinalRating;
use App\Services\TemplateManager;
use Illuminate\Database\Seeder;

/**
 * Data the system needs in every environment. Safe to run more than once:
 * nothing that already exists is changed.
 *
 *   php artisan db:seed --class=ReferenceDataSeeder --force
 */
class ReferenceDataSeeder extends Seeder
{
    /** Starting units. The Administrator adds the rest under Colleges & Offices. */
    public const UNITS = [
        ['College of Engineering', 'COE', 'college', ['Computer Engineering', 'Civil Engineering', 'Electrical Engineering']],
        ['College of Arts and Sciences', 'CAS', 'college', ['Mathematics', 'Biological Sciences']],
        ['Records Office', 'RO', 'office', []],
        ['Finance Office', 'FO', 'office', []],
        ['Human Resource Management Office', 'HRMO', 'office', []],
    ];

    /** [section, program/outcome, indicator, target] */
    public const TEACHING_ITEMS = [
        ['strategic', 'Higher Education Program', 'Percentage of first-time licensure-exam takers who pass the licensure exam', 'Contributes to the attainment of at least 75.80% passing percentage for first-time licensure exam takers'],
        ['strategic', 'Higher Education Program', 'Percentage of graduates (2 years prior) who are employed', 'Contributes to the attainment of at least 80% employment rate of graduates within two years of graduation'],
        ['strategic', 'Advanced Education Program', 'Percentage of graduate students enrolled in research degree programs', 'Contributes to the attainment of at least 60% of graduate students enrolled in research degree programs'],
        ['core', 'Instruction', "Teaches according to one's field of specialization based on university academic workload", 'Teaches 18 units regular workload or the prescribed load for faculty with designation'],
        ['core', 'Instruction', 'Prepares or revises syllabi for courses handled following the outcomes-based education format', 'Submits revised syllabi for all courses handled before the start of classes'],
        ['core', 'Instruction', 'Compiles or designs appropriate instructional materials (IMs)', 'Develops at least one instructional material for a course handled within the rating period'],
        ['support', 'Quality Assurance', 'Performs required tasks in the preparation of accreditation and assessment documents', 'Provides at least 80% of required documents for accreditation'],
        ['support', 'Faculty Development', 'Percentage of faculty to pursue studies', 'Contributes to the attainment of at least 10% of faculty enrolled in or pursuing graduate studies'],
        ['support', 'Faculty Development', 'Percentage of faculty to participate in trainings', 'Attends at least two relevant trainings, seminars or workshops within the rating period'],
    ];

    public const FIRST_PERIOD = ['July – December 2026', '2026-07-01', '2026-12-31', '2027-01-15'];

    public function run(): void
    {
        foreach (self::UNITS as [$name, $code, $kind, $departments]) {
            $college = College::firstOrCreate(['name' => $name], ['code' => $code, 'kind' => $kind]);
            foreach ($departments as $department) {
                $college->departments()->firstOrCreate(['name' => $department]);
            }
        }

        if (! Setting::where('key', 'rating_scale')->exists()) {
            Setting::put('rating_scale', IpcrTemplate::DEFAULT_SCALE);
        }
        if (! Setting::where('key', FinalRating::SETTING)->exists()) {
            Setting::put(FinalRating::SETTING, FinalRating::defaults());
        }

        [$name, $start, $end, $deadline] = self::FIRST_PERIOD;
        $openNow = RatingPeriod::open() === null;
        $period = RatingPeriod::firstOrCreate(['name' => $name], [
            'starts_on' => $start,
            'ends_on' => $end,
            'submission_deadline' => $deadline,
            'status' => $openNow ? 'open' : 'draft',
            'opened_at' => $openNow ? now() : null,
        ]);

        // Teaching: published Version 1 so HR has something to edit. Non-teaching: empty draft for HR to fill in.
        if (! IpcrTemplate::for($period->id, 'teaching')->exists()) {
            $teaching = $this->template($period, 'teaching', 'published');
            foreach (self::TEACHING_ITEMS as $i => [$section, $outcome, $indicator, $target]) {
                $teaching->items()->create([
                    'section' => $section, 'program_outcome' => $outcome, 'indicator' => $indicator, 'target' => $target, 'sort_order' => $i + 1,
                ]);
            }
            $teaching->changeLogs()->create(['description' => 'Published Version 1, the starting Teaching template.']);
        }

        if (! IpcrTemplate::for($period->id, 'nonteaching')->exists()) {
            $nonTeaching = $this->template($period, 'nonteaching', 'draft');
            $nonTeaching->changeLogs()->create(['description' => 'Created Version 1 as an empty draft for HR to fill in.']);
        }
    }

    public static function template(RatingPeriod $period, string $type, string $status): IpcrTemplate
    {
        return IpcrTemplate::create([
            'rating_period_id' => $period->id,
            'employee_type' => $type,
            'version' => 1,
            'status' => $status,
            'published_at' => $status === 'published' ? now() : null,
            'commitment_period' => $period->name,
            'commitment_statement' => TemplateManager::defaultStatement(),
            'rating_scale' => Setting::get('rating_scale', IpcrTemplate::DEFAULT_SCALE),
            'signatories' => IpcrTemplate::DEFAULT_SIGNATORIES,
        ]);
    }
}
