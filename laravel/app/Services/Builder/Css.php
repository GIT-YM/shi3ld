<?php

declare(strict_types=1);

namespace App\Services\Builder;

/** A bounded stylesheet parser. CSS is scoped before it reaches a public page. */
class Css
{
    public function compile(string $css, string $scope): string
    {
        if (strlen($css) > 65536 || ! mb_check_encoding($css, 'UTF-8')) {
            throw new \InvalidArgumentException('Custom CSS must be valid UTF-8 and no larger than 64 KB.');
        }
        $css = preg_replace('~/\*.*?\*/~s', '', $css);
        if (preg_match('~[<\\\\\x00-\x08\x0b\x0c\x0e-\x1f]|(?:url|expression|(?:-webkit-)?image-set)\s*\(|(?:javascript|https?|data)\s*:|behavior\s*:|-moz-binding~i', $css)) {
            throw new \InvalidArgumentException('Use CSS declarations and the image picker. External imports, URLs and executable styles are not supported.');
        }

        return $this->rules($css, $scope, 0);
    }

    private function rules(string $input, string $scope, int $depth): string
    {
        if ($depth > 8) {
            throw new \InvalidArgumentException('CSS contains too many nested rules.');
        }
        $result = '';
        $offset = 0;
        $length = strlen($input);
        while ($offset < $length) {
            while ($offset < $length && ctype_space($input[$offset])) {
                $offset++;
            }
            if ($offset >= $length) {
                break;
            }
            $start = $offset;
            $quote = '';
            while ($offset < $length) {
                $char = $input[$offset];
                if ($quote !== '') {
                    if ($char === $quote) {
                        $quote = '';
                    }
                } elseif ($char === '"' || $char === "'") {
                    $quote = $char;
                } elseif ($char === '{') {
                    break;
                } elseif ($char === '}' || $char === ';') {
                    throw new \InvalidArgumentException('CSS rule is missing an opening brace near character '.$offset.'.');
                }
                $offset++;
            }
            if ($offset >= $length || $quote !== '') {
                throw new \InvalidArgumentException('CSS rule is missing its body. Use selector { property: value; }.');
            }
            $header = trim(substr($input, $start, $offset - $start));
            $offset++;
            $start = $offset;
            $level = 1;
            $quote = '';
            while ($offset < $length && $level > 0) {
                $char = $input[$offset];
                if ($quote !== '') {
                    if ($char === $quote) {
                        $quote = '';
                    }
                } elseif ($char === '"' || $char === "'") {
                    $quote = $char;
                } elseif ($char === '{') {
                    $level++;
                } elseif ($char === '}') {
                    $level--;
                }
                if ($level > 0) {
                    $offset++;
                }
            }
            if ($level !== 0 || $quote !== '') {
                throw new \InvalidArgumentException('CSS has an unclosed brace or quoted value.');
            }
            $body = substr($input, $start, $offset - $start);
            $offset++;
            if (str_starts_with($header, '@')) {
                if (! preg_match('/^@(media|supports)\s+[^{};@]{1,300}$/iD', $header)) {
                    throw new \InvalidArgumentException('Only @media and @supports are supported in custom CSS.');
                }
                $result .= $header.'{'.$this->rules($body, $scope, $depth + 1).'}';
            } else {
                $selectors = $this->selectors($header);
                $scoped = [];
                foreach ($selectors as $selector) {
                    if (preg_match('/\bselector\b/', $selector)) {
                        if (! preg_match('/^selector(?:$|[\s.\[:#>])/', $selector) || preg_match('/^selector\s*[+~]/', $selector)
                            || substr_count($selector, 'selector') > 1) {
                            throw new \InvalidArgumentException('Use selector at the beginning of a scoped CSS rule.');
                        }
                        $scoped[] = preg_replace('/\bselector\b/', $scope, $selector);
                    } elseif ($selector === ':root' || $selector === 'body' || $selector === 'html') {
                        $scoped[] = $scope;
                    } else {
                        $scoped[] = $scope.' '.$selector;
                    }
                }
                $result .= implode(',', $scoped).'{'.$this->declarations($body).'}';
            }
        }

        return $result;
    }

    private function selectors(string $input): array
    {
        $result = [];
        $start = 0;
        $quote = '';
        $depth = 0;
        for ($i = 0; $i < strlen($input); $i++) {
            $char = $input[$i];
            if ($quote !== '') {
                if ($char === $quote) {
                    $quote = '';
                }
            } elseif ($char === '"' || $char === "'") {
                $quote = $char;
            } elseif ($char === '[' || $char === '(') {
                $depth++;
            } elseif ($char === ']' || $char === ')') {
                $depth--;
            } elseif ($char === ',' && $depth === 0) {
                $result[] = trim(substr($input, $start, $i - $start));
                $start = $i + 1;
            }
            if ($depth < 0) {
                throw new \InvalidArgumentException('CSS selector has unmatched brackets.');
            }
        }
        $result[] = trim(substr($input, $start));
        if ($depth !== 0 || $quote !== '' || count($result) > 100) {
            throw new \InvalidArgumentException('Enter a valid CSS selector.');
        }
        foreach ($result as $selector) {
            if ($selector === '' || strlen($selector) > 500 || preg_match('/[{};@]/', $selector)) {
                throw new \InvalidArgumentException('Enter a valid CSS selector.');
            }
        }

        return $result;
    }

    public function declarations(string $input): string
    {
        $rules = [];
        $start = 0;
        $quote = '';
        $depth = 0;
        for ($i = 0; $i <= strlen($input); $i++) {
            $char = $input[$i] ?? ';';
            if ($quote !== '') {
                if ($char === $quote) {
                    $quote = '';
                }
            } elseif ($char === '"' || $char === "'") {
                $quote = $char;
            } elseif ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
            } elseif ($char === ';' && $depth === 0) {
                $rule = trim(substr($input, $start, $i - $start));
                $start = $i + 1;
                if ($rule === '') {
                    continue;
                }
                if (! preg_match('/^(--[a-zA-Z0-9_-]+|[a-zA-Z][a-zA-Z0-9-]*)\s*:\s*(.+)$/sD', $rule, $match)
                    || preg_match('/[{}@<>\\\\]/', $rule)) {
                    throw new \InvalidArgumentException('Enter declarations in the form property: value;');
                }
                $rules[] = strtolower($match[1]).':'.trim($match[2]).';';
            }
            if ($depth < 0) {
                throw new \InvalidArgumentException('CSS value has unmatched parentheses.');
            }
        }
        if ($depth !== 0 || $quote !== '') {
            throw new \InvalidArgumentException('CSS contains an unclosed quoted value or function.');
        }

        return implode('', $rules);
    }
}
