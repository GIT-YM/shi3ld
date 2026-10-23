<?php

declare(strict_types=1);

namespace Shi3ld\Blog\Model\Editorial;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;

class Tokens
{
    private ?string $key = null;

    public function __construct(private Filesystem $filesystem) {}

    public function prepare(): void
    {
        $this->secret();
    }

    public function sign(string $purpose, string $value): string
    {
        return hash_hmac('sha256', $purpose.'|'.$value, $this->secret());
    }

    public function issue(string $purpose, int $postId = 0, int $ttl = 1800): string
    {
        $now = time();
        $value = $postId.'.'.$now.'.'.($now + $ttl).'.'.bin2hex(random_bytes(8));

        return $value.'.'.$this->sign($purpose, $value);
    }

    public function verify(string $purpose, string $token, int $postId = 0, int $minimumAge = 0): bool
    {
        return $this->verifyAt($purpose, $token, $postId, time(), $minimumAge);
    }

    public function verifyAt(string $purpose, string $token, int $postId, int $at, int $minimumAge = 0): bool
    {
        $parts = explode('.', $token);
        if (count($parts) !== 5 || ! ctype_digit($parts[0]) || ! ctype_digit($parts[1]) || ! ctype_digit($parts[2]) || (int) $parts[0] !== $postId || ! preg_match('/^[a-f0-9]{16}$/D', $parts[3])) {
            return false;
        }

        return (int) $parts[1] <= $at - $minimumAge && (int) $parts[2] >= $at && (int) $parts[2] >= (int) $parts[1] && (int) $parts[2] - (int) $parts[1] <= 604800
            && hash_equals($this->sign($purpose, implode('.', array_slice($parts, 0, 4))), $parts[4]);
    }

    private function secret(): string
    {
        if ($this->key !== null) {
            return $this->key;
        }
        $directory = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
        $path = 'shi3ld/site-access/blog.key';
        $directory->create(dirname($path));
        $absolute = $directory->getAbsolutePath($path);
        $handle = @fopen($absolute, 'x');
        if ($handle) {
            try {
                if (fwrite($handle, bin2hex(random_bytes(32))) !== 64) {
                    throw new \RuntimeException('Unable to initialise blog integration key.');
                }fflush($handle);
            } finally {
                fclose($handle);
            }chmod($absolute, 0640);
        }
        $key = trim($directory->readFile($path));
        if (! preg_match('/^[a-f0-9]{64}$/D', $key)) {
            throw new \RuntimeException('The blog integration key is unavailable.');
        }

        return $this->key = $key;
    }
}
