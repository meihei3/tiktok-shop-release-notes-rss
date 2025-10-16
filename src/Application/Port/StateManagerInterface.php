<?php

declare(strict_types=1);

namespace TikTokShopRss\Application\Port;

use TikTokShopRss\Model\DocumentItem;
use TikTokShopRss\Model\State;

interface StateManagerInterface
{
    public function load(string $filePath): State;

    public function save(string $filePath, State $state): void;

    /**
     * @param array<int, DocumentItem> $items
     */
    public function findItemByDocumentPath(array $items, string $documentPath): ?DocumentItem;

    /**
     * @param array<int, DocumentItem> $existingItems
     * @param array<int, DocumentItem> $newItems
     * @return array<int, DocumentItem>
     */
    public function mergeItems(array $existingItems, array $newItems): array;
}
