<?php

declare(strict_types=1);

namespace TikTokShopRss\Application\UseCase;

use TikTokShopRss\Application\Dto\BuildResult;
use TikTokShopRss\Application\Port\DocumentFetcherInterface;
use TikTokShopRss\Application\Port\RssGeneratorInterface;
use TikTokShopRss\Application\Port\StateManagerInterface;
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
use function strtotime;
use function trim;
use function usleep;
use function usort;

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

            if ($treeResult->notModified === true) {
                continue;
            }

            $documentPaths = $this->documentFetcher->extractDocumentPaths($treeResult->documentTree);

            // Sort by update_time descending (newest first)
            usort($documentPaths, function ($a, $b): int {
                $timeA = $a->updateTime ?? 0;
                $timeB = $b->updateTime ?? 0;
                return $timeB <=> $timeA;
            });

            $documentPaths = array_slice($documentPaths, 0, $config->limits->pages);

            foreach ($documentPaths as $docInfo) {
                try {
                    $documentPath = $docInfo->path;
                    $treeUpdateTime = $docInfo->updateTime;

                    $detail = $this->documentFetcher->fetchDetail($source, $documentPath);

                    $content = $detail->content;
                    $description = $detail->description;

                    if ($description === '') {
                        $description = $this->generateSummary($content);
                    }

                    $contentHash = hash('sha256', $content);

                    $existingItem = $this->stateManager->findItemByDocumentPath($state->items, $documentPath);

                    if ($existingItem !== null && $existingItem->contentHash === $contentHash) {
                        continue;
                    }

                    $title = $detail->title;
                    $link = str_replace('{document_path}', $documentPath, $source->publicUrlTemplate);

                    // Use tree's update_time, fallback to detail's update_time, then current time
                    $updateTime = $treeUpdateTime ?? $detail->updateTime;
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

            $treeJson = json_encode($treeResult->documentTree);
            if ($treeJson === false) {
                throw new \RuntimeException('Failed to encode tree data to JSON');
            }

            $state = new State(
                version: $state->version,
                sources: $this->updateSourceState(
                    $state->sources,
                    $source->treeUrl,
                    $treeResult->etag,
                    $treeResult->lastModified,
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
        return $this->rssGenerator->generate(
            $config->channel,
            $state->items,
            $config->rss->enableContentEncoded,
            $config->limits->items
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
