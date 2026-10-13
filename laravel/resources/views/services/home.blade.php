@extends('services.layout')
@section('title', 'Cleaning for the spaces that matter')
@section('content')
<section class="hero container">
    <div class="hero-copy"><p class="eyebrow"><span class="small-line"></span> A LITTLE CARE. A FRESH PERSPECTIVE.</p><h1>Cleaning for<br>the spaces<br>that <em>matter.</em></h1><p class="lead">From the comfort of home to the places we work and care for others. Let’s make room for a cleaner, calmer day.</p><div class="button-row"><a class="button" href="{{ route('quote.create') }}">Find your cleaning plan <span aria-hidden="true">↗</span></a><a class="text-link" href="#our-services">Explore our services <span aria-hidden="true">↓</span></a></div><div class="hero-note"><span class="round-icon" aria-hidden="true">✓</span><p>Your space. Your priorities.<br><strong>A quotation tailored to you.</strong></p></div></div>
    <div class="hero-image"><img src="{{ asset('services/assets/home-sample.jpg') }}" alt="Illustrative sunlit living room with an oak table, cream sofa and plants" width="1672" height="941" fetchpriority="high"><div class="image-note"><span aria-hidden="true">✦</span><div>A little less to do.<br><strong>A little more room to live.</strong></div></div></div>
</section>
<div class="values-strip"><div class="container"><span>Cleaning around your routine</span><span>Clear, agreed task lists</span><span>One-off & recurring enquiries</span><span>One connected alliance</span></div></div>
<section class="section container" id="our-services">
    <div class="section-heading"><div><p class="eyebrow">A CLEANING PLAN FOR EVERY SETTING</p><h2>Different spaces.<br>The same thoughtful approach.</h2></div><p>Choose the service that fits your space. We’ll help you work through the details.</p></div>
    <div class="service-grid">
        @foreach(['domestic'=>['01','At home','home-sample.jpg'], 'commercial'=>['02','At work','workplace-sample.jpg'], 'care_home'=>['03','In care','care-sample.jpg']] as $category=>$meta)
        @if($servicePage = $pages->get($category))
        <article class="service-card"><a class="card-image" href="{{ route('services.service', $servicePage->slug) }}" tabindex="-1" aria-hidden="true"><img src="{{ asset('services/assets/'.$meta[2]) }}" alt="" width="800" height="600" loading="lazy"><span>{{ $meta[1] }}</span></a><div class="card-body"><span class="card-number">{{ $meta[0] }} / CLEANING SERVICES</span><h3><a href="{{ route('services.service', $servicePage->slug) }}">{{ $servicePage->title }}</a></h3><p>{{ $servicePage->summary }}</p><a class="text-link" href="{{ route('services.service', $servicePage->slug) }}">Explore {{ str_replace('_', ' ', $category) }} cleaning <span aria-hidden="true">↗</span></a></div></article>
        @endif
        @endforeach
    </div>
</section>
<section class="approach-section"><div class="container split-section"><div class="approach-mark" aria-hidden="true"><span>CARE<br>IN EVERY<br><em>DETAIL.</em></span><div class="orbit orbit-one"></div><div class="orbit orbit-two"></div></div><div><p class="eyebrow">MORE THAN A CLEAN SPACE</p><h2>Good cleaning starts<br>with understanding you.</h2><p class="lead">No two homes or businesses work quite the same way. We start with a conversation about your priorities, your space and your schedule.</p><ul class="check-list"><li>A scope you can understand</li><li>A schedule you can discuss</li><li>A clear quotation before you commit</li></ul><a class="text-link" href="{{ config('shi3ld.about_url') }}">Get to know our approach ↗</a></div></div></section>
<section class="section container">
    <div class="section-heading"><div><p class="eyebrow">SIMPLE FROM THE START</p><h2>Tell us. Talk it through.<br>Take the next step.</h2></div><a class="text-link" href="{{ route('services.how-it-works') }}">How it works ↗</a></div>
    <div class="steps-grid">@foreach([['01','Tell us about your space','Choose your service, add your postcode and share what you need.'],['02','Discuss the details','We’ll talk about the scope, frequency, access and available dates.'],['03','Agree your cleaning plan','Review your quotation and agree the work before a booking is confirmed.']] as $step)<article class="step"><span>{{ $step[0] }}</span><h3>{{ $step[1] }}</h3><p>{{ $step[2] }}</p></article>@endforeach</div>
</section>
<section class="faq-section"><div class="container split-section"><div><p class="eyebrow">A FEW HELPFUL ANSWERS</p><h2>A clearer picture,<br>before you enquire.</h2><p>Have something else in mind?<br>Tell us what you’d like to discuss.</p><a class="text-link" href="{{ route('services.faqs') }}">All common questions ↗</a></div><div class="faq-list">@foreach(array_slice(config('services_site.faqs'),0,4) as $faq)<details><summary>{{ $faq['question'] }}</summary><p>{{ $faq['answer'] }}</p></details>@endforeach</div></div></section>
@endsection
