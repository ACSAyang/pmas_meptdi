@extends('layouts.app')
@section('title', 'Import Masterlist')
@section('crumb', 'Personnel Masterlist')
@section('content')
<div class="page-head"><div><h2>Import masterlist</h2><div class="sub">Upload a CSV file exported from Excel. Rows are validated and shown for review before anything is saved.</div></div>
    <div class="page-actions"><a href="{{ route('personnel.import.template') }}" class="btn btn-secondary"><x-icon name="download" size="sm"/> Download template</a></div></div>
<div class="grid grid-main">
    <div class="card">
        <div class="card-header"><h3><x-icon name="upload"/> Upload CSV</h3></div>
        <div class="card-body">
            <form method="POST" action="{{ route('personnel.import.preview') }}" enctype="multipart/form-data" class="stack">@csrf
                <x-field label="CSV file" name="file" required help="Maximum 2 MB. In Excel use File → Save As → CSV UTF-8."><input type="file" name="file" accept=".csv,text/csv" class="input" required></x-field>
                <div class="form-actions"><button class="btn btn-primary"><x-icon name="eye" size="sm"/> Validate &amp; preview</button></div>
            </form>
        </div>
    </div>
    <div class="card">
        <div class="card-header"><h3><x-icon name="info"/> Column guide</h3></div>
        <div class="card-body small">
            <p><strong>Required:</strong> <code>service_number</code>, <code>rank</code> (abbreviation), <code>last_name</code>, <code>first_name</code>, <code>unit_code</code>.</p>
            <p><strong>Optional:</strong> middle_name, suffix, sex (M/F), assignment_type (regular/attached/temporary), start_date (YYYY-MM-DD), designation, email, contact_number.</p>
            <p class="mb-0"><strong>Unit codes:</strong> @foreach($units as $u)<code>{{ $u->code }}</code>@if(!$loop->last), @endif @endforeach</p>
            <p class="mt-2 mb-0"><strong>Ranks:</strong> @foreach($ranks as $r)<code>{{ $r->abbreviation }}</code>@if(!$loop->last), @endif @endforeach</p>
        </div>
    </div>
</div>
@endsection
