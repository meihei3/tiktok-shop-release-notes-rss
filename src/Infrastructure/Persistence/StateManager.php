<?php

declare(strict_types=1);

namespace TikTokShopRss\Infrastructure\Persistence;

use TikTokShopRss\Model\DocumentItem;
use TikTokShopRss\Model\SourceState;
use TikTokShopRss\Model\State;

use function array_map;
use function array_search;
use function dirname;
use function fclose;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function flock;
use function fopen;
use function is_array;
use function is_dir;
use function json_decode;
use function json_encode;
use function mkdir;
use function rename;
use function unlink;

class StateManager
{
    private const VERSION = 2;

    public function load(string $filePath): State
    {
        if (!file_exists($filePath)) {
            return new State(
                version: self::VERSION,
                sources: [],
                items: [],
            );
        }

        $content = file_get_contents($filePath);

        if ($content === false) {
            throw new \RuntimeException("Failed to read state file: {$filePath}");
        }

        $data = json_decode($content, true);

        if (!is_array($data)) {
            throw new \RuntimeException("Invalid state file format: {$filePath}");
        }

        $sources = [];
        foreach ($data['sources'] ?? [] as $sourceData) {
            $sources[] = new SourceState(
                url: $sourceData['url'] ?? '',
                etag: $sourceData['etag'] ?? null,
                lastModified: $sourceData['lastModified'] ?? null,
                contentHash: $sourceData['contentHash'] ?? '',
                lastSeenAt: $sourceData['lastSeenAt'] ?? '',
            );
        }

        $items = [];
        foreach ($data['items'] ?? [] as $itemData) {
            $items[] = new DocumentItem(
                documentPath: $itemData['document_path'] ?? '',
                title: $itemData['title'] ?? '',
                description: $itemData['description'] ?? '',
                contentHash: $itemData['contentHash'] ?? '',
                pubDate: $itemData['pubDate'] ?? '',
                link: $itemData['link'] ?? '',
            );
        }

        return new State(
            version: $data['version'] ?? 1,
            sources: $sources,
            items: $items,
        );
    }

    public function save(string $filePath, State $state): void
    {
        $dir = dirname($filePath);

        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException("Failed to create directory: {$dir}");
        }

        $data = [
            'version' => $state->version,
            'sources' => array_map(fn (SourceState $s) => [
                'url' => $s->url,
                'etag' => $s->etag,
                'lastModified' => $s->lastModified,
                'contentHash' => $s->contentHash,
                'lastSeenAt' => $s->lastSeenAt,
            ], $state->sources),
            'items' => array_map(fn (DocumentItem $item) => [
                'document_path' => $item->documentPath,
                'title' => $item->title,
                'description' => $item->description,
                'contentHash' => $item->contentHash,
                'pubDate' => $item->pubDate,
                'link' => $item->link,
            ], $state->items),
        ];

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            throw new \RuntimeException("Failed to encode state to JSON");
        }

        $tempFile = $filePath . '.tmp';
        $lockFile = $filePath . '.lock';

        $lockHandle = fopen($lockFile, 'c');

        if ($lockHandle === false) {
            throw new \RuntimeException("Failed to open lock file: {$lockFile}");
        }

        try {
            if (!flock($lockHandle, LOCK_EX)) {
                throw new \RuntimeException("Failed to acquire lock on: {$lockFile}");
            }

            if (file_put_contents($tempFile, $json) === false) {
                throw new \RuntimeException("Failed to write temporary state file: {$tempFile}");
            }

            if (!rename($tempFile, $filePath)) {
                throw new \RuntimeException("Failed to rename temporary state file to: {$filePath}");
            }
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);

            if (file_exists($lockFile)) {
                unlink($lockFile);
            }
        }
    }

    /**
     * @param array<int, DocumentItem> $items
     */
    public function findItemByDocumentPath(array $items, string $documentPath): ?DocumentItem
    {
        foreach ($items as $item) {
            if ($item->documentPath === $documentPath) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @param array<int, DocumentItem> $existingItems
     * @param array<int, DocumentItem> $newItems
     * @return array<int, DocumentItem>
     */
    public function mergeItems(array $existingItems, array $newItems): array
    {
        $merged = [];
        $pathMap = [];

        foreach ($existingItems as $item) {
            $pathMap[$item->documentPath] = $item;
            $merged[] = $item;
        }

        foreach ($newItems as $newItem) {
            if (isset($pathMap[$newItem->documentPath])) {
                $index = array_search($pathMap[$newItem->documentPath], $merged, true);
                if ($index !== false) {
                    $merged[$index] = $newItem;
                }
            } else {
                $merged[] = $newItem;
            }
        }

        return $merged;
    }
}
