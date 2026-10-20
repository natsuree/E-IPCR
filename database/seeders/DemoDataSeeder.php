<?php

namespace Database\Seeders;

use App\Enums\IpcrStatus;
use App\Enums\Role;
use App\Models\College;
use App\Models\Department;
use App\Models\Ipcr;
use App\Models\IpcrTemplate;
use App\Models\RatingPeriod;
use App\Models\User;
use App\Services\IpcrWorkflow;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Demo accounts and records for local development and tests. Never runs in
 * production. All demo passwords are "password".
 *
 * IPCRs are built by running the real workflow with the clock set to past
 * dates, so history, ratings and notifications match what the system produces.
 */
class DemoDataSeeder extends Seeder
{
    /** [name, email, role, position, college, department] */
    public const PEOPLE = [
        ['Sofia Mendoza', 'sofia.mendoza@mmsu.test', Role::Teaching, 'Instructor I', 'College of Engineering', 'Computer Engineering'],
        ['Luis Navarro', 'luis.navarro@mmsu.test', Role::NonTeaching, 'Records Officer II', 'Records Office', null],
        ['Daniel Ramos', 'daniel.ramos@mmsu.test', Role::DepartmentHead, 'Department Head, Computer Engineering', 'College of Engineering', 'Computer Engineering'],
        ['Elena Flores', 'elena.flores@mmsu.test', Role::Dean, 'Dean, College of Engineering', 'College of Engineering', null],
        ['Mika Santos', 'mika.santos@mmsu.test', Role::Hr, 'Human Resource Management Officer', 'Human Resource Management Office', null],
        ['Alex Rivera', 'alex.rivera@mmsu.test', Role::Admin, 'System Administrator', null, null],
        ['Diana Torres', 'diana.torres@mmsu.test', Role::Teaching, 'Assistant Professor II', 'College of Engineering', 'Computer Engineering'],
        ['Leo Pascual', 'leo.pascual@mmsu.test', Role::Teaching, 'Instructor II', 'College of Engineering', 'Computer Engineering'],
        ['Mia Domingo', 'mia.domingo@mmsu.test', Role::Teaching, 'Instructor I', 'College of Engineering', 'Computer Engineering'],
        ['Nina Aquino', 'nina.aquino@mmsu.test', Role::Teaching, 'Assistant Professor I', 'College of Engineering', 'Civil Engineering'],
        ['Bea Fernandez', 'bea.fernandez@mmsu.test', Role::Teaching, 'Instructor III', 'College of Arts and Sciences', 'Mathematics'],
        ['Grace Abad', 'grace.abad@mmsu.test', Role::NonTeaching, 'Administrative Officer III', 'Finance Office', null],
        ['Carlo Reyes', 'carlo.reyes@mmsu.test', Role::Teaching, 'Instructor II', 'College of Engineering', 'Computer Engineering'],
        ['Ana Lim', 'ana.lim@mmsu.test', Role::Teaching, 'Assistant Professor I', 'College of Engineering', 'Computer Engineering'],
        ['Paolo Garcia', 'paolo.garcia@mmsu.test', Role::NonTeaching, 'Department Secretary', 'College of Engineering', 'Computer Engineering'],
        ['Marco Villanueva', 'marco.villanueva@mmsu.test', Role::Teaching, 'Instructor I', 'College of Engineering', 'Civil Engineering'],
        ['Joy Castillo', 'joy.castillo@mmsu.test', Role::Teaching, 'Associate Professor I', 'College of Engineering', 'Civil Engineering'],
        ['Teresa Uy', 'teresa.uy@mmsu.test', Role::Teaching, 'Instructor III', 'College of Engineering', 'Electrical Engineering'],
        ['Allan Ong', 'allan.ong@mmsu.test', Role::NonTeaching, 'College Secretary', 'College of Engineering', null],
    ];

    /** Remarks used when a reviewer returns a demo IPCR. */
    private const RETURN_REMARKS = [
        'Please state the number of students and sections handled under Core Functions.',
        'List the trainings you attended, with dates, under Support Functions.',
        'Attach the accreditation documents you prepared, or describe them in more detail.',
        'The accomplishment for the syllabi indicator does not match the target. Please revise.',
    ];

    private int $remark = 0;

    /** [section, program/outcome, indicator, target] */
    public const NON_TEACHING_ITEMS = [
        ['strategic', 'Office Services', 'Percentage of client requests acted upon within the prescribed processing time', "Acts on at least 95% of client requests within the Citizen's Charter processing time"],
        ['strategic', 'Office Services', 'Client satisfaction rating for office services', 'Attains an average client satisfaction rating of at least 4.0 out of 5'],
        ['core', 'Office Operations', 'Receives, records and routes incoming documents and requests', 'Logs and routes all incoming documents within one working day of receipt'],
        ['core', 'Office Operations', 'Prepares reports, letters and other office documents', 'Submits required office reports on or before the deadline with no more than two revisions'],
        ['core', 'Office Operations', 'Maintains an organized filing system for office records', 'Keeps 100% of office records filed and retrievable within ten minutes'],
        ['support', 'Professional Development', 'Participates in office and university activities', 'Attends at least 90% of required office meetings and university activities'],
        ['support', 'Professional Development', "Attends trainings related to one's work", 'Completes at least one work-related training within the rating period'],
    ];

    /** Accomplishment text per indicator (first words of the indicator => text). */
    private const ACCOMPLISHMENTS = [
        'Percentage of first-time' => 'Handled two review sessions for the board exam review program; 78% of first-time takers from the department passed.',
        'Percentage of graduates' => 'Assisted in the graduate tracer study and the department job fair; 82% of graduates traced are employed.',
        'Percentage of graduate students' => 'Advised three graduate students on their research proposals.',
        'Teaches according' => 'Taught 18 units across four sections in the field of specialization.',
        'Prepares or revises' => 'Submitted revised syllabi for all four courses before the first day of classes.',
        'Compiles or designs' => 'Developed one laboratory manual that was approved by the instructional materials review committee.',
        'Performs required tasks' => 'Provided 90% of the documents assigned for the program accreditation visit.',
        'Percentage of faculty to pursue' => "Enrolled in a master's degree program and completed 12 units.",
        'Percentage of faculty to participate' => 'Attended three trainings on outcomes-based teaching and laboratory safety.',
        'Percentage of client requests' => 'Acted on 97% of 420 client requests within the processing time.',
        'Client satisfaction' => 'Received an average client satisfaction rating of 4.4.',
        'Receives, records' => 'Logged and routed 1,150 documents, all within one working day.',
        'Prepares reports' => 'Submitted all six monthly reports on time with at most one revision.',
        'Maintains an organized' => 'Kept all office records filed; spot checks found every record within five minutes.',
        'Participates in office' => 'Attended 95% of office meetings and university activities.',
        'Attends trainings' => 'Completed a records management training.',
    ];

    /** Rating patterns per indicator, cycled for variety. */
    private const PATTERNS = [
        [5, 4, 4, 5, 5, 4, 4, 4, 5],
        [4, 4, 3, 4, 4, 4, 4, 3, 4],
        [5, 5, 5, 5, 4, 5, 5, 4, 5],
        [4, 3, 3, 4, 3, 3, 4, 3, 3],
        [4, 4, 4, 5, 4, 4, 4, 4, 4],
        [5, 4, 5, 4, 5, 4, 5, 4, 4],
    ];

    private int $pattern = 0;

    public function __construct(private IpcrWorkflow $workflow) {}

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('DemoDataSeeder must never run in production.');
        }

        $this->users();
        $hr = User::where('email', 'mika.santos@mmsu.test')->firstOrFail();

        // Past periods (closed), each with published templates.
        $past = [];
        foreach ([
            ['January – June 2025', '2025-01-01', '2025-06-30', '2025-07-15'],
            ['July – December 2025', '2025-07-01', '2025-12-31', '2026-01-15'],
            ['January – June 2026', '2026-01-01', '2026-06-30', '2026-07-15'],
        ] as [$name, $start, $end, $deadline]) {
            $period = RatingPeriod::firstOrCreate(['name' => $name], [
                'starts_on' => $start, 'ends_on' => $end, 'submission_deadline' => $deadline,
                'status' => 'closed', 'opened_at' => $start, 'closed_at' => $end,
            ]);
            if (! IpcrTemplate::for($period->id, 'teaching')->exists()) {
                $this->publishedTemplate($period, 'teaching', ReferenceDataSeeder::TEACHING_ITEMS, $hr);
                $this->publishedTemplate($period, 'nonteaching', self::NON_TEACHING_ITEMS, $hr);
            }
            $past[] = $period;
        }

        // Current period: fill in and publish the non-teaching draft from the reference data.
        $current = RatingPeriod::where('name', ReferenceDataSeeder::FIRST_PERIOD[0])->firstOrFail();
        $draft = IpcrTemplate::for($current->id, 'nonteaching')->where('status', 'draft')->first();
        if ($draft && $draft->items()->doesntExist()) {
            $this->addItems($draft, self::NON_TEACHING_ITEMS);
            $draft->update(['status' => 'published', 'published_at' => now()]);
            $draft->changeLogs()->create(['user_id' => $hr->id, 'description' => 'Added the non-teaching indicators and published Version 1.']);
        }

        if (Ipcr::exists()) {
            return; // records already seeded
        }

        // Completed records from past periods. A name ending in "*" was returned once, revised and then completed.
        [$p2025a, $p2025b, $p2026a] = $past;
        foreach ([
            [$p2025a, ['Sofia Mendoza', 'Diana Torres', 'Carlo Reyes', 'Marco Villanueva', 'Teresa Uy']],
            [$p2025b, ['Sofia Mendoza', 'Diana Torres', 'Nina Aquino', 'Grace Abad', 'Carlo Reyes', 'Ana Lim*', 'Joy Castillo', 'Teresa Uy', 'Paolo Garcia']],
            [$p2026a, ['Sofia Mendoza', 'Diana Torres', 'Leo Pascual', 'Nina Aquino', 'Bea Fernandez', 'Luis Navarro', 'Carlo Reyes*', 'Ana Lim', 'Marco Villanueva*', 'Joy Castillo', 'Allan Ong', 'Paolo Garcia']],
        ] as [$period, $names]) {
            foreach ($names as $i => $name) {
                $returnOnce = str_ends_with($name, '*');
                $this->runFlow($this->user(rtrim($name, '*')), $period, $period->ends_on->copy()->addDays(3 + $i)->setTime(9, 0), 'completed', returnOnce: $returnOnce);
            }
        }

        // Old notifications and their queued emails would only clutter the demo.
        DB::table('notifications')->delete();
        DB::table('jobs')->delete();

        // Current period, at different steps.
        $oct = fn (int $day) => Carbon::create(2026, 10, $day, 9);
        $this->runFlow($this->user('Leo Pascual'), $current, $oct(2), 'with_dean');
        $this->runFlow($this->user('Diana Torres'), $current, $oct(6), 'submitted');
        $this->runFlow($this->user('Nina Aquino'), $current, $oct(5), 'returned', 'Please state the number of students and sections handled under Core Functions, and list the trainings you attended under Support Functions.');
        $this->runFlow($this->user('Grace Abad'), $current, $oct(8), 'with_hr');
        $this->runFlow($this->user('Bea Fernandez'), $current, $oct(9), 'with_hr');
        $this->runFlow($this->user('Luis Navarro'), $current, $oct(12), 'draft');
        $this->runFlow($this->user('Carlo Reyes'), $current, $oct(10), 'submitted');
        $this->runFlow($this->user('Ana Lim'), $current, $oct(3), 'returned', 'Please list the instructional materials you developed and the courses they are for.', Role::DepartmentHead);
        $this->runFlow($this->user('Paolo Garcia'), $current, Carbon::create(2026, 9, 24, 9), 'completed', returnOnce: true);
        $this->runFlow($this->user('Marco Villanueva'), $current, $oct(4), 'with_dean');
        $this->runFlow($this->user('Joy Castillo'), $current, $oct(1), 'with_hr');
        $this->runFlow($this->user('Teresa Uy'), $current, Carbon::create(2026, 9, 21, 9), 'completed');
        // Sofia Mendoza, Mia Domingo and Allan Ong have not started an IPCR for the current period.

        Carbon::setTestNow();
    }

    private function users(): void
    {
        $password = Hash::make('password');

        foreach (self::PEOPLE as $n => [$name, $email, $role, $position, $collegeName, $departmentName]) {
            $college = $collegeName ? College::where('name', $collegeName)->firstOrFail() : null;
            $department = $departmentName ? Department::where('college_id', $college->id)->where('name', $departmentName)->firstOrFail() : null;

            User::firstOrCreate(['email' => $email], [
                'name' => $name,
                'password' => $password,
                'role' => $role,
                'employee_type' => $role->employeeType(),
                'employee_id' => sprintf('DEMO-%04d', $n + 1),
                'position' => $position,
                'college_id' => $college?->id,
                'department_id' => $department?->id,
                'active' => true,
                'email_verified_at' => now(),
            ])->forceFill(['created_at' => '2024-12-01 08:00:00'])->saveQuietly();
        }
    }

    private function publishedTemplate(RatingPeriod $period, string $type, array $items, User $hr): void
    {
        $template = ReferenceDataSeeder::template($period, $type, 'published');
        $template->update(['published_at' => $period->starts_on->copy()->subDays(10)->setTime(9, 0)]);
        $this->addItems($template, $items);

        $log = $template->changeLogs()->make([
            'user_id' => $hr->id,
            'description' => "Published Version 1 of the {$template->employeeTypeLabel()} template for {$period->name}.",
        ]);
        $log->created_at = $log->updated_at = $template->published_at;
        $log->save();
    }

    private function addItems(IpcrTemplate $template, array $items): void
    {
        foreach ($items as $i => [$section, $outcome, $indicator, $target]) {
            $template->items()->create([
                'section' => $section, 'program_outcome' => $outcome, 'indicator' => $indicator, 'target' => $target, 'sort_order' => $i + 1,
            ]);
        }
    }

    /** Create, fill and push an IPCR through the workflow until it reaches $until. */
    private function runFlow(User $employee, RatingPeriod $period, Carbon $at, string $until, ?string $returnRemarks = null, Role $returnAt = Role::Dean, bool $returnOnce = false): Ipcr
    {
        Carbon::setTestNow($at);
        $ipcr = $this->workflow->create($employee, $period);

        foreach ($ipcr->items as $n => $item) {
            if ($until === 'draft' && $n >= 4) {
                break;
            }
            $item->update(['actual_accomplishment' => $this->accomplishment($item->indicator)]);
        }

        if ($until === 'draft') {
            return $ipcr;
        }

        Carbon::setTestNow($at->copy()->addDay()->setTime(14, 30));
        $this->workflow->submit($ipcr, $employee);

        $pattern = self::PATTERNS[$this->pattern++ % count(self::PATTERNS)];
        $ratings = [];
        foreach ($ipcr->items as $n => $item) {
            $ratings[$item->id] = ['rating' => $pattern[$n % count($pattern)], 'remarks' => null];
        }

        $day = 3;
        while (true) {
            $ipcr->refresh();
            $role = $ipcr->status->reviewerRole();

            if (! $role || $ipcr->status->value === $until) {
                break;
            }

            $reviewer = $this->workflow->reviewersFor($ipcr, $role)->first();
            Carbon::setTestNow($at->copy()->addDays($day)->setTime(10, 15));
            $day += 2;

            if ($until === 'returned' && $role === $returnAt) {
                $this->workflow->return($ipcr, $reviewer, $ratings, $returnRemarks ?? 'Please complete the missing details.');
                break;
            }

            if ($returnOnce) {
                // Returned at the first step, revised by the employee two days later, then resubmitted.
                $returnOnce = false;
                $this->workflow->return($ipcr, $reviewer, $ratings, self::RETURN_REMARKS[$this->remark++ % count(self::RETURN_REMARKS)]);
                Carbon::setTestNow($at->copy()->addDays($day)->setTime(15, 0));
                $day += 2;
                $this->workflow->submit($ipcr->fresh(), $employee);
                continue;
            }

            $this->workflow->approve($ipcr, $reviewer, $ratings, $role === Role::Hr ? 'Final rating recorded.' : null);
        }

        $ipcr->refresh();
        if ($until === 'completed' && $ipcr->status !== IpcrStatus::Completed) {
            throw new RuntimeException("Seeding {$employee->name}'s IPCR stopped at {$ipcr->status->value}.");
        }

        return $ipcr;
    }

    private function accomplishment(string $indicator): string
    {
        foreach (self::ACCOMPLISHMENTS as $start => $text) {
            if (str_starts_with($indicator, $start)) {
                return $text;
            }
        }

        return 'Met the target for this indicator.';
    }

    private function user(string $name): User
    {
        return User::where('name', $name)->firstOrFail();
    }
}
