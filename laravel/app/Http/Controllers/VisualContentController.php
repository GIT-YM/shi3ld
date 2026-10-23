<?php

namespace App\Http\Controllers;

use App\Http\Requests\VisualContentRequest;
use App\Services\BuilderCommands;
use App\Services\BuilderLibrary;
use App\Services\ContentDocuments;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\URL;

class VisualContentController extends Controller
{
    public function __construct(private ContentDocuments $documents, private BuilderCommands $advanced) {}

    public function index(string $key): View
    {
        $state = $this->documents->get($key);
        $navigation = array_map(fn ($item) => $item + ['url' => route('services.staff.visual', $item['key'])], $this->documents->keys());

        $extra = $this->advanced->configuration();
        if (str_starts_with($key, 'asset-')) {
            $extra['assetKind'] = $state['payload']['builder']['asset']['kind'] ?? 'section';
            $extra['assetKey'] = app(BuilderLibrary::class)->row((int) substr($key, 6))->asset_key;
        }

        return view('services.staff.visual', ['config' => array_merge([
            'framework' => 'Laravel services', 'actor' => auth()->id(), 'key' => $key, 'title' => $state['payload']['title'],
            'commandUrl' => route('services.staff.visual.command', $key), 'csrf' => csrf_token(), 'csrfName' => '_token',
            'state' => $state, 'liveUrl' => route('services.staff.visual.frame', $key), 'selector' => '[data-shi3ld-region="page"]',
            'backUrl' => route('services.staff.content'), 'navigation' => $navigation, 'canPublish' => (bool) auth()->user()->builder_publisher, 'pageBuilder' => false,
        ], $extra)]);
    }

    public function locate(Request $request): JsonResponse
    {
        $key = $request->query('key');
        $allowed = $request->user()?->services_staff && is_string($key) && in_array($key, array_column($this->documents->keys(), 'key'), true);

        return response()->json(['links' => $allowed ? [['title' => 'Edit this page', 'url' => route('services.staff.visual', $key)]] : []])->header('Cache-Control', 'private, no-store');
    }

    public function frame(string $key): View
    {
        $document = $this->documents->live($key);

        return view('services.visual-page', ['document' => $document, 'html' => $this->documents->render($key, $document['html'], $document), 'contentEditorKey' => $key]);
    }

    public function preview(Request $request, string $key): Response
    {
        abort_unless($request->hasValidSignature(), 403);
        $preview = $request->session()->get('content_previews.'.$request->query('preview_id'));
        abort_unless($preview && $preview['key'] === $key && $preview['expires_at'] >= now()->timestamp, 404);
        $document = $preview['payload'];
        if (str_starts_with($key, 'asset-')) {
            $request->attributes->set('builder_asset_preview', ['key' => app(BuilderLibrary::class)->row((int) substr($key, 6))->asset_key, 'payload' => $document]);
        }

        return response()->view('services.visual-page', ['document' => $document, 'html' => $this->documents->render($key, $document['html'], $document), 'contentEditorKey' => $key, 'isContentPreview' => true])
            ->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow')->header('Referrer-Policy', 'no-referrer');
    }

    public function command(VisualContentRequest $request, string $key): JsonResponse
    {
        try {
            $data = $request->validated();
            $intent = $data['intent'];
            $version = (int) $data['version'];
            $author = mb_substr((string) $request->user()->email, 0, 120).' #'.$request->user()->id;
            $current = $this->documents->get($key);
            if (in_array($intent, BuilderCommands::INTENTS, true)) {
                return response()->json($this->advanced->run($key, $request, $current))->header('Cache-Control', 'private, no-store');
            }
            if ($intent === 'published') {
                $published = $this->documents->live($key);

                return response()->json(['payload' => $published, 'html' => $this->documents->render($key, $published['html'], $published, true)]);
            }
            if ($intent === 'media') {
                return response()->json(['media' => $this->media()]);
            }
            if ($intent === 'upload') {
                $file = $request->file('image');
                $directory = public_path('services/assets/content');
                File::ensureDirectoryExists($directory);
                $name = hash_file('sha256', $file->getRealPath()).'.'.$file->extension();
                if (! is_file($directory.'/'.$name)) {
                    $file->move($directory, $name);
                }

                return response()->json(['image' => ['url' => '/services/assets/content/'.$name, 'source' => '/services/assets/content/'.$name, 'name' => $name]]);
            }
            if ($intent === 'revision') {
                return response()->json(['payload' => $this->documents->revision($key, (int) $data['revision_id'])]);
            }
            if (in_array($intent, ['render', 'save'], true)) {
                $payload = json_decode($data['payload'], true, 32, JSON_THROW_ON_ERROR);
                if (! is_array($payload)) {
                    throw new \InvalidArgumentException('The page content could not be read.');
                }
                $this->advanced->assert($payload, $current['payload'], (bool) $request->user()->builder_designer);
                if ($intent === 'render') {
                    $payload = $this->documents->validate($payload, $current['payload']['html'], true);

                    return response()->json(['html' => $this->documents->render($key, $payload['html'], $payload, true)]);
                }
                $state = $this->documents->save($key, $payload, $version, $author, $data['live_hash']);
            } elseif ($intent === 'publish') {
                abort_unless($request->user()->builder_publisher, 403);
                $state = $this->documents->publish($key, $version, $author);
            } elseif ($intent === 'restore') {
                $restored = $this->documents->revision($key, (int) $data['revision_id']);
                $this->advanced->assert($restored, $current['payload'], (bool) $request->user()->builder_designer);
                $state = $this->documents->save($key, $this->documents->revision($key, (int) $data['revision_id']), $version, $author, $current['live_hash'], 'restore');
            } elseif ($intent === 'preview') {
                $id = bin2hex(random_bytes(16));
                $previews = array_filter($request->session()->get('content_previews', []), fn ($preview) => $preview['expires_at'] >= now()->timestamp);
                $previews[$id] = ['key' => $key, 'payload' => $current['payload'], 'expires_at' => now()->addMinutes(15)->timestamp];
                $request->session()->put('content_previews', array_slice($previews, -10, null, true));

                return response()->json(['url' => URL::temporarySignedRoute('services.staff.visual.preview', now()->addMinutes(15), ['key' => $key, 'preview_id' => $id])]);
            } else {
                throw new \InvalidArgumentException('Choose a valid editor action.');
            }

            return response()->json(['state' => $state]);
        } catch (\InvalidArgumentException|\JsonException $exception) {
            return response()->json(['error' => $exception->getMessage()], 409);
        }
    }

    private function media(): array
    {
        $result = [];
        foreach ([public_path('services/assets'), public_path('services/assets/content')] as $directory) {
            if (! is_dir($directory)) {
                continue;
            }
            foreach (File::files($directory) as $file) {
                if (preg_match('/\.(?:jpe?g|png|gif|webp)$/i', $file->getFilename())) {
                    $url = '/'.str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen(public_path()) + 1));
                    $result[] = ['url' => $url, 'source' => $url, 'name' => $file->getFilename()];
                }
                if (count($result) >= 250) {
                    return $result;
                }
            }
        }

        return $result;
    }
}
