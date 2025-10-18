<?php

declare(strict_types=1);

namespace TikTokShopRss\Model;

final readonly class DocumentItem
{
    /**
     * @param list<string> $keywords
     */
    public function __construct(
        public string $documentPath,
        public string $title,
        public string $description,
        public string $contentHash,
        public string $pubDate,
        public string $link,
        public array $keywords = [],
    ) {
    }
}
