@extends('services.layout')
@section('title', 'Staff sign in')
@section('content')
<section class="container section"><div class="login-panel"><p class="eyebrow">SERVICES / STAFF AREA</p><h1>Welcome back.</h1><p>Sign in to manage service enquiries and page content.</p>@include('services.feedback')<form method="post" action="{{ route('services.staff.authenticate') }}">@csrf<div class="field"><label for="email">Email</label><input id="email" name="email" type="email" autocomplete="username" value="{{ old('email') }}" required maxlength="255"></div><div class="field"><label for="password">Password</label><input id="password" name="password" type="password" autocomplete="current-password" required></div><button class="button">Sign in ↗</button></form><p class="hint">Access is limited to authorised services staff.</p></div></section>
@endsection
