@extends('layouts.app')
@section('title', $canAll ? 'Applications' : 'My Applications')
@section('crumb', 'Applications')
@section('content')
<div class="page-head">
    <div><h2>{{ $scope === 'mine' ? 'My applications' : 'Applications register' }}</h2><div class="sub">Leave, pass, R&amp;R, official business, training and other authorized absences.</div></div>
    <div class="page-actions">
        @if($canAll)<a href="{{ route('applications.index', request()->query() + ['format' => 'csv']) }}" class="btn btn-secondary"><x-icon name="download" size="sm"/> Export CSV</a>@endif
        @if(auth()->user()->hasPermission('applications.create') || auth()->user()->hasPermission('applications.create_on_behalf'))<a href="{{ route('applications.create') }}" class="btn btn-primary"><x-icon name="plus" size="sm"/> New application</a>@endif
    </div>
</div>
<div class="card">
    @if($canAll)
    <nav class="tabs">
        <a href="{{ route('applications.index', ['scope' => 'all'] + $filters) }}" class="{{ $scope === 'all' ? 'active' : '' }}">All applications</a>
        <a href="{{ route('applications.index', ['scope' => 'mine'] + $filters) }}" class="{{ $scope === 'mine' ? 'active' : '' }}">Mine &amp; prepared by me</a>
    </nav>
    @endif
    <form method="GET" class="filters">
        <input type="hidden" name="scope" value="{{ $scope }}">
        <div class="field grow"><label>Search</label><div class="search-wrap"><x-icon name="search" size="sm"/><input name="q" class="input" value="{{ $filters['q'] ?? '' }}" placeholder="Reference no., name or service no."></div></div>
        <div class="field"><label>Status</label><select name="status" class="select" data-autosubmit><option value="">Any status</option>@foreach(\App\Enums\ApplicationStatus::cases() as $s)<option value="{{ $s->value }}" @selected(($filters['status'] ?? '') === $s->value)>{{ $s->label() }}</option>@endforeach</select></div>
        <div class="field"><label>Type</label><select name="type" class="select" data-autosubmit><option value="">Any type</option>@foreach($types as $t)<option value="{{ $t->id }}" @selected(($filters['type'] ?? '') == $t->id)>{{ $t->name }}</option>@endforeach</select></div>
        <div class="field"><label>From</label><input type="date" name="from" class="input" value="{{ $filters['from'] ?? '' }}"></div>
        <div class="field"><label>To</label><input type="date" name="to" class="input" value="{{ $filters['to'] ?? '' }}"></div>
        <div class="field"><button class="btn btn-primary"><x-icon name="filter" size="sm"/> Apply</button></div>
    </form>
    @include('applications.partials.table', ['applications' => $applications])
</div>
@endsection
