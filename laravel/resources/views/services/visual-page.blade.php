@extends('services.layout')
@section('title', e($document['meta_title'] ?: $document['title']))
@section('description', e($document['meta_description']))
@section('content')
@if($isContentPreview ?? false)<div class="notice">Private draft preview — this content has not been published.</div>@endif
<div data-shi3ld-region="page">{!! $html !!}</div>
@endsection
