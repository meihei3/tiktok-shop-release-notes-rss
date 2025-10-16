<?php

declare(strict_types=1);

namespace TikTokShopRss\Application\Port;

use TikTokShopRss\Model\DocumentItem;

interface RssGeneratorInterface
{
    /**
     * @param array<string, mixed> $channel
     * @param array<int, DocumentItem> $items
     */
    public function generate(
        array $channel,
        array $items,
        bool $enableContentEncoded = true,
        int $limit = 50
    ): string;
}
