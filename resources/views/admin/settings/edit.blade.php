@extends('layouts.app')
@section('title', 'System Settings')
@section('crumb', 'Administration')
@section('content')
<div class="page-head"><div><h2>System settings</h2><div class="sub">Policy-driven values. Defaults are placeholders until MEPTDI confirms the official rules.</div></div></div>
<form method="POST" action="{{ route('admin.settings.update') }}" class="stack" style="max-width:900px">@csrf @method('PUT')
    @foreach(['Organization' => ['org_name', 'org_short_name', 'parent_org', 'office_timezone'], 'Accountability rules' => ['overdue_grace_minutes', 'muster_validity_hours'], 'Documents' => ['upload_max_kb', 'upload_types'], 'Reports' => ['report_footer_note', 'prepared_by_label', 'verified_by_label', 'noted_by_label']] as $section => $keys)
    <div class="card"><div class="card-header"><h3>{{ $section }}</h3></div><div class="card-body"><div class="form-grid">
        @foreach($keys as $key)
            <x-field :label="$labels[$key][0]" :name="$key" :help="$labels[$key][1]" class="{{ in_array($key, ['org_name', 'report_footer_note']) ? 'span-2' : '' }}">
                <input id="{{ $key }}" name="{{ $key }}" class="input" value="{{ old($key, $values[$key] ?? '') }}" @if(in_array($key, ['overdue_grace_minutes', 'muster_validity_hours', 'upload_max_kb'])) type="number" min="0" @endif>
            </x-field>
        @endforeach
    </div></div></div>
    @endforeach
    <div class="form-actions"><button class="btn btn-primary"><x-icon name="check" size="sm"/> Save settings</button></div>
</form>
@endsection
