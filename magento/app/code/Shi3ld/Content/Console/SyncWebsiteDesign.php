<?php

declare(strict_types=1);

namespace Shi3ld\Content\Console;

use Shi3ld\Content\Model\Editor\SharedDesign;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SyncWebsiteDesign extends Command
{
    public function __construct(private SharedDesign $design, private \Shi3ld\Content\Model\Builder\Runtime $builder) { parent::__construct(); }

    protected function configure(): void
    {
        $this->setName('shi3ld:cms:sync-design')->setDescription('Share the published CMS header and footer with Laravel services.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->design->export();
        $this->builder->export();
        $output->writeln('<info>Published website header/footer shared with services.</info>');
        return Command::SUCCESS;
    }
}
