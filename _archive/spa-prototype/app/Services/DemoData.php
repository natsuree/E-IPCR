<?php

namespace App\Services;

use App\Models\EvaluationPeriod;
use App\Models\IpcrRecord;
use App\Models\Notification;
use App\Models\Setting;
use App\Models\Unit;
use App\Models\User;
use App\Services\Ipcr\IpcrRowFactory;
use App\Services\Ipcr\RatingService;
use App\Services\Ipcr\WorkflowService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Exact port of dist/js/model.js seed() plus progress.js upgradeRoster(_, true)
 * from the frontend prototype: fictional users, units, periods, records,
 * history, notifications and roster assignments.
 */
class DemoData
{
    private const ROLES = [
        'Administrator', 'HR Personnel', 'President', 'Vice President',
        'Dean', 'Department Head', 'Teaching Employee', 'Non-Teaching Employee',
    ];

    private const NAMES = [
        'Alex Rivera', 'Mika Santos', 'Gabriel Reyes', 'Isabel Cruz',
        'Elena Flores', 'Daniel Ramos', 'Sofia Mendoza', 'Luis Navarro',
    ];

    private const EMAILS = ['admin', 'hr', 'president', 'vp', 'dean', 'head', 'teaching', 'nonteaching'];

    private const POSITIONS = [
        'System Administrator', 'HR Officer', 'University President', 'Vice President',
        'College Dean', 'Department Chair', 'Assistant Professor', 'Administrative Officer',
    ];

    public static function seed(): void
    {
        $settings = Setting::defaults();
        $ratingService = new RatingService;

        // --- Organizational units (model.js seed) ---
        Unit::create(['id' => 'coe', 'name' => 'College of Engineering', 'departments' => ['Computer Engineering', 'Civil Engineering']]);
        Unit::create(['id' => 'admin', 'name' => 'Administrative Services', 'departments' => ['Records Office', 'Finance Office']]);

        // --- Base users u0..u7 ---
        $baseUsers = [];
        foreach (self::ROLES as $i => $role) {
            $baseUsers[$i] = User::create([
                'id' => 'u' . $i,
                'name' => self::NAMES[$i],
                'email' => self::EMAILS[$i] . '@demo.mmsu.edu.ph',
                'password' => 'Demo@2026',
                'role' => $role,
                'position' => self::POSITIONS[$i],
                'unit' => $i === 7 ? 'Administrative Services' : 'College of Engineering',
                'department' => $i === 7 ? 'Records Office' : 'Computer Engineering',
                'type' => $i === 7 ? 'Non-Teaching' : 'Teaching',
                'active' => true,
            ]);
        }

        // A second pair of reviewers demonstrates assignment boundaries for office staff.
        User::create([
            'id' => 'head-office', 'name' => 'Clara Dizon', 'email' => 'head.office@demo.mmsu.edu.ph',
            'password' => 'Demo@2026', 'role' => 'Department Head', 'position' => 'Department Chair',
            'unit' => 'Administrative Services', 'department' => 'Records Office', 'type' => 'Teaching', 'active' => true,
        ]);
        User::create([
            'id' => 'dean-office', 'name' => 'Rafael Lim', 'email' => 'dean.office@demo.mmsu.edu.ph',
            'password' => 'Demo@2026', 'role' => 'Dean', 'position' => 'College Dean',
            'unit' => 'Administrative Services', 'department' => 'Records Office', 'type' => 'Teaching', 'active' => true,
        ]);

        Setting::create($settings);

        EvaluationPeriod::create(['id' => '2026-2', 'name' => 'July – December 2026', 'start' => '2026-07-01', 'end' => '2026-12-31', 'active' => true]);
        EvaluationPeriod::create(['id' => '2026-1', 'name' => 'January – June 2026', 'start' => '2026-01-01', 'end' => '2026-06-30', 'active' => false]);

        // --- Eight sample IPCR records (IPCR-1001 .. IPCR-1008) ---
        $statuses = ['Draft', 'Department Head', 'Dean', 'HR Personnel', 'Vice President', 'President', 'Completed', 'Returned'];
        $actuals = ['Evidence prepared and filed', 'Targets delivered within the period', 'Activity completed and documented'];

        for ($i = 0; $i < 8; $i++) {
            $owner = $baseUsers[$i % 2 ? 7 : 6];
            $periodId = $i < 6 ? '2026-2' : '2026-1';
            $created = sprintf('2026-%s-%sT08:30:00.000Z', $i < 6 ? '09' : '06', str_pad((string) (10 + $i), 2, '0', STR_PAD_LEFT));

            $record = self::makeRecord($owner, $periodId, $settings, 'IPCR-' . (1001 + $i), $created);
            $record->status = $statuses[$i];
            $record->save();

            foreach ($record->rows as $rowIndex => $row) {
                $row->actual = $actuals[$rowIndex];
                $row->q = 3 + ($i % 3);
                $row->e = 4;
                $row->t = 4;
                $row->save();
            }

            $history = [
                ['at' => $created, 'actor' => $owner->name, 'role' => $owner->role,
                    'action' => 'Created sample record', 'comment' => 'Fictional demonstration record.'],
            ];

            if ($record->status !== 'Draft') {
                $record->submissions()->create([
                    'at' => $created,
                    'snapshot' => [
                        'rows' => $record->rows->map->toApi()->values()->all(),
                        'commitment' => $record->commitment,
                        'development' => $record->development,
                        'ratingSettings' => $settings,
                    ],
                ]);

                $history[] = ['at' => $created, 'actor' => $owner->name, 'role' => $owner->role,
                    'action' => 'Submitted', 'comment' => ''];

                $approved = $record->status === 'Returned'
                    ? 2
                    : array_search($record->status, WorkflowService::STAGES, true);

                for ($j = 0; $j < $approved; $j++) {
                    $role = WorkflowService::STAGES[$j];
                    $reviewer = User::where('role', $role)
                        ->when($role === 'Department Head', fn ($q) => $q->where('department', $record->department))
                        ->when($role === 'Dean', fn ($q) => $q->where('unit', $record->unit))
                        ->get()->first();

                    $history[] = [
                        'at' => self::offsetSeconds($created, ($j + 1) * 3600),
                        'actor' => $reviewer?->name ?? $role,
                        'role' => $role,
                        'action' => $role === 'President' ? 'Final approval · Completed' : 'Approved and forwarded',
                        'comment' => 'Sample review completed.',
                    ];
                }

                if ($record->status === 'Returned') {
                    $history[] = ['at' => self::offsetSeconds($created, 3 * 3600), 'actor' => 'Mika Santos',
                        'role' => 'HR Personnel', 'action' => 'Returned for correction',
                        'comment' => 'Please specify the number of requests completed and the completion dates.'];
                }
            }

            foreach ($history as $entry) {
                $record->history()->create($entry);
            }

            $record->updated = $history[array_key_last($history)]['at'];
            $record->save();
        }

        // --- upgradeRoster(): explicit period membership for employees ---
        foreach (User::all() as $person) {
            if ($person->isEmployee()) {
                $person->periods()->attach(EvaluationPeriod::pluck('id')->all());
            }
        }

        // --- upgradeRoster(): backfill final ratings for approved records ---
        foreach (IpcrRecord::where('status', 'Completed')->get() as $record) {
            $hasFinalApproval = $record->history()
                ->where('role', 'President')
                ->where('action', 'Final approval · Completed')
                ->exists();
            if ($hasFinalApproval && $record->final_rating === null) {
                $presidentAt = $record->history()->where('role', 'President')->orderByDesc('id')->value('at');
                $record->final_rating = [
                    ...$ratingService->rating($record->rows, $record->rating_settings),
                    'approvedAt' => $presidentAt,
                ];
                $record->save();
            }
        }

        // --- upgradeRoster(addSamples = true): extra unit, reviewers and roster ---
        Unit::create(['id' => 'cas', 'name' => 'College of Arts and Sciences', 'departments' => ['Mathematics', 'Biology']]);

        $specs = [
            ['eng-civil-head', 'Paolo Reyes', 'Department Head', 'College of Engineering', 'Civil Engineering'],
            ['cas-dean', 'Marina Acosta', 'Dean', 'College of Arts and Sciences', 'Mathematics'],
            ['math-head', 'Carlo Garcia', 'Department Head', 'College of Arts and Sciences', 'Mathematics'],
            ['bio-head', 'Liza Mercado', 'Department Head', 'College of Arts and Sciences', 'Biology'],
            ['finance-head', 'Ramon Castillo', 'Department Head', 'Administrative Services', 'Finance Office'],
        ];

        foreach ($specs as [$id, $name, $role, $unit, $department]) {
            if (User::where('id', $id)->exists()) {
                continue;
            }
            User::create([
                'id' => $id, 'name' => $name, 'role' => $role, 'unit' => $unit, 'department' => $department,
                'email' => $id . '@demo.mmsu.edu.ph', 'password' => 'Demo@2026', 'position' => $role,
                'type' => $unit === 'Administrative Services' ? 'Non-Teaching' : 'Teaching', 'active' => true,
            ]);
        }

        $samples = [
            ['civil-1', 'Ana Bautista', 'College of Engineering', 'Civil Engineering', 'Completed', 4.8],
            ['civil-2', 'Marco Valdez', 'College of Engineering', 'Civil Engineering', 'Completed', 4.8],
            ['civil-3', 'Nina Aquino', 'College of Engineering', 'Civil Engineering', 'HR Personnel', 4.9],
            ['civil-4', 'Jose Santiago', 'College of Engineering', 'Civil Engineering', null],
            ['comp-1', 'Diana Torres', 'College of Engineering', 'Computer Engineering', 'Draft'],
            ['comp-2', 'Leo Pascual', 'College of Engineering', 'Computer Engineering', 'Returned'],
            ['comp-3', 'Mia Domingo', 'College of Engineering', 'Computer Engineering', null],
            ['math-1', 'Bea Fernandez', 'College of Arts and Sciences', 'Mathematics', 'HR Personnel'],
            ['math-2', 'Anton Lopez', 'College of Arts and Sciences', 'Mathematics', 'Vice President'],
            ['math-3', 'Rica Manuel', 'College of Arts and Sciences', 'Mathematics', 'Completed', 4.5],
            ['math-4', 'Tom Villanueva', 'College of Arts and Sciences', 'Mathematics', null],
            ['bio-1', 'Ella Ramos', 'College of Arts and Sciences', 'Biology', 'President'],
            ['bio-2', 'Noel Agustin', 'College of Arts and Sciences', 'Biology', 'Department Head'],
            ['bio-3', 'Irene Tan', 'College of Arts and Sciences', 'Biology', 'Dean'],
            ['finance-1', 'Grace Abad', 'Administrative Services', 'Finance Office', 'HR Personnel'],
            ['finance-2', 'Ben Flores', 'Administrative Services', 'Finance Office', 'Vice President'],
            ['finance-3', 'Rose Diaz', 'Administrative Services', 'Finance Office', null],
            ['civil-5', 'Victor Cruz', 'College of Engineering', 'Civil Engineering', 'President'],
        ];

        $activePeriod = EvaluationPeriod::where('active', true)->value('id')
            ?? EvaluationPeriod::orderBy('id')->value('id');

        foreach ($samples as $sample) {
            [$key, $name, $unit, $department, $status] = $sample;
            $score = $sample[5] ?? 4;
            $id = 'sample-' . $key;
            if (User::where('id', $id)->exists() || $activePeriod === null) {
                continue;
            }

            $type = $unit === 'Administrative Services' ? 'Non-Teaching' : 'Teaching';
            $person = User::create([
                'id' => $id, 'name' => $name, 'unit' => $unit, 'department' => $department, 'type' => $type,
                'role' => $type . ' Employee', 'email' => $key . '@demo.mmsu.edu.ph', 'password' => 'Demo@2026',
                'position' => $type === 'Teaching' ? 'Assistant Professor' : 'Administrative Officer',
                'active' => true,
            ]);
            $person->periods()->attach($activePeriod);

            if ($status === null) {
                continue;
            }

            $created = WorkflowService::nowIso();
            $record = self::makeRecord($person, $activePeriod, $settings, 'DEMO-' . $key, $created);
            $record->status = $status;
            $record->save();

            foreach ($record->rows as $row) {
                $row->q = $score;
                $row->e = $score;
                $row->t = $score;
                $row->actual = 'Sample accomplishment with supporting evidence.';
                $row->save();
            }

            if ($status !== 'Draft') {
                $record->submissions()->create([
                    'at' => $created,
                    'snapshot' => [
                        'rows' => $record->rows->map->toApi()->values()->all(),
                        'ratingSettings' => $settings,
                    ],
                ]);

                $record->history()->create([
                    'at' => $created, 'actor' => $name, 'role' => $person->role,
                    'action' => 'Submitted', 'comment' => 'Fictional sample.',
                ]);

                $end = $status === 'Returned' ? 2 : array_search($status, WorkflowService::STAGES, true);
                $workflow = new WorkflowService($ratingService);

                foreach (array_slice(WorkflowService::STAGES, 0, $end) as $role) {
                    $reviewer = User::where('role', $role)->get()
                        ->first(fn (User $candidate) => $workflow->inScope($candidate, $person));

                    $record->history()->create([
                        'at' => $created,
                        'actor' => $reviewer?->name ?? $role,
                        'role' => $role,
                        'action' => $role === 'President' ? 'Final approval · Completed' : 'Approved and forwarded',
                        'comment' => 'Sample approval.',
                    ]);
                }

                if ($status === 'Returned') {
                    $record->history()->create([
                        'at' => $created, 'actor' => 'Mika Santos', 'role' => 'HR Personnel',
                        'action' => 'Returned for correction', 'comment' => 'Please add evidence.',
                    ]);
                }
            }

            if ($status === 'Completed') {
                $record->final_rating = [
                    ...$ratingService->rating($record->rows, $settings),
                    'approvedAt' => $created,
                ];
            }

            $record->save();
        }

        // --- Seed notification (model.js seed) ---
        Notification::create([
            'id' => (string) Str::uuid(),
            'user' => 'u6',
            'text' => 'Your July – December evaluation period is open.',
            'record' => null,
            'at' => WorkflowService::nowIso(),
            'read' => false,
        ]);
    }

    /** Wipe every E-IPCR table and rebuild the original sample data. */
    public static function reset(): void
    {
        foreach ([
            'notifications', 'ipcr_submissions', 'ipcr_history', 'ipcr_rows', 'ipcr_records',
            'evaluation_period_user', 'users', 'units', 'evaluation_periods', 'settings',
        ] as $table) {
            DB::table($table)->delete();
        }

        self::seed();
    }

    /** Prototype makeRecord() for seeds with fixed ids/dates. */
    private static function makeRecord(User $owner, string $period, array $settings, string $id, string $created): IpcrRecord
    {
        $record = IpcrRecord::create([
            'id' => $id,
            'owner_id' => $owner->id,
            'name' => $owner->name,
            'position' => $owner->position,
            'unit' => $owner->unit,
            'department' => $owner->department,
            'type' => $owner->type,
            'period' => $period,
            'status' => 'Draft',
            'created' => $created,
            'updated' => $created,
            'commitment' => 'I commit to deliver and agree to be evaluated on the attainment of the following targets in accordance with the indicated measures.',
            'development' => '',
            'mfo' => null,
            'rating_settings' => $settings,
        ]);

        foreach (WorkflowService::templateRows($owner->type) as $row) {
            IpcrRowFactory::create($record, $row);
        }

        return $record->load('rows');
    }

    /** ISO timestamp offset (whole seconds) in UTC, matching JS toISOString(). */
    private static function offsetSeconds(string $iso, int $seconds): string
    {
        $base = strtotime($iso);

        return gmdate('Y-m-d\TH:i:s.000Z', ($base === false ? 0 : $base) + $seconds);
    }
}
