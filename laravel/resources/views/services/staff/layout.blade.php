@extends('services.layout')
@section('content')
<section class="container section"><div class="staff-heading"><div><p class="eyebrow">SERVICES / STAFF AREA</p><h1>@yield('staff-title', 'Enquiries')</h1></div><form method="post" action="{{ route('services.staff.logout') }}">@csrf<button class="button button-outline">Sign out</button></form></div><nav class="staff-nav" aria-label="Staff navigation"><a href="{{ route('services.staff.index') }}">Enquiry inbox</a><a href="{{ route('services.staff.content') }}">Service page content</a><a href="{{ route('services.home') }}">View services site ↗</a></nav>@include('services.feedback')@yield('staff-content')</section>
@endsection
