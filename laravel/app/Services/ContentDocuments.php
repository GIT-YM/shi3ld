<?php

namespace App\Services;

use App\Models\ServicePage;
use App\Services\Builder\Schema;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ContentDocuments
{
    public function __construct(private ContentMarkup $markup, private BuilderLibrary $library, private BuilderRuntime $builder, private Schema $schema) {}

    public function keys(): array
    {
        $result = [['key' => 'home', 'title' => 'Services homepage']];
        foreach (ServicePage::orderBy('title')->get() as $page) {
            $result[] = ['key' => 'page-'.$page->id, 'title' => $page->title];
        }
        foreach (['how-it-works', 'areas', 'faqs', 'contact', 'privacy', 'cookies', 'terms', 'gallery'] as $slug) {
            $result[] = ['key' => 'info-'.$slug, 'title' => ucfirst(str_replace('-', ' ', $slug))];
        }

        foreach (DB::connection('mysql')->table('site_builder_assets')->orderBy('title')->get(['id', 'title']) as $asset) {
            $result[] = ['key' => 'asset-'.$asset->id, 'title' => 'Design: '.$asset->title];
        }

        return $result;
    }

    public function defaults(string $key): array
    {
        abort_unless(in_array($key, array_column($this->keys(), 'key'), true), 404);
        if (str_starts_with($key, 'asset-')) {
            return $this->library->snapshot((int) substr($key, 6));
        }
        if ($key === 'home') {
            $html = view('services.content.home', ['pages' => $this->primaryPages()])->render();
            $title = 'Cleaning for the spaces that matter';
            $summary = 'Explore domestic, commercial and care-home cleaning with Shi3ld Alliance. Discuss your requirements and request a tailored quotation.';
        } elseif (str_starts_with($key, 'page-')) {
            $page = ServicePage::findOrFail((int) substr($key, 5));
            $html = view('services.content.service', compact('page'))->render();
            $title = $page->title;
            $summary = $page->summary;
        } else {
            $slug = substr($key, 5);
            $html = view('services.content.information', ['page' => $slug])->render();
            $titles = ['how-it-works' => 'How it works', 'areas' => 'Service areas', 'faqs' => 'Common questions', 'contact' => 'Let’s talk about your space', 'privacy' => 'Privacy information', 'cookies' => 'Cookie information', 'terms' => 'Service terms', 'gallery' => 'Spaces, thoughtfully considered'];
            $title = $titles[$slug];
            $summary = $title.' — helpful information about Shi3ld Alliance domestic, commercial and care-home cleaning services.';
        }

        return ['html' => $html, 'title' => $title, 'meta_title' => $title, 'meta_description' => $summary, 'content_heading' => ''];
    }

    public function live(string $key): array
    {
        $defaults = $this->defaults($key);
        $row = DB::connection('mysql')->table('site_content_documents')->where('document_key', $key)->first();

        return $row?->live_payload ? json_decode($row->live_payload, true, 32, JSON_THROW_ON_ERROR) : $defaults;
    }

    public function get(string $key): array
    {
        $live = $this->live($key);
        $row = DB::connection('mysql')->table('site_content_documents')->where('document_key', $key)->first();
        $draft = $row?->draft_payload ? json_decode($row->draft_payload, true, 32, JSON_THROW_ON_ERROR) : $live;
        $revisions = DB::connection('mysql')->table('site_content_revisions')->where('document_key', $key)->latest('id')->limit(100)->get()
            ->map(fn ($revision) => ['revision_id' => $revision->id, 'action' => $revision->action, 'created_at' => $revision->created_at, 'created_by' => $revision->created_by])->all();

        return ['payload' => $draft, 'version' => (int) ($row->version ?? 0), 'has_draft' => $this->hash($draft) !== $this->hash($live),
            'live_hash' => $this->hash($live), 'revisions' => $revisions, 'updated_at' => $row->updated_at ?? null];
    }

    public function save(string $key, array $payload, int $version, string $author, string $liveHash, string $action = 'draft'): array
    {
        DB::connection('mysql')->transaction(function () use ($key, $payload, $version, $author, $liveHash, $action): void {
            $live = $this->live($key);
            $table = DB::connection('mysql')->table('site_content_documents');
            $row = (clone $table)->where('document_key', $key)->lockForUpdate()->first();
            if ((int) ($row->version ?? 0) !== $version || (! $row && ! hash_equals($this->hash($live), $liveHash))) {
                throw new \InvalidArgumentException('This draft or its published page changed. Reload the editor before saving.');
            }
            $payload = $this->validate($payload, $live['html']);
            if (! $row) {
                $this->record($key, $live, 'baseline', $author);
            }
            $values = ['draft_payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'base_hash' => $action === 'restore' || ! $row ? $this->hash($live) : $row->base_hash,
                'version' => $version + 1, 'updated_by' => $author, 'updated_at' => now()];
            if ($row) {
                $table->where('document_key', $key)->update($values);
            } else {
                $table->insert($values + ['document_key' => $key, 'created_at' => now()]);
            }
            $this->record($key, $payload, $action, $author);
        });

        return $this->get($key);
    }

    public function publish(string $key, int $version, string $author): array
    {
        DB::connection('mysql')->transaction(function () use ($key, $version, $author): void {
            $table = DB::connection('mysql')->table('site_content_documents');
            $row = (clone $table)->where('document_key', $key)->lockForUpdate()->first();
            if (! $row || (int) $row->version !== $version) {
                throw new \InvalidArgumentException('Save the current draft before publishing.');
            }
            if (str_starts_with($key, 'page-')) {
                ServicePage::whereKey((int) substr($key, 5))->lockForUpdate()->firstOrFail();
            }
            $live = $this->live($key);
            if (! hash_equals($row->base_hash, $this->hash($live))) {
                throw new \InvalidArgumentException('The published page changed outside this draft. Reload and restore a revision before publishing.');
            }
            $payload = $this->validate(json_decode($row->draft_payload, true, 32, JSON_THROW_ON_ERROR), $live['html']);
            $this->record($key, $live, 'before_publish', $author);
            $table->where('document_key', $key)->update(['live_payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'draft_payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                'base_hash' => $this->hash($payload), 'version' => $version + 1, 'updated_by' => $author, 'updated_at' => now()]);
            $this->record($key, $payload, 'published', $author);
            if (str_starts_with($key, 'asset-')) {
                $this->library->publish((int) substr($key, 6), $payload);
            }
            DB::connection('mysql')->table('site_builder_workflows')->where('document_key', $key)->whereIn('state', ['review', 'scheduled'])->update(['state' => 'published', 'note' => 'Published by '.$author, 'updated_at' => now()]);
            if (str_starts_with($key, 'page-')) {
                ServicePage::findOrFail((int) substr($key, 5))->update(['published' => true]);
            }
        });

        return $this->get($key);
    }

    public function revision(string $key, int $revision): array
    {
        $row = DB::connection('mysql')->table('site_content_revisions')->where('document_key', $key)->where('id', $revision)->first();
        abort_unless($row, 404);

        return json_decode($row->payload, true, 32, JSON_THROW_ON_ERROR);
    }

    public function validate(array $payload, string $baseline, bool $render = false): array
    {
        $result = [];
        foreach (['html' => 524288, 'title' => 160, 'meta_title' => 255, 'meta_description' => 1000, 'content_heading' => 255] as $field => $limit) {
            $value = $payload[$field] ?? '';
            if (! is_string($value) || mb_strlen($value) > $limit || ($field === 'title' && trim($value) === '')) {
                throw new \InvalidArgumentException('Enter a valid '.str_replace('_', ' ', $field).'.');
            }
            $result[$field] = $field === 'html' ? $this->markup->validate($value, $baseline, $render) : $value;
        }

        if (array_key_exists('builder', $payload) || str_contains($payload['html'], 'data-builder-')) {
            $result['builder'] = $this->schema->settings((array) ($payload['builder'] ?? []));
            $decoded = html_entity_decode($payload['html'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $result['builder']['has_audience_rules'] = (bool) preg_match('/"audience"\s*:\s*"(?:guest|customer|staff)"/', $decoded);
            $result['builder']['has_date_rules'] = (bool) preg_match('/"(?:start|end)"\s*:\s*"2\d{3}-/', $decoded);
            $this->schema->templateContent($result['builder'], $payload['html']);
        }

        return $result;
    }

    public function render(string $key, string $html, ?array $payload = null, bool $editing = false): string
    {
        $widgets = ['service-cards' => view('services.content.cards', ['pages' => $this->primaryPages()])->render(),
            'related-services' => '', 'area-enquiry' => view('services.content.area-enquiry')->render()];
        if (str_starts_with($key, 'page-')) {
            $page = ServicePage::findOrFail((int) substr($key, 5));
            $related = ServicePage::where('published', true)->where('category', $page->category)->where('id', '!=', $page->id)->get();
            $widgets['related-services'] = view('services.content.related', compact('related'))->render();
        }

        $html = preg_replace_callback('~<div\b([^>]*\bdata-editor-widget="(service-cards|related-services|area-enquiry)"[^>]*)>\s*</div>~',
            fn ($match) => '<div'.$match[1].'>'.$widgets[$match[2]].'</div>', $html);
        $payload ??= $this->live($key);
        $context = $this->builder->context($key, ['title' => $payload['title'], 'excerpt' => $payload['meta_description'], 'url' => $this->url($key), 'service.title' => $payload['title'], 'service.summary' => $payload['meta_description'], 'service.url' => $this->url($key)]);
        $context['editing'] = $editing;
        $context['path'] = (string) parse_url($this->url($key), PHP_URL_PATH);
        if (str_starts_with($key, 'asset-') && (($payload['builder']['asset']['kind'] ?? '') === 'form')) {
            $form = $payload['builder']['asset']['form'];
            $html .= '<div data-builder-form="'.$this->library->row((int) substr($key, 6))->asset_key.'" data-builder-form-preview="'.e(json_encode($form, JSON_THROW_ON_ERROR)).'"></div>';
        }
        $html = $this->builder->wrap($html, $payload['builder'] ?? [], $context);

        return str_starts_with($key, 'asset-') || $editing ? $html : $this->builder->template('services', $html, $context);
    }

    public function url(string $key): string
    {
        if (str_starts_with($key, 'asset-')) {
            return route('services.home');
        }
        if ($key === 'home') {
            return route('services.home');
        }
        if (str_starts_with($key, 'page-')) {
            return route('services.service', ServicePage::findOrFail((int) substr($key, 5))->slug);
        }

        return route('services.'.substr($key, 5));
    }

    public function isManaged(string $key): bool
    {
        return DB::connection('mysql')->table('site_content_documents')->where('document_key', $key)->whereNotNull('live_payload')->exists();
    }

    public function updateStructured(ServicePage $page, array $data): bool
    {
        return DB::connection('mysql')->transaction(function () use ($page, $data): bool {
            $row = DB::connection('mysql')->table('site_content_documents')->where('document_key', 'page-'.$page->id)->lockForUpdate()->first();
            if ($row?->live_payload) {
                return false;
            }
            $page->update($data);

            return true;
        });
    }

    private function primaryPages(): Collection
    {
        return ServicePage::where('published', true)->whereIn('slug', ['domestic-cleaning', 'commercial-cleaning', 'care-home-cleaning'])->get()->keyBy('category');
    }

    private function hash(array $payload): string
    {
        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    private function record(string $key, array $payload, string $action, string $author): void
    {
        DB::connection('mysql')->table('site_content_revisions')->insert(['document_key' => $key, 'payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'action' => $action, 'created_by' => $author, 'created_at' => now()]);
    }
}
