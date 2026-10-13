<?php

namespace App\Http\Controllers;

use App\Models\ServicePage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ServicesPageController extends Controller
{
    public function home(): View
    {
        return view('services.home', [
            'pages' => ServicePage::where('published', true)->whereIn('slug', ['domestic-cleaning', 'commercial-cleaning', 'care-home-cleaning'])->get()->keyBy('category'),
        ]);
    }

    public function service(string $slug): View
    {
        $page = ServicePage::where('slug', $slug)->where('published', true)->firstOrFail();

        return view('services.service', [
            'page' => $page,
            'related' => ServicePage::where('published', true)->where('category', $page->category)->where('id', '!=', $page->id)->get(),
        ]);
    }

    public function sitemap(): Response
    {
        return response()->view('services.sitemap', [
            'pages' => ServicePage::where('published', true)->get(),
        ], headers: ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function information(Request $request): View
    {
        $page = $request->route('page');
        abort_unless(in_array($page, ['how-it-works', 'areas', 'faqs', 'contact', 'privacy', 'cookies', 'terms', 'gallery'], true), 404);

        return view('services.information', ['page' => $page]);
    }
}
