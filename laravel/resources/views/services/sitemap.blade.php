<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url><loc>{{ route('services.home') }}</loc></url>
    @foreach(['how-it-works', 'areas', 'faqs', 'contact', 'gallery'] as $name)
    <url><loc>{{ route('services.'.$name) }}</loc></url>
    @endforeach
    @foreach($pages as $page)
    <url><loc>{{ route('services.service', $page->slug) }}</loc><lastmod>{{ $page->updated_at->toAtomString() }}</lastmod></url>
    @endforeach
</urlset>
