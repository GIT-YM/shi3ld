@extends('services.staff.layout')
@section('title', 'Service page content')
@section('staff-title', 'Service page content')
@section('staff-content')
<p>Edit the service copy, task lists and questions shown on the public site. Changes appear when the page is saved. Keep descriptions and claims accurate.</p><div class="inbox-list">@foreach($pages as $page)<div class="enquiry-row"><div><span class="status-tag">{{ $page->published ? 'Published' : 'Unpublished' }}</span><h2>{{ $page->title }}</h2><p>{{ ucfirst(str_replace('_',' ', $page->category)) }}</p></div><div><a class="button button-outline" href="{{ route('services.staff.content.edit', $page) }}">Edit page</a>@if($page->published)<a class="text-link" href="{{ route('services.service', $page->slug) }}">View page ↗</a>@endif</div></div>@endforeach</div>
@endsection
