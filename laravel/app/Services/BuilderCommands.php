<?php

namespace App\Services;

use App\Http\Requests\VisualContentRequest;
use App\Services\Builder\Engine;
use App\Services\Builder\Schema;

class BuilderCommands
{
    public const INTENTS = ['assets', 'create_asset', 'load_asset', 'unpublish_asset', 'export_design', 'import_design', 'submit_review', 'schedule', 'cancel_schedule', 'review_queue'];

    public function __construct(private BuilderLibrary $library, private ContentDocuments $documents, private Schema $schema,
        private Engine $engine, private BuilderWorkflow $workflow, private BuilderPackage $packages) {}

    public function configuration(): array
    {
        return ['canDesign' => (bool) auth()->user()?->builder_designer, 'advanced' => ['properties' => Schema::PROPERTIES, 'fields' => Schema::FIELDS,
            'kinds' => ['section', 'template', 'card', 'popup', 'form'], 'targets' => Schema::TARGETS, 'libraryUrl' => route('services.staff.builder.library')]];
    }

    public function assert(array $payload, array $baseline, bool $designer): void
    {
        $this->engine->protect($payload['html'], $baseline['html'], $designer);
        if (! $designer && $this->schema->settings($payload['builder'] ?? []) !== $this->schema->settings($baseline['builder'] ?? [])) {
            throw new \InvalidArgumentException('Design settings require design permission.');
        }
    }

    public function run(string $key, VisualContentRequest $request, array $current): array
    {
        $intent = $request->validated('intent');
        $actor = $request->user();
        if ($intent === 'assets') {
            return ['assets' => $this->library->all(), 'workflow' => $this->workflow->get($key)];
        }
        if ($intent === 'load_asset') {
            return ['payload' => $this->library->snapshot((int) $request->input('asset_id'))];
        }
        if ($intent === 'submit_review' || $intent === 'schedule') {
            if ($intent === 'schedule' && ! $actor->builder_publisher) {
                abort(403);
            }

            return ['workflow' => $this->workflow->request($key, (int) $request->input('version'), $actor, $intent === 'schedule' ? (string) $request->input('scheduled_at') : null)];
        }
        if ($intent === 'cancel_schedule') {
            $this->workflow->cancel($key);

            return ['workflow' => $this->workflow->get($key)];
        }
        if ($intent === 'review_queue') {
            abort_unless($actor->builder_publisher, 403);

            return ['requests' => $this->workflow->pending()];
        }
        if ($intent === 'unpublish_asset') {
            abort_unless($actor->builder_publisher, 403);
            $this->library->unpublish((int) $request->input('asset_id'));

            return ['ok' => true];
        }
        abort_unless($actor->builder_designer, 403);
        if ($intent === 'create_asset') {
            $payload = $request->input('asset_payload') ? json_decode($request->input('asset_payload'), true, 32, JSON_THROW_ON_ERROR) : null;
            if ($payload) {
                $payload = $this->documents->validate($payload, $current['payload']['html']);
            }
            $id = $this->library->create((string) $request->input('kind', 'section'), (string) $request->input('title', 'New design'), $payload);

            return ['id' => $id, 'key' => $this->library->row($id)->asset_key, 'url' => route('services.staff.visual', 'asset-'.$id)];
        }
        if ($intent === 'export_design') {
            return ['package' => $this->packages->export($current['payload'])];
        }
        if ($intent === 'import_design') {
            return ['payload' => $this->packages->import(file_get_contents($request->file('package')->getRealPath()), $current['payload']['html'])];
        }
        throw new \InvalidArgumentException('Choose a valid builder action.');
    }
}
