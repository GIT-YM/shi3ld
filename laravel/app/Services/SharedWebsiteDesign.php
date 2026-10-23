<?php

namespace App\Services;

class SharedWebsiteDesign
{
    public function __construct(private BuilderRuntime $builder) {}

    public function get(): array
    {
        $path = (string) config('shi3ld.shared_design_file');
        if (! is_file($path)) {
            return [];
        }
        try {
            $data = json_decode(file_get_contents($path), true, 8, JSON_THROW_ON_ERROR);
            $result = [];
            foreach (['header', 'footer'] as $region) {
                if (! is_string($data[$region] ?? null)) {
                    return [];
                }
                $html = preg_replace_callback('~\{\{(store|media)\s+url\s*=\s*[\x22\x27]?([^\x22\x27}]*?)[\x22\x27]?\s*\}\}~', function ($match): string {
                    $path = trim(html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));

                    return e(rtrim(config('shi3ld.site_url'), '/').($match[1] === 'media' ? '/media/' : '/').ltrim($path, '/'));
                }, $data[$region]);
                if (str_contains($html, '{{')) {
                    return [];
                }
                $result[$region] = $this->builder->region($region, $html);
            }

            return $result;
        } catch (\JsonException $exception) {
            return [];
        }
    }
}
