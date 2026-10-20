@extends('layouts.app')
@section('title', 'Create / Edit IPCR')
@section('heading', 'Confirm submission')
@section('subtitle', 'Check the summary, then submit your IPCR for review.')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <x-card title="Submit your IPCR for {{ $ipcr->period->name }}?">
                <dl class="row mb-3">
                    <dt class="col-sm-4">Employee</dt><dd class="col-sm-8">{{ $ipcr->user->name }}</dd>
                    <dt class="col-sm-4">Unit</dt><dd class="col-sm-8">{{ $ipcr->user->unitLabel() }}</dd>
                    <dt class="col-sm-4">Template</dt><dd class="col-sm-8">{{ $ipcr->template->employeeTypeLabel() }} · Version {{ $ipcr->template->version }}</dd>
                    @foreach (\App\Models\Ipcr::SECTIONS as $key => $label)
                        <dt class="col-sm-4">{{ $label }}</dt><dd class="col-sm-8">{{ $ipcr->items->where('section', $key)->count() }} indicators, all with accomplishments</dd>
                    @endforeach
                    <dt class="col-sm-4">Goes to</dt><dd class="col-sm-8">{{ $firstStep === \App\Enums\Role::Hr ? 'HR Personnel' : $firstStep->label() }}</dd>
                </dl>
                <div class="notice-soft mb-3 small">
                    After you submit, you cannot edit the IPCR unless a reviewer returns it to you.
                    The route is Department Head → Dean/Director → HR. HR gives the final approval.
                </div>
                <div class="d-flex flex-wrap gap-2 justify-content-end">
                    <a href="{{ route('employee.ipcrs.edit', $ipcr) }}" class="btn btn-outline-secondary">Go back and edit</a>
                    <form method="POST" action="{{ route('employee.ipcrs.submit', $ipcr) }}">
                        @csrf
                        <button class="btn btn-primary"><i class="bi bi-send"></i> Confirm and submit</button>
                    </form>
                </div>
            </x-card>
        </div>
    </div>
@endsection
