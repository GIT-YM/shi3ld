@extends('services.staff.layout')
@section('title', 'Contact messages')
@section('staff-title', 'Contact messages')
@section('staff-content')
<form class="inbox-filters" method="get" action="{{ route('services.staff.contacts') }}">
    <div class="field"><label for="status">Status</label><select name="status" id="status"><option value="">All messages</option>@foreach(['new'=>'New','replied'=>'Replied','closed'=>'Closed'] as $key=>$label)<option value="{{ $key }}" @selected(request('status')===$key)>{{ $label }}</option>@endforeach</select></div>
    <button class="button">Filter</button><a href="{{ route('services.staff.contacts') }}">Reset</a>
</form>
<div class="inbox-heading"><p>{{ $messages->total() }} {{ Str::plural('contact message', $messages->total()) }}</p></div>
<div class="inbox-list">@forelse($messages as $message)<a class="enquiry-row" href="{{ route('services.staff.contact', $message) }}"><div><span class="status-tag">{{ ucfirst($message->status) }}</span><h2>{{ $message->subject }}</h2><p>{{ $message->name }} · {{ $message->email }}</p></div><div><p>{{ $message->created_at->format('d M Y, H:i') }}</p><span class="reference">{{ $message->reference }}</span></div><span aria-hidden="true">↗</span></a>@empty<div class="empty-state"><h2>No contact messages yet.</h2><p>Messages submitted through the public contact form will appear here.</p></div>@endforelse</div>
@if($messages->hasPages())<nav class="pagination" aria-label="Contact message pages">@if($messages->previousPageUrl())<a class="button button-outline" href="{{ $messages->previousPageUrl() }}">Previous</a>@endif<span>Page {{ $messages->currentPage() }} of {{ $messages->lastPage() }}</span>@if($messages->nextPageUrl())<a class="button button-outline" href="{{ $messages->nextPageUrl() }}">Next</a>@endif</nav>@endif
@endsection
