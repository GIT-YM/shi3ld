<div class="service-grid">
        @foreach(['domestic'=>['01','At home','home-sample.jpg'], 'commercial'=>['02','At work','workplace-sample.jpg'], 'care_home'=>['03','In care','care-sample.jpg']] as $category=>$meta)
        @if($servicePage = $pages->get($category))
        <article class="service-card"><a class="card-image" href="{{ route('services.service', $servicePage->slug) }}" tabindex="-1" aria-hidden="true"><img src="{{ asset('services/assets/'.$meta[2]) }}" alt="" width="800" height="600" loading="lazy"><span>{{ $meta[1] }}</span></a><div class="card-body"><span class="card-number">{{ $meta[0] }} / CLEANING SERVICES</span><h3><a href="{{ route('services.service', $servicePage->slug) }}">{{ $servicePage->title }}</a></h3><p>{{ $servicePage->summary }}</p><a class="text-link" href="{{ route('services.service', $servicePage->slug) }}">Explore {{ str_replace('_', ' ', $category) }} cleaning <span aria-hidden="true">↗</span></a></div></article>
        @endif
        @endforeach
    </div>
