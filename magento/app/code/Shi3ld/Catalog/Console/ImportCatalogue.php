<?php

declare(strict_types=1);

namespace Shi3ld\Catalog\Console;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Shi3ld\Catalog\Model\Transfer\Jobs;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ImportCatalogue extends Command
{
    public function __construct(private State $state, private Jobs $jobs)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('shi3ld:catalog:import')->setDescription('Preview or apply a catalogue promotion package; defaults to preview')
            ->addArgument('file', InputArgument::REQUIRED, 'Catalogue ZIP file path')
            ->addOption('apply', null, InputOption::VALUE_NONE, 'Apply after a successful preview')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview without changing catalogue data')
            ->addOption('prices', null, InputOption::VALUE_NONE, 'Apply packaged prices')
            ->addOption('stock', null, InputOption::VALUE_NONE, 'Apply packaged stock quantities')
            ->addOption('confirm-host', null, InputOption::VALUE_REQUIRED, 'Required target hostname for a production import', '')
            ->addOption('allow-same-environment', null, InputOption::VALUE_NONE, 'Allow a local refresh or recovery from the same environment; does not permit skipping staging');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            return $this->state->emulateAreaCode(Area::AREA_ADMINHTML, function () use ($input, $output): int {
                if ($input->getOption('apply') && $input->getOption('dry-run')) {
                    throw new \RuntimeException('Choose either --apply or --dry-run.');
                }
                $job = $this->jobs->prepare($input->getArgument('file'), 0, (bool) $input->getOption('prices'), (bool) $input->getOption('stock'), (bool) $input->getOption('allow-same-environment'));
                $plan = $job['plan'];
                $output->writeln(sprintf('Transfer %s: %s → %s; create %d, update %d, categories %d, images %d.', $job['id'], $plan['source']['environment'], $plan['target'], $plan['new'], $plan['update'], $plan['categories'], $plan['images']));
                $output->writeln(sprintf('Prices: %s. Stock: %s. New products kept disabled: %d.', $plan['prices'] ? 'apply' : 'preserve', $plan['stock'] ? 'apply' : 'preserve', $plan['new_disabled']));
                foreach ($plan['errors'] as $error) {
                    $output->writeln('<error>' . OutputFormatter::escape($error) . '</error>');
                }
                foreach ($plan['warnings'] as $warning) {
                    $output->writeln('<comment>' . OutputFormatter::escape($warning) . '</comment>');
                }
                if ($plan['errors']) {
                    return Command::FAILURE;
                }
                if (!$input->getOption('apply')) {
                    $output->writeln('Preview only. Re-run with --apply to import.');
                    return Command::SUCCESS;
                }
                $job = $this->jobs->apply($job['id'], 0, $input->getOption('confirm-host'));
                $output->writeln(sprintf('Completed: %d created, %d updated. Catalogue indexes and storefront caches refreshed.', $job['result']['created'], $job['result']['updated']));
                return Command::SUCCESS;
            });
        } catch (\Throwable $exception) {
            $output->writeln('<error>' . OutputFormatter::escape($exception->getMessage()) . '</error>');
            return Command::FAILURE;
        }
    }
}
