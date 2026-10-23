<?php

declare(strict_types=1);

namespace Shi3ld\Blog\Console;

use Shi3ld\Blog\Model\Editorial\Tokens;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Prepare extends Command
{
    public function __construct(private Tokens $tokens)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('shi3ld:blog:prepare')->setDescription('Prepare the private blog integration key shared read-only with services.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->tokens->prepare();
        $output->writeln('<info>The private blog integration key is ready.</info>');

        return self::SUCCESS;
    }
}
