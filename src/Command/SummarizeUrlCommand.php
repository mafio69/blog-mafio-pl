<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\SummarizerService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:summarize-url',
    description: 'Summarizes a given URL using Gemini AI.',
)]
class SummarizeUrlCommand extends Command
{
    public function __construct(private SummarizerService $summarizer)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('url', InputArgument::REQUIRED, 'The URL of the article to summarize');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $url = $input->getArgument('url');

        $io->info(sprintf('Fetching and summarizing: %s', $url));

        try {
            $summary = $this->summarizer->summarizeUrl($url);
            $io->success('Summary generated:');
            $io->writeln($summary);
        } catch (\Exception $e) {
            $io->error(sprintf('Error: %s', $e->getMessage()));
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
