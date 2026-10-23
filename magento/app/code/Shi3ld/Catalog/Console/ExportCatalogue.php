<?php

declare(strict_types=1);

namespace Shi3ld\Catalog\Console;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Shi3ld\Catalog\Model\Transfer\Exporter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ExportCatalogue extends Command
{
    public function __construct(private State $state, private Exporter $exporter)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('shi3ld:catalog:export')->setDescription('Export a portable catalogue package with variants, attributes, categories and images')
            ->addOption('sku', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Product SKU; repeat for multiple products. Variants are included automatically.')
            ->addOption('include-samples', null, InputOption::VALUE_NONE, 'Include development sample SKUs')
            ->addOption('include-prices', null, InputOption::VALUE_NONE, 'Include price and tax-class data')
            ->addOption('include-stock', null, InputOption::VALUE_NONE, 'Include inventory source quantities')
            ->addOption('output', null, InputOption::VALUE_REQUIRED, 'ZIP filename inside var/shi3ld/catalogue-transfer');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $path = $this->state->emulateAreaCode(Area::AREA_ADMINHTML, fn (): string => $this->exporter->export(
                $input->getOption('sku'), (bool) $input->getOption('include-samples'),
                (bool) $input->getOption('include-prices'), (bool) $input->getOption('include-stock'), $input->getOption('output')
            ));
            $output->writeln('Catalogue package: ' . OutputFormatter::escape($path));
            return Command::SUCCESS;
        } catch (\Throwable $exception) {
            $output->writeln('<error>' . OutputFormatter::escape($exception->getMessage()) . '</error>');
            return Command::FAILURE;
        }
    }
}
