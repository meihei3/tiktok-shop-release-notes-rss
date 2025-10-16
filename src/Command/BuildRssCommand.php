<?php

declare(strict_types=1);

namespace TikTokShopRss\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TikTokShopRss\Application\UseCase\BuildRssUseCase;
use TikTokShopRss\Infrastructure\Persistence\StateManager;
use TikTokShopRss\Service\ConfigLoader;

use function count;
use function dirname;
use function file_exists;
use function file_put_contents;
use function filesize;
use function is_dir;
use function json_encode;
use function microtime;
use function min;
use function mkdir;
use function round;
use function rtrim;
use function strtotime;
use function time;

#[AsCommand(
    name: 'tts:rss:build',
    description: 'Build RSS feed from TikTok Shop documentation updates'
)]
class BuildRssCommand extends Command
{
    public function __construct(
        private readonly BuildRssUseCase $buildRssUseCase,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('since', null, InputOption::VALUE_REQUIRED, 'Baseline datetime (ISO8601)')
            ->addOption('full', null, InputOption::VALUE_NONE, 'Full scan instead of differential')
            ->addOption('output', null, InputOption::VALUE_REQUIRED, 'Output file path', 'var/rss/tiktok-shop.xml')
            ->addOption('docs-dir', null, InputOption::VALUE_REQUIRED, 'GitHub Pages public directory')
            ->addOption(
                'output-basename',
                null,
                InputOption::VALUE_REQUIRED,
                'Output filename in docs-dir',
                'index.xml'
            )
            ->addOption('state', null, InputOption::VALUE_REQUIRED, 'State file path', 'var/state/tiktok-shop.json')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Display detection count only')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output execution summary as JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $startTime = microtime(true);

        try {
            $since = $input->getOption('since');
            if ($since !== null && strtotime($since) > time()) {
                $output->writeln('<error>--since must not be a future datetime</error>');
                return 2;
            }

            $configPath = '.ttsrss.yaml';
            if (!file_exists($configPath)) {
                $output->writeln("<error>Config file not found: {$configPath}</error>");
                return 2;
            }

            $configLoader = new ConfigLoader();
            $config = $configLoader->load($configPath);

            $stateFile = $input->getOption('state') ?? $config->stateFile;
            $stateManager = new StateManager();
            $state = $stateManager->load($stateFile);

            $buildResult = $this->buildRssUseCase->build($config, $state);
            $pagesChanged = $buildResult->pagesChanged;
            $state = $buildResult->state;

            if (!$input->getOption('dry-run')) {
                $stateWriteStart = microtime(true);
                $stateManager->save($stateFile, $state);
                $stateWriteMs = (microtime(true) - $stateWriteStart) * 1000;

                $rss = $this->buildRssUseCase->generateRss($config, $state);

                $outputPath = $input->getOption('output');

                if ($outputPath === '-') {
                    $output->writeln($rss);
                } else {
                    $outputDir = dirname($outputPath);
                    if (!is_dir($outputDir) && !mkdir($outputDir, 0755, true) && !is_dir($outputDir)) {
                        $output->writeln("<error>Failed to create output directory: {$outputDir}</error>");
                        return 4;
                    }

                    if (file_put_contents($outputPath, $rss) === false) {
                        $output->writeln("<error>Failed to write output file: {$outputPath}</error>");
                        return 4;
                    }

                    $docsDir = $input->getOption('docs-dir');
                    if ($docsDir !== null) {
                        $basename = $input->getOption('output-basename');
                        $docsPath = rtrim($docsDir, '/') . '/' . $basename;

                        if (!is_dir($docsDir) && !mkdir($docsDir, 0755, true) && !is_dir($docsDir)) {
                            $output->writeln("<error>Failed to create docs directory: {$docsDir}</error>");
                            return 4;
                        }

                        if (file_put_contents($docsPath, $rss) === false) {
                            $output->writeln("<error>Failed to write docs file: {$docsPath}</error>");
                            return 4;
                        }
                    }
                }

                $durationMs = (microtime(true) - $startTime) * 1000;

                if ($input->getOption('json')) {
                    $itemsLimit = $config->limits['items'] ?? 50;
                    $summary = [
                        'crawl.pages_total' => count($state->items),
                        'crawl.pages_changed' => $pagesChanged,
                        'rss.items_emitted' => min(count($state->items), $itemsLimit),
                        'duration_ms' => round($durationMs, 2),
                        'state.file_write_ms' => round($stateWriteMs, 2),
                        'state.file_size_bytes' => file_exists($stateFile) ? filesize($stateFile) : 0,
                    ];
                    $summaryJson = json_encode($summary, JSON_PRETTY_PRINT);
                    if ($summaryJson === false) {
                        throw new \RuntimeException('Failed to encode summary to JSON');
                    }
                    $output->writeln($summaryJson);
                } else {
                    $output->writeln("<info>RSS feed generated successfully</info>");
                    $output->writeln("Pages changed: {$pagesChanged}");
                    $output->writeln("Total items: " . count($state->items));
                }
            } else {
                $output->writeln("<info>Dry run: {$pagesChanged} pages detected as changed</info>");
            }

            return 0;
        } catch (\Exception $e) {
            $output->writeln("<error>Error: {$e->getMessage()}</error>");
            return 5;
        }
    }
}
