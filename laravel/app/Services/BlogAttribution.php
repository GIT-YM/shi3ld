<?php

namespace App\Services;

use App\Jobs\RecordBlogConversion;
use App\Models\ServiceEnquiry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

class BlogAttribution
{
    public function capture(Request $request): void
    {
        if (! $request->hasSession()) {
            return;
        }
        if ($this->excluded($request)) {
            $request->session()->forget('blog_attribution');

            return;
        }
        if ($request->has('blog_ref') || $request->has('blog_token')) {
            $reference = $this->verify($request->input('blog_ref'), $request->input('blog_token'));
            if ($reference) {
                $request->session()->put('blog_attribution', $reference + ['captured_at' => time()]);
            } else {
                $request->session()->forget('blog_attribution');
            }
        }
    }

    public function current(Request $request): ?array
    {
        if ($this->excluded($request)) {
            return null;
        }
        $value = $request->hasSession() ? $request->session()->get('blog_attribution') : null;
        if (! is_array($value) || (int) ($value['captured_at'] ?? 0) < time() - 86400) {
            return null;
        }

        return $this->verify($value['post_id'] ?? null, $value['token'] ?? null);
    }

    public function attributes(Request $request): array
    {
        $reference = $this->current($request);

        return $reference ? ['blog_post_id' => $reference['post_id'], 'blog_attribution_token' => $reference['token'], 'blog_attributed_at' => now()] : [];
    }

    public function queue(ServiceEnquiry $enquiry): void
    {
        if ($enquiry->blog_post_id) {
            try {
                Bus::dispatch((new RecordBlogConversion($enquiry->id))->afterCommit());
            } catch (\Throwable $exception) {
                report($exception);
            }
        }
    }

    public function record(ServiceEnquiry $enquiry): void
    {
        if (! $enquiry->blog_post_id || $enquiry->blog_conversion_recorded_at || ! $enquiry->blog_attribution_token) {
            return;
        }
        $key = $this->secret();
        if (! $key) {
            throw new \RuntimeException('The shared blog integration key is unavailable.');
        }
        $timestamp = time();
        $occurred = ($enquiry->blog_attributed_at ?? $enquiry->created_at)->timestamp;
        $value = $enquiry->blog_post_id.'|'.$enquiry->reference.'|'.$timestamp.'|'.$occurred.'|'.hash('sha256', $enquiry->blog_attribution_token);
        $headers = ['X-Blog-Signature' => hash_hmac('sha256', 'conversion|'.$value, $key), 'X-Forwarded-Proto' => parse_url(config('shi3ld.site_url'), PHP_URL_SCHEME)];
        if ($host = parse_url(config('shi3ld.site_url'), PHP_URL_HOST)) {
            $headers['Host'] = $host;
        }
        $response = Http::connectTimeout(2)->timeout(8)->withoutRedirecting()->acceptJson()->withHeaders($headers)
            ->post(rtrim(config('shi3ld.magento.base_url'), '/').'/blog/conversion/record', ['post_id' => $enquiry->blog_post_id, 'reference' => $enquiry->reference,
                'timestamp' => $timestamp, 'occurred_at' => $occurred, 'attribution' => $enquiry->blog_attribution_token])->throw();
        if (! $response->json('ok')) {
            throw new \RuntimeException('Blog conversion recording was not acknowledged.');
        }
        $title = $response->json('article.title');
        $url = $response->json('article.url');
        $validUrl = is_string($url) && parse_url($url, PHP_URL_HOST) === parse_url(config('shi3ld.site_url'), PHP_URL_HOST) && parse_url($url, PHP_URL_SCHEME) === 'https';
        $enquiry->forceFill(['blog_conversion_recorded_at' => now(), 'blog_source_title' => is_string($title) ? mb_substr(strip_tags($title), 0, 255) : null,
            'blog_source_url' => $validUrl ? mb_substr($url, 0, 1000) : null])->save();
    }

    public function verify(mixed $id, mixed $token): ?array
    {
        if ((! is_string($id) && ! is_int($id)) || ! ctype_digit((string) $id) || (int) $id < 1 || (int) $id > 4294967295 || ! is_string($token) || strlen($token) > 180) {
            return null;
        }
        $parts = explode('.', $token);
        if (count($parts) !== 5 || ! ctype_digit($parts[0]) || (int) $parts[0] !== (int) $id || ! ctype_digit($parts[1]) || ! ctype_digit($parts[2])
            || ! preg_match('/^[a-f0-9]{16}$/D', $parts[3]) || ! preg_match('/^[a-f0-9]{64}$/D', $parts[4]) || (int) $parts[1] > time() || (int) $parts[2] < time()
            || (int) $parts[2] - (int) $parts[1] > 604800 || (int) $parts[2] < (int) $parts[1]) {
            return null;
        }
        $key = $this->secret();
        if (! $key || ! hash_equals(hash_hmac('sha256', 'attribution|'.implode('.', array_slice($parts, 0, 4)), $key), $parts[4])) {
            return null;
        }

        return ['post_id' => (int) $id, 'token' => $token];
    }

    private function secret(): ?string
    {
        $path = dirname(config('shi3ld.shared_design_file')).'/blog.key';
        if (! is_file($path) || ! is_readable($path)) {
            return null;
        }
        $key = trim(file_get_contents($path));

        return preg_match('/^[a-f0-9]{64}$/D', $key) ? $key : null;
    }

    private function excluded(Request $request): bool
    {
        return $request->header('DNT') === '1' || $request->header('Sec-GPC') === '1';
    }
}
