<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\AggregatorService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:fetch-feeds',
    description: 'Fetches RSS feeds, filters with AI and saves drafts.',
)]
class FetchFeedsCommand extends Command
{
    public function __construct(private AggregatorService $aggregator)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Starting RSS Aggregation');

        $results = $this->aggregator->run();

        if ($results['processed'] > 0) {
            $io->success(sprintf('Processed %d new articles:', $results['processed']));
            foreach ($results['titles'] as $title) {
                $io->writeln(' - ' . $title);
            }
        } else {
            $io->note('No new significant articles found.');
        }

        return Command::SUCCESS;
    }
}
