<?php

declare(strict_types=1);

namespace Shi3ld\Catalog\Model\Transfer;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Lock\LockManagerInterface;
use Psr\Log\LoggerInterface;

class Jobs
{
    public function __construct(
        private Package $packages,
        private Importer $importer,
        private Exporter $exporter,
        private ProductRepositoryInterface $products,
        private Environment $environment,
        private LockManagerInterface $locks,
        private LoggerInterface $logger
    ) {
    }

    public function prepare(string $file, int $owner, bool $prices, bool $stock, bool $allowSameEnvironment = false): array
    {
        $id = bin2hex(random_bytes(16));
        $copy = $this->packages->path($id . '.zip');
        if (!is_file($file) || !is_readable($file) || filesize($file) > Package::MAX_BYTES || !copy($file, $copy)) {
            throw new \RuntimeException('Unable to store the catalogue package.');
        }
        chmod($copy, 0600);
        try {
            $plan = $this->importer->preview($copy, $prices, $stock, $allowSameEnvironment);
        } catch (\Throwable $exception) {
            unlink($copy);
            throw $exception;
        }
        $job = [
            'id' => $id, 'owner' => $owner, 'status' => 'preview', 'created_at' => time(),
            'sha256' => hash_file('sha256', $copy), 'prices' => $prices, 'stock' => $stock,
            'allow_same_environment' => $allowSameEnvironment, 'plan' => $plan, 'saved' => [],
        ];
        $this->save($job);
        return $job;
    }

    public function get(string $id, ?int $owner = null): array
    {
        if (!preg_match('/\A[a-f0-9]{32}\z/D', $id)) {
            throw new \RuntimeException('Invalid catalogue transfer reference.');
        }
        $path = $this->packages->path($id . '.json');
        if (!is_file($path)) {
            throw new \RuntimeException('The catalogue transfer was not found.');
        }
        $job = json_decode(file_get_contents($path), true, 48, JSON_THROW_ON_ERROR);
        if ($owner !== null && (int) $job['owner'] !== $owner) {
            throw new \RuntimeException('This catalogue preview belongs to another administrator.');
        }
        return $job;
    }

    public function recent(int $owner): array
    {
        $files = glob(dirname($this->packages->path('index')) . '/*.json') ?: [];
        usort($files, fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));
        $jobs = [];
        foreach (array_slice($files, 0, 100) as $file) {
            try {
                $job = $this->get(basename($file, '.json'));
                if (in_array((int) $job['owner'], [0, $owner], true)) {
                    $jobs[] = $job;
                }
            } catch (\Throwable $exception) {
                continue;
            }
            if (count($jobs) >= 10) {
                break;
            }
        }
        return $jobs;
    }

    public function apply(string $id, int $owner, string $confirmHost = ''): array
    {
        if (!$this->locks->lock('SHI3LD_CATALOGUE_JOB_' . $id, 0)) {
            throw new \RuntimeException('This catalogue transfer is already running.');
        }
        try {
            $job = $this->get($id, $owner);
            if ($job['status'] !== 'preview' || time() - $job['created_at'] > 86400) {
                throw new \RuntimeException('Upload and preview the package again before applying it.');
            }
            $file = $this->packages->path($id . '.zip');
            if (!hash_equals($job['sha256'], (string) hash_file('sha256', $file))) {
                throw new \RuntimeException('The stored catalogue package changed. Upload it again.');
            }
            $plan = $this->importer->preview($file, $job['prices'], $job['stock'], $job['allow_same_environment']);
            if ($plan['errors'] || !hash_equals($job['plan']['fingerprint'], $plan['fingerprint'])) {
                throw new \RuntimeException($plan['errors'] ? implode("\n", $plan['errors']) : 'Target products changed. Preview the package again.');
            }
            if ($this->environment->getName() === 'production' && !hash_equals($this->environment->getHost(), $confirmHost)) {
                throw new \RuntimeException('Type the production hostname to confirm this import.');
            }
            $existing = [];
            foreach ($plan['rows'] as $row) {
                try {
                    $this->products->get($row['sku'], false, 0, true);
                    $existing[] = $row['sku'];
                } catch (NoSuchEntityException $exception) {
                    continue;
                }
            }
            if ($existing) {
                $job['backup'] = basename($this->exporter->export($existing, true, true, true, $id . '-before.zip'));
            }
            $job['status'] = 'running';
            $this->save($job);
            try {
                $job['result'] = $this->importer->apply(
                    $file, $job['prices'], $job['stock'], $plan['fingerprint'], $confirmHost, $job['allow_same_environment'],
                    function (string $sku) use (&$job): void {
                        $job['saved'][] = $sku;
                        $this->save($job);
                    }
                );
                $job['status'] = 'complete';
            } catch (\Throwable $exception) {
                $job['status'] = $job['saved'] ? 'partial' : 'failed';
                $job['error'] = mb_substr($exception->getMessage(), 0, 3000);
                $this->logger->error('Shi3ld catalogue transfer failed.', ['job' => $id, 'exception' => $exception]);
                $this->save($job);
                throw new \RuntimeException('Catalogue transfer ' . $id . ' stopped. ' . count($job['saved']) . ' products were saved. ' . $job['error'], 0, $exception);
            }
            $this->save($job);
            return $job;
        } finally {
            $this->locks->unlock('SHI3LD_CATALOGUE_JOB_' . $id);
        }
    }

    private function save(array $job): void
    {
        $path = $this->packages->path($job['id'] . '.json');
        $temporary = $path . '.' . bin2hex(random_bytes(4));
        $job['updated_at'] = time();
        if (file_put_contents($temporary, json_encode($job, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT), LOCK_EX) === false) {
            throw new \RuntimeException('Unable to record catalogue transfer progress.');
        }
        chmod($temporary, 0600);
        if (!rename($temporary, $path)) {
            throw new \RuntimeException('Unable to save the catalogue transfer record.');
        }
    }
}
