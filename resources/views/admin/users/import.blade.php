@extends('layouts.app')
@section('title', 'Users')
@section('heading', 'Import users from CSV')
@section('subtitle', 'Upload a CSV file, check every row, then import the rows without errors.')

@section('content')
    <div class="row g-4">
        <div class="col-xl-5">
            <x-card title="1. Upload the file">
                <form method="POST" action="{{ route('admin.users.import.preview') }}" enctype="multipart/form-data" novalidate>
                    @csrf
                    <div class="mb-3">
                        <label for="file" class="form-label fw-semibold">CSV file</label>
                        <input id="file" name="file" type="file" accept=".csv,text/csv" class="form-control @error('file') is-invalid @enderror">
                        @error('file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <button class="btn btn-primary"><i class="bi bi-search"></i> Check the file</button>
                </form>
            </x-card>
            <x-card title="File format">
                <p class="small">The first row must be the column names:</p>
                <code class="d-block small mb-2">{{ implode(', ', \App\Services\UserImporter::COLUMNS) }}</code>
                <ul class="small mb-2">
                    <li><strong>role</strong>: Teaching Employee, Non-Teaching Employee, Department Head, Dean/Director, HR Personnel or Administrator.</li>
                    <li><strong>employee_type</strong>: Teaching or Non-teaching (for employees; may be left blank).</li>
                    <li><strong>college_office</strong>: the exact name or code from Colleges &amp; Offices.</li>
                    <li><strong>department</strong>: required for Department Heads; leave blank for offices.</li>
                </ul>
                <a href="{{ route('admin.users.import.sample') }}" class="small"><i class="bi bi-download"></i> Download a blank template</a>
            </x-card>
        </div>

        <div class="col-xl-7">
            @if ($preview)
                @if ($preview['fileErrors'])
                    <div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> {{ $preview['fileErrors'][0] }}</div>
                @else
                    <x-card title="2. Check the rows" flush>
                        <div class="d-flex flex-wrap gap-3 px-3 pt-3">
                            <span class="badge badge-status badge-status-completed">{{ $preview['valid'] }} ready to import</span>
                            @if ($preview['invalid'])
                                <span class="badge badge-status badge-status-returned">{{ $preview['invalid'] }} with errors, will be skipped</span>
                            @endif
                        </div>
                        <div class="table-responsive mt-2">
                            <table class="table table-eipcr table-sm">
                                <thead><tr><th>Row</th><th>Name / email</th><th>Role</th><th>Unit</th><th>Result</th></tr></thead>
                                <tbody>
                                @foreach ($preview['rows'] as $row)
                                    <tr @class(['table-warning' => $row['errors']])>
                                        <td>{{ $row['line'] }}</td>
                                        <td>{{ $row['values']['name'] ?: '—' }}<div class="small text-secondary">{{ $row['values']['email'] }}</div></td>
                                        <td>{{ $row['values']['role'] }}</td>
                                        <td>{{ $row['values']['college_office'] }}@if ($row['values']['department']) · {{ $row['values']['department'] }}@endif</td>
                                        <td>
                                            @if ($row['errors'])
                                                <ul class="small text-danger mb-0 ps-3">@foreach ($row['errors'] as $error)<li>{{ $error }}</li>@endforeach</ul>
                                            @else
                                                <span class="text-accent small fw-semibold"><i class="bi bi-check-circle"></i> Ready</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </x-card>
                    <x-card title="3. Import">
                        @if ($preview['valid'] > 0)
                            <p class="small">Import <strong>{{ $preview['valid'] }}</strong> account(s). Each person gets an email with a link to set their password.
                                @if ($preview['invalid']) Rows with errors are skipped; fix them in the file and upload it again.@endif</p>
                            <form method="POST" action="{{ route('admin.users.import.store') }}">
                                @csrf
                                <button class="btn btn-primary"><i class="bi bi-upload"></i> Import {{ $preview['valid'] }} account(s)</button>
                                <a href="{{ route('admin.users.import') }}" class="btn btn-outline-secondary">Cancel</a>
                            </form>
                        @else
                            <p class="small mb-0">No rows can be imported. Fix the errors in the file, then upload it again.</p>
                        @endif
                    </x-card>
                @endif
            @else
                <div class="empty-state notice-soft"><i class="bi bi-file-earmark-spreadsheet"></i>Upload a CSV file to see a preview of every row before anything is saved.</div>
            @endif
        </div>
    </div>
@endsection
