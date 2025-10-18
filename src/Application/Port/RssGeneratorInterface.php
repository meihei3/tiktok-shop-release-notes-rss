<?php

declare(strict_types=1);

namespace TikTokShopRss\Application\Port;

use TikTokShopRss\Application\Dto\ChannelConfig;
use TikTokShopRss\Model\DocumentItem;

interface RssGeneratorInterface
{
    /**
     * @param list<DocumentItem> $items
     */
    public function generate(
        ChannelConfig $channel,
        array $items,
        bool $enableContentEncoded = true,
        int $limit = 50
    ): string;
}
