<x-card title="Highest-performing employees by department">
    <p class="small text-secondary">Prototype rankings use completed IPCRs with recorded HR final approval.</p>
    <table class="table table-eipcr">
        <thead><tr><th>College / office</th><th>Department</th><th>Employee(s)</th><th>Final rating</th></tr></thead>
        <tbody>@foreach ($analytics->departmentLeaders() as $unit)
            <tr><td>{{ $unit['college'] }}</td><td>{{ $unit['department'] }}</td><td>{{ $unit['names'] ?: 'No final ratings yet.' }}</td><td>{{ $unit['rating'] ?? '—' }}</td></tr>
        @endforeach</tbody>
    </table>
</x-card>
