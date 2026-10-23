<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class BuilderLibraryController extends Controller
{
    public function index(): View
    {
        return view('services.staff.builder-library', ['config' => ['title' => 'Services design library', 'csrf' => csrf_token(), 'csrfName' => '_token',
            'commandUrl' => route('services.staff.visual.command', 'home'), 'backUrl' => route('services.staff.content'), 'editorUrl' => route('services.staff.visual', 'home'),
            'canDesign' => (bool) auth()->user()->builder_designer, 'canPublish' => (bool) auth()->user()->builder_publisher,
            'kinds' => ['section', 'template', 'card', 'popup', 'form']]]);
    }
}
