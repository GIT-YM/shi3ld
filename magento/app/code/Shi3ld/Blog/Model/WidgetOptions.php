<?php

declare(strict_types=1);

namespace Shi3ld\Blog\Model;

use Shi3ld\Content\Api\EditorDirectiveInterface;
use Shi3ld\Content\Model\Builder\Library;

class WidgetOptions implements EditorDirectiveInterface
{
    public const WIDGET = 'Shi3ld\\Blog\\Block\\Widget\\Articles';

    public function __construct(private Repository $posts, private Library $designs) {}

    public function fields(): array
    {
        $select = fn ($name, $label, $default, $options) => compact('name', 'label', 'default', 'options') + ['type' => 'select'];
        $number = fn ($name, $label, $default, $min, $max) => compact('name', 'label', 'default', 'min', 'max') + ['type' => 'number'];
        $text = fn ($name, $label, $default, $max) => compact('name', 'label', 'default', 'max') + ['type' => 'text'];
        $boolean = fn ($name, $label, $default = 1) => compact('name', 'label', 'default') + ['type' => 'checkbox'];

        return [
            $text('heading', 'Section heading', 'Advice & resources', 150),
            $select('presentation', 'Card presentation', 'default', ['default' => 'Standard article cards', 'homepage' => 'Alliance homepage editorial cards']),
            ['name' => 'card_template', 'label' => 'Custom card design', 'type' => 'select', 'default' => '', 'options' => ['' => 'Theme card layout'] + array_column(array_map(fn ($row) => ['value' => $row['asset_key'], 'label' => $row['title']], array_filter($this->designs->all(true), fn ($row) => $row['kind'] === 'card')), 'label', 'value')],
            ['name' => 'categories', 'label' => 'Categories (any selected category; none means all)', 'type' => 'multiselect', 'default' => '',
                'options' => array_map(fn ($row) => ['value' => (string) $row['category_id'], 'label' => $row['title'].($row['is_active'] ? '' : ' (hidden)')], $this->posts->categories(false))],
            $number('limit', 'Number of articles', 6, 1, 24),
            $number('columns', 'Desktop columns', 3, 1, 4),
            $number('tablet_columns', 'Tablet columns', 2, 1, 3),
            $number('mobile_columns', 'Mobile columns', 1, 1, 2),
            $select('layout', 'Image position', 'image-top', ['image-top' => 'Image above text', 'image-bottom' => 'Image below text', 'image-left' => 'Image beside text', 'text-only' => 'Text only']),
            $select('design', 'Display style', 'cards', ['cards' => 'Cards', 'list' => 'List', 'featured-grid' => 'First article featured + grid']),
            $select('sort', 'Article order', 'newest', ['newest' => 'Newest first', 'oldest' => 'Oldest first', 'featured' => 'Featured articles only', 'manual' => 'Manually selected articles']),
            $text('articles', 'Article IDs in display order (manual selection)', '', 600),
            $number('excerpt_length', 'Excerpt length in characters', 150, 0, 500),
            $boolean('show_category', 'Show categories'), $boolean('show_date', 'Show publication date'),
            $boolean('show_author', 'Show author', 0), $boolean('show_excerpt', 'Show excerpt'),
            $boolean('show_read_more', 'Show Read more'), $text('read_more_label', 'Read more label', 'Read more', 60),
            $boolean('view_all', 'Show View all articles'),
            $select('image_ratio', 'Image shape', 'landscape', ['landscape' => 'Landscape', 'square' => 'Square', 'portrait' => 'Portrait']),
            $select('spacing', 'Spacing', 'normal', ['compact' => 'Compact', 'normal' => 'Normal', 'relaxed' => 'Relaxed']),
            $select('background', 'Background', 'white', ['white' => 'White', 'soft' => 'Soft green', 'dark' => 'Dark green']),
            $select('button_style', 'Read more style', 'link', ['link' => 'Text link', 'button' => 'Button']),
        ];
    }

    public function normalize(array $input): array
    {
        $result = [];
        foreach ($this->fields() as $field) {
            $name = $field['name'];
            $value = $input[$name] ?? $field['default'];
            if (is_string($value)) {
                $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if (($input['encoded'] ?? '') === '1' || ($input['encoded'] ?? '') === 1) {
                    $value = rawurldecode($value);
                }
            }
            if (! is_scalar($value) && ! is_array($value)) {
                $value = $field['default'];
            }
            if ($field['type'] === 'multiselect' || $name === 'articles') {
                $values = is_array($value) ? $value : explode(',', (string) $value);
                $result[$name] = implode(',', array_slice(array_values(array_unique(array_filter(array_map('intval', $values), fn ($id) => $id > 0))), 0, 100));
            } elseif ($field['type'] === 'number') {
                $result[$name] = max($field['min'], min($field['max'], (int) $value));
            } elseif ($field['type'] === 'checkbox') {
                $result[$name] = in_array($value, [1, '1', true], true) ? 1 : 0;
            } elseif ($field['type'] === 'select') {
                $result[$name] = is_string($value) && isset($field['options'][$value]) ? $value : $field['default'];
            } else {
                $result[$name] = mb_substr(trim(strip_tags(is_scalar($value) ? (string) $value : '')), 0, $field['max']);
            }
        }

        return $result;
    }

    public function directive(array $input): string
    {
        $attributes = '';
        foreach ($this->normalize($input) as $key => $value) {
            $attributes .= ' '.$key.'="'.rawurlencode((string) $value).'"';
        }

        return '{{widget type="'.self::WIDGET.'" encoded="1"'.$attributes.'}}';
    }

    public function allows(string $directive): bool
    {
        if (strlen($directive) > 6000 || ! preg_match('/^\{\{widget\s+(.+?)\}\}$/sD', $directive, $match)) {
            return false;
        }
        $rest = $match[1];
        $params = [];
        while ($rest !== '') {
            if (! preg_match('/^([a-z_]+)="([^"<>\{\}]*)"(?:\s+|$)/s', $rest, $attribute) || isset($params[$attribute[1]])) {
                return false;
            }
            $params[$attribute[1]] = html_entity_decode($attribute[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $rest = substr($rest, strlen($attribute[0]));
        }
        if (($params['type'] ?? '') !== self::WIDGET) {
            return false;
        }
        unset($params['type']);

        return ! array_diff(array_keys($params), array_merge(['encoded'], array_column($this->fields(), 'name')));
    }
}
