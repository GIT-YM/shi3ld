@extends('services.staff.layout')
@section('title', 'Service page content')
@section('staff-title', 'Service page content')
@section('staff-content')
<p>Open a page in the visual editor, save a private draft, preview desktop/mobile layouts and publish when ready. The History tab restores earlier versions as a draft.</p>
<div class="inbox-list">@foreach($pages as $page)<div class="enquiry-row"><div><span class="status-tag">{{ $page->published ? 'Published' : 'Unpublished' }}</span><h2>{{ $page->title }}</h2><p>{{ ucfirst(str_replace('_',' ', $page->category)) }}</p></div><div><a class="button button-outline" href="{{ route('services.staff.visual', 'page-'.$page->id) }}">Open visual editor</a><a href="{{ route('services.staff.content.edit', $page) }}">Original structured fields</a>@if($page->published)<a class="text-link" href="{{ route('services.service', $page->slug) }}">View page ↗</a>@endif</div></div>@endforeach</div>
<h2>Website pages</h2><div class="inbox-list">@foreach($visualDocuments as $document)<div class="enquiry-row"><h3>{{ $document['title'] }}</h3><a class="button button-outline" href="{{ route('services.staff.visual', $document['key']) }}">Open visual editor</a></div>@endforeach</div>
<h2>Shared header and footer</h2><p>These regions are managed in Magento and shared across the complete website. A Magento account with visual editing permission is required.</p><div class="button-row">@foreach(['header','footer'] as $region)<a class="button button-outline" href="{{ rtrim(config('shi3ld.site_url'), '/') }}/admin/shi3ld_content/editor/region/?region={{ $region }}">Edit {{ $region }} ↗</a>@endforeach</div>
@endsection
