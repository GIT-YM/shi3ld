<?php

namespace Shi3ld\Content\Console;

use Shi3ld\Content\Model\SiteSitemaps;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateSiteSitemaps extends Command
{
    public function __construct(private SiteSitemaps $generator)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('shi3ld:site:sitemaps')->setDescription('Generate the default-store commerce sitemap and shared sitemap index.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->generator->generate();
        $output->writeln('Default-store commerce sitemap and site sitemap index generated.');

        return Command::SUCCESS;
    }
}
