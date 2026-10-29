@extends('services.layout')
@section('title', $page->title)
@section('description', $page->summary)
@section('content')
<div class="container breadcrumb"><a href="{{ route('services.home') }}">Services</a><span aria-hidden="true">/</span><span>{{ $page->title }}</span></div>
<section class="container page-hero split-section">
    <div><p class="eyebrow">{{ $page->eyebrow }}</p><h1>{{ $page->title }}.<br><em>Made around you.</em></h1><p class="lead">{{ $page->summary }}</p><a class="button" href="{{ route('quote.create', ['service'=>$page->category]) }}">Discuss your requirements ↗</a></div>
    <figure class="service-hero-image"><img src="{{ asset('services/assets/'.(['domestic'=>'home-sample.jpg','commercial'=>'workplace-sample.jpg','care_home'=>'care-sample.jpg'][$page->category])) }}" alt="{{ str_replace('_', ' ', $page->category) }} setting" width="800" height="600"></figure>
</section>
<section class="section container detail-grid">
    <div><p class="eyebrow">YOUR SPACE, CONSIDERED</p><h2>A cleaning plan<br>with the details agreed.</h2><p class="preserve-lines">{{ $page->description }}</p><h3>Tasks to discuss</h3><ul class="check-list">@foreach($page->tasks as $task)<li>{{ $task }}</li>@endforeach</ul><p class="muted">The included tasks, exclusions and price are confirmed in your quotation.</p>@if($page->extras)<h3>Additional requirements</h3><ul class="plain-list">@foreach($page->extras as $extra)<li>{{ $extra }}</li>@endforeach</ul>@endif<h3>Getting ready</h3><p>{{ $page->preparation }}</p></div>
    <aside class="info-card"><p class="eyebrow">AT A GLANCE</p><h3>Your cleaning enquiry</h3><dl><dt>Frequency</dt><dd>{{ $page->frequency }}</dd><dt>Coverage</dt><dd>Share your postcode so the team can confirm availability.</dd><dt>Pricing</dt><dd>A tailored quotation based on your requirements.</dd></dl><a class="button" href="{{ route('quote.create', ['service'=>$page->category]) }}">Request a quote ↗</a><a href="{{ route('services.areas') }}">Ask about your area</a></aside>
</section>
@if($page->faqs)<section class="faq-section"><div class="container split-section"><div><p class="eyebrow">GOOD TO KNOW</p><h2>Your questions,<br>answered.</h2></div><div class="faq-list">@foreach($page->faqs as $faq)<details><summary>{{ $faq['question'] }}</summary><p>{{ $faq['answer'] }}</p></details>@endforeach</div></div></section>@endif
@if($related->isNotEmpty())<section class="section container"><p class="eyebrow">EXPLORE YOUR OPTIONS</p><h2>More ways we can help.</h2><div class="link-grid">@foreach($related as $relatedPage)<a class="link-card" href="{{ route('services.service', $relatedPage->slug) }}"><h3>{{ $relatedPage->title }}</h3><p>{{ $relatedPage->summary }}</p><span aria-hidden="true">↗</span></a>@endforeach</div></section>@endif
@endsection
