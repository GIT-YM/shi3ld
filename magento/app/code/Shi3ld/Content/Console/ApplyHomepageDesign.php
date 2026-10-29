<?php

declare(strict_types=1);

namespace Shi3ld\Content\Console;

use Magento\Framework\App\State;
use Shi3ld\Content\Model\Editor\SharedDesign;
use Shi3ld\Content\Model\HomepageDesign;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ApplyHomepageDesign extends Command
{
    public function __construct(private HomepageDesign $design, private State $state, private SharedDesign $sharedDesign)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('shi3ld:cms:apply-home-design')->setDescription('Preview or apply the supplied Alliance homepage design, preserving a private CMS backup.')
            ->addOption('apply', null, InputOption::VALUE_NONE, 'Back up the current content, publish the homepage/shared regions, and register supplied images.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $result = $this->state->emulateAreaCode('adminhtml', fn (): array => $this->design->install((bool) $input->getOption('apply')));
            foreach ($result['entities'] as $entity) {
                $output->writeln($entity['identifier'].': '.($entity['changed'] ? 'design update available' : 'already matches').($entity['has_draft'] ? ' (existing draft included in backup when applying)' : ''));
            }
            if ($result['applied']) {
                $this->sharedDesign->export();
                $output->writeln('<info>Homepage and shared header/footer published. Backup: '.OutputFormatter::escape($result['backup']).'</info>');
            } else {
                $output->writeln('No content changed. Use --apply to install available design changes.');
            }

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $output->writeln('<error>'.OutputFormatter::escape($exception->getMessage()).'</error>');

            return self::FAILURE;
        }
    }
}
