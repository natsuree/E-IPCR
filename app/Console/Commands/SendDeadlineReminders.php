<?php

namespace App\Console\Commands;

use App\Models\Ipcr;
use App\Models\RatingPeriod;
use App\Models\User;
use App\Notifications\IpcrNotification;
use Illuminate\Console\Command;

/** Reminds employees who have not submitted, in the days before the open period's deadline. Runs daily from the scheduler. */
class SendDeadlineReminders extends Command
{
    protected $signature = 'eipcr:remind-deadline {--days=3 : Start reminding this many days before the deadline}';

    protected $description = 'Email and notify employees who have not submitted their IPCR before the deadline';

    public function handle(): int
    {
        $period = RatingPeriod::open();

        if (! $period?->submission_deadline) {
            $this->info('No open rating period with a deadline. Nothing to send.');

            return self::SUCCESS;
        }

        $daysLeft = (int) now()->startOfDay()->diffInDays($period->submission_deadline, false);
        if ($daysLeft < 0 || $daysLeft > (int) $this->option('days')) {
            $this->info("The deadline for {$period->name} is not within the reminder window. Nothing to send.");

            return self::SUCCESS;
        }

        $submitted = Ipcr::where('rating_period_id', $period->id)
            ->whereNotIn('status', ['draft', 'returned'])
            ->pluck('user_id');

        $employees = User::employees()->where('active', true)->whereNotIn('id', $submitted)->get();
        $when = $daysLeft === 0 ? 'today' : ($daysLeft === 1 ? 'tomorrow' : "in {$daysLeft} days");

        foreach ($employees as $employee) {
            $employee->notify(new IpcrNotification(
                "Reminder: the IPCR deadline for {$period->name} is {$when} ({$period->submission_deadline->format('F j, Y')}). Submit your IPCR before then.",
                'employee.ipcrs.current',
                [],
                'IPCR deadline reminder',
            ));
        }

        $this->info("Sent reminders to {$employees->count()} employee(s).");

        return self::SUCCESS;
    }
}
