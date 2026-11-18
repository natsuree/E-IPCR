@extends('layouts.app')
@section('title', 'Eligible employee roster')
@section('subtitle', $period->name)
@section('content')
    <x-card title="Employees assigned to this evaluation period">
        <form method="POST" action="{{ route('hr.periods.roster.save', $period) }}">
            @csrf @method('PUT')
            @foreach ($employees as $employee)
                <label class="d-block mb-2"><input type="checkbox" name="employees[]" value="{{ $employee->id }}" @checked(in_array($employee->id, $selected))>
                    {{ $employee->name }} · {{ $employee->unitLabel() }}</label>
            @endforeach
            <button class="btn btn-primary">Save roster</button>
            <a href="{{ route('hr.periods.index') }}">Cancel</a>
        </form>
    </x-card>
@endsection
