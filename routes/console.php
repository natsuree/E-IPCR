<?php

use Illuminate\Support\Facades\Schedule;

// Daily at 8:00 AM: remind employees who have not submitted, in the 3 days before the deadline.
Schedule::command('eipcr:remind-deadline')->dailyAt('08:00');

// Clear expired password-reset and set-password tokens.
Schedule::command('auth:clear-resets')->daily();
