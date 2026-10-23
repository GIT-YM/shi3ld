<!doctype html>
<html lang="en-GB">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="@yield('description', 'Explore domestic, commercial and care-home cleaning with Shi3ld Alliance. Discuss your requirements and request a tailored quotation.')">
    @if(app()->environment('local') || request()->is('services/staff*'))
    <meta name="robots" content="noindex,nofollow">
    @else
    <meta name="robots" content="index,follow">
    @endif
    <link rel="canonical" href="{{ url()->current() }}">
    <meta name="theme-color" content="#183f37">
    <meta property="og:title" content="@yield('title', 'Cleaning for the spaces that matter') | Shi3ld Alliance">
    <meta property="og:description" content="@yield('description', 'Domestic, commercial and care-home cleaning. Discuss your requirements with Shi3ld Alliance.')">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="{{ asset('services/assets/home-sample.jpg') }}">
    <title>@yield('title', 'Cleaning for the spaces that matter') | Shi3ld Alliance</title>
    <link rel="stylesheet" href="{{ asset('services/assets/site.css') }}">
    <link rel="stylesheet" href="{{ asset('services/assets/cms.css') }}?v=20261021.3">
    <script src="{{ asset('services/assets/site.js') }}" defer></script>
    <link rel="stylesheet" href="{{ asset('services/assets/builder-runtime.css') }}?v=20261024.2">
    {!! app(\App\Services\BuilderRuntime::class)->globalHtml() !!}
    <script src="{{ asset('services/assets/builder-runtime.js') }}?v=20261024.2" defer></script>
    <link rel="icon" href="{{ asset('services/assets/favicon.svg') }}" type="image/svg+xml">
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>
@if(config('services_site.sample_content'))
<div class="preview-bar">Development preview · Sample copy and imagery · Please use sample enquiry details.</div>
@endif
@if(!empty($sharedWebsiteDesign['header']))
{!! $sharedWebsiteDesign['header'] !!}
@else
<div class="utility-bar"><div class="container"><span>Accessibility products · Cleaning services</span><a href="{{ config('shi3ld.shop_url') }}">Explore accessibility products <span aria-hidden="true">↗</span></a></div></div>
<header class="site-header">
    <div class="container header-inner">
        <a class="brand" href="{{ config('shi3ld.site_url') }}" aria-label="Shi3ld Alliance home">
            <svg aria-hidden="true" viewBox="0 0 42 46"><path d="M4 4h34v20c0 10-17 18-17 18S4 34 4 24Z" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 14h18M12 21h18M12 28h11" stroke="currentColor" stroke-width="3"/></svg>
            <span>SHI3LD <span class="brand-small">ALLIANCE</span></span>
        </a>
        <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="site-nav">Menu <span aria-hidden="true">☰</span></button>
        <nav id="site-nav" class="site-nav" aria-label="Main navigation">
            <a href="{{ config('shi3ld.site_url') }}">Home</a>
            <a href="{{ config('shi3ld.shop_url') }}">Shop</a>
            <a href="{{ route('services.home') }}" @if(request()->is('services*')) aria-current="page" @endif>Services</a>
            <a href="{{ config('shi3ld.about_url') }}">About</a>
            <a href="{{ config('shi3ld.account_url') }}">Account</a>
            <a class="button button-small" href="{{ route('quote.create') }}">Request a quote <span aria-hidden="true">↗</span></a>
        </nav>
    </div>
</header>
@endif
<main id="main">@yield('content')</main>
@unless((request()->is('services/staff*') && !isset($document)) || request()->routeIs('quote.*'))
<section class="cta-band"><div class="container cta-inner"><div><p class="eyebrow">LET’S TALK ABOUT YOUR SPACE</p><h2>A fresh start begins<br>with a conversation.</h2></div><div><p>Tell us what you need. We’ll discuss a cleaning plan that works for you.</p><a class="button button-light" href="{{ route('quote.create') }}">Request your quote <span aria-hidden="true">↗</span></a></div></div></section>
@endunless
@if(!empty($sharedWebsiteDesign['footer']))
{!! $sharedWebsiteDesign['footer'] !!}
@else
<footer class="site-footer">
    <div class="container footer-grid">
        <div><a class="brand" href="{{ config('shi3ld.site_url') }}"><span>SHI3LD <span class="brand-small">ALLIANCE</span></span></a><p>Thoughtful care.<br>Support for everyday spaces.</p><a href="{{ config('shi3ld.shop_url') }}">Visit the accessibility shop ↗</a></div>
        <div><h2>Cleaning services</h2>@foreach($navigationPages as $navigationPage)<a href="{{ route('services.service', $navigationPage->slug) }}">{{ $navigationPage->title }}</a>@endforeach<a href="{{ route('quote.create') }}">Request a quote</a></div>
        <div><h2>Helpful information</h2><a href="{{ route('services.how-it-works') }}">How it works</a><a href="{{ route('services.areas') }}">Service areas</a><a href="{{ route('services.faqs') }}">Common questions</a><a href="{{ route('services.gallery') }}">Our spaces</a></div>
        <div><h2>Shi3ld Alliance</h2><a href="{{ config('shi3ld.about_url') }}">About us</a><a href="{{ route('services.contact') }}">Contact us</a><a href="{{ route('services.privacy') }}">Service privacy information</a><a href="{{ route('services.cookies') }}">Service cookie information</a><a href="{{ route('services.terms') }}">Service terms</a></div>
    </div>
    <div class="container footer-bottom"><span>© {{ now()->year }} Shi3ld Alliance</span><span>Accessibility products · Domestic, commercial and care-home cleaning.</span><a href="{{ route('login') }}">Services staff sign in</a></div>
</footer>
@endif
@isset($contentEditorKey)
@if(auth()->user()?->services_staff)
<script src="{{ asset('services/assets/content-toolbar.js') }}?v=20261021.3" data-locate-url="{{ route('services.visual.locate', ['key' => $contentEditorKey]) }}" defer></script>
@endif
@endisset
<script src="{{ asset('services/assets/content-toolbar.js') }}?v=20261021.3" data-locate-url="{{ rtrim(config('shi3ld.site_url'), '/') }}/admin/shi3ld_content/editor/locate/" defer></script>
{!! app(\App\Services\BuilderRuntime::class)->popups() !!}
</body>
</html>
