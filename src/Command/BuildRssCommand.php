<?php

declare(strict_types=1);

namespace TikTokShopRss\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use TikTokShopRss\Model\DocumentItem;
use TikTokShopRss\Model\SourceState;
use TikTokShopRss\Model\State;
use TikTokShopRss\Service\ConfigLoader;
use TikTokShopRss\Service\DocumentFetcher;
use TikTokShopRss\Service\RssGenerator;
use TikTokShopRss\Service\StateManager;

#[AsCommand(
    name: 'tts:rss:build',
    description: 'Build RSS feed from TikTok Shop documentation updates'
)]
class BuildRssCommand extends Command
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
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

            $documentFetcher = new DocumentFetcher($this->httpClient);
            $newItems = [];
            $pagesChanged = 0;

            foreach ($config->sources as $source) {
                $existingSourceState = $this->findSourceState($state->sources, $source->treeUrl);

                $treeResult = $documentFetcher->fetchTree(
                    $source,
                    $existingSourceState?->etag,
                    $existingSourceState?->lastModified
                );

                if ($treeResult['not_modified'] === true) {
                    continue;
                }

                $treeData = $treeResult['data'];
                $documentPaths = $documentFetcher->extractDocumentPaths($treeData['data']['document_tree'] ?? []);

                $pagesLimit = $config->limits['pages'] ?? 300;
                $documentPaths = array_slice($documentPaths, 0, $pagesLimit);

                foreach ($documentPaths as $documentPath) {
                    try {
                        $detailData = $documentFetcher->fetchDetail($source, $documentPath);

                        $content = $detailData['data']['content'] ?? '';
                        $description = $detailData['data']['description'] ?? '';

                        if ($description === '') {
                            $description = $this->generateSummary($content);
                        }

                        $contentHash = hash('sha256', $content);

                        $existingItem = $stateManager->findItemByDocumentPath($state->items, $documentPath);

                        if ($existingItem !== null && $existingItem->contentHash === $contentHash) {
                            continue;
                        }

                        $title = $detailData['data']['title'] ?? 'Untitled';
                        $link = str_replace('{document_path}', $documentPath, $source->publicUrlTemplate);
                        $updateTime = $detailData['data']['update_time'] ?? null;
                        $pubDate = $updateTime !== null ? date('c', (int) $updateTime) : date('c');

                        $newItems[] = new DocumentItem(
                            documentPath: $documentPath,
                            title: $title,
                            description: $description,
                            contentHash: $contentHash,
                            pubDate: $pubDate,
                            link: $link,
                            contentHtml: $content,
                        );

                        $pagesChanged++;

                        usleep($config->sleepBetweenRequestsMs * 1000);
                    } catch (\Exception $e) {
                        $warning = "<comment>Warning: Failed to fetch detail for {$documentPath}: "
                            . "{$e->getMessage()}</comment>";
                        $output->writeln($warning);
                    }
                }

                $treeJson = json_encode($treeData);
                if ($treeJson === false) {
                    throw new \RuntimeException('Failed to encode tree data to JSON');
                }

                $state = new State(
                    version: $state->version,
                    sources: $this->updateSourceState(
                        $state->sources,
                        $source->treeUrl,
                        $treeResult['etag'] ?? null,
                        $treeResult['last_modified'] ?? null,
                        hash('sha256', $treeJson),
                        date('c')
                    ),
                    items: $stateManager->mergeItems($state->items, $newItems),
                );
            }

            if (!$input->getOption('dry-run')) {
                $stateWriteStart = microtime(true);
                $stateManager->save($stateFile, $state);
                $stateWriteMs = (microtime(true) - $stateWriteStart) * 1000;

                $rssGenerator = new RssGenerator(__DIR__ . '/../../templates');
                $enableContentEncoded = $config->rss['enable_content_encoded'] ?? true;
                $itemsLimit = $config->limits['items'] ?? 50;

                $rss = $rssGenerator->generate(
                    $config->channel,
                    $state->items,
                    $enableContentEncoded,
                    $itemsLimit
                );

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

    /**
     * @param array<int, SourceState> $sources
     */
    private function findSourceState(array $sources, string $url): ?SourceState
    {
        foreach ($sources as $source) {
            if ($source->url === $url) {
                return $source;
            }
        }

        return null;
    }

    /**
     * @param array<int, SourceState> $sources
     * @return array<int, SourceState>
     */
    private function updateSourceState(
        array $sources,
        string $url,
        ?string $etag,
        ?string $lastModified,
        string $contentHash,
        string $lastSeenAt
    ): array {
        $updated = [];
        $found = false;

        foreach ($sources as $source) {
            if ($source->url === $url) {
                $updated[] = new SourceState($url, $etag, $lastModified, $contentHash, $lastSeenAt);
                $found = true;
            } else {
                $updated[] = $source;
            }
        }

        if (!$found) {
            $updated[] = new SourceState($url, $etag, $lastModified, $contentHash, $lastSeenAt);
        }

        return $updated;
    }

    private function generateSummary(string $html): string
    {
        $text = strip_tags($html);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text ?? '');

        if (mb_strlen($text) > 500) {
            return mb_substr($text, 0, 500) . '...';
        }

        return $text;
    }
}
