<?php

declare(strict_types=1);

namespace TikTokShopRss\Application\UseCase;

use TikTokShopRss\Application\Port\DocumentFetcherInterface;
use TikTokShopRss\Application\Port\RssGeneratorInterface;
use TikTokShopRss\Application\Port\StateManagerInterface;
use TikTokShopRss\Model\BuildResult;
use TikTokShopRss\Model\Config;
use TikTokShopRss\Model\DocumentItem;
use TikTokShopRss\Model\SourceState;
use TikTokShopRss\Model\State;

use function array_slice;
use function date;
use function hash;
use function json_encode;
use function mb_strlen;
use function mb_substr;
use function preg_replace;
use function str_replace;
use function strip_tags;
use function trim;
use function usleep;

readonly class BuildRssUseCase
{
    public function __construct(
        private DocumentFetcherInterface $documentFetcher,
        private StateManagerInterface $stateManager,
        private RssGeneratorInterface $rssGenerator,
    ) {
    }

    public function build(Config $config, State $state): BuildResult
    {
        $newItems = [];
        $pagesChanged = 0;

        foreach ($config->sources as $source) {
            $existingSourceState = $this->findSourceState($state->sources, $source->treeUrl);

            $treeResult = $this->documentFetcher->fetchTree(
                $source,
                $existingSourceState?->etag,
                $existingSourceState?->lastModified
            );

            if ($treeResult['not_modified'] === true) {
                continue;
            }

            $treeData = $treeResult['data'];
            $documentPaths = $this->documentFetcher->extractDocumentPaths($treeData['data']['document_tree'] ?? []);

            $pagesLimit = $config->limits['pages'] ?? 300;
            $documentPaths = array_slice($documentPaths, 0, $pagesLimit);

            foreach ($documentPaths as $docInfo) {
                try {
                    $documentPath = $docInfo->path;
                    $treeUpdateTime = $docInfo->updateTime;

                    $detailData = $this->documentFetcher->fetchDetail($source, $documentPath);

                    $content = $detailData['data']['content'] ?? '';
                    $description = $detailData['data']['description'] ?? '';

                    if ($description === '') {
                        $description = $this->generateSummary($content);
                    }

                    $contentHash = hash('sha256', $content);

                    $existingItem = $this->stateManager->findItemByDocumentPath($state->items, $documentPath);

                    if ($existingItem !== null && $existingItem->contentHash === $contentHash) {
                        continue;
                    }

                    $title = $detailData['data']['title'] ?? 'Untitled';
                    $link = str_replace('{document_path}', $documentPath, $source->publicUrlTemplate);

                    // Use tree's update_time, fallback to detail's update_time, then current time
                    $updateTime = $treeUpdateTime ?? ($detailData['data']['update_time'] ?? null);
                    $pubDate = $updateTime !== null ? date('c', $updateTime) : date('c');

                    $newItems[] = new DocumentItem(
                        documentPath: $documentPath,
                        title: $title,
                        description: $description,
                        contentHash: $contentHash,
                        pubDate: $pubDate,
                        link: $link,
                    );

                    $pagesChanged++;

                    usleep($config->sleepBetweenRequestsMs * 1000);
                } catch (\Exception $e) {
                    // Let caller handle warning output
                    throw new \RuntimeException(
                        "Failed to fetch detail for {$documentPath}: {$e->getMessage()}",
                        0,
                        $e
                    );
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
                items: $this->stateManager->mergeItems($state->items, $newItems),
            );
        }

        return new BuildResult(
            pagesChanged: $pagesChanged,
            state: $state,
        );
    }

    public function generateRss(Config $config, State $state): string
    {
        $enableContentEncoded = $config->rss['enable_content_encoded'] ?? true;
        $itemsLimit = $config->limits['items'] ?? 50;

        return $this->rssGenerator->generate(
            $config->channel,
            $state->items,
            $enableContentEncoded,
            $itemsLimit
        );
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
