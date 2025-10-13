<?php

declare(strict_types=1);

namespace TikTokShopRss\Model;

readonly class DocumentItem
{
    public function __construct(
        public string $documentPath,
        public string $title,
        public string $description,
        public string $contentHash,
        public string $pubDate,
        public string $link,
    ) {
    }
}
