@extends('layouts.app')
@section('title', 'Notifications')
@section('content')
<div class="page-head"><div><h2>Notifications</h2><div class="sub">In-app notices about your applications, approvals and alerts.</div></div>
    <div class="page-actions"><form method="POST" action="{{ route('notifications.read-all') }}">@csrf<button class="btn btn-secondary"><x-icon name="check" size="sm"/> Mark all as read</button></form></div>
</div>
<div class="card">
    @forelse($notifications as $n)
        @if($loop->first)<ul class="list">@endif
        <li style="{{ $n->read_at ? '' : 'background:var(--navy-50)' }}">
            <span class="tone-dot tone-{{ $n->data['tone'] ?? 'navy' }}" style="margin-top:0"></span>
            <div class="grow"><div class="t">{{ $n->data['title'] ?? 'Notification' }}</div><div class="s">{{ $n->data['body'] ?? '' }}</div></div>
            <div class="xs muted nowrap">{{ $n->created_at->format('d M Y H:i') }}</div>
            <a class="btn btn-secondary btn-xs" href="{{ route('notifications.open', $n->id) }}">Open</a>
        </li>
        @if($loop->last)</ul>@endif
    @empty
        <x-empty icon="bell" title="No notifications"/>
    @endforelse
    {{ $notifications->links() }}
</div>
@endsection
