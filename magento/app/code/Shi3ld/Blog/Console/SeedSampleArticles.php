<?php

declare(strict_types=1);

namespace Shi3ld\Blog\Console;

use Magento\Framework\App\Area;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\State;
use Magento\Store\Model\StoreManagerInterface;
use Shi3ld\Blog\Model\Repository;
use Shi3ld\Content\Model\Editor\Documents;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SeedSampleArticles extends Command
{
    public function __construct(private State $state, private StoreManagerInterface $stores, private ResourceConnection $database,
        private Repository $posts, private Documents $documents)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('shi3ld:blog:seed-samples')->setDescription('Create three clearly labelled development blog samples, preserving existing articles')
            ->addOption('publish', null, InputOption::VALUE_NONE, 'Publish new samples on a local development site');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $host = (string) parse_url($this->stores->getDefaultStoreView()->getBaseUrl(), PHP_URL_HOST);
        $environment = (string) getenv('SHI3LD_ENVIRONMENT');
        $local = ! str_contains($host, '.') || preg_match('/(?:\.test|\.localhost|\.rpidevserver|\.gmkserver)$/D', $host);
        if ($this->state->getMode() !== State::MODE_DEVELOPER || $environment === 'production' || ! $local) {
            $output->writeln('<error>Sample articles can only be seeded on a local site in Magento developer mode.</error>');

            return Command::FAILURE;
        }
        $this->state->emulateAreaCode(Area::AREA_ADMINHTML, function () use ($input, $output): void {
            $categoryIds = [];
            foreach (['Cleaning guides', 'Product guides', 'Planning ahead'] as $title) {
                foreach ($this->posts->categories(false) as $category) {
                    if ($category['title'] === $title) {
                        $categoryIds[$title] = (int) $category['category_id'];
                        break;
                    }
                }
                if (! isset($categoryIds[$title])) {
                    $categoryIds[$title] = $this->posts->saveCategory(0, ['title' => $title, 'is_active' => 1,
                        'description' => 'Development sample category. Replace with approved editorial content before launch.']);
                }
            }
            $samples = [
                ['title' => 'Sample: Planning a cleaning enquiry', 'key' => 'sample-planning-a-cleaning-enquiry',
                    'categories' => ['Cleaning guides', 'Planning ahead'], 'image' => 'home-sample.jpg', 'alt' => 'Illustrative living room — development sample image',
                    'excerpt' => 'Sample article: a few details to gather before discussing your cleaning requirements.',
                    'sections' => ['Describe your space' => 'List the rooms or shared areas you would like to discuss. Mention access arrangements and any areas that should be left untouched.',
                        'Think about your routine' => 'Note your preferred days and times, how often you would like help, and any priorities for an initial conversation.',
                        'Ask for a tailored conversation' => 'Use the cleaning enquiry form to share your requirements. This sample describes an enquiry process and does not promise availability or a fixed quotation.']],
                ['title' => 'Sample: Keeping workspaces organised', 'key' => 'sample-keeping-workspaces-organised',
                    'categories' => ['Cleaning guides'], 'image' => 'workplace-sample.jpg', 'alt' => 'Illustrative meeting room — development sample image',
                    'excerpt' => 'Sample article: simple planning ideas for a tidier shared workspace.',
                    'sections' => ['Agree your priorities' => 'Identify the shared spaces that matter to your team, such as meeting rooms, reception areas and kitchen counters.',
                        'Make expectations clear' => 'Choose a place for shared items and agree who looks after personal belongings. Discuss these arrangements when preparing a cleaning enquiry.',
                        'Review what works' => 'Keep a short list of changes to discuss at your next review. This is illustrative planning content for the development website.']],
                ['title' => 'Sample: Comparing product options', 'key' => 'sample-comparing-product-options',
                    'categories' => ['Product guides', 'Planning ahead'], 'image' => 'care-sample.jpg', 'alt' => 'Illustrative communal lounge — development sample image',
                    'excerpt' => 'Sample article: keeping a clear record of colours, sizes and product references while browsing.',
                    'sections' => ['Keep the product reference' => 'Save the product name or SKU alongside the options you are considering so that you can return to the same item later.',
                        'Compare the listed choices' => 'Review the colours, sizes, descriptions and specifications on the product page. Ask for clarification where a detail is unclear.',
                        'Share your questions' => 'Prepare a short list of questions before ordering. This illustrative article does not provide installation advice or product suitability recommendations.']],
            ];
            $db = $this->database->getConnection();
            foreach ($samples as $sample) {
                if ($db->fetchOne($db->select()->from($this->posts->table('post'), ['post_id'])
                    ->where('slug = ?', $sample['key'])->orWhere('title = ?', $sample['title']))) {
                    $output->writeln('Preserved existing sample: '.$sample['key']);

                    continue;
                }
                $id = $this->posts->create($sample['title'], '');
                $state = $this->documents->get('article', $id);
                $payload = $state['payload'];
                $payload['html'] = '<section class="blog-copy"><p><strong>Development sample article.</strong> This copy and its image are illustrative. Replace them with approved content before public launch.</p></section>';
                foreach ($sample['sections'] as $heading => $text) {
                    $payload['html'] .= '<section class="blog-copy"><h2>'.htmlspecialchars($heading, ENT_QUOTES, 'UTF-8').'</h2><p>'.htmlspecialchars($text, ENT_QUOTES, 'UTF-8').'</p></section>';
                }
                $payload['category_ids'] = array_map(fn ($title) => $categoryIds[$title], $sample['categories']);
                $payload['featured_image'] = 'wysiwyg/shi3ld/'.$sample['image'];
                $payload['featured_alt'] = $sample['alt'];
                $payload['excerpt'] = $sample['excerpt'];
                $payload['meta_description'] = $sample['excerpt'];
                $payload['is_featured'] = 1;
                $saved = $this->documents->save('article', $id, $payload, 0, 'Development samples', 'draft', $state['live_hash']);
                if ($input->getOption('publish')) {
                    $this->documents->publish('article', $id, $saved['version'], 'Development samples');
                }
                $output->writeln(($input->getOption('publish') ? 'Published' : 'Saved draft').': '.$sample['key']);
            }
        });

        return Command::SUCCESS;
    }
}
