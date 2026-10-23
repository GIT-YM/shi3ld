<?php

namespace App\Services;

use App\Services\Builder\Schema;
use Illuminate\Support\Facades\DB;

class BuilderLibrary
{
    public function __construct(private Schema $schema) {}

    public function all(): array
    {
        $items = [];
        foreach (DB::connection('mysql')->table('site_builder_assets')->orderByDesc('id')->get() as $row) {
            $draft = DB::connection('mysql')->table('site_content_documents')->where('document_key', 'asset-'.$row->id)->value('draft_payload');
            $items[] = ['id' => $row->id, 'key' => $row->asset_key, 'kind' => $row->kind, 'title' => $row->title,
                'payload' => json_decode($draft ?: $row->live_payload ?: $row->initial_payload, true, 32, JSON_THROW_ON_ERROR),
                'published' => $row->live_payload !== null, 'shared' => false, 'url' => route('services.staff.visual', 'asset-'.$row->id)];
        }
        foreach ($this->shared() as $key => $payload) {
            $payload['html'] = $this->schema->portableHtml($payload['html']);
            $items[] = ['id' => $key, 'key' => $key, 'kind' => $payload['builder']['asset']['kind'] ?? 'section', 'title' => $payload['title'],
                'payload' => $payload, 'published' => true, 'shared' => true,
                'url' => rtrim(config('shi3ld.site_url'), '/').'/admin/shi3ld_content/editor/index/?type=asset&id='.($payload['_id'] ?? 0)];
        }

        return $items;
    }

    public function row(int $id): object
    {
        return DB::connection('mysql')->table('site_builder_assets')->where('id', $id)->first() ?? abort(404);
    }

    public function snapshot(int $id): array
    {
        $row = $this->row($id);

        return json_decode($row->live_payload ?: $row->initial_payload, true, 32, JSON_THROW_ON_ERROR);
    }

    public function shared(): array
    {
        $path = dirname(config('shi3ld.shared_design_file')).'/builder.json';
        if (! is_file($path)) {
            return [];
        }
        try {
            $data = json_decode(file_get_contents($path), true, 48, JSON_THROW_ON_ERROR);

            return is_array($data['assets'] ?? null) ? $data['assets'] : [];
        } catch (\JsonException $exception) {
            report($exception);

            return [];
        }
    }

    public function regionSettings(string $region): array
    {
        $path = dirname(config('shi3ld.shared_design_file')).'/builder.json';
        if (! is_file($path)) {
            return [];
        }
        try {
            return json_decode(file_get_contents($path), true, 48, JSON_THROW_ON_ERROR)['regions'][$region] ?? [];
        } catch (\JsonException $exception) {
            report($exception);

            return [];
        }
    }

    public function published(): array
    {
        $items = $this->shared();
        foreach (DB::connection('mysql')->table('site_builder_assets')->whereNotNull('live_payload')->get() as $row) {
            $items[$row->asset_key] = json_decode($row->live_payload, true, 32, JSON_THROW_ON_ERROR) + ['asset_key' => $row->asset_key];
        }

        return $items;
    }

    public function get(string $key): ?array
    {
        return preg_match('/^[a-f0-9]{32}$/D', $key) ? ($this->published()[$key] ?? null) : null;
    }

    public function create(string $kind, string $title, ?array $payload = null): int
    {
        if (! in_array($kind, ['section', 'template', 'card', 'popup', 'form'], true) || trim($title) === '' || mb_strlen($title) > 160) {
            throw new \InvalidArgumentException('Choose a service design type and title. Shared brand styles and product arrangements are managed in Magento.');
        }
        $html = match ($kind) {
            'template' => '<section class="section container"><div data-builder-slot="content"></div></section>',
            'card' => '<article class="builder-card"><h3 data-builder-field="title">Service title</h3><p data-builder-field="excerpt">Service introduction</p><a data-builder-field="url" href="/services">Explore service</a></article>',
            'popup' => '<section class="section"><h2>Your announcement</h2><p>Add your message.</p><a class="button" href="/services/quote">Request a quote</a></section>',
            'form' => '<section class="section container"><h2>Your enquiry form</h2><p>Configure additional fields in the Design panel.</p></section>',
            default => '<section class="section container"><h2>Your reusable section</h2><p>Add your content.</p></section>',
        };
        $payload ??= ['html' => $html, 'title' => $title, 'meta_title' => '', 'meta_description' => '', 'content_heading' => '',
            'builder' => $this->schema->settings(['asset' => ['kind' => $kind, 'target' => $kind === 'template' ? 'none' : 'services']])];
        $payload['builder'] = $this->schema->settings($payload['builder'] ?? []);
        $payload['builder']['asset'] = array_replace($this->schema->settings(['asset' => ['kind' => $kind, 'target' => $kind === 'template' ? 'none' : 'services']])['asset'], $payload['builder']['asset'] ?? [], ['kind' => $kind]);
        if ($kind === 'template' && ! str_contains($payload['html'], 'data-builder-slot')) {
            $payload['builder']['asset']['target'] = 'none';
        }

        return DB::connection('mysql')->table('site_builder_assets')->insertGetId(['asset_key' => bin2hex(random_bytes(16)), 'kind' => $kind, 'title' => $title,
            'initial_payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);
    }

    public function publish(int $id, array $payload): void
    {
        $row = $this->row($id);
        $visit = function (string $key, array $item, array $stack = []) use (&$visit, $row, $payload): void {
            if (count($stack) > 8 || in_array($key, $stack, true)) {
                throw new \InvalidArgumentException('Linked designs contain a circular reference.');
            }
            $stack[] = $key;
            preg_match_all('/data-builder-ref="([a-f0-9]{32})"/', $item['html'], $matches);
            foreach ($matches[1] as $ref) {
                $child = $ref === $row->asset_key ? $payload : $this->get($ref);
                if ($child) {
                    $visit($ref, $child, $stack);
                }
            }
        };
        $visit($row->asset_key, $payload);
        if (($payload['builder']['asset']['kind'] ?? '') !== $row->kind) {
            throw new \InvalidArgumentException('A saved design cannot change its type.');
        }
        DB::connection('mysql')->table('site_builder_assets')->where('id', $id)->update(['live_payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'title' => $payload['title'], 'updated_at' => now()]);
    }

    public function unpublish(int $id): void
    {
        $row = $this->row($id);
        DB::connection('mysql')->table('site_builder_assets')->where('id', $id)->update(['initial_payload' => $row->live_payload ?: $row->initial_payload, 'live_payload' => null, 'updated_at' => now()]);
    }
}
