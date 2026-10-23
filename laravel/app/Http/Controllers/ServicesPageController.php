<?php

namespace App\Http\Controllers;

use App\Models\ServicePage;
use App\Services\ContentDocuments;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ServicesPageController extends Controller
{
    public function __construct(private ContentDocuments $documents) {}

    public function home(): View
    {
        return $this->document('home');
    }

    public function service(string $slug): View
    {
        $page = ServicePage::where('slug', $slug)->where('published', true)->firstOrFail();

        return $this->document('page-'.$page->id);
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

        return $this->document('info-'.$page);
    }

    private function document(string $key): View
    {
        $document = $this->documents->live($key);

        return view('services.visual-page', ['document' => $document,
            'html' => $this->documents->render($key, $document['html']), 'contentEditorKey' => $key]);
    }
}
