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
     * @param list<DocumentItem> $items
     */
    public function findItemByDocumentPath(array $items, string $documentPath): ?DocumentItem;

    /**
     * @param list<DocumentItem> $existingItems
     * @param list<DocumentItem> $newItems
     * @return list<DocumentItem>
     */
    public function mergeItems(array $existingItems, array $newItems): array;
}
