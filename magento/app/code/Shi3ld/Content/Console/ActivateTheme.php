<?php
namespace Shi3ld\Content\Console;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Theme\Model\ResourceModel\Theme\CollectionFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
class ActivateTheme extends Command
{
    public function __construct(private CollectionFactory $themes, private WriterInterface $configWriter, private TypeListInterface $cacheTypes)
    { parent::__construct(); }
    protected function configure(): void
    { $this->setName('shi3ld:theme:activate')->setDescription('Activate the Shi3ld Alliance theme for the default scope.'); }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $theme = $this->themes->create()->addFieldToFilter('theme_path', 'Shi3ld/Alliance')->getFirstItem();
        if (!$theme->getId()) { $output->writeln('Theme is not registered. Run setup:upgrade first.'); return Command::FAILURE; }
        $this->configWriter->save('design/theme/theme_id', (string) $theme->getId());
        $this->cacheTypes->cleanType('config');
        $this->cacheTypes->cleanType('layout');
        $this->cacheTypes->cleanType('block_html');
        $this->cacheTypes->cleanType('full_page');
        $output->writeln('Shi3ld Alliance theme activated. Store-level overrides retain their configured values.');
        return Command::SUCCESS;
    }
}
