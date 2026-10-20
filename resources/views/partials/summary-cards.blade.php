{{-- Summary cards shared by the dashboards. Params: $summary, $analytics, $period, $scopeLabel, $overdueCount. --}}
<div class="row g-3 mb-2">
    <div class="col-6 col-lg"><x-stat label="Employees" :value="$summary['employees']" :hint="$scopeLabel" /></div>
    <div class="col-6 col-lg"><x-stat label="Submitted" :value="$summary['submitted']" :hint="$analytics->percent($summary['submitted']).'% of employees'" /></div>
    <div class="col-6 col-lg"><x-stat label="Returned" :value="$summary['returned']" hint="Back with the employee" /></div>
    <div class="col-6 col-lg"><x-stat label="Completed" :value="$summary['completed']" :hint="$analytics->percent($summary['completed']).'% of employees'" /></div>
    <div class="col-6 col-lg"><x-stat label="Not yet submitted" :value="$summary['not_submitted']"
        :hint="$period->submission_deadline ? 'Deadline '.$period->submission_deadline->format('M j, Y') : 'Not started or draft'"
        :class="$overdueCount > 0 ? 'stat-alert' : ''" /></div>
</div>
<p class="small text-secondary mb-4">Percentages are out of all employees in {{ $scopeLabel }} for {{ $period->name }}.</p>
